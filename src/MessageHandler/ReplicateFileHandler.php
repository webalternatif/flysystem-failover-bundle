<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\MessageHandler;

use GuzzleHttp\Psr7\CachingStream;
use GuzzleHttp\Psr7\StreamWrapper;
use GuzzleHttp\Psr7\Utils;
use League\Flysystem\Config;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemException;
use Webf\FlysystemFailoverBundle\Flysystem\FailoverAdaptersLocatorInterface;
use Webf\FlysystemFailoverBundle\Message\ReplicateFile;
use Webf\FlysystemFailoverBundle\MessageRepository\MessageRepositoryInterface;

class ReplicateFileHandler implements MessageHandlerInterface
{
    /**
     * @template T of FilesystemAdapter
     *
     * @param FailoverAdaptersLocatorInterface<T> $adaptersLocator
     */
    public function __construct(
        private FailoverAdaptersLocatorInterface $adaptersLocator,
        private MessageRepositoryInterface $messageRepository,
    ) {
    }

    public function __invoke(ReplicateFile $message): void
    {
        $failoverAdapter = $this->adaptersLocator->get(
            $message->getFailoverAdapter()
        );

        $sourceAdapter = $failoverAdapter->getInnerAdapter(
            $message->getInnerSourceAdapter()
        );

        $destinationAdapter = $failoverAdapter->getInnerAdapter(
            $message->getInnerDestinationAdapter()
        );

        try {
            $destinationAdapter->writeStream(
                $message->getPath(),
                StreamWrapper::getResource(
                    new CachingStream(
                        Utils::streamFor(
                            $sourceAdapter->readStream($message->getPath())
                        )
                    )
                ),
                new Config()
            );
        } catch (FilesystemException) {
            // TODO log exception ?

            $this->messageRepository->push(new ReplicateFile(
                $message->getFailoverAdapter(),
                $message->getPath(),
                $message->getInnerSourceAdapter(),
                $message->getInnerDestinationAdapter(),
                $message->getRetryCount() + 1
            ));
        }
    }
}
