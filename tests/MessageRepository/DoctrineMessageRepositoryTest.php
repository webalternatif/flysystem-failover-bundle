<?php

declare(strict_types=1);

namespace Tests\Webf\FlysystemFailoverBundle\MessageRepository;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\TestCase;
use Webf\FlysystemFailoverBundle\Message\MessageInterface;
use Webf\FlysystemFailoverBundle\Message\ReplicateFile;
use Webf\FlysystemFailoverBundle\MessageRepository\DoctrineMessageRepository;
use Webf\FlysystemFailoverBundle\MessageRepository\FindByCriteria;
use Webf\FlysystemFailoverBundle\MessageRepository\MessageWithMetadata;

/**
 * @internal
 *
 * @covers \Webf\FlysystemFailoverBundle\MessageRepository\DoctrineMessageRepository
 */
final class DoctrineMessageRepositoryTest extends TestCase
{
    public function test_push_then_pop(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $repository = new DoctrineMessageRepository($connection, ['table_name' => 'adapter_messages']);

        $repository->push($m1 = new ReplicateFile('adapter', 'path1', 0, 1));
        $repository->push($m2 = new ReplicateFile('adapter', 'path2', 0, 1));

        $this->assertEquals($m1, $repository->pop());

        $repository->push($m3 = new ReplicateFile('adapter', 'path3', 0, 1));

        $this->assertEquals($m2, $repository->pop());
        $this->assertEquals($m3, $repository->pop());
        $this->assertNull($repository->pop());

        $repository->push($m4 = new ReplicateFile('adapter', 'path4', 0, 1));

        $this->assertEquals($m4, $repository->pop());
    }

    public function test_push_idempotency(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $repository = new DoctrineMessageRepository($connection, ['table_name' => 'adapter_messages']);

        $m1 = new ReplicateFile('adapter', 'path', 0, 1);
        $m2 = new ReplicateFile('adapter', 'path', 0, 1);

        $this->assertEquals($m1, $m2);

        $repository->push($m1);
        $repository->push($m2);

        $this->assertEquals($m1, $repository->pop());
        $this->assertNull($repository->pop());
    }

    public function test_push_creates_table_without_altering_the_rest_of_the_database(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE other_table (id INTEGER PRIMARY KEY AUTOINCREMENT)');
        $connection->executeStatement('INSERT INTO other_table (id) VALUES (1)');
        $repository = new DoctrineMessageRepository($connection, ['table_name' => 'adapter_messages']);

        $this->assertEquals([['id' => 1]], $connection->executeQuery('SELECT * FROM other_table')->fetchAllAssociative());
        $repository->push(new ReplicateFile('adapter', 'path1', 0, 1));
        $this->assertEquals([['id' => 1]], $connection->executeQuery('SELECT * FROM other_table')->fetchAllAssociative());
    }

    public function test_push_does_not_create_or_update_table_if_it_already_exists(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $repository = new DoctrineMessageRepository($connection, ['table_name' => 'adapter_messages']);
        $connection->executeStatement('CREATE TABLE adapter_messages (id INTEGER PRIMARY KEY AUTOINCREMENT)');

        $this->expectException(Exception::class);

        $repository->push(new ReplicateFile('adapter', 'path1', 0, 1));
    }

    public function test_pushing_retried_message(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $repository = new DoctrineMessageRepository($connection, ['table_name' => 'adapter_messages']);

        $repository->push(new ReplicateFile('adapter', 'path1', 0, 1, 1));

        $this->assertNull($repository->pop());
    }

    public function test_pop_without_table(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $repository = new DoctrineMessageRepository($connection, ['table_name' => 'adapter_messages']);

        $this->assertNull($repository->pop());
    }

    public function test_find_by_adapter(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $repository = new DoctrineMessageRepository($connection, ['table_name' => 'adapter_messages']);

        $repository->push($m1 = new ReplicateFile('adapter1', 'path1', 0, 1, 1));
        $repository->push($m2 = new ReplicateFile('adapter2', 'path2', 0, 1, 1));

        $this->assertEquals(
            [$m1],
            array_map(
                fn (MessageWithMetadata $m): MessageInterface => $m->getMessage(),
                [...$repository->findBy((new FindByCriteria())->setFailoverAdapter('adapter1'))->getItems()]
            )
        );

        $this->assertEquals(
            [$m2],
            array_map(
                fn (MessageWithMetadata $m) => $m->getMessage(),
                [...$repository->findBy((new FindByCriteria())->setFailoverAdapter('adapter2'))->getItems()]
            )
        );
    }

    public function test_find_by_without_table(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $repository = new DoctrineMessageRepository($connection, ['table_name' => 'adapter_messages']);

        $this->assertEquals(0, $repository->findBy(new FindByCriteria())->getTotal());
    }
}
