<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\Flysystem;

use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemException;
use League\Flysystem\UnableToCheckDirectoryExistence;
use League\Flysystem\UnableToCheckFileExistence;
use League\Flysystem\UnableToGeneratePublicUrl;
use League\Flysystem\UnableToGenerateTemporaryUrl;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\UrlGeneration\PublicUrlGenerator;
use League\Flysystem\UrlGeneration\TemporaryUrlGenerator;
use Webf\Flysystem\Composite\CompositeFilesystemAdapter;
use Webf\FlysystemFailoverBundle\Exception\InnerAdapterNotFoundException;
use Webf\FlysystemFailoverBundle\Exception\UnsupportedOperationException;
use Webf\FlysystemFailoverBundle\Message\DeleteDirectory;
use Webf\FlysystemFailoverBundle\Message\DeleteFile;
use Webf\FlysystemFailoverBundle\Message\ReplicateFile;
use Webf\FlysystemFailoverBundle\MessageRepository\MessageRepositoryInterface;

/**
 * @template T of FilesystemAdapter
 *
 * @template-implements CompositeFilesystemAdapter<InnerAdapter<T>>
 */
final class FailoverAdapter implements CompositeFilesystemAdapter, PublicUrlGenerator, TemporaryUrlGenerator
{
    /**
     * @param iterable<int, InnerAdapter<T>> $adapters
     */
    public function __construct(
        private string $name,
        private iterable $adapters,
        private MessageRepositoryInterface $messageRepository,
    ) {
    }

    #[\Override]
    public function fileExists(string $path): bool
    {
        foreach ($this->adapters as $adapter) {
            try {
                return $adapter->fileExists($path);
            } catch (FilesystemException) {
                // TODO log exception ?
            }
        }

        throw UnableToCheckFileExistence::forLocation($path);
    }

    #[\Override]
    public function directoryExists(string $path): bool
    {
        foreach ($this->adapters as $adapter) {
            try {
                return $adapter->directoryExists($path);
            } catch (FilesystemException) {
                // TODO log exception ?
            }
        }

        throw UnableToCheckDirectoryExistence::forLocation($path);
    }

    #[\Override]
    public function write(string $path, string $contents, Config $config): void
    {
        $writtenAdapter = null;

        foreach ($this->adapters as $name => $adapter) {
            try {
                $adapter->write($path, $contents, $config);
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

    #[\Override]
    public function writeStream(string $path, $contents, Config $config): void
    {
        $writtenAdapter = null;

        foreach ($this->adapters as $name => $adapter) {
            try {
                $adapter->writeStream($path, $contents, $config);
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

    #[\Override]
    public function read(string $path): string
    {
        foreach ($this->adapters as $adapter) {
            try {
                return $adapter->read($path);
            } catch (FilesystemException) {
                // TODO log exception ?
            }
        }

        throw UnableToReadFile::fromLocation($path);
    }

    #[\Override]
    public function readStream(string $path)
    {
        foreach ($this->adapters as $adapter) {
            try {
                return $adapter->readStream($path);
            } catch (FilesystemException) {
                // TODO log exception ?
            }
        }

        throw UnableToReadFile::fromLocation($path);
    }

    #[\Override]
    public function delete(string $path): void
    {
        foreach ($this->adapters as $name => $adapter) {
            try {
                $adapter->delete($path);
            } catch (FilesystemException) {
                // TODO log exception ?
                $this->messageRepository->push(
                    new DeleteFile($this->name, $path, $name)
                );
            }
        }
    }

    #[\Override]
    public function deleteDirectory(string $path): void
    {
        foreach ($this->adapters as $name => $adapter) {
            try {
                $adapter->deleteDirectory($path);
            } catch (FilesystemException) {
                // TODO log exception ?
                $this->messageRepository->push(
                    new DeleteDirectory($this->name, $path, $name)
                );
            }
        }
    }

    #[\Override]
    public function createDirectory(string $path, Config $config): void
    {
        throw new UnsupportedOperationException(sprintf('Method "createDirectory" is not supported with "%s".', self::class));
    }

    #[\Override]
    public function setVisibility(string $path, string $visibility): void
    {
        throw new UnsupportedOperationException(sprintf('Method "setVisibility" is not supported with "%s".', self::class));
    }

    #[\Override]
    public function visibility(string $path): FileAttributes
    {
        foreach ($this->adapters as $adapter) {
            try {
                return $adapter->visibility($path);
            } catch (FilesystemException) {
                // TODO log exception ?
            }
        }

        throw UnableToRetrieveMetadata::visibility($path);
    }

    #[\Override]
    public function mimeType(string $path): FileAttributes
    {
        foreach ($this->adapters as $adapter) {
            try {
                return $adapter->mimeType($path);
            } catch (FilesystemException) {
                // TODO log exception ?
            }
        }

        throw UnableToRetrieveMetadata::mimeType($path);
    }

    #[\Override]
    public function lastModified(string $path): FileAttributes
    {
        foreach ($this->adapters as $adapter) {
            try {
                return $adapter->lastModified($path);
            } catch (FilesystemException) {
                // TODO log exception ?
            }
        }

        throw UnableToRetrieveMetadata::lastModified($path);
    }

    #[\Override]
    public function fileSize(string $path): FileAttributes
    {
        foreach ($this->adapters as $adapter) {
            try {
                return $adapter->fileSize($path);
            } catch (FilesystemException) {
                // TODO log exception ?
            }
        }

        throw UnableToRetrieveMetadata::fileSize($path);
    }

    #[\Override]
    public function listContents(string $path, bool $deep): iterable
    {
        throw new UnsupportedOperationException(sprintf('Method "listContents" is not supported with "%s".', self::class));
    }

    #[\Override]
    public function move(string $source, string $destination, Config $config): void
    {
        throw new UnsupportedOperationException(sprintf('Method "move" is not supported with "%s".', self::class));
    }

    #[\Override]
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
     * @return iterable<int, InnerAdapter<T>>
     */
    #[\Override]
    public function getInnerAdapters(): iterable
    {
        return $this->adapters;
    }

    #[\Override]
    public function publicUrl(string $path, Config $config): string
    {
        foreach ($this->adapters as $adapter) {
            $innerAdapter = $adapter->getInnerAdapter();
            if (!$innerAdapter instanceof PublicUrlGenerator) {
                continue;
            }

            try {
                return $innerAdapter->publicUrl($path, $config);
            } catch (UnableToGeneratePublicUrl) {
                // TODO log exception ?
            }
        }

        throw new UnableToGeneratePublicUrl('no inner adapter is configured or has succeeded.', $path);
    }

    #[\Override]
    public function temporaryUrl(string $path, \DateTimeInterface $expiresAt, Config $config): string
    {
        foreach ($this->adapters as $adapter) {
            $innerAdapter = $adapter->getInnerAdapter();
            if (!$innerAdapter instanceof TemporaryUrlGenerator) {
                continue;
            }

            try {
                return $innerAdapter->temporaryUrl($path, $expiresAt, $config);
            } catch (UnableToGenerateTemporaryUrl) {
                // TODO log exception ?
            }
        }

        throw new UnableToGenerateTemporaryUrl('no inner adapter is configured or has succeeded.', $path);
    }
}
