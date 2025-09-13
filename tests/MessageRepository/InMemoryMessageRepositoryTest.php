<?php

declare(strict_types=1);

namespace Tests\Webf\FlysystemFailoverBundle\MessageRepository;

use PHPUnit\Framework\TestCase;
use Webf\FlysystemFailoverBundle\Message\MessageInterface;
use Webf\FlysystemFailoverBundle\MessageRepository\FindByCriteria;
use Webf\FlysystemFailoverBundle\MessageRepository\FindResults;
use Webf\FlysystemFailoverBundle\MessageRepository\InMemoryMessageRepository;
use Webf\FlysystemFailoverBundle\MessageRepository\MessageWithMetadata;

/**
 * @internal
 *
 * @covers \Webf\FlysystemFailoverBundle\MessageRepository\InMemoryMessageRepository
 */
final class InMemoryMessageRepositoryTest extends TestCase
{
    public function test_messages_are_pushed_and_popped_in_the_right_order(): void
    {
        $repository = new InMemoryMessageRepository();

        $repository->push($this->getFakeMessage('1'));
        $repository->push($this->getFakeMessage('2'));
        $repository->push($this->getFakeMessage('3'));
        $repository->push($this->getFakeMessage('4'));
        $repository->push($this->getFakeMessage('5'));

        $this->assertEquals(['1', '2', '3', '4', '5'], $this->getAll($repository));
        $this->assertEquals('1', (string) $repository->pop());
        $this->assertEquals(['2', '3', '4', '5'], $this->getAll($repository));
        $this->assertEquals('2', (string) $repository->pop());
        $this->assertEquals(['3', '4', '5'], $this->getAll($repository));
        $this->assertEquals(['3', '4', '5'], array_map(fn (MessageInterface $message) => (string) $message, $repository->popAll()));
        $this->assertEquals([], $this->getAll($repository));
        $this->assertEquals(null, $repository->pop());
        $this->assertEquals([], $repository->popAll());
    }

    public function test_find_by_with_page_and_limit_criteria(): void
    {
        $repository = new InMemoryMessageRepository();

        $repository->push($this->getFakeMessage('1'));
        $repository->push($this->getFakeMessage('2'));
        $repository->push($this->getFakeMessage('3'));
        $repository->push($this->getFakeMessage('4'));
        $repository->push($this->getFakeMessage('5'));

        $this->assertEquals(
            ['1', '2', '3'],
            $this->getMessages($repository->findBy((new FindByCriteria())->setLimit(3)))
        );

        $this->assertEquals(
            ['4', '5'],
            $this->getMessages($repository->findBy((new FindByCriteria())->setLimit(3)->setPage(2)))
        );

        $this->assertEquals(
            [],
            $this->getMessages($repository->findBy((new FindByCriteria())->setLimit(3)->setPage(3)))
        );

        $this->assertEquals(
            ['3', '4'],
            $this->getMessages($repository->findBy((new FindByCriteria())->setLimit(2)->setPage(2)))
        );

        $this->assertEquals(
            ['5'],
            $this->getMessages($repository->findBy((new FindByCriteria())->setLimit(2)->setPage(3)))
        );
    }

    public function test_find_by_with_failover_adapter_criteria(): void
    {
        $repository = new InMemoryMessageRepository();

        $repository->push($this->getFakeMessage('1', 'a'));
        $repository->push($this->getFakeMessage('2', 'b'));
        $repository->push($this->getFakeMessage('3', 'a'));
        $repository->push($this->getFakeMessage('4', 'b'));
        $repository->push($this->getFakeMessage('5', 'a'));

        $this->assertEquals(
            ['1', '3', '5'],
            $this->getMessages($repository->findBy((new FindByCriteria())->setFailoverAdapter('a')))
        );

        $this->assertEquals(
            ['2', '4'],
            $this->getMessages($repository->findBy((new FindByCriteria())->setFailoverAdapter('b')))
        );

        $this->assertEquals(
            [],
            $this->getMessages($repository->findBy((new FindByCriteria())->setFailoverAdapter('c')))
        );

        $this->assertEquals(
            ['1', '3'],
            $this->getMessages($repository->findBy((new FindByCriteria())->setFailoverAdapter('a')->setLimit(2)))
        );

        $this->assertEquals(
            ['5'],
            $this->getMessages($repository->findBy((new FindByCriteria())->setFailoverAdapter('a')->setLimit(2)->setPage(2)))
        );
    }

    private function getFakeMessage(string $number, string $failoverAdapter = 'default'): MessageInterface
    {
        return new class($number, $failoverAdapter) implements MessageInterface {
            public function __construct(
                private readonly string $number,
                private readonly string $failoverAdapter,
            ) {
            }

            #[\Override]
            public function getFailoverAdapter(): string
            {
                return $this->failoverAdapter;
            }

            #[\Override]
            public function getPath(): string
            {
                throw new \LogicException('Not implemented');
            }

            #[\Override]
            public function getInnerSourceAdapter(): ?int
            {
                throw new \LogicException('Not implemented');
            }

            #[\Override]
            public function getInnerDestinationAdapter(): int
            {
                throw new \LogicException('Not implemented');
            }

            #[\Override]
            public function getRetryCount(): int
            {
                throw new \LogicException('Not implemented');
            }

            #[\Override]
            public function __toString()
            {
                return $this->number;
            }
        };
    }

    /**
     * @return array<string>
     */
    private function getMessages(FindResults $results): array
    {
        return array_map(
            fn (MessageWithMetadata $message) => (string) $message->getMessage(),
            iterator_to_array($results->getItems())
        );
    }

    /**
     * @return array<string>
     */
    private function getAll(InMemoryMessageRepository $repository): array
    {
        return $this->getMessages($repository->findBy(new FindByCriteria()));
    }
}
