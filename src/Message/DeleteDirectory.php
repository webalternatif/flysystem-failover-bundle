<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\Message;

class DeleteDirectory implements MessageInterface
{
    public function __construct(
        private string $failoverAdapter,
        private string $path,
        private int $innerAdapter,
        private int $retryCount = 0
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

    public function getInnerSourceAdapter(): ?int
    {
        return null;
    }

    public function getInnerDestinationAdapter(): int
    {
        return $this->innerAdapter;
    }

    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    public function __toString()
    {
        return sprintf(
            'For failover adapter "%s", delete directory "%s" from adapter %s.',
            $this->failoverAdapter,
            $this->path,
            $this->innerAdapter
        );
    }
}
