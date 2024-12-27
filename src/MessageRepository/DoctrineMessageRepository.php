<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\MessageRepository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\ForwardCompatibility\DriverStatement;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Webf\FlysystemFailoverBundle\Exception\InvalidArgumentException;
use Webf\FlysystemFailoverBundle\Message\DeleteDirectory;
use Webf\FlysystemFailoverBundle\Message\DeleteFile;
use Webf\FlysystemFailoverBundle\Message\MessageInterface;
use Webf\FlysystemFailoverBundle\Message\ReplicateFile;

/**
 * @psalm-type _Options=array{
 *     table_name: string
 * }
 * @psalm-type _DatabaseRow=array{
 *     id: string,
 *     failover_adapter: string,
 *     action: string,
 *     path: string,
 *     source_adapter: string,
 *     destination_adapter: string,
 *     retry_count: string,
 *     created_at: string,
 *     available_at: string,
 * }
 */
class DoctrineMessageRepository implements MessageRepositoryInterface
{
    private const POP_RETRY_COUNT = 10;

    private const DEFAULT_OPTIONS = [
        'table_name' => 'webf_flysystem_failover_messages',
    ];

    /**
     * @var _Options
     */
    private array $options;

    /**
     * @param array{table_name?: string} $options
     */
    public function __construct(
        private Connection $connection,
        array $options = [],
    ) {
        $this->options = array_merge(self::DEFAULT_OPTIONS, $options);
    }

    public function push(MessageInterface $message): void
    {
        if ($this->exists($message)) {
            return;
        }

        $now = new \DateTimeImmutable();
        $availableAt = $message->getRetryCount() > 0
            ? $now->modify(sprintf(
                '+%d seconds',
                min(10 * 60, 2 ** $message->getRetryCount()) * 1000
            ))
            : $now;

        $qb = $this->connection->createQueryBuilder()
            ->insert($this->options['table_name'])
            ->values([
                'failover_adapter' => ':failover_adapter',
                'action' => ':action',
                'path' => ':path',
                'source_adapter' => ':source_adapter',
                'destination_adapter' => ':destination_adapter',
                'retry_count' => ':retry_count',
                'created_at' => ':created_at',
                'available_at' => ':available_at',
            ])
            ->setParameters([
                'failover_adapter' => $message->getFailoverAdapter(),
                'action' => $this->getAction($message),
                'path' => $message->getPath(),
                'source_adapter' => $message->getInnerSourceAdapter(),
                'destination_adapter' => $message->getInnerDestinationAdapter(),
                'retry_count' => $message->getRetryCount(),
                'created_at' => $now,
                'available_at' => $availableAt,
            ], [
                'created_at' => Types::DATETIME_IMMUTABLE,
                'available_at' => Types::DATETIME_IMMUTABLE,
            ])
        ;

        try {
            $this->executeStatement($qb);
        } catch (TableNotFoundException) {
            $this->setup();
            $this->executeStatement($qb);
        }
    }

    public function pop(): ?MessageInterface
    {
        for ($i = 0; $i < self::POP_RETRY_COUNT; ++$i) {
            try {
                return $this->doPop();
            } catch (RetryableException) {
            }
        }

        return null;
    }

    public function findBy(FindByCriteria $criteria): FindResults
    {
        $limit = $criteria->getLimit();
        $page = $criteria->getPage();

        $qb = $this->connection->createQueryBuilder();

        $qb
            ->select('*')
            ->from($this->options['table_name'])
            ->orderBy('available_at')
            ->addOrderBy('created_at')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
        ;

        try {
            $result = $this->executeQuery($qb);
        } catch (TableNotFoundException) {
            return new FindResults($limit, 0, $page, []);
        }

        $qb
            ->select('COUNT(*)')
            ->setFirstResult(0)
            ->setMaxResults(null)
        ;

        $countResult = $this->executeQuery($qb);
        $total = (int) $countResult->fetchOne();

        $fetchItems = function () use ($result): \Generator {
            while (true) {
                /** @var false|_DatabaseRow $data */
                $data = $result->fetchAssociative();

                if (false === $data) {
                    return;
                }

                yield $this->getMessageWithMetadata($data);
            }
        };

        return new FindResults($limit, $total, $page, $fetchItems());
    }

    private function doPop(): ?MessageInterface
    {
        $now = new \DateTimeImmutable();

        $qb = $this->connection->createQueryBuilder();

        $qb
            ->select('*')
            ->from($this->options['table_name'])
            ->where('available_at <= ?')
            ->orderBy('created_at')
            ->setMaxResults(1)
            ->setParameters([$now], [Types::DATETIME_IMMUTABLE])
        ;

        try {
            $result = $this->executeQuery($qb);
        } catch (TableNotFoundException) {
            return null;
        }

        /** @var false|_DatabaseRow $data */
        $data = $result->fetchAssociative();

        if (false === $data) {
            return null;
        }

        $this->connection->delete(
            $this->options['table_name'],
            ['id' => $data['id']]
        );

        return $this->getMessage($data);
    }

    private function exists(MessageInterface $message): bool
    {
        $qb = $this->connection->createQueryBuilder();

        $qb
            ->select('*')
            ->from($this->options['table_name'])
            ->where($qb->expr()->and(
                'failover_adapter = :failover_adapter',
                'action = :action',
                'path = :path',
                'source_adapter = :source_adapter',
                'destination_adapter = :destination_adapter',
            ))
            ->setMaxResults(1)
            ->setParameters([
                'failover_adapter' => $message->getFailoverAdapter(),
                'action' => $this->getAction($message),
                'path' => $message->getPath(),
                'source_adapter' => $message->getInnerSourceAdapter(),
                'destination_adapter' => $message->getInnerDestinationAdapter(),
            ])
        ;

        try {
            $result = $this->executeQuery($qb);
        } catch (TableNotFoundException) {
            return false;
        }

        return false !== $result->fetchAssociative();
    }

    private function getAction(MessageInterface $message): string
    {
        return match (get_class($message)) {
            DeleteDirectory::class => 'delete_directory',
            DeleteFile::class => 'delete_file',
            ReplicateFile::class => 'replicate_file',
            default => throw new InvalidArgumentException('Unsupported message'),
        };
    }

    /**
     * @param _DatabaseRow $data
     */
    private function getMessage(array $data): MessageInterface
    {
        return match ($data['action']) {
            'delete_directory' => new DeleteDirectory(
                $data['failover_adapter'],
                $data['path'],
                (int) $data['destination_adapter'],
                (int) $data['retry_count'],
            ),
            'delete_file' => new DeleteFile(
                $data['failover_adapter'],
                $data['path'],
                (int) $data['destination_adapter'],
                (int) $data['retry_count'],
            ),
            'replicate_file' => new ReplicateFile(
                $data['failover_adapter'],
                $data['path'],
                (int) $data['source_adapter'],
                (int) $data['destination_adapter'],
                (int) $data['retry_count'],
            ),
            default => throw new InvalidArgumentException('Unsupported message'),
        };
    }

    /**
     * @param _DatabaseRow $data
     */
    private function getMessageWithMetadata(array $data): MessageWithMetadata
    {
        return new MessageWithMetadata(
            $this->getMessage($data),
            new \DateTimeImmutable($data['created_at']),
            new \DateTimeImmutable($data['available_at']),
        );
    }

    /**
     * @throws DBALException
     */
    private function executeQuery(QueryBuilder $qb): DriverStatement|Result
    {
        if (method_exists($qb, 'executeQuery')) {
            /** @var Result $result */
            $result = $qb->executeQuery();
        } else {
            /** @var DriverStatement $result */
            $result = $qb->execute();
        }

        return $result;
    }

    /**
     * @throws DBALException
     */
    private function executeStatement(QueryBuilder $qb): void
    {
        if (method_exists($qb, 'executeStatement')) {
            $qb->executeStatement();
        } else {
            $qb->execute();
        }
    }

    private function setup(): void
    {
        $configuration = $this->connection->getConfiguration();
        $assetFilter = $configuration->getSchemaAssetsFilter();
        $configuration->setSchemaAssetsFilter(null);
        $this->updateSchema();
        $configuration->setSchemaAssetsFilter($assetFilter);
    }

    private function updateSchema(): void
    {
        $comparator = new Comparator();
        $schemaDiff = $comparator->compare(
            $this->connection->getSchemaManager()->createSchema(),
            $this->getSchema()
        );

        foreach ($schemaDiff->toSaveSql($this->connection->getDatabasePlatform()) as $sql) {
            $this->connection->executeStatement($sql);
        }
    }

    private function getSchema(): Schema
    {
        $schemaManager = $this->connection->getSchemaManager();
        $schema = new Schema([], [], $schemaManager->createSchemaConfig());
        $this->addTableToSchema($schema);

        return $schema;
    }

    private function addTableToSchema(Schema $schema): void
    {
        $table = $schema->createTable($this->options['table_name']);

        $table->addColumn('id', Types::BIGINT)
            ->setAutoincrement(true)
            ->setNotnull(true)
        ;
        $table->addColumn('failover_adapter', Types::STRING)
            ->setNotnull(true)
        ;
        $table->addColumn('action', Types::STRING)
            ->setNotnull(true)
        ;
        $table->addColumn('path', Types::TEXT)
            ->setNotnull(true)
        ;
        $table->addColumn('source_adapter', Types::SMALLINT)
            ->setNotnull(false)
        ;
        $table->addColumn('destination_adapter', Types::SMALLINT)
            ->setNotnull(true)
        ;
        $table->addColumn('retry_count', Types::SMALLINT)
            ->setNotnull(true)
        ;
        $table->addColumn('created_at', Types::DATETIME_MUTABLE)
            ->setNotnull(true)
        ;
        $table->addColumn('available_at', Types::DATETIME_MUTABLE)
            ->setNotnull(true)
        ;

        $table->setPrimaryKey(['id']);
        $table->addIndex(['available_at']);
        $table->addIndex(['created_at']);
    }

    public function configureSchema(Schema $schema, Connection $connection): void
    {
        // only update the schema for this connection
        if ($connection !== $this->connection) {
            return;
        }

        if ($schema->hasTable($this->options['table_name'])) {
            return;
        }

        $this->addTableToSchema($schema);
    }
}
