<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\MessageRepository;

class FindByCriteria
{
    public const DEFAULT_LIMIT = 30;
    public const DEFAULT_PAGE = 1;

    private ?string $failoverAdapter = null;
    private int $limit = self::DEFAULT_LIMIT;
    private int $page = self::DEFAULT_PAGE;

    public function getFailoverAdapter(): ?string
    {
        return $this->failoverAdapter;
    }

    public function setFailoverAdapter(?string $failoverAdapter): static
    {
        $this->failoverAdapter = $failoverAdapter;

        return $this;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function setLimit(?int $limit): static
    {
        $this->limit = null === $limit ? self::DEFAULT_LIMIT : $limit;

        return $this;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function setPage(?int $page): static
    {
        $this->page = null === $page ? self::DEFAULT_PAGE : $page;

        return $this;
    }
}
