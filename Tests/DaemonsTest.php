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

    private function assertAllProcessesRemoved($grp)
    {
        $supervisor = parent::getSupervisorClient();

        $removed = false;
        for ($i = 1; $i < 50; ++$i) {
            usleep(1000 * 100); // 100 milliseconds
            $removed = true;
            foreach ($supervisor->getAllProcessInfo() as $sp) {
                if ($sp["group"] == $grp->getName()) {
                    $removed = false;
                    break;
                }
            }
            if ($removed) {
                break;
            }
        }
        $this->assertTrue($removed);
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
        $grp->applyChanges();
        $this->assertProcessLivesAndOutputs($proc, "narfomatic");

        $grp->removeProcess($proc);
        $grp->applyChanges();
        $this->assertAllProcessesRemoved($grp);
    }

    // TODO Test starting multiple copies of the same daemon with the same config

    // TODO Test appropriate failure messages when master daemon isn't running

    // TODO Test heartbeats
}
