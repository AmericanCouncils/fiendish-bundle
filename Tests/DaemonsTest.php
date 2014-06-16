<?php

namespace AC\FiendishBundle\Tests;

use AC\FiendishBundle\Tests\Fixtures\Daemon\SimpleDaemon;

class DaemonsTest extends FiendishTestCase
{
    private function assertProcessLivesAndOutputs($proc, $expectedOutput)
    {
        $supervisor = parent::getSupervisorClient();
        $ok = false;
        for ($i = 1; $i < 50; ++$i) {
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

    private function assertProcessRestarts($proc)
    {
        $supervisor = parent::getSupervisorClient();
        $pids = [];
        $ok = false;
        for ($i = 1; $i < 50; ++$i) {
            usleep(1000 * 100); // 100 milliseconds
            $procInfo = $supervisor->getProcessInfo($proc->getFullProcName());
            if (!is_null($procInfo)) {
                $pid = (int)($procInfo['pid']);
                if ($pid > 0) {
                    $pids[$pid] = true;
                }
            }
            if (count($pids) >= 2) {
                $ok = true;
                break;
            }
        }
        $this->assertTrue($ok);
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
            ["content" => "die"] // Causes SimpleDaemon to die after one print
        );
        $grp->applyChanges();
        $this->assertProcessRestarts($proc);
    }

    //public function testRecoveryOnSupervisorRestart()
    //{
    //}

    public function testRecoveryOnMasterRestart()
    {
    }

    // TODO Test appropriate failure messages when master daemon isn't running

    // TODO Test heartbeats
}
