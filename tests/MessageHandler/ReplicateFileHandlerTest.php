<?php

declare(strict_types=1);

namespace Tests\Webf\FlysystemFailoverBundle\MessageHandler;

use GuzzleHttp\Psr7\NoSeekStream;
use GuzzleHttp\Psr7\StreamWrapper;
use GuzzleHttp\Psr7\Utils;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToWriteFile;
use PHPUnit\Framework\TestCase;
use Webf\FlysystemFailoverBundle\Flysystem\FailoverAdapter;
use Webf\FlysystemFailoverBundle\Flysystem\FailoverAdaptersLocator;
use Webf\FlysystemFailoverBundle\Flysystem\InnerAdapter;
use Webf\FlysystemFailoverBundle\Message\ReplicateFile;
use Webf\FlysystemFailoverBundle\MessageHandler\ReplicateFileHandler;
use Webf\FlysystemFailoverBundle\MessageRepository\InMemoryMessageRepository;

/**
 * @internal
 *
 * @covers \Webf\FlysystemFailoverBundle\MessageHandler\ReplicateFileHandler
 */
class ReplicateFileHandlerTest extends TestCase
{
    public function test_source_stream_can_be_non_seekable(): void
    {
        $streamContent = 'stream content';

        $adapter0 = $this->createMock(FilesystemAdapter::class);
        $adapter0
            ->method('readStream')
            ->willReturn(StreamWrapper::getResource(new NoSeekStream(Utils::streamFor($streamContent))))
        ;

        $adapter1 = $this->createMock(FilesystemAdapter::class);
        $adapter1
            ->method('writeStream')
            ->will($this->returnCallback(
                /**
                 * @param resource $contents
                 */
                function (string $path, $contents) use ($streamContent) {
                    try {
                        $this->assertEquals($streamContent, stream_get_contents($contents));
                        $this->assertTrue(rewind($contents));
                        $this->assertEquals($streamContent, stream_get_contents($contents));
                    } catch (\Throwable $e) {
                        throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
                    }
                }
            ))
        ;

        $messageRepository = new InMemoryMessageRepository();
        $failoverAdapter = new FailoverAdapter(
            'default',
            [
                new InnerAdapter($adapter0),
                new InnerAdapter($adapter1),
            ],
            $messageRepository
        );

        $handler = new ReplicateFileHandler(
            new FailoverAdaptersLocator([$failoverAdapter]),
            $messageRepository,
        );

        $handler(new ReplicateFile($failoverAdapter->getName(), 'file', 0, 1));

        $this->assertEmpty($messageRepository->popAll());
    }
}
