<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\Event\SyncService;

abstract class AbstractListingContentEvent
{
    public function __construct(
        private string $failoverAdapter,
        private int $innerAdapter
    ) {
    }

    public function getFailoverAdapter(): string
    {
        return $this->failoverAdapter;
    }

    public function getInnerAdapter(): int
    {
        return $this->innerAdapter;
    }
}
