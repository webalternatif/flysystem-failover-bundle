<?php

namespace Tests\Webf\FlysystemFailoverBundle\EventListener;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Webf\FlysystemFailoverBundle\EventListener\DoctrineSchemaListener;
use Webf\FlysystemFailoverBundle\MessageRepository\DoctrineMessageRepository;

/**
 * @internal
 *
 * @covers \Webf\FlysystemFailoverBundle\EventListener\DoctrineSchemaListener
 * @covers \Webf\FlysystemFailoverBundle\MessageRepository\DoctrineMessageRepository
 */
class DoctrineSchemaListenerTest extends TestCase
{
    public function test_schema_is_creatable_on_all_platforms(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $config = ORMSetup::createConfiguration(cache: $this->createMock(CacheItemPoolInterface::class));
        $config->setMetadataDriverImpl($this->createMock(MappingDriver::class));
        $entityManager = new EntityManager($connection, $config);
        $schema = new Schema();
        $repository = new DoctrineMessageRepository($connection);

        $listener = new DoctrineSchemaListener($repository);
        $listener->postGenerateSchema(new GenerateSchemaEventArgs($entityManager, $schema));

        $this->assertNotEmpty($schema->toSql(new MySQLPlatform()));
        $this->assertNotEmpty($schema->toSql(new MariaDBPlatform()));
        $this->assertNotEmpty($schema->toSql(new DB2Platform()));
        $this->assertNotEmpty($schema->toSql(new OraclePlatform()));
        $this->assertNotEmpty($schema->toSql(new PostgreSQLPlatform()));
        $this->assertNotEmpty($schema->toSql(new SQLServerPlatform()));
        $this->assertNotEmpty($schema->toSql(new SQLitePlatform()));
    }
}
