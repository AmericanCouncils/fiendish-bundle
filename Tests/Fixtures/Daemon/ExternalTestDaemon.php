<?php

namespace AC\FiendishBundle\Tests\Fixtures\Daemon;

use AC\FiendishBundle\Daemon\ExternalDaemon;

class ExternalTestDaemon extends ExternalDaemon
{
    public static function getExternalCommand()
    {
        return "@ACFiendishBundle/Tests/Fixtures/Resources/myapp.py";
    }
}
