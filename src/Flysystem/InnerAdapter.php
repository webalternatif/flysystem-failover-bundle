<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\Flysystem;

use League\Flysystem\FilesystemAdapter;

/**
 * @psalm-type _Options=array{
 *     time_shift?: int
 * }
 */
class InnerAdapter
{
    /**
     * @param _Options $options
     */
    public function __construct(
        private FilesystemAdapter $adapter,
        private array $options = [],
    ) {
    }

    public function getAdapter(): FilesystemAdapter
    {
        return $this->adapter;
    }

    public function getTimeShift(): int
    {
        return $this->options['time_shift'] ?? 0;
    }
}
