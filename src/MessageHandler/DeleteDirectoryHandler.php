<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\MessageHandler;

use League\Flysystem\FilesystemException;
use Webf\FlysystemFailoverBundle\Flysystem\FailoverAdaptersLocatorInterface;
use Webf\FlysystemFailoverBundle\Message\DeleteDirectory;
use Webf\FlysystemFailoverBundle\MessageRepository\MessageRepositoryInterface;

final class DeleteDirectoryHandler implements MessageHandlerInterface
{
    public function __construct(
        private FailoverAdaptersLocatorInterface $adaptersLocator,
        private MessageRepositoryInterface $messageRepository,
    ) {
    }

    public function __invoke(DeleteDirectory $message): void
    {
        $adapter = $this->adaptersLocator
            ->get($message->getFailoverAdapter())
            ->getInnerAdapter($message->getInnerDestinationAdapter())
        ;

        try {
            $adapter->deleteDirectory($message->getPath());
        } catch (FilesystemException) {
            // TODO log exception ?

            $this->messageRepository->push(new DeleteDirectory(
                $message->getFailoverAdapter(),
                $message->getPath(),
                $message->getInnerDestinationAdapter(),
                $message->getRetryCount() + 1
            ));
        }
    }
}
