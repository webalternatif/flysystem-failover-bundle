<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\MessageRepository;

use Webf\FlysystemFailoverBundle\Message\MessageInterface;

interface MessageRepositoryInterface
{
    /**
     * Persist the given message in the underlaying storage so that it can be
     * fetched later by another process.
     */
    public function push(MessageInterface $message): void;

    /**
     * Return the next message (if any) to process right now and remove it from
     * the underlaying storage.
     */
    public function pop(): ?MessageInterface;
}
