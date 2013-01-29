<?php

namespace DavidMikeSimon\FiendishBundle\Tests;

use DavidMikeSimon\FiendishBundle\Tests\FiendishTestCase;

class TestHarnessTest extends FiendishTestCase
{
    public function testNewMasterDaemonStartup()
    {
        $this->requiresMaster();

        $supervisor = parent::getSupervisorClient();
        $procInfo = $supervisor->getProcessInfo(parent::GROUP_NAME . "_master");
        $this->assertEquals("RUNNING", $procInfo["statename"]);
        $this->assertLessThanOrEqual(3, $procInfo["now"] - $procInfo["start"]);
    }

    public function testMasterDaemonNotStartedByDefault()
    {
        $supervisor = parent::getSupervisorClient();
        $procInfo = $supervisor->getProcessInfo(parent::GROUP_NAME . "_master");
        $this->assertEquals("STOPPED", $procInfo["statename"]);
    }
}
