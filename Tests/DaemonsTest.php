<?php

namespace AC\FiendishBundle\Tests;

use AC\FiendishBundle\Tests\Fixtures\Daemon\SimpleDaemon;

class DaemonsTest extends FiendishTestCase
{
    private function assertProcessLivesAndOutputs($proc, $expectedOutput)
    {
        $supervisor = parent::getSupervisorClient();
        $ok = false;
        for ($i = 1; $i < 30; ++$i) {
            usleep(1000 * 100); // 100 milliseconds

            $procInfo = $supervisor->getProcessInfo($proc->getFullProcName());
            if (!is_null($procInfo)) {
                $output = $supervisor->tailProcessStdoutLog(
                    $proc->getFullProcName(),
                    0,
                    5000
                )[0];

                if (
                    strpos($output, $expectedOutput) !== FALSE &&
                    in_array($procInfo["statename"], [
                        "RUNNING",
                        "STARTING"
                    ])
                ) {
                    $ok = true;
                    break;
                }
            }
        }
        $this->assertTrue($ok);
    }

    private function getProcessPids($proc)
    {
        $pids = [];
        for ($i = 1; $i < 30; ++$i) {
            usleep(1000 * 100); // 100 milliseconds
            // Get within the loop in case supervisor restarts
            $supervisor = parent::getSupervisorClient();
            $procInfo = $supervisor->getProcessInfo($proc->getFullProcName());
            if (!is_null($procInfo)) {
                $pid = (int)($procInfo['pid']);
                if ($pid > 0) {
                    $pids[$pid] = true;
                }
            }
        }
        return array_keys($pids);
    }

    private function assertGroupSize($grp, $size)
    {
        $supervisor = parent::getSupervisorClient();

        $ok = false;
        for ($i = 1; $i < 50; ++$i) {
            usleep(1000 * 100); // 100 milliseconds
            $count = 0;
            foreach ($supervisor->getAllProcessInfo() as $sp) {
                if ($sp["group"] == $grp->getName()) {
                    ++$count;
                }
            }
            if ($count === $size) {
                $ok = true;
                break;
            }
        }
        $this->assertTrue($ok);
    }

    public function testDaemonControl()
    {
        $this->requiresMaster();

        $grp = $this->getGroup();
        $rootDir = $this->getContainer()->get('kernel')->getRootDir();
        $proc = $grp->newProcess(
            "simple",
            SimpleDaemon::toCommand($rootDir),
            ["content" => "narf"]
        );
        $this->assertGroupSize($grp, 0);
        $grp->applyChanges();
        $this->assertGroupSize($grp, 1);
        $this->assertProcessLivesAndOutputs($proc, "narfomatic");
        $this->assertEquals(count($this->getProcessPids($proc)), 1);

        $proc2 = $grp->newProcess(
            "simple2",
            SimpleDaemon::toCommand($rootDir),
            ["content" => "bork"]
        );
        $this->assertGroupSize($grp, 1);
        $grp->applyChanges();
        $this->assertGroupSize($grp, 2);
        $this->assertProcessLivesAndOutputs($proc2, "borkomatic");

        $grp->removeProcess($proc);
        $this->assertGroupSize($grp, 2);
        $grp->applyChanges();
        $this->assertGroupSize($grp, 1);

        $grp->removeProcess($proc2);
        $this->assertGroupSize($grp, 1);
        $grp->applyChanges();
        $this->assertGroupSize($grp, 0);
    }

    public function testProcessAutoRestart()
    {
        $this->requiresMaster();

        $grp = $this->getGroup();
        $rootDir = $this->getContainer()->get('kernel')->getRootDir();
        $proc = $grp->newProcess(
            "simple",
            SimpleDaemon::toCommand($rootDir),
            ["content" => "die"]
        );
        $grp->applyChanges();

        $pidsBefore = $this->getProcessPids($proc);
        $this->assertEquals(count($pidsBefore), 1);
        posix_kill($pidsBefore[0], 9);
        usleep(1000 * 100);
        $pidsAfter = $this->getProcessPids($proc);
        $this->assertEquals(count($pidsAfter), 1);
        $this->assertNotEquals($pidsBefore[0], $pidsAfter[0]); // Proc restarted
    }

    public function testMasterRestart()
    {
        $this->requiresMaster();

        $grp = $this->getGroup();
        $rootDir = $this->getContainer()->get('kernel')->getRootDir();
        $proc = $grp->newProcess(
            "simple",
            SimpleDaemon::toCommand($rootDir),
            ["content" => "narf"]
        );
        $grp->applyChanges();
        $pidsBefore = $this->getProcessPids($proc);
        $this->assertEquals(count($pidsBefore), 1);

        // Kill master
        $supervisor = parent::getSupervisorClient();
        $masterInfo = $supervisor->getProcessInfo("testfiendish_master");
        $this->assertGreaterThan(0, $masterInfo['pid']);
        posix_kill($masterInfo['pid'], 9);
        usleep(1000 * 100);
        $masterInfo2 = $supervisor->getProcessInfo("testfiendish_master");
        $this->assertEquals(0, $masterInfo2['pid']); // Master is dead

        // Start master up again.
        // Normally master would be set up to autorestart, but for our test
        // environment it must be manually restarted.
        $supervisor->startProcess("testfiendish_master");
        sleep(2);
        $masterInfo = $supervisor->getProcessInfo("testfiendish_master");
        $this->assertGreaterThan(0, $masterInfo['pid']);

        // Assert that SimpleDaemon did not have to restart
        $pidsAfter = $this->getProcessPids($proc);
        $this->assertEquals($pidsBefore, $pidsAfter);
    }

    public function testSupervisorRestart()
    {
        $this->requiresMaster();

        $grp = $this->getGroup();
        $rootDir = $this->getContainer()->get('kernel')->getRootDir();
        $proc = $grp->newProcess(
            "simple",
            SimpleDaemon::toCommand($rootDir),
            ["content" => "narf"]
        );
        $grp->applyChanges();
        $pidsBefore = $this->getProcessPids($proc);
        $this->assertEquals(1, count($pidsBefore));
        system("/etc/init.d/supervisor restart");
        sleep(5);

        // Restart master daemon
        $supervisor = parent::getSupervisorClient();
        $supervisor->startProcess("testfiendish_master");
        sleep(5);
        $masterInfo = $supervisor->getProcessInfo("testfiendish_master");
        $this->assertGreaterThan(0, $masterInfo['pid']);

        // Assert that daemon was restarted as well
        $pidsAfter = $this->getProcessPids($proc);
        $this->assertEquals(1, count($pidsAfter));
        $this->assertNotEquals($pidsBefore[0], $pidsAfter[0]);
    }

    // TODO Test heartbeats
}
