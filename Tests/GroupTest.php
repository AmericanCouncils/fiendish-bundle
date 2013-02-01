<?php

namespace DavidMikeSimon\FiendishBundle\Tests;

class GroupTest extends FiendishTestCase
{
    public function testProcessCreation()
    {
        $grp = $this->getGroup();
        $proc = $grp->newProcess(
            "foobar",
            "somecmd xyz abc",
            ["content" => "narf"]
        );

        $this->assertEquals(parent::GROUP_NAME, $proc->getGroupName());
        $this->assertContains("foobar", $proc->getProcName());
        $this->assertNotEquals("foobar", $proc->getProcName());
        $this->assertStringStartsWith(parent::GROUP_NAME . ":", $proc->getFullProcName());
        $this->assertStringStartsWith("somecmd xyz abc", $proc->getCommand());
        $this->assertContains("narf", $proc->getCommand());
    }

    public function testProcessRemoval()
    {
        $grp = $this->getGroup();

        $this->assertNull($grp->getProcess("whatever"));
        
        // This process won't start properly, but that's not what we're testing
        $proc = $grp->newProcess("x", "y");
        $grp->applyChanges();
        $procName = $proc->getProcName();
        $this->assertEquals($procName, $grp->getProcess($procName)->getProcName());

        $grp->removeProcess($proc);
        $grp->applyChanges();
        $this->assertNull($grp->getProcess($procName));
    }
}
