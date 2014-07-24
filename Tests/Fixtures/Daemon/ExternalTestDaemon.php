<?php

namespace AC\FiendishBundle\Tests\Fixtures\Daemon;

use AC\FiendishBundle\Daemon\ExternalDaemon;

class ExternalTestDaemon extends ExternalDaemon
{
    public static function getExternalCommand($container)
    {
        return "@ACFiendishBundle/Tests/Fixtures/Resources/myapp.py";
    }
}
