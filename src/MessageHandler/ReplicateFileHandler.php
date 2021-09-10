<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\MessageHandler;

use League\Flysystem\Config;
use League\Flysystem\FilesystemException;
use Webf\FlysystemFailoverBundle\Flysystem\FailoverAdaptersLocatorInterface;
use Webf\FlysystemFailoverBundle\Message\ReplicateFile;
use Webf\FlysystemFailoverBundle\MessageRepository\MessageRepositoryInterface;

class ReplicateFileHandler implements MessageHandlerInterface
{
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

        $sourceAdapter = $failoverAdapter
            ->getInnerAdapter($message->getInnerSourceAdapter())
            ->getAdapter()
        ;

        $destinationAdapter = $failoverAdapter
            ->getInnerAdapter($message->getInnerDestinationAdapter())
            ->getAdapter()
        ;

        try {
            $destinationAdapter->writeStream(
                $message->getPath(),
                $sourceAdapter->readStream($message->getPath()),
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
