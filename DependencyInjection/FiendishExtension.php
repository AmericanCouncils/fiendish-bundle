<?php

namespace DavidMikeSimon\FiendishBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Config\FileLocator;

class FiendishExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        foreach($config['groups'] as $groupname => $group) {
            $def = new Definition(
                '%fiendish.group.class%',
                [
                    $groupname,
                    // TODO Get connection name from config
                    new Reference('old_sound_rabbit_mq.connection.default'),
                    new Reference('doctrine')
                ]
            );
            $container->setDefinition(
                "fiendish.groups.$groupname",
                $def
            );
        }
    }

    public function getAlias()
    {
        return "fiendish";
    }
}

