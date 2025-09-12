<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\Event\SyncService;

final class ListingContentSucceeded extends AbstractListingContentEvent
{
    public function __construct(
        string $failoverAdapter,
        int $innerAdapter,
        private int $nbItems,
    ) {
        parent::__construct($failoverAdapter, $innerAdapter);
    }

    public function getNbItems(): int
    {
        return $this->nbItems;
    }
}
