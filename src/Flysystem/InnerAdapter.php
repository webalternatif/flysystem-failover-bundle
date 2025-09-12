<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\Flysystem;

use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use Webf\Flysystem\Composite\CompositeFilesystemAdapter;

/**
 * @psalm-type _Options=array{
 *     time_shift?: int
 * }
 *
 * @template T of FilesystemAdapter
 *
 * @template-implements CompositeFilesystemAdapter<T>
 */
final class InnerAdapter implements CompositeFilesystemAdapter
{
    /**
     * @param T        $adapter
     * @param _Options $options
     */
    public function __construct(
        private FilesystemAdapter $adapter,
        private array $options = [],
    ) {
    }

    public function getTimeShift(): int
    {
        return $this->options['time_shift'] ?? 0;
    }

    #[\Override]
    public function fileExists(string $path): bool
    {
        return $this->adapter->fileExists($path);
    }

    #[\Override]
    public function directoryExists(string $path): bool
    {
        return $this->adapter->directoryExists($path);
    }

    #[\Override]
    public function write(string $path, string $contents, Config $config): void
    {
        $this->adapter->write($path, $contents, $config);
    }

    #[\Override]
    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->adapter->writeStream($path, $contents, $config);
    }

    #[\Override]
    public function read(string $path): string
    {
        return $this->adapter->read($path);
    }

    #[\Override]
    public function readStream(string $path)
    {
        return $this->adapter->readStream($path);
    }

    #[\Override]
    public function delete(string $path): void
    {
        $this->adapter->delete($path);
    }

    #[\Override]
    public function deleteDirectory(string $path): void
    {
        $this->adapter->deleteDirectory($path);
    }

    #[\Override]
    public function createDirectory(string $path, Config $config): void
    {
        $this->adapter->createDirectory($path, $config);
    }

    #[\Override]
    public function setVisibility(string $path, string $visibility): void
    {
        $this->adapter->setVisibility($path, $visibility);
    }

    #[\Override]
    public function visibility(string $path): FileAttributes
    {
        return $this->adapter->visibility($path);
    }

    #[\Override]
    public function mimeType(string $path): FileAttributes
    {
        return $this->adapter->mimeType($path);
    }

    #[\Override]
    public function lastModified(string $path): FileAttributes
    {
        return $this->adapter->lastModified($path);
    }

    #[\Override]
    public function fileSize(string $path): FileAttributes
    {
        return $this->adapter->fileSize($path);
    }

    #[\Override]
    public function listContents(string $path, bool $deep): iterable
    {
        return $this->adapter->listContents($path, $deep);
    }

    #[\Override]
    public function move(string $source, string $destination, Config $config): void
    {
        $this->adapter->move($source, $destination, $config);
    }

    #[\Override]
    public function copy(string $source, string $destination, Config $config): void
    {
        $this->adapter->copy($source, $destination, $config);
    }

    #[\Override]
    public function getInnerAdapters(): iterable
    {
        return [$this->adapter];
    }
}
