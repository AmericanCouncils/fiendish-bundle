<?php

namespace AC\FiendishBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $t = new TreeBuilder();

        $t->root('fiendish')
            ->children()
                ->arrayNode('groups')
                    ->useAttributeAsKey('key')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('heartbeat_timeout')->defaultValue(30)->end()
                            ->scalarNode('rabbit_conn')->defaultValue('default')->end()
                            ->scalarNode('process_user')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;


        return $t;
    }
}
