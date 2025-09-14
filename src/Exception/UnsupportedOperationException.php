<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\Exception;

use League\Flysystem\FilesystemException;

final class UnsupportedOperationException extends LogicException implements FilesystemException
{
}
