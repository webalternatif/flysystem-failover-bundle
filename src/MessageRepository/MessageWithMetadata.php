<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\MessageRepository;

use Webf\FlysystemFailoverBundle\Message\MessageInterface;

final class MessageWithMetadata
{
    public function __construct(
        private MessageInterface $message,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $availableAt,
    ) {
    }

    public function getMessage(): MessageInterface
    {
        return $this->message;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getAvailableAt(): \DateTimeImmutable
    {
        return $this->availableAt;
    }
}
