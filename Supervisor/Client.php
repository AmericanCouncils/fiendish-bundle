<?php

namespace AC\FiendishBundle\Supervisor;

use Functional as F;
use SupervisorClient\SupervisorClient;

class Client extends SupervisorClient
{
    const REMOVAL_CYCLE_DELAY_MS = 100;

    private function addFullName(&$proc)
    {
        if (!empty($proc["group"])) {
            $proc["fullName"] = $proc["group"] . ":" . $proc["name"];
        } else {
            $proc["fullName"] = $proc["name"];
        }
    }

    private function isStoppedState($stateName)
    {
        return in_array($stateName, ["STOPPED", "FATAL", "EXITED", "UNKNOWN"]);
    }

    private function logMsg($msg)
    {
        // TODO Should do this with monolog instead
        print(date(\DateTime::W3C) . " " . $msg . "\n");
    }

    public function getProcessInfo($fullProcName)
    {
        $procInfo = parent::getProcessInfo($fullProcName);
        $this->addFullName($procInfo);
        return $procInfo;
    }

    public function getAllProcessInfo()
    {
        $procs = parent::getAllProcessInfo();
        foreach ($procs as &$p) { $this->addFullname($p); }
        return $procs;
    }

    public function getGroupProcessInfo($groupName)
    {
        $allProcs = $this->getAllProcessInfo();
        return F\filter($allProcs, function ($p) use ($groupName) {
            return $p["group"] == $groupName;
        });
    }

    public function stopProcessesParallel($fullProcNames, $timeoutSecs = 20)
    {
        $startTime = microtime(true);

        while (count($fullProcNames) > 0) {
            foreach (array_keys($fullProcNames) as $idx) {
                $tgtName = $fullProcNames[$idx];
                $curStatus = $this->getProcessInfo($tgtName);
                if ($curStatus["statename"] == "RUNNING") {
                    // The second boolean argument sets it to non-blocking,
                    // due to an issue where blocking stopProcess sometimes
                    // never returns...
                    $this->logMsg("Stopping $tgtName");
                    $this->stopProcess($tgtName, false);
                } elseif ($this->isStoppedState($curStatus["statename"])) {
                    $this->logMsg("Confirmed down $tgtName");
                    unset($fullProcNames[$idx]);
                }
            }

            $elapsed = microtime(true) - $startTime;
            if (count($fullProcNames) > 0 && $elapsed > $timeoutSecs) {
                throw new \RuntimeException("Supervisor unable to stop processes");
            }

            usleep(self::REMOVAL_CYCLE_DELAY_MS * 1000);
        }
    }

    public function removeProcessesFromGroup($groupName, $procNames)
    {
        foreach ($procNames as $procName) {
            $this->logMsg("Removing $procName from $groupName");
            $this->removeProcessFromGroup($groupName, $procName);
        }
    }

    public function restartProcesses($fullProcNames, $stopTimeoutSecs = 20)
    {
        $this->stopProcessesParallel($fullProcNames, $stopTimeoutSecs);
        foreach ($fullProcNames as $procName) {
            $this->logMsg("Restarting $procName");
            $this->startProcess($procName);
        }
    }
}
