<?php

namespace DavidMikeSimon\FiendishBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Config\FileLocator;

class DavidMikeSimonFiendishExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        foreach($config['groups'] as $groupname => $group) {
            $def = new Definition(
                '%david_mike_simon_fiendish.group.class%',
                [
                    $groupname,
                    new Reference('old_sound_rabbit_mq.connection.default')
                ]
            );
            // TODO Get connection name from config
            $container->setDefinition(
                "david_mike_simon_fiendish.groups.$groupname",
                $def
            );
        }
    }
}

