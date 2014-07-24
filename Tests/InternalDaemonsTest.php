<?php

namespace AC\FiendishBundle\Tests;

use AC\FiendishBundle\Tests\Fixtures\Daemon\TestDaemon;

class InternalDaemonsTest extends DaemonsTestCase
{
    protected function getDaemonClass()
    {
        return TestDaemon::class;
    }
}
