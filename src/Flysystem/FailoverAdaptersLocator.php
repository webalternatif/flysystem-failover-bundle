<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\Flysystem;

use ArrayIterator;
use IteratorAggregate;
use Webf\FlysystemFailoverBundle\Exception\FailoverAdapterNotFoundException;

class FailoverAdaptersLocator implements FailoverAdaptersLocatorInterface, IteratorAggregate
{
    /**
     * @var array<string, FailoverAdapter>
     */
    private array $failoverAdapters;

    /**
     * @param iterable<FailoverAdapter> $adapters
     */
    public function __construct(iterable $adapters)
    {
        $this->failoverAdapters = [];

        foreach ($adapters as $adapter) {
            $this->failoverAdapters[$adapter->getName()] = $adapter;
        }
    }

    public function get(string $name): FailoverAdapter
    {
        if (key_exists($name, $this->failoverAdapters)) {
            return $this->failoverAdapters[$name];
        }

        throw FailoverAdapterNotFoundException::withName($name);
    }

    /**
     * @return ArrayIterator<string, FailoverAdapter>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->failoverAdapters);
    }
}
