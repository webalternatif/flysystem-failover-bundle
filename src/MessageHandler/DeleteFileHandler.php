<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\MessageHandler;

use League\Flysystem\FilesystemException;
use Webf\FlysystemFailoverBundle\Flysystem\FailoverAdaptersLocatorInterface;
use Webf\FlysystemFailoverBundle\Message\DeleteFile;
use Webf\FlysystemFailoverBundle\MessageRepository\MessageRepositoryInterface;

class DeleteFileHandler implements MessageHandlerInterface
{
    public function __construct(
        private FailoverAdaptersLocatorInterface $adaptersLocator,
        private MessageRepositoryInterface $messageRepository,
    ) {
    }

    public function __invoke(DeleteFile $message): void
    {
        $adapter = $this->adaptersLocator
            ->get($message->getFailoverAdapter())
            ->getInnerAdapter($message->getInnerDestinationAdapter())
            ->getAdapter()
        ;

        try {
            $adapter->delete($message->getPath());
        } catch (FilesystemException) {
            // TODO log exception ?

            $this->messageRepository->push(new DeleteFile(
                $message->getFailoverAdapter(),
                $message->getPath(),
                $message->getInnerDestinationAdapter(),
                $message->getRetryCount() + 1
            ));
        }
    }
}
