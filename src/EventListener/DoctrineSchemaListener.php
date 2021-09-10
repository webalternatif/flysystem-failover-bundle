<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webf\FlysystemFailoverBundle\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Event\SchemaCreateTableEventArgs;
use Doctrine\DBAL\Events;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\ToolEvents;
use Webf\FlysystemFailoverBundle\MessageRepository\DoctrineMessageRepository;

class DoctrineSchemaListener implements EventSubscriber
{
    private const PROCESSING_TABLE_FLAG = self::class . ':processing';

    public function __construct(private DoctrineMessageRepository $repository)
    {
    }

    public function onSchemaCreateTable(SchemaCreateTableEventArgs $event): void
    {
        $table = $event->getTable();

        // avoid this same listener from creating a loop on this table
        if ($table->hasOption(self::PROCESSING_TABLE_FLAG)) {
            return;
        }
        $table->addOption(self::PROCESSING_TABLE_FLAG, true);

        $sql = $event->getPlatform()->getCreateTableSQL($table);
        $event->addSql($sql);
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
        $connection = $event->getEntityManager()->getConnection();
        $this->repository->configureSchema($event->getSchema(), $connection);
    }

    public function getSubscribedEvents(): array
    {
        $subscribedEvents = [];

        if (class_exists(ToolEvents::class)) {
            $subscribedEvents[] = ToolEvents::postGenerateSchema;
        }

        if (class_exists(Events::class)) {
            $subscribedEvents[] = Events::onSchemaCreateTable;
        }

        return $subscribedEvents;
    }
}
