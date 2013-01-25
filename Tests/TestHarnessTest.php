<?php

namespace DavidMikeSimon\FiendishBundle\Tests;

use DavidMikeSimon\FiendishBundle\Tests\FiendishTestCase;

class TestHarnessTest extends FiendishTestCase
{
    public function testNewMasterDaemonRunning()
    {
        $this->requiresMaster();

        $supervisor = $this->getSupervisorClient();
        $proc_info = $supervisor->getProcessInfo(parent::GROUP_NAME . "_master");
        $this->assertEquals(parent::GROUP_NAME . "_master", $proc_info["name"]);
        $this->assertEquals("RUNNING", $proc_info["statename"]);
        $this->assertLessThanOrEqual(3, $proc_info["now"] - $proc_info["start"]);
    }
}
