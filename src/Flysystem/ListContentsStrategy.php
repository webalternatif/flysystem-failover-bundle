<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\Flysystem;

enum ListContentsStrategy: string
{
    case Disabled = 'disabled';
    case First = 'first';
}
