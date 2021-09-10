<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\Flysystem;

use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemException;
use League\Flysystem\UnableToCheckFileExistence;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToWriteFile;
use Webf\FlysystemFailoverBundle\Exception\InnerAdapterNotFoundException;
use Webf\FlysystemFailoverBundle\Exception\UnsupportedOperationException;
use Webf\FlysystemFailoverBundle\Message\DeleteDirectory;
use Webf\FlysystemFailoverBundle\Message\DeleteFile;
use Webf\FlysystemFailoverBundle\Message\ReplicateFile;
use Webf\FlysystemFailoverBundle\MessageRepository\MessageRepositoryInterface;

class FailoverAdapter implements FilesystemAdapter
{
    /**
     * @param iterable<int, InnerAdapter> $adapters
     */
    public function __construct(
        private string $name,
        private iterable $adapters,
        private MessageRepositoryInterface $messageRepository
    ) {
    }

    public function fileExists(string $path): bool
    {
        foreach ($this->adapters as $adapter) {
            try {
                return $adapter->getAdapter()->fileExists($path);
            } catch (FilesystemException) {
                // TODO log exception ?
            }
        }

        throw UnableToCheckFileExistence::forLocation($path);
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $writtenAdapter = null;

        foreach ($this->adapters as $name => $adapter) {
            try {
                $adapter->getAdapter()->write($path, $contents, $config);
                $writtenAdapter = $name;
                break;
            } catch (FilesystemException) {
                // TODO log exception ?
            }
        }

        if (null !== $writtenAdapter) {
            foreach ($this->adapters as $name => $_) {
                if ($name !== $writtenAdapter) {
                    $this->messageRepository->push(
                        new ReplicateFile(
                            $this->name,
                            $path,
                            $writtenAdapter,
                            $name
                        )
                    );
                }
            }

            return;
        }

        throw UnableToWriteFile::atLocation($path);
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $writtenAdapter = null;

        foreach ($this->adapters as $name => $adapter) {
            try {
                $adapter->getAdapter()->writeStream($path, $contents, $config);
                $writtenAdapter = $name;
                break;
            } catch (FilesystemException) {
                // TODO log exception ?
            }
        }

        if (null !== $writtenAdapter) {
            foreach ($this->adapters as $name => $_) {
                if ($name !== $writtenAdapter) {
                    $this->messageRepository->push(
                        new ReplicateFile(
                            $this->name,
                            $path,
                            $writtenAdapter,
                            $name
                        )
                    );
                }
            }

            return;
        }

        throw UnableToWriteFile::atLocation($path);
    }

    public function read(string $path): string
    {
        foreach ($this->adapters as $adapter) {
            try {
                return $adapter->getAdapter()->read($path);
            } catch (FilesystemException) {
                // TODO log exception ?
            }
        }

        throw UnableToReadFile::fromLocation($path);
    }

    public function readStream(string $path)
    {
        foreach ($this->adapters as $adapter) {
            try {
                return $adapter->getAdapter()->readStream($path);
            } catch (FilesystemException) {
                // TODO log exception ?
            }
        }

        throw UnableToReadFile::fromLocation($path);
    }

    public function delete(string $path): void
    {
        foreach ($this->adapters as $name => $adapter) {
            try {
                $adapter->getAdapter()->delete($path);
            } catch (FilesystemException) {
                // TODO log exception ?
                $this->messageRepository->push(
                    new DeleteFile($this->name, $path, $name)
                );
            }
        }
    }

    public function deleteDirectory(string $path): void
    {
        foreach ($this->adapters as $name => $adapter) {
            try {
                $adapter->getAdapter()->deleteDirectory($path);
            } catch (FilesystemException) {
                // TODO log exception ?
                $this->messageRepository->push(
                    new DeleteDirectory($this->name, $path, $name)
                );
            }
        }
    }

    public function createDirectory(string $path, Config $config): void
    {
        throw new UnsupportedOperationException(sprintf('Method "createDirectory" is not supported with "%s".', self::class));
    }

    public function setVisibility(string $path, string $visibility): void
    {
        throw new UnsupportedOperationException(sprintf('Method "setVisibility" is not supported with "%s".', self::class));
    }

    public function visibility(string $path): FileAttributes
    {
        foreach ($this->adapters as $adapter) {
            try {
                return $adapter->getAdapter()->visibility($path);
            } catch (FilesystemException) {
                // TODO log exception ?
            }
        }

        throw UnableToRetrieveMetadata::visibility($path);
    }

    public function mimeType(string $path): FileAttributes
    {
        foreach ($this->adapters as $adapter) {
            try {
                return $adapter->getAdapter()->mimeType($path);
            } catch (FilesystemException) {
                // TODO log exception ?
            }
        }

        throw UnableToRetrieveMetadata::mimeType($path);
    }

    public function lastModified(string $path): FileAttributes
    {
        foreach ($this->adapters as $adapter) {
            try {
                return $adapter->getAdapter()->lastModified($path);
            } catch (FilesystemException) {
                // TODO log exception ?
            }
        }

        throw UnableToRetrieveMetadata::lastModified($path);
    }

    public function fileSize(string $path): FileAttributes
    {
        foreach ($this->adapters as $adapter) {
            try {
                return $adapter->getAdapter()->fileSize($path);
            } catch (FilesystemException) {
                // TODO log exception ?
            }
        }

        throw UnableToRetrieveMetadata::fileSize($path);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        throw new UnsupportedOperationException(sprintf('Method "listContents" is not supported with "%s".', self::class));
    }

    public function move(string $source, string $destination, Config $config): void
    {
        throw new UnsupportedOperationException(sprintf('Method "move" is not supported with "%s".', self::class));
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        throw new UnsupportedOperationException(sprintf('Method "copy" is not supported with "%s".', self::class));
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @throws InnerAdapterNotFoundException
     */
    public function getInnerAdapter(int $index): InnerAdapter
    {
        foreach ($this->adapters as $i => $adapter) {
            if ($i === $index) {
                return $adapter;
            }
        }

        throw InnerAdapterNotFoundException::in($this->name, $index);
    }

    /**
     * @return iterable<int, InnerAdapter>
     */
    public function getInnerAdapters(): iterable
    {
        return $this->adapters;
    }
}
