<?php

namespace AC\FiendishBundle\Tests;

use AC\FiendishBundle\Tests\Fixtures\Daemon\ExternalTestDaemon;

class ExternalDaemonsTest extends DaemonsTestCase
{
    protected function getNewProcessCommand()
    {
        $kernel = $this->getContainer()->get('kernel');
        return ExternalTestDaemon::toCommand($kernel);
    }
}
