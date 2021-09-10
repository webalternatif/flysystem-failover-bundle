<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\MessageRepository;

use Webf\FlysystemFailoverBundle\Message\MessageInterface;

class InMemoryMessageRepository implements MessageRepositoryInterface
{
    /**
     * @var array<MessageInterface>
     */
    private array $messages = [];

    public function push(MessageInterface $message): void
    {
        $this->messages[] = $message;
    }

    public function pop(): ?MessageInterface
    {
        return array_shift($this->messages);
    }

    public function popAll(): array
    {
        $messages = $this->messages;
        $this->messages = [];

        return $messages;
    }
}
