<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\Event\SyncService;

class SearchingFilesToReplicateStarted
{
    public function __construct(
        private string $failoverAdapter,
    ) {
    }

    public function getFailoverAdapter(): string
    {
        return $this->failoverAdapter;
    }
}
