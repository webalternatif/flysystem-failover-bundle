<?php

declare(strict_types=1);

namespace Tests\Webf\FlysystemFailoverBundle\Flysystem;

use League\Flysystem\ChecksumAlgoIsNotSupported;
use League\Flysystem\ChecksumProvider;
use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemException;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToGeneratePublicUrl;
use League\Flysystem\UnableToGenerateTemporaryUrl;
use League\Flysystem\UnableToListContents;
use League\Flysystem\UnableToProvideChecksum;
use League\Flysystem\UrlGeneration\PublicUrlGenerator;
use League\Flysystem\UrlGeneration\TemporaryUrlGenerator;
use PHPUnit\Framework\TestCase;
use Webf\FlysystemFailoverBundle\Exception\UnsupportedOperationException;
use Webf\FlysystemFailoverBundle\Flysystem\FailoverAdapter;
use Webf\FlysystemFailoverBundle\Flysystem\InnerAdapter;
use Webf\FlysystemFailoverBundle\Flysystem\ListContentsStrategy;
use Webf\FlysystemFailoverBundle\MessageRepository\InMemoryMessageRepository;

/**
 * @internal
 *
 * @covers \Webf\FlysystemFailoverBundle\Flysystem\FailoverAdapter
 */
final class FailoverAdapterTest extends TestCase
{
    public function test_checksum_forwards_parameters_to_inner_adapter(): void
    {
        $adapter = new class extends InMemoryFilesystemAdapter implements ChecksumProvider {
            #[\Override]
            public function checksum(string $path, Config $config): string
            {
                $config = $config->toArray();

                return sprintf(
                    '%s?%s',
                    $path,
                    join('&', array_map(fn ($k, $v) => "{$k}={$v}", array_keys($config), $config)),
                );
            }
        };

        $filesystem = new Filesystem(
            new FailoverAdapter('default', $this->toInner([$adapter]), new InMemoryMessageRepository())
        );

        $this->assertEquals(
            'file.txt?foo=bar&baz=qux',
            $filesystem->checksum('file.txt', ['foo' => 'bar', 'baz' => 'qux']),
        );
    }

    public function test_providing_checksum_returns_result_of_first_successful_inner_adapter(): void
    {
        $adapter0 = new InMemoryFilesystemAdapter();

        $adapter1 = new class extends InMemoryFilesystemAdapter implements ChecksumProvider {
            #[\Override]
            public function checksum(string $path, Config $config): string
            {
                throw new ChecksumAlgoIsNotSupported();
            }
        };

        $adapter2 = new class extends InMemoryFilesystemAdapter implements ChecksumProvider {
            #[\Override]
            public function checksum(string $path, Config $config): string
            {
                throw new UnableToProvideChecksum('this adapter fails to provide checksum.', $path);
            }
        };

        $adapter3 = new class extends InMemoryFilesystemAdapter implements ChecksumProvider {
            #[\Override]
            public function checksum(string $path, Config $config): string
            {
                return 'checksum_of_adapter_3';
            }
        };

        $adapter4 = new class extends InMemoryFilesystemAdapter implements ChecksumProvider {
            #[\Override]
            public function checksum(string $path, Config $config): string
            {
                return 'checksum_of_adapter_4';
            }
        };

        $filesystem = new Filesystem(
            new FailoverAdapter(
                'default',
                $this->toInner([$adapter0, $adapter1, $adapter2, $adapter3, $adapter4]),
                new InMemoryMessageRepository()
            )
        );

        $this->assertEquals(
            'checksum_of_adapter_3',
            $filesystem->checksum('file.txt')
        );
    }

    public function test_checksum_throw_exception_when_no_inner_adapter_can_provide_checksum(): void
    {
        $adapter0 = new InMemoryFilesystemAdapter();
        $adapter1 = new InMemoryFilesystemAdapter();
        $adapter2 = new InMemoryFilesystemAdapter();

        $filesystem = new Filesystem(
            new FailoverAdapter(
                'default',
                $this->toInner([$adapter0, $adapter1, $adapter2]),
                new InMemoryMessageRepository()
            )
        );

        $this->expectException(UnableToProvideChecksum::class);

        $filesystem->checksum('file.txt');
    }

    public function test_checksum_throw_exception_when_no_inner_adapter_succeed_to_provide_checksum(): void
    {
        $adapter0 = new class extends InMemoryFilesystemAdapter implements ChecksumProvider {
            #[\Override]
            public function checksum(string $path, Config $config): string
            {
                throw new ChecksumAlgoIsNotSupported();
            }
        };

        $adapter1 = new class extends InMemoryFilesystemAdapter implements ChecksumProvider {
            #[\Override]
            public function checksum(string $path, Config $config): string
            {
                throw new UnableToProvideChecksum('this adapter fails to provide checksum.', $path);
            }
        };

        $filesystem = new Filesystem(
            new FailoverAdapter(
                'default',
                $this->toInner([$adapter0, $adapter1]),
                new InMemoryMessageRepository()
            )
        );

        $this->expectException(UnableToProvideChecksum::class);

        $filesystem->checksum('file.txt');
    }

    public function test_public_url_forwards_parameters_to_inner_adapter(): void
    {
        $adapter = new class extends InMemoryFilesystemAdapter implements PublicUrlGenerator {
            #[\Override]
            public function publicUrl(string $path, Config $config): string
            {
                $config = $config->toArray();

                return sprintf(
                    '%s?%s',
                    $path,
                    join('&', array_map(fn ($k, $v) => "{$k}={$v}", array_keys($config), $config)),
                );
            }
        };

        $filesystem = new Filesystem(
            new FailoverAdapter('default', $this->toInner([$adapter]), new InMemoryMessageRepository())
        );

        $this->assertEquals(
            'file.txt?foo=bar&baz=qux',
            $filesystem->publicUrl('file.txt', ['foo' => 'bar', 'baz' => 'qux']),
        );
    }

    public function test_generating_public_url_returns_first_successful_public_url(): void
    {
        $adapter0 = new InMemoryFilesystemAdapter();

        $adapter1 = new class extends InMemoryFilesystemAdapter implements PublicUrlGenerator {
            #[\Override]
            public function publicUrl(string $path, Config $config): string
            {
                throw new UnableToGeneratePublicUrl('this adapter fails to generate public URL.', $path);
            }
        };

        $adapter2 = new class extends InMemoryFilesystemAdapter implements PublicUrlGenerator {
            #[\Override]
            public function publicUrl(string $path, Config $config): string
            {
                return 'public_url_of_adapter_1';
            }
        };

        $adapter3 = new class extends InMemoryFilesystemAdapter implements PublicUrlGenerator {
            #[\Override]
            public function publicUrl(string $path, Config $config): string
            {
                return 'public_url_of_adapter_2';
            }
        };

        $filesystem = new Filesystem(
            new FailoverAdapter(
                'default',
                $this->toInner([$adapter0, $adapter1, $adapter2, $adapter3]),
                new InMemoryMessageRepository()
            )
        );

        $this->assertEquals(
            'public_url_of_adapter_1',
            $filesystem->publicUrl('file.txt')
        );
    }

    public function test_public_url_throw_exception_when_no_inner_adapter_can_generate_public_url(): void
    {
        $adapter0 = new InMemoryFilesystemAdapter();
        $adapter1 = new InMemoryFilesystemAdapter();
        $adapter2 = new InMemoryFilesystemAdapter();

        $filesystem = new Filesystem(
            new FailoverAdapter(
                'default',
                $this->toInner([$adapter0, $adapter1, $adapter2]),
                new InMemoryMessageRepository()
            )
        );

        $this->expectException(UnableToGeneratePublicUrl::class);

        $filesystem->publicUrl('file.txt');
    }

    public function test_public_url_throw_exception_when_no_inner_adapter_succeed_to_provide_public_url(): void
    {
        $adapter = new class extends InMemoryFilesystemAdapter implements PublicUrlGenerator {
            #[\Override]
            public function publicUrl(string $path, Config $config): string
            {
                throw new UnableToGeneratePublicUrl('this adapter fails to generate public URL.', $path);
            }
        };

        $filesystem = new Filesystem(
            new FailoverAdapter(
                'default',
                $this->toInner([$adapter]),
                new InMemoryMessageRepository()
            )
        );

        $this->expectException(UnableToGeneratePublicUrl::class);

        $filesystem->publicUrl('file.txt');
    }

    public function test_temporary_url_forwards_parameters_to_inner_adapter(): void
    {
        $adapter = new class extends InMemoryFilesystemAdapter implements TemporaryUrlGenerator {
            #[\Override]
            public function temporaryUrl(string $path, \DateTimeInterface $expiresAt, Config $config): string
            {
                $config = $config->toArray();

                return sprintf(
                    '%s?expires=%s&%s',
                    $path,
                    $expiresAt->getTimestamp(),
                    join('&', array_map(fn ($k, $v) => "{$k}={$v}", array_keys($config), $config)),
                );
            }
        };

        $filesystem = new Filesystem(
            new FailoverAdapter('default', $this->toInner([$adapter]), new InMemoryMessageRepository())
        );

        $expiresAt = new \DateTime('+1 day');
        $this->assertEquals(
            "file.txt?expires={$expiresAt->getTimestamp()}&foo=bar&baz=qux",
            $filesystem->temporaryUrl('file.txt', $expiresAt, ['foo' => 'bar', 'baz' => 'qux']),
        );
    }

    public function test_generating_temporary_url_returns_first_successful_temporary_url(): void
    {
        $adapter0 = new InMemoryFilesystemAdapter();

        $adapter1 = new class extends InMemoryFilesystemAdapter implements TemporaryUrlGenerator {
            #[\Override]
            public function temporaryUrl(string $path, \DateTimeInterface $expiresAt, Config $config): string
            {
                throw new UnableToGenerateTemporaryUrl('this adapter fails to generate temporary URL.', $path);
            }
        };

        $adapter2 = new class extends InMemoryFilesystemAdapter implements TemporaryUrlGenerator {
            #[\Override]
            public function temporaryUrl(string $path, \DateTimeInterface $expiresAt, Config $config): string
            {
                return 'temporary_url_of_adapter_1';
            }
        };

        $adapter3 = new class extends InMemoryFilesystemAdapter implements TemporaryUrlGenerator {
            #[\Override]
            public function temporaryUrl(string $path, \DateTimeInterface $expiresAt, Config $config): string
            {
                return 'temporary_url_of_adapter_2';
            }
        };

        $filesystem = new Filesystem(
            new FailoverAdapter(
                'default',
                $this->toInner([$adapter0, $adapter1, $adapter2, $adapter3]),
                new InMemoryMessageRepository()
            )
        );

        $this->assertEquals(
            'temporary_url_of_adapter_1',
            $filesystem->temporaryUrl('file.txt', new \DateTime('+1 day'))
        );
    }

    public function test_temporary_url_throw_exception_when_no_inner_adapter_can_generate_temporary_url(): void
    {
        $adapter0 = new InMemoryFilesystemAdapter();
        $adapter1 = new InMemoryFilesystemAdapter();
        $adapter2 = new InMemoryFilesystemAdapter();

        $filesystem = new Filesystem(
            new FailoverAdapter(
                'default',
                $this->toInner([$adapter0, $adapter1, $adapter2]),
                new InMemoryMessageRepository()
            )
        );

        $this->expectException(UnableToGenerateTemporaryUrl::class);

        $filesystem->temporaryUrl('file.txt', new \DateTime('+1 day'));
    }

    public function test_temporary_url_throw_exception_when_no_inner_adapter_succeed_to_provide_temporary_url(): void
    {
        $adapter = new class extends InMemoryFilesystemAdapter implements TemporaryUrlGenerator {
            #[\Override]
            public function temporaryUrl(string $path, \DateTimeInterface $expiresAt, Config $config): string
            {
                throw new UnableToGenerateTemporaryUrl('this adapter fails to generate temporary URL.', $path);
            }
        };

        $filesystem = new Filesystem(
            new FailoverAdapter(
                'default',
                $this->toInner([$adapter]),
                new InMemoryMessageRepository()
            )
        );

        $this->expectException(UnableToGenerateTemporaryUrl::class);

        $filesystem->temporaryUrl('file.txt', new \DateTime('+1 day'));
    }

    public function test_list_contents_is_disabled_by_default(): void
    {
        $adapter = new FailoverAdapter(
            'default',
            $this->toInner([$this->adapterWithFiles(['a'])]),
            new InMemoryMessageRepository()
        );

        $this->expectException(UnsupportedOperationException::class);

        $adapter->listContents('/', true);
    }

    public function test_list_contents_with_disabled_strategy_throws_exception(): void
    {
        $adapter = new FailoverAdapter(
            'default',
            $this->toInner([$this->adapterWithFiles(['a'])]),
            new InMemoryMessageRepository(),
            ['list_contents_strategy' => ListContentsStrategy::Disabled]
        );

        $this->expectException(UnsupportedOperationException::class);

        $adapter->listContents('/', true);
    }

    public function test_list_contents_with_first_strategy_returns_contents_of_first_inner_adapter(): void
    {
        $this->assertSame(
            ['a', 'c'],
            $this->listPaths(ListContentsStrategy::First, [
                $this->adapterWithFiles(['a', 'c']),
                $this->adapterWithFiles(['a', 'b', 'c']),
            ])
        );
    }

    public function test_list_contents_with_first_strategy_returns_empty_contents_of_first_inner_adapter(): void
    {
        $this->assertSame(
            [],
            $this->listPaths(ListContentsStrategy::First, [
                $this->adapterWithFiles([]),
                $this->adapterWithFiles(['a']),
            ])
        );
    }

    public function test_list_contents_with_first_strategy_forwards_parameters_to_inner_adapter(): void
    {
        $this->assertSame(
            ['dir/a', 'dir/sub'],
            $this->listPaths(
                ListContentsStrategy::First,
                [$this->adapterWithFiles(['a', 'dir/a', 'dir/sub/b'])],
                'dir',
                false
            )
        );

        $this->assertSame(
            ['dir/a', 'dir/sub', 'dir/sub/b'],
            $this->listPaths(
                ListContentsStrategy::First,
                [$this->adapterWithFiles(['a', 'dir/a', 'dir/sub/b'])],
                'dir',
                true
            )
        );
    }

    public function test_list_contents_with_first_strategy_uses_next_inner_adapter_when_one_fails_before_first_item(): void
    {
        $this->assertSame(
            ['b'],
            $this->listPaths(ListContentsStrategy::First, [
                $this->failingAdapterWithFiles(['a'], 0, new UnableToListContents('adapter 0 failed.')),
                $this->adapterWithFiles(['b']),
            ])
        );
    }

    public function test_list_contents_with_first_strategy_throws_exception_when_inner_adapter_fails_after_first_item(): void
    {
        $failure = new UnableToListContents('adapter 0 failed.');
        $nextAdapterListed = false;

        [$paths, $exception] = $this->listPathsUntilException(ListContentsStrategy::First, [
            $this->failingAdapterWithFiles(['a', 'b'], 1, $failure),
            $this->spyAdapterWithFiles(['a', 'b'], function () use (&$nextAdapterListed): void {
                $nextAdapterListed = true;
            }),
        ]);

        $this->assertSame(['a'], $paths);
        $this->assertInstanceOf(UnableToListContents::class, $exception);
        $this->assertSame(
            "Inner adapter #0 failed after some items were listed, the listing is incomplete:\n"
            .'- inner adapter #0: adapter 0 failed.',
            $exception->getMessage()
        );
        $this->assertSame($failure, $exception->getPrevious());
        $this->assertFalse($nextAdapterListed);
    }

    public function test_list_contents_with_first_strategy_throws_exception_when_all_inner_adapters_fail(): void
    {
        $lastFailure = new UnableToListContents('adapter 1 failed.');

        [$paths, $exception] = $this->listPathsUntilException(ListContentsStrategy::First, [
            $this->failingAdapterWithFiles(['a'], 0, new UnableToListContents('adapter 0 failed.')),
            $this->failingAdapterWithFiles(['a'], 0, $lastFailure),
        ]);

        $this->assertSame([], $paths);
        $this->assertInstanceOf(UnableToListContents::class, $exception);
        $this->assertSame(
            "No inner adapter has succeeded:\n"
            ."- inner adapter #0: adapter 0 failed.\n"
            .'- inner adapter #1: adapter 1 failed.',
            $exception->getMessage()
        );
        $this->assertSame($lastFailure, $exception->getPrevious());
    }

    public function test_list_contents_with_first_strategy_throws_exception_when_there_is_no_inner_adapter(): void
    {
        [$paths, $exception] = $this->listPathsUntilException(ListContentsStrategy::First, []);

        $this->assertSame([], $paths);
        $this->assertInstanceOf(UnableToListContents::class, $exception);
        $this->assertSame('No inner adapter is configured.', $exception->getMessage());
        $this->assertNull($exception->getPrevious());
    }

    /**
     * @param list<string> $paths
     */
    private function adapterWithFiles(array $paths): InMemoryFilesystemAdapter
    {
        $adapter = new InMemoryFilesystemAdapter();
        foreach ($paths as $path) {
            $adapter->write($path, '', new Config());
        }

        return $adapter;
    }

    /**
     * Returns an adapter that throws $failure after listing $failAfter items.
     *
     * @param list<string> $paths
     */
    private function failingAdapterWithFiles(
        array $paths,
        int $failAfter,
        FilesystemException $failure,
    ): InMemoryFilesystemAdapter {
        $adapter = new class($failAfter, $failure) extends InMemoryFilesystemAdapter {
            public function __construct(
                private int $failAfter,
                private FilesystemException $failure,
            ) {
                parent::__construct();
            }

            #[\Override]
            public function listContents(string $path, bool $deep): iterable
            {
                $count = 0;
                foreach (parent::listContents($path, $deep) as $item) {
                    if ($count++ === $this->failAfter) {
                        break;
                    }

                    yield $item;
                }

                throw $this->failure;
            }
        };

        foreach ($paths as $path) {
            $adapter->write($path, '', new Config());
        }

        return $adapter;
    }

    /**
     * Returns an adapter that calls $onList when its contents are listed.
     *
     * @param list<string>     $paths
     * @param \Closure(): void $onList
     */
    private function spyAdapterWithFiles(array $paths, \Closure $onList): InMemoryFilesystemAdapter
    {
        $adapter = new class($onList) extends InMemoryFilesystemAdapter {
            /**
             * @param \Closure(): void $onList
             */
            public function __construct(private \Closure $onList)
            {
                parent::__construct();
            }

            #[\Override]
            public function listContents(string $path, bool $deep): iterable
            {
                ($this->onList)();

                return parent::listContents($path, $deep);
            }
        };

        foreach ($paths as $path) {
            $adapter->write($path, '', new Config());
        }

        return $adapter;
    }

    /**
     * @param list<FilesystemAdapter> $adapters
     *
     * @return array<string>
     */
    private function listPaths(
        ListContentsStrategy $strategy,
        array $adapters,
        string $path = '/',
        bool $deep = true,
    ): array {
        $filesystem = new Filesystem(
            new FailoverAdapter(
                'default',
                $this->toInner($adapters),
                new InMemoryMessageRepository(),
                ['list_contents_strategy' => $strategy]
            )
        );

        return $filesystem
            ->listContents($path, $deep)
            ->map(fn (StorageAttributes $item) => $item->path())
            ->toArray()
        ;
    }

    /**
     * Iterates the failover adapter directly rather than through `Filesystem`,
     * which rewraps any `Throwable` as `UnableToListContents` and would hide
     * which exception was actually thrown.
     *
     * @param list<FilesystemAdapter> $adapters
     *
     * @return array{list<string>, ?\Throwable} paths listed before the exception, and the exception
     */
    private function listPathsUntilException(ListContentsStrategy $strategy, array $adapters): array
    {
        $adapter = new FailoverAdapter(
            'default',
            $this->toInner($adapters),
            new InMemoryMessageRepository(),
            ['list_contents_strategy' => $strategy]
        );

        $paths = [];
        try {
            foreach ($adapter->listContents('/', true) as $item) {
                $paths[] = $item->path();
            }
        } catch (\Throwable $e) {
            return [$paths, $e];
        }

        return [$paths, null];
    }

    /**
     * @param list<FilesystemAdapter> $adapters
     *
     * @return list<InnerAdapter>
     */
    private function toInner(array $adapters): array
    {
        return array_map(fn (FilesystemAdapter $adapter) => new InnerAdapter($adapter), $adapters);
    }
}
