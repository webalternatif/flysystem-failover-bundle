<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\Flysystem;

use ArrayIterator;
use IteratorAggregate;
use Webf\FlysystemFailoverBundle\Exception\FailoverAdapterNotFoundException;

class FailoverAdaptersLocator implements FailoverAdaptersLocatorInterface, IteratorAggregate
{
    private bool $iterableConsumed = false;

    /**
     * @var array<string, FailoverAdapter>
     */
    private array $failoverAdapters = [];

    /**
     * @param iterable<FailoverAdapter> $adaptersIterable
     */
    public function __construct(private iterable $adaptersIterable)
    {
    }

    public function get(string $name): FailoverAdapter
    {
        if (key_exists($name, $this->failoverAdapters)) {
            return $this->failoverAdapters[$name];
        }

        if (!$this->iterableConsumed) {
            foreach ($this->adaptersIterable as $adapter) {
                $this->failoverAdapters[$adapter->getName()] = $adapter;

                if ($adapter->getName() === $name) {
                    return $adapter;
                }
            }

            $this->iterableConsumed = true;
        }

        throw FailoverAdapterNotFoundException::withName($name);
    }

    /**
     * @return ArrayIterator<string, FailoverAdapter>
     */
    public function getIterator(): ArrayIterator
    {
        if (!$this->iterableConsumed) {
            foreach ($this->adaptersIterable as $adapter) {
                $this->failoverAdapters[$adapter->getName()] = $adapter;
            }

            $this->iterableConsumed = true;
        }

        return new ArrayIterator($this->failoverAdapters);
    }
}
