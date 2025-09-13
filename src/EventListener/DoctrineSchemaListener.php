<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webf\FlysystemFailoverBundle\EventListener;

use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Webf\FlysystemFailoverBundle\MessageRepository\DoctrineMessageRepository;

final class DoctrineSchemaListener
{
    public function __construct(private DoctrineMessageRepository $repository)
    {
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
        $connection = $event->getEntityManager()->getConnection();
        $this->repository->configureSchema($event->getSchema(), $connection);
    }
}
