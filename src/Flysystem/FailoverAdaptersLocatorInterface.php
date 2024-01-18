<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\Flysystem;

use League\Flysystem\FilesystemAdapter;
use Traversable;
use Webf\FlysystemFailoverBundle\Exception\FailoverAdapterNotFoundException;

/**
 * @template T of FilesystemAdapter
 *
 * @template-extends Traversable<string, FailoverAdapter<T>>
 */
interface FailoverAdaptersLocatorInterface extends Traversable
{
    /**
     * @throws FailoverAdapterNotFoundException
     */
    public function get(string $name): FailoverAdapter;
}
