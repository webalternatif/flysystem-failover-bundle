<?php

declare(strict_types=1);

namespace Tests\Webf\FlysystemFailoverBundle\Service;

use League\Flysystem\Config;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Webf\FlysystemFailoverBundle\Flysystem\FailoverAdapter;
use Webf\FlysystemFailoverBundle\Flysystem\FailoverAdaptersLocator;
use Webf\FlysystemFailoverBundle\Flysystem\InnerAdapter;
use Webf\FlysystemFailoverBundle\Message\DeleteFile;
use Webf\FlysystemFailoverBundle\Message\ReplicateFile;
use Webf\FlysystemFailoverBundle\MessageRepository\InMemoryMessageRepository;
use Webf\FlysystemFailoverBundle\Service\SyncService;

/**
 * @internal
 * @covers \Webf\FlysystemFailoverBundle\Service\SyncService
 */
class SyncServiceTest extends TestCase
{
    public function test_missing_files_in_secondary_storages_are_replicated_from_first_storage(): void
    {
        $config = new Config();
        $innerAdapter1 = new InMemoryFilesystemAdapter();
        $innerAdapter2 = new InMemoryFilesystemAdapter();
        $innerAdapter3 = new InMemoryFilesystemAdapter();

        $innerAdapter1->write($file1 = 'file1', $content1 = 'content1', $config);
        $innerAdapter1->write($file2 = 'file2', 'content2', $config);
        $innerAdapter2->write($file1, $content1, $config);

        $messageRepository = new InMemoryMessageRepository();
        $syncService = new SyncService(
            new EventDispatcher(),
            new FailoverAdaptersLocator([
                new FailoverAdapter(
                    $adapterName = 'default',
                    [
                        new InnerAdapter($innerAdapter1),
                        new InnerAdapter($innerAdapter2),
                        new InnerAdapter($innerAdapter3),
                    ],
                    $messageRepository
                ),
            ]),
            $messageRepository
        );

        $syncService->sync($adapterName);

        $this->assertEquals(
            [
                new ReplicateFile($adapterName, $file1, 0, 2),
                new ReplicateFile($adapterName, $file2, 0, 1),
                new ReplicateFile($adapterName, $file2, 0, 2),
            ],
            $messageRepository->popAll()
        );
    }

    public function test_only_older_files_in_secondary_storages_are_replicated(): void
    {
        $innerAdapter1 = new InMemoryFilesystemAdapter();
        $innerAdapter2 = new InMemoryFilesystemAdapter();

        $innerAdapter1->write(
            $file1 = 'file1',
            'content11',
            new Config(['timestamp' => 20])
        );
        $innerAdapter1->write(
            $file2 = 'file2',
            'content12',
            new Config(['timestamp' => 20])
        );
        $innerAdapter2->write(
            $file1,
            'content21',
            new Config(['timestamp' => 10])
        );
        $innerAdapter2->write(
            $file2,
            'content22',
            new Config(['timestamp' => 30])
        );

        $messageRepository = new InMemoryMessageRepository();
        $syncService = new SyncService(
            new EventDispatcher(),
            new FailoverAdaptersLocator([
                new FailoverAdapter(
                    $adapterName = 'default',
                    [
                        new InnerAdapter($innerAdapter1),
                        new InnerAdapter($innerAdapter2),
                    ],
                    $messageRepository
                ),
            ]),
            $messageRepository
        );

        $syncService->sync($adapterName);

        $this->assertEquals(
            [
                new ReplicateFile($adapterName, $file1, 0, 1),
            ],
            $messageRepository->popAll()
        );
    }

    public function test_extra_files_in_secondary_storages_are_ignored_by_default(): void
    {
        $config = new Config();
        $innerAdapter1 = new InMemoryFilesystemAdapter();
        $innerAdapter2 = new InMemoryFilesystemAdapter();
        $innerAdapter3 = new InMemoryFilesystemAdapter();

        $innerAdapter2->write('file1', 'content1', $config);
        $innerAdapter3->write('file2', 'content2', $config);

        $messageRepository = new InMemoryMessageRepository();
        $syncService = new SyncService(
            new EventDispatcher(),
            new FailoverAdaptersLocator([
                new FailoverAdapter(
                    $adapterName = 'default',
                    [
                        new InnerAdapter($innerAdapter1),
                        new InnerAdapter($innerAdapter2),
                        new InnerAdapter($innerAdapter3),
                    ],
                    $messageRepository
                ),
            ]),
            $messageRepository
        );

        $syncService->sync($adapterName);

        $this->assertEquals([], $messageRepository->popAll());
    }

    public function test_extra_files_in_secondary_storages_could_be_deleted(): void
    {
        $config = new Config();
        $innerAdapter1 = new InMemoryFilesystemAdapter();
        $innerAdapter2 = new InMemoryFilesystemAdapter();
        $innerAdapter3 = new InMemoryFilesystemAdapter();

        $innerAdapter2->write($file1 = 'file1', 'content1', $config);
        $innerAdapter3->write($file2 = 'file2', 'content2', $config);

        $messageRepository = new InMemoryMessageRepository();
        $syncService = new SyncService(
            new EventDispatcher(),
            new FailoverAdaptersLocator([
                new FailoverAdapter(
                    $adapterName = 'default',
                    [
                        new InnerAdapter($innerAdapter1),
                        new InnerAdapter($innerAdapter2),
                        new InnerAdapter($innerAdapter3),
                    ],
                    $messageRepository
                ),
            ]),
            $messageRepository
        );

        $syncService->sync($adapterName, SyncService::EXTRA_FILES_DELETE);

        $this->assertEquals(
            [
                new DeleteFile($adapterName, $file1, 1),
                new DeleteFile($adapterName, $file2, 2),
            ],
            $messageRepository->popAll()
        );
    }

    public function test_extra_files_in_secondary_storages_could_be_copied_in_other_storages(): void
    {
        $config = new Config();
        $innerAdapter1 = new InMemoryFilesystemAdapter();
        $innerAdapter2 = new InMemoryFilesystemAdapter();
        $innerAdapter3 = new InMemoryFilesystemAdapter();

        $innerAdapter2->write($file1 = 'file1', 'content1', $config);
        $innerAdapter3->write($file2 = 'file2', 'content2', $config);

        $messageRepository = new InMemoryMessageRepository();
        $syncService = new SyncService(
            new EventDispatcher(),
            new FailoverAdaptersLocator([
                new FailoverAdapter(
                    $adapterName = 'default',
                    [
                        new InnerAdapter($innerAdapter1),
                        new InnerAdapter($innerAdapter2),
                        new InnerAdapter($innerAdapter3),
                    ],
                    $messageRepository
                ),
            ]),
            $messageRepository
        );

        $syncService->sync($adapterName, SyncService::EXTRA_FILES_COPY);

        $this->assertEquals(
            [
                new ReplicateFile($adapterName, $file1, 1, 0),
                new ReplicateFile($adapterName, $file1, 1, 2),
                new ReplicateFile($adapterName, $file2, 2, 0),
                new ReplicateFile($adapterName, $file2, 2, 1),
            ],
            $messageRepository->popAll()
        );
    }
}
