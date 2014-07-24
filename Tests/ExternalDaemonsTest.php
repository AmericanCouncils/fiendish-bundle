<?php

namespace AC\FiendishBundle\Tests;

use AC\FiendishBundle\Tests\Fixtures\Daemon\ExternalTestDaemon;

class ExternalDaemonsTest extends DaemonsTestCase
{
    protected function getDaemonClass()
    {
        return ExternalTestDaemon::class;
    }
}
