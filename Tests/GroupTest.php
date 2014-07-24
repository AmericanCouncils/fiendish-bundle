<?php

namespace AC\FiendishBundle\Tests;

use AC\FiendishBundle\Tests\Fixtures\Daemon\TestDaemon;

class GroupTest extends FiendishTestCase
{
    public function testProcessCreation()
    {
        $grp = $this->getGroup();
        $proc = $grp->newProcess("foobar", TestDaemon::class, ["content" => "narf"]);

        $this->assertEquals(parent::GROUP_NAME, $proc->getGroupName());
        $this->assertContains("foobar", $proc->getProcName());
        $this->assertNotEquals("foobar", $proc->getProcName());
        $this->assertStringStartsWith(parent::GROUP_NAME . ":", $proc->getFullProcName());
        $this->assertContains("console", $proc->getCommand());
        $this->assertContains("narf", $proc->getCommand());
    }

    public function testProcessRemoval()
    {
        $grp = $this->getGroup();

        $this->assertNull($grp->getProcess("whatever"));
        
        $proc = $grp->newProcess("x", TestDaemon::class);
        $grp->applyChanges();
        $procName = $proc->getProcName();
        $this->assertEquals($procName, $grp->getProcess($procName)->getProcName());

        $grp->removeProcess($proc);
        $grp->applyChanges();
        $this->assertNull($grp->getProcess($procName));
    }
}
