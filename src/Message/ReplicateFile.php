<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\Message;

class ReplicateFile implements MessageInterface
{
    public function __construct(
        private string $failoverAdapter,
        private string $path,
        private int $innerSourceAdapter,
        private int $innerDestinationAdapter,
        private int $retryCount = 0,
    ) {
    }

    public function getFailoverAdapter(): string
    {
        return $this->failoverAdapter;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getInnerSourceAdapter(): int
    {
        return $this->innerSourceAdapter;
    }

    public function getInnerDestinationAdapter(): int
    {
        return $this->innerDestinationAdapter;
    }

    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    public function __toString()
    {
        return sprintf(
            'For failover adapter "%s", replicate file "%s" from adapter %s to %s.',
            $this->failoverAdapter,
            $this->path,
            $this->innerSourceAdapter,
            $this->innerDestinationAdapter
        );
    }
}
