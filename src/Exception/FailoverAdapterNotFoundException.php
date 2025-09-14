<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\Exception;

final class FailoverAdapterNotFoundException extends OutOfBoundsException
{
    public static function withName(string $name): FailoverAdapterNotFoundException
    {
        return new FailoverAdapterNotFoundException(sprintf(
            'Unable to find failover adapter "%s".',
            $name
        ));
    }
}
