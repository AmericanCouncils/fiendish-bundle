<?php

namespace DavidMikeSimon\FiendishBundle\Tests;

use DavidMikeSimon\FiendishBundle\Tests\FiendishTestCase;

class TestHarnessTest extends FiendishTestCase
{
    public function testNewMasterDaemonStartup()
    {
        $this->requiresMaster();

        $supervisor = parent::getSupervisorClient();
        $proc_info = $supervisor->getProcessInfo(parent::GROUP_NAME . "_master");
        $this->assertEquals(parent::GROUP_NAME . "_master", $proc_info["name"]);
        $this->assertEquals("RUNNING", $proc_info["statename"]);
        $this->assertLessThanOrEqual(3, $proc_info["now"] - $proc_info["start"]);
    }
}
