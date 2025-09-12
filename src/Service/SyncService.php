<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\Service;

use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemException;
use League\Flysystem\StorageAttributes;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Webf\FlysystemFailoverBundle\Event\SyncService\DeleteFileMessageDispatched;
use Webf\FlysystemFailoverBundle\Event\SyncService\DeleteFileMessagePreDispatch;
use Webf\FlysystemFailoverBundle\Event\SyncService\ListingContentFailed;
use Webf\FlysystemFailoverBundle\Event\SyncService\ListingContentStarted;
use Webf\FlysystemFailoverBundle\Event\SyncService\ListingContentSucceeded;
use Webf\FlysystemFailoverBundle\Event\SyncService\ReplicateFileMessageDispatched;
use Webf\FlysystemFailoverBundle\Event\SyncService\ReplicateFileMessagePreDispatch;
use Webf\FlysystemFailoverBundle\Event\SyncService\SearchingFilesToReplicateStarted;
use Webf\FlysystemFailoverBundle\Exception\FailoverAdapterNotFoundException;
use Webf\FlysystemFailoverBundle\Exception\InvalidArgumentException;
use Webf\FlysystemFailoverBundle\Flysystem\FailoverAdaptersLocatorInterface;
use Webf\FlysystemFailoverBundle\Message\DeleteFile;
use Webf\FlysystemFailoverBundle\Message\ReplicateFile;
use Webf\FlysystemFailoverBundle\MessageRepository\MessageRepositoryInterface;

final class SyncService
{
    public const EXTRA_FILES_COPY = 'copy';
    public const EXTRA_FILES_DELETE = 'delete';
    public const EXTRA_FILES_IGNORE = 'ignore';

    /**
     * @template T of FilesystemAdapter
     *
     * @param FailoverAdaptersLocatorInterface<T> $failoverAdaptersLocator
     */
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private FailoverAdaptersLocatorInterface $failoverAdaptersLocator,
        private MessageRepositoryInterface $messageRepository,
    ) {
    }

    /**
     * @throws FailoverAdapterNotFoundException if $adapterName is not found
     * @throws InvalidArgumentException         if $extraFilesStrategy is invalid
     */
    public function sync(
        string $adapterName,
        string $extraFilesStrategy = self::EXTRA_FILES_IGNORE,
        bool $ignoreModificationDates = false,
    ): void {
        $extraFilesStrategies = [
            self::EXTRA_FILES_COPY,
            self::EXTRA_FILES_DELETE,
            self::EXTRA_FILES_IGNORE,
        ];

        if (!in_array($extraFilesStrategy, $extraFilesStrategies)) {
            throw new InvalidArgumentException(sprintf('Argument $extraFilesStrategy must be one of "%s". "%s" given.', join('", "', $extraFilesStrategies), $extraFilesStrategy));
        }

        $adapter = $this->failoverAdaptersLocator->get($adapterName);

        $cache = new class($ignoreModificationDates) {
            /** @var array<int, array<string, int>> */
            private array $cache = [];

            public function __construct(private bool $ignoreModificationDates)
            {
            }

            public function adaptersCount(): int
            {
                return count($this->cache);
            }

            public function adapterItemsCount(int $adapter): int
            {
                return count($this->cache[$adapter] ?? []);
            }

            public function initializeAdapter(int $adapter): void
            {
                $this->cache[$adapter] = [];
            }

            public function clearAdapter(int $adapter): void
            {
                unset($this->cache[$adapter]);
            }

            public function addFile(StorageAttributes $file, int $adapter, int $timeShift): void
            {
                $lastModified = $file->lastModified();

                $this->cache[$adapter][$file->path()] = null !== $lastModified
                    ? $lastModified - $timeShift
                    : 0;
            }

            /**
             * Yield files that are present in $source adapter but not in other
             * ones.
             *
             * @return iterable<array{0: string, 1: int}> path of the file and the adapter in which it's missing
             */
            public function missingFilesFrom(int $source): iterable
            {
                foreach ($this->cache[$source] as $path => $lastModified) {
                    for ($destination = 0; $destination < count($this->cache); ++$destination) {
                        if ($source === $destination) {
                            continue;
                        }

                        $fileIsMissing = !key_exists($path, $this->cache[$destination] ?? []);
                        $fileIsOlder = $lastModified > ($this->cache[$destination][$path] ?? 0);

                        if ($fileIsMissing || !$this->ignoreModificationDates && $fileIsOlder) {
                            yield [$path, $destination];
                        }
                    }
                }
            }
        };

        foreach ($adapter->getInnerAdapters() as $i => $innerAdapter) {
            $cache->initializeAdapter($i);

            $this->eventDispatcher->dispatch(
                new ListingContentStarted($adapterName, $i)
            );

            try {
                $timeShift = $innerAdapter->getTimeShift();
                foreach ($innerAdapter->listContents('/', true) as $item) {
                    if ($item->isFile()) {
                        $cache->addFile($item, $i, $timeShift);
                    }
                }

                $this->eventDispatcher->dispatch(
                    new ListingContentSucceeded(
                        $adapterName,
                        $i,
                        $cache->adapterItemsCount($i)
                    )
                );
            } catch (FilesystemException) {
                $cache->clearAdapter($i);

                $this->eventDispatcher->dispatch(
                    new ListingContentFailed($adapterName, $i)
                );
            }
        }

        $this->eventDispatcher->dispatch(
            new SearchingFilesToReplicateStarted($adapterName)
        );

        foreach ($cache->missingFilesFrom(0) as [$path, $destination]) {
            $this->replicateFile($adapterName, $path, 0, $destination);
        }

        if (self::EXTRA_FILES_IGNORE !== $extraFilesStrategy) {
            for ($source = 1; $source < $cache->adaptersCount(); ++$source) {
                foreach ($cache->missingFilesFrom($source) as [$path, $destination]) {
                    switch ($extraFilesStrategy) {
                        case self::EXTRA_FILES_COPY:
                            $this->replicateFile(
                                $adapterName,
                                $path,
                                $source,
                                $destination
                            );
                            break;
                        case self::EXTRA_FILES_DELETE:
                            $this->deleteFile($adapterName, $path, $source);
                            break 2;
                    }
                }
            }
        }
    }

    private function replicateFile(
        string $adapterName,
        string $path,
        int $source,
        int $destination,
    ): void {
        $message = new ReplicateFile(
            $adapterName,
            $path,
            $source,
            $destination
        );

        $this->eventDispatcher->dispatch(
            new ReplicateFileMessagePreDispatch($message)
        );

        $this->messageRepository->push($message);

        $this->eventDispatcher->dispatch(
            new ReplicateFileMessageDispatched($message)
        );
    }

    private function deleteFile(
        string $adapterName,
        string $path,
        int $adapter,
    ): void {
        $message = new DeleteFile(
            $adapterName,
            $path,
            $adapter
        );

        $this->eventDispatcher->dispatch(
            new DeleteFileMessagePreDispatch($message)
        );

        $this->messageRepository->push($message);

        $this->eventDispatcher->dispatch(
            new DeleteFileMessageDispatched($message)
        );
    }
}
