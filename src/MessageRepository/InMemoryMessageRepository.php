<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\MessageRepository;

use Webf\FlysystemFailoverBundle\Message\MessageInterface;

final class InMemoryMessageRepository implements MessageRepositoryInterface
{
    /**
     * @var array<MessageInterface>
     */
    private array $messages = [];

    #[\Override]
    public function push(MessageInterface $message): void
    {
        $this->messages[] = $message;
    }

    #[\Override]
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

    #[\Override]
    public function findBy(FindByCriteria $criteria): FindResults
    {
        return new FindResults(
            $criteria->getLimit(),
            count($this->messages),
            $criteria->getPage(),
            $this->iterateBy($criteria),
        );
    }

    /**
     * @return iterable<int, MessageWithMetadata>
     */
    private function iterateBy(FindByCriteria $criteria): iterable
    {
        $now = new \DateTimeImmutable();

        $i = 0; // index of the message in the complete array
        $j = 0; // number of eligible messages (without considering page and limit)
        $k = 0; // number of returned messages

        while (isset($this->messages[$i]) && $k < $criteria->getLimit()) {
            if (null !== $criteria->getFailoverAdapter() && $criteria->getFailoverAdapter() !== $this->messages[$i]->getFailoverAdapter()) {
                ++$i;
                continue;
            }

            if ($j < ($criteria->getPage() - 1) * $criteria->getLimit()) {
                ++$i;
                ++$j;
                continue;
            }

            yield new MessageWithMetadata($this->messages[$i], $now, $now);

            ++$i;
            ++$j;
            ++$k;
        }
    }
}
