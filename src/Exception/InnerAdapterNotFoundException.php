<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\Exception;

final class InnerAdapterNotFoundException extends OutOfBoundsException
{
    public static function in(
        string $failoverAdapterName,
        int $innerAdapterIndex,
    ): InnerAdapterNotFoundException {
        return new InnerAdapterNotFoundException(sprintf(
            'Unable to find adapter of index "%s" in failover adapter "%s".',
            $innerAdapterIndex,
            $failoverAdapterName
        ));
    }
}
