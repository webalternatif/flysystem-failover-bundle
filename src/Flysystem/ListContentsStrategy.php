<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\Flysystem;

enum ListContentsStrategy: string
{
    case Disabled = 'disabled';
    case First = 'first';

    public function description(): string
    {
        return match ($this) {
            self::Disabled => 'throw an exception',
            self::First => 'contents of the first inner adapter that works',
        };
    }
}
