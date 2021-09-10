<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\Event\SyncService;

use Webf\FlysystemFailoverBundle\Message\MessageInterface;

class DeleteFileMessageDispatched
{
    public function __construct(private MessageInterface $message)
    {
    }

    public function getMessage(): MessageInterface
    {
        return $this->message;
    }
}
