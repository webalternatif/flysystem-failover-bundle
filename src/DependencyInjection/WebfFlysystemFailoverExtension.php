<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\DependencyInjection;

use Nyholm\Dsn\DsnParser;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Webf\FlysystemFailoverBundle\Command\ListMessagesCommand;
use Webf\FlysystemFailoverBundle\Command\ProcessMessagesCommand;
use Webf\FlysystemFailoverBundle\Command\SyncCommand;
use Webf\FlysystemFailoverBundle\EventListener\DoctrineSchemaListener;
use Webf\FlysystemFailoverBundle\Flysystem\FailoverAdapter;
use Webf\FlysystemFailoverBundle\Flysystem\FailoverAdaptersLocator;
use Webf\FlysystemFailoverBundle\Flysystem\InnerAdapter;
use Webf\FlysystemFailoverBundle\MessageHandler\DeleteDirectoryHandler;
use Webf\FlysystemFailoverBundle\MessageHandler\DeleteFileHandler;
use Webf\FlysystemFailoverBundle\MessageHandler\MessageHandlerLocator;
use Webf\FlysystemFailoverBundle\MessageHandler\ReplicateFileHandler;
use Webf\FlysystemFailoverBundle\MessageRepository\DoctrineMessageRepository;
use Webf\FlysystemFailoverBundle\Serializer\Normalizer\FindResultsNormalizer;
use Webf\FlysystemFailoverBundle\Service\SyncService;

/**
 * @psalm-type _Config=array{
 *     adapters: array<
 *         string,
 *         array{
 *             adapters: list<array{
 *                 service_id: string,
 *                 time_shift?: int
 *             }>
 *         }
 *     >,
 *     message_repository_dsn: string
 * }
 */
class WebfFlysystemFailoverExtension extends Extension
{
    private const PREFIX = 'webf_flysystem_failover';

    public const FAILOVER_ADAPTER_SERVICE_ID_PREFIX = self::PREFIX . '.adapter';
    public const FAILOVER_ADAPTERS_LOCATOR_SERVICE_ID =
        self::PREFIX . '.adapters_locator';

    public const MESSAGE_REPOSITORY_SERVICE_ID =
        self::PREFIX . '.message_repository';

    public const MESSAGE_HANDLER_LOCATOR_SERVICE_ID =
        self::PREFIX . '.message_handler_locator';
    public const DELETE_DIRECTORY_MESSAGE_HANDLER_SERVICE_ID =
        self::PREFIX . '.message_handler.delete_directory';
    public const DELETE_FILE_MESSAGE_HANDLER_SERVICE_ID =
        self::PREFIX . '.message_handler.delete_file';
    public const REPLICATE_FILE_MESSAGE_HANDLER_SERVICE_ID =
        self::PREFIX . '.message_handler.replicate_file';
    public const MESSAGE_HANDLER_TAG_NAME = self::PREFIX . '.message_handler';

    public const FIND_RESULTS_NORMALIZER_SERVICE_ID =
        self::PREFIX . '.normalizer.find_results';

    public const SYNC_SERVICE_ID = self::PREFIX . '.service.sync';

    public const DOCTRINE_SCHEMA_LISTENER_SERVICE_ID =
        self::PREFIX . '.listener.doctrine_schema';

    public const LIST_MESSAGES_COMMAND_SERVICE_ID =
        self::PREFIX . '.command.list_messages';
    public const PROCESS_MESSAGE_COMMAND_SERVICE_ID =
        self::PREFIX . '.command.process_message';
    public const SCAN_COMMAND_SERVICE_ID = self::PREFIX . '.command.scan';

    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var _Config $config */
        $config = $this->processConfiguration(new Configuration(), $configs);

        $this->registerCommands($container);
        $this->registerFailoverAdapters($container, $config);
        $this->registerMessageHandlers($container);
        $this->registerMessageRepository($container, $config);
        if (interface_exists(NormalizerInterface::class)) {
            $this->registerNormalizers($container);
        }
        $this->registerServices($container);
    }

    private function registerCommands(
        ContainerBuilder $container,
    ): void {
        $container->setDefinition(
            self::LIST_MESSAGES_COMMAND_SERVICE_ID,
            (new Definition(ListMessagesCommand::class))
                ->setArguments([
                    new Reference(self::FAILOVER_ADAPTERS_LOCATOR_SERVICE_ID),
                    new Reference(self::MESSAGE_REPOSITORY_SERVICE_ID),
                    new Reference('serializer', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                ])
                ->addTag('console.command')
        );

        $container->setDefinition(
            self::PROCESS_MESSAGE_COMMAND_SERVICE_ID,
            (new Definition(ProcessMessagesCommand::class))
                ->setArguments([
                    new Reference(self::MESSAGE_HANDLER_LOCATOR_SERVICE_ID),
                    new Reference(self::MESSAGE_REPOSITORY_SERVICE_ID),
                ])
                ->addTag('console.command')
        );

        $container->setDefinition(
            self::SCAN_COMMAND_SERVICE_ID,
            (new Definition(SyncCommand::class))
                ->setArguments([
                    new Reference('event_dispatcher'),
                    new Reference(self::FAILOVER_ADAPTERS_LOCATOR_SERVICE_ID),
                    new Reference(self::SYNC_SERVICE_ID),
                ])
                ->addTag('console.command')
        );
    }

    /**
     * @param _Config $config
     */
    private function registerFailoverAdapters(
        ContainerBuilder $container,
        array $config,
    ): void {
        $references = [];
        foreach ($config['adapters'] as $name => $failoverAdapter) {
            $serviceId = self::FAILOVER_ADAPTER_SERVICE_ID_PREFIX . '.' . $name;

            $container->setDefinition(
                $serviceId,
                (new Definition(FailoverAdapter::class))
                    ->setArguments([
                        $name,
                        array_map(
                            fn (array $adapter) => (new Definition(InnerAdapter::class))
                                ->setArguments([
                                    new Reference($adapter['service_id']),
                                    ['time_shift' => $adapter['time_shift'] ?? null],
                                ]),
                            $failoverAdapter['adapters']
                        ),
                        new Reference(self::MESSAGE_REPOSITORY_SERVICE_ID),
                    ])
            );

            $references[] = new Reference($serviceId);
        }

        $container->setDefinition(
            self::FAILOVER_ADAPTERS_LOCATOR_SERVICE_ID,
            (new Definition(FailoverAdaptersLocator::class))
                ->setArguments([
                    new IteratorArgument($references),
                ])
        );
    }

    private function registerMessageHandlers(ContainerBuilder $container): void
    {
        $container->setDefinition(
            self::MESSAGE_HANDLER_LOCATOR_SERVICE_ID,
            (new Definition(MessageHandlerLocator::class))
                ->setArguments([
                    new TaggedIteratorArgument(self::MESSAGE_HANDLER_TAG_NAME),
                ])
        );

        $container->setDefinition(
            self::DELETE_DIRECTORY_MESSAGE_HANDLER_SERVICE_ID,
            (new Definition(DeleteDirectoryHandler::class))
                ->setArguments([
                    new Reference(self::FAILOVER_ADAPTERS_LOCATOR_SERVICE_ID),
                    new Reference(self::MESSAGE_REPOSITORY_SERVICE_ID),
                ])
                ->addTag(self::MESSAGE_HANDLER_TAG_NAME)
        );

        $container->setDefinition(
            self::DELETE_FILE_MESSAGE_HANDLER_SERVICE_ID,
            (new Definition(DeleteFileHandler::class))
                ->setArguments([
                    new Reference(self::FAILOVER_ADAPTERS_LOCATOR_SERVICE_ID),
                    new Reference(self::MESSAGE_REPOSITORY_SERVICE_ID),
                ])
                ->addTag(self::MESSAGE_HANDLER_TAG_NAME)
        );

        $container->setDefinition(
            self::REPLICATE_FILE_MESSAGE_HANDLER_SERVICE_ID,
            (new Definition(ReplicateFileHandler::class))
                ->setArguments([
                    new Reference(self::FAILOVER_ADAPTERS_LOCATOR_SERVICE_ID),
                    new Reference(self::MESSAGE_REPOSITORY_SERVICE_ID),
                ])
                ->addTag(self::MESSAGE_HANDLER_TAG_NAME)
        );
    }

    /**
     * @param _Config $config
     */
    private function registerMessageRepository(
        ContainerBuilder $container,
        array $config,
    ): void {
        $dsn = DsnParser::parse($config['message_repository_dsn']);

        switch ($dsn->getScheme()) {
            case 'doctrine':
                $container->setDefinition(
                    self::MESSAGE_REPOSITORY_SERVICE_ID,
                    (new Definition(DoctrineMessageRepository::class))
                        ->setArguments([
                            new Reference(sprintf(
                                'doctrine.dbal.%s_connection',
                                $dsn->getHost() ?? 'default'
                            )),
                        ])
                );

                $container->setDefinition(
                    self::DOCTRINE_SCHEMA_LISTENER_SERVICE_ID,
                    (new Definition(DoctrineSchemaListener::class))
                        ->setArguments([
                            new Reference(self::MESSAGE_REPOSITORY_SERVICE_ID),
                        ])
                        ->addTag('doctrine.event_subscriber')
                );
                break;
            case 'service':
                // TODO check that service implements MessageRepositoryInterface
                $serviceId = $dsn->withScheme(null)->__toString();

                $container->setAlias(
                    self::MESSAGE_REPOSITORY_SERVICE_ID,
                    $serviceId
                );
                break;
            default:
                throw new InvalidArgumentException('"doctrine://" and "service://" are the only supported DSN at configuration path "webf_flysystem_failover.message_repository_dsn".');
        }
    }

    private function registerNormalizers(ContainerBuilder $container): void
    {
        $container->setDefinition(
            self::FIND_RESULTS_NORMALIZER_SERVICE_ID,
            (new Definition(FindResultsNormalizer::class))
                ->addTag('serializer.normalizer')
        );
    }

    private function registerServices(ContainerBuilder $container): void
    {
        $container->setDefinition(
            self::SYNC_SERVICE_ID,
            (new Definition(SyncService::class))
                ->setArguments([
                    new Reference('event_dispatcher'),
                    new Reference(self::FAILOVER_ADAPTERS_LOCATOR_SERVICE_ID),
                    new Reference(self::MESSAGE_REPOSITORY_SERVICE_ID),
                ])
        );
    }
}
