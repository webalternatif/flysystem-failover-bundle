<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\Message;

final class DeleteFile implements MessageInterface
{
    public function __construct(
        private string $failoverAdapter,
        private string $path,
        private int $innerAdapter,
        private int $retryCount = 0,
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
        return $this->path;
    }

    #[\Override]
    public function getInnerSourceAdapter(): ?int
    {
        return null;
    }

    #[\Override]
    public function getInnerDestinationAdapter(): int
    {
        return $this->innerAdapter;
    }

    #[\Override]
    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    #[\Override]
    public function __toString()
    {
        return sprintf(
            'For failover adapter "%s", delete file "%s" from adapter %s.',
            $this->failoverAdapter,
            $this->path,
            $this->innerAdapter
        );
    }
}
