<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\Message;

interface MessageInterface extends \Stringable
{
    public function getFailoverAdapter(): string;

    public function getPath(): string;

    public function getInnerSourceAdapter(): ?int;

    public function getInnerDestinationAdapter(): int;

    public function getRetryCount(): int;
}
