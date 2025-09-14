<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\Event\SyncService;

use Webf\FlysystemFailoverBundle\Message\DeleteFile;

final class DeleteFileMessagePreDispatch
{
    public function __construct(private DeleteFile $message)
    {
    }

    public function getMessage(): DeleteFile
    {
        return $this->message;
    }
}
