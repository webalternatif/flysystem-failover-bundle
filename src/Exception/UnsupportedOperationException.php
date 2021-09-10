<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\Exception;

use League\Flysystem\FilesystemException;

class UnsupportedOperationException extends LogicException implements FilesystemException
{
}
