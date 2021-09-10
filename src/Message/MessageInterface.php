<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\Message;

interface MessageInterface extends \Stringable
{
    public function getRetryCount(): int;
}
