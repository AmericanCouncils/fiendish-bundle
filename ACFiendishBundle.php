<?php

namespace AC\FiendishBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use AC\FiendishBundle\DependencyInjection\FiendishExtension;

class ACFiendishBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->registerExtension(new FiendishExtension());
    }
}
