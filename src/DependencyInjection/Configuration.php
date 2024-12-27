<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('webf_flysystem_failover');
        $rootNode = $treeBuilder->getRootNode();
        $rootNodeChildren = $rootNode->children();

        $this->addAdaptersSection($rootNode);
        $this->addMessageRepositoryDsnSection($rootNodeChildren);

        return $treeBuilder;
    }

    private function addAdaptersSection(ArrayNodeDefinition $rootNode): void
    {
        $rootNodeChildren = $rootNode
            ->fixXmlConfig('adapter')
            ->children()
        ;

        $adapterPrototype = $rootNodeChildren
            ->arrayNode('adapters')
            ->info(sprintf(
                'Failover adapter services that will be defined as "%s.{name}".',
                WebfFlysystemFailoverExtension::FAILOVER_ADAPTER_SERVICE_ID_PREFIX
            ))
            ->useAttributeAsKey('name')
            ->arrayPrototype()
            ->children()
        ;

        $adapterPrototype
            ->scalarNode('name')
            ->info('Name of the failover adapter. Defaults to the prototype array key.')
            ->cannotBeEmpty()
        ;

        $innerAdapterPrototype = $adapterPrototype
            ->arrayNode('adapters')
            ->info('Inner adapter services used to build the failover adapter (at least 2).')
            ->validate()->ifArray()->then(function (array $adapters) {
                if (count($adapters) < 2) {
                    throw new \InvalidArgumentException('There must be at least 2 adapters');
                }

                return $adapters;
            })->end()
            ->cannotBeEmpty()
            ->arrayPrototype()
            ->info('Can be a string to only specify "service_id".')
        ;

        $innerAdapterPrototype
            ->beforeNormalization()
            ->ifString()
            ->then(fn (string $value) => ['service_id' => $value])
            ->end()
        ;

        $innerAdapterPrototype
            ->children()

            ->scalarNode('service_id')
            ->info('Identifier of the inner adapter service.')
            ->cannotBeEmpty()
            ->end()

            ->integerNode('time_shift')
            ->info(
                'Time shift in seconds of the inner adapter compared to ' .
                'others (e.g. if the underlying storage use a different ' .
                'timezone or if has an incorrect server time).'
            )
            ->end()
        ;
    }

    private function addMessageRepositoryDsnSection(
        NodeBuilder $rootNodeChildren,
    ): void {
        $rootNodeChildren
            ->scalarNode('message_repository_dsn')
            ->info('DSN of message repository, used to store async messages produced by the failover adapters.')
            ->defaultValue('doctrine://default')
        ;
    }
}
