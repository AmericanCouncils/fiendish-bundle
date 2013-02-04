<?php

namespace DavidMikeSimon\FiendishBundle\DependencyInjection;

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
                            ->scalarNode('process_user')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;


        return $t;
    }
}
