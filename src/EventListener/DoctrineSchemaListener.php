<?php

declare(strict_types=1);

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
