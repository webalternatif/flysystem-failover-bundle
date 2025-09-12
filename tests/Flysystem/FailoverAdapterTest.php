<?php

namespace Tests\Webf\FlysystemFailoverBundle\Flysystem;

use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\UnableToGeneratePublicUrl;
use League\Flysystem\UnableToGenerateTemporaryUrl;
use League\Flysystem\UrlGeneration\PublicUrlGenerator;
use League\Flysystem\UrlGeneration\TemporaryUrlGenerator;
use PHPUnit\Framework\TestCase;
use Webf\FlysystemFailoverBundle\Flysystem\FailoverAdapter;
use Webf\FlysystemFailoverBundle\Flysystem\InnerAdapter;
use Webf\FlysystemFailoverBundle\MessageRepository\InMemoryMessageRepository;

/**
 * @internal
 *
 * @covers \Webf\FlysystemFailoverBundle\Flysystem\FailoverAdapter
 */
final class FailoverAdapterTest extends TestCase
{
    public function test_public_url_forwards_parameters_to_inner_adapter(): void
    {
        $adapter = new class() extends InMemoryFilesystemAdapter implements PublicUrlGenerator {
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

        $adapter1 = new class() extends InMemoryFilesystemAdapter implements PublicUrlGenerator {
            #[\Override]
            public function publicUrl(string $path, Config $config): string
            {
                throw new UnableToGeneratePublicUrl('this adapter fails to generate public URL.', $path);
            }
        };

        $adapter2 = new class() extends InMemoryFilesystemAdapter implements PublicUrlGenerator {
            #[\Override]
            public function publicUrl(string $path, Config $config): string
            {
                return 'public_url_of_adapter_1';
            }
        };

        $adapter3 = new class() extends InMemoryFilesystemAdapter implements PublicUrlGenerator {
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
        $adapter = new class() extends InMemoryFilesystemAdapter implements PublicUrlGenerator {
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
        $adapter = new class() extends InMemoryFilesystemAdapter implements TemporaryUrlGenerator {
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

        $adapter1 = new class() extends InMemoryFilesystemAdapter implements TemporaryUrlGenerator {
            #[\Override]
            public function temporaryUrl(string $path, \DateTimeInterface $expiresAt, Config $config): string
            {
                throw new UnableToGenerateTemporaryUrl('this adapter fails to generate temporary URL.', $path);
            }
        };

        $adapter2 = new class() extends InMemoryFilesystemAdapter implements TemporaryUrlGenerator {
            #[\Override]
            public function temporaryUrl(string $path, \DateTimeInterface $expiresAt, Config $config): string
            {
                return 'temporary_url_of_adapter_1';
            }
        };

        $adapter3 = new class() extends InMemoryFilesystemAdapter implements TemporaryUrlGenerator {
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
        $adapter = new class() extends InMemoryFilesystemAdapter implements TemporaryUrlGenerator {
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
