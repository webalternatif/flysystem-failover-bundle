<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\MessageHandler;

use Webf\FlysystemFailoverBundle\Exception\InvalidArgumentException;
use Webf\FlysystemFailoverBundle\Message\MessageInterface;

class MessageHandlerLocator
{
    /**
     * @var array<class-string, callable(MessageInterface)|MessageHandlerInterface>
     */
    private array $handlers = [];

    /**
     * @param iterable<MessageHandlerInterface> $handlers
     */
    public function __construct(iterable $handlers)
    {
        foreach ($handlers as $handler) {
            try {
                $class = new \ReflectionClass($handler);
                $method = $class->getMethod('__invoke');
            } catch (\ReflectionException $e) {
                throw new InvalidArgumentException($e->getMessage(), previous: $e);
            }

            $parameters = $method->getParameters();
            /** @var \ReflectionNamedType $type */
            $type = $parameters[0]->getType();
            /** @var class-string $name */
            $name = $type->getName();

            $this->handlers[$name] = $handler;
        }
    }

    /**
     * @return callable(MessageInterface)
     */
    public function getHandlerForMessage(
        MessageInterface $message
    ): callable {
        $handler = $this->handlers[get_class($message)];
        assert(is_callable($handler));

        return $handler;
    }
}
