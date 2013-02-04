<?php

namespace DavidMikeSimon\FiendishBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use DavidMikeSimon\FiendishBundle\DependencyInjection\FiendishExtension;

class DavidMikeSimonFiendishBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->registerExtension(new FiendishExtension());
    }
}
