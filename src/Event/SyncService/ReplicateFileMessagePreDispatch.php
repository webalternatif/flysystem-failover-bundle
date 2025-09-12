<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\Event\SyncService;

use Webf\FlysystemFailoverBundle\Message\ReplicateFile;

final class ReplicateFileMessagePreDispatch
{
    public function __construct(private ReplicateFile $message)
    {
    }

    public function getMessage(): ReplicateFile
    {
        return $this->message;
    }
}
