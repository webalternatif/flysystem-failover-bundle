<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\MessageRepository;

class FindResults
{
    /**
     * @param iterable<MessageWithMetadata> $items
     */
    public function __construct(
        private int $limit,
        private int $total,
        private int $page,
        private iterable $items,
    ) {
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    /**
     * @return iterable<MessageWithMetadata>
     */
    public function getItems(): iterable
    {
        return $this->items;
    }

    public function getFirstItemNb(): int
    {
        return ($this->getPage() - 1) * $this->getLimit() + 1;
    }

    public function getTotalPages(): int
    {
        return (int) ceil($this->getTotal() / $this->getLimit());
    }
}
