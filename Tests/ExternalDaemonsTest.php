<?php

namespace AC\FiendishBundle\Tests;

use AC\FiendishBundle\Tests\Fixtures\Daemon\ExternalTestDaemon;

class ExternalDaemonsTest extends DaemonsTestCase
{
    protected function getDaemonClass()
    {
        return ExternalTestDaemon::class;
    }

    public function testExternalCommandPathLookup()
    {
        $cmd = ExternalTestDaemon::toCommand($this->getContainer(), ['arg' => null]);
        $this->assertRegExp("#/.+/Tests/Fixtures/Resources/myapp.py#", $cmd);
    }
}
