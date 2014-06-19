<?php

namespace AC\FiendishBundle\Tests;

use AC\FiendishBundle\Tests\Fixtures\Daemon\TestDaemon;

class InternalDaemonsTest extends DaemonsTestCase
{
    protected function getNewProcessCommand()
    {
        $kernel = $this->getContainer()->get('kernel');
        return TestDaemon::toCommand($kernel);
    }
}
