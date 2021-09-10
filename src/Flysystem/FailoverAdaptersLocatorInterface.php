<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\Flysystem;

use Traversable;
use Webf\FlysystemFailoverBundle\Exception\FailoverAdapterNotFoundException;

interface FailoverAdaptersLocatorInterface extends Traversable
{
    /**
     * @throws FailoverAdapterNotFoundException
     */
    public function get(string $name): FailoverAdapter;
}
