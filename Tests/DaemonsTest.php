<?php

namespace DavidMikeSimon\FiendishBundle\Tests;

use DavidMikeSimon\FiendishBundle\Entity\Process;
use DavidMikeSimon\FiendishBundle\Daemon\MasterDaemon;

class DaemonsTest extends FiendishTestCase
{
    public function testDaemonControl()
    {
        $this->requiresMaster();

        $em = $this->getContainer()->get('doctrine')->getEntityManager();
        // SimpleDaemon appends "omatic" to input content, then prints it
        $proc = new Process(
            parent::GROUP_NAME,
            "simple",
            "DavidMikeSimon\FiendishBundle\Tests\Fixtures\Daemon\SimpleDaemon",
            ["content" => "narf"]
        );
        $em->persist($proc);
        $em->flush();
        MasterDaemon::sendSyncRequest(parent::GROUP_NAME);

        $supervisor = parent::getSupervisorClient();

        $ok = false;
        for ($i = 1; $i < 50; ++$i) {
            usleep(1000 * 100); // 100 milliseconds
            $em->refresh($proc);
            if (!$proc->isSetup()) {
                continue;
            }

            $procInfo = $supervisor->getProcessInfo($proc->getFullProcName());
            if (!is_null($procInfo)) {
                $output = $supervisor->tailProcessStdoutLog(
                    $proc->getFullProcName(),
                    0,
                    5000
                )[0];

                if (strpos($output, "narfomatic") !== FALSE) {
                    $ok = true;
                    break;
                }
            }
        }
        $this->assertTrue($ok);

        $procInfo = $supervisor->getProcessInfo($proc->getFullProcName());
        $this->assertContains($procInfo["statename"], ["RUNNING", "STARTING"]);
        $em->remove($proc);
        $em->flush();
        MasterDaemon::sendSyncRequest(parent::GROUP_NAME);

        $supervisor->logMessage("POINT A");
        $removed = false;
        for ($i = 1; $i < 50; ++$i) {
            usleep(1000 * 100); // 100 milliseconds
            $removed = true;
            foreach ($supervisor->getAllProcessInfo() as $proc) {
                if ($proc["group"] == parent::GROUP_NAME) {
                    $removed = false;
                    break;
                }
            }
            if ($removed) {
                break;
            }
        }
        $supervisor->logMessage("POINT B");
        $this->assertTrue($removed);
    }
}
