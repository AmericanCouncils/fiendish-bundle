<?php

namespace AC\FiendishBundle\Supervisor;

use SupervisorClient\SupervisorClient;
use AC\FiendishBundle\Exception\RuntimeException;

// TODO Maybe should refactor much of this into MasterDaemon?

class Manager
{
    const MAX_REMOVAL_CYCLES = 200;
    const REMOVAL_CYCLE_DELAY_MS = 100;

    private $group;
    private $lastHeartbeats = [];

    public function __construct($group)
    {
        $this->group = $group;
    }

    private function getGroupSupervisorProcs($supervisor)
    {
        $sv_procs = [];
        foreach ($supervisor->getAllProcessInfo() as $sp) {
            if ($sp["group"] == $this->group->getName()) {
                $sv_procs[$sp["name"]] = $sp;
            }
        }
        return $sv_procs;
    }

    private function stopProcs($supervisor, $svProcs)
    {
        $removal_cycles = 0;
        while (count($svProcs) > 0) {
            foreach (array_keys($svProcs) as $idx) {
                $sp = $svProcs[$idx];
                $tgtName = $sp["group"] . ":" . $sp["name"];
                $curStatus = $supervisor->getProcessInfo($tgtName);
                if ($curStatus["statename"] == "RUNNING") {
                    // The second boolean argument sets it to non-blocking,
                    // due to an issue where blocking stopProcess sometimes
                    // never returns...
                    $this->logMsg($supervisor, "Stopping " . $sp["name"]);
                    $supervisor->stopProcess($tgtName, false);
                } elseif (self::isStoppedState($curStatus["statename"])) {
                    $this->logMsg($supervisor, "Confirmed down " . $sp["name"]);
                    unset($svProcs[$idx]);
                }
            }

            ++$removal_cycles;
            if ($removal_cycles > self::MAX_REMOVAL_CYCLES) {
                throw new RuntimeException("Supervisor unable to stop processes");
            }

            usleep(self::REMOVAL_CYCLE_DELAY_MS * 1000);
        }
    }

    public function sync()
    {
        $supervisor = $this->getSupervisorClient();
        $this->logMsg($supervisor, "Syncing...");
        $sv_procs = $this->getGroupSupervisorProcs($supervisor);

        $tgt_procs = [];
        foreach ($this->group->getAllProcesses() as $tp) {
            $tgt_procs[$tp->getProcName()] = $tp;
        }

        $procs_to_remove = [];
        foreach ($sv_procs as $sp) {
            if (!array_key_exists($sp["name"], $tgt_procs)) {
                $sp["tgtName"] = $this->group->getName() . ":" . $sp["name"];
                $procs_to_remove[] = $sp;
            }
        }
        $this->stopProcs($supervisor, $procs_to_remove);
        foreach ($procs_to_remove as $sp) {
            $this->logMsg($supervisor, "Removing " . $name);
            $supervisor->removeProcessFromGroup(
                $this->group->getName(),
                $sp["name"]
            );
        }

        foreach ($tgt_procs as $tp) {
            if (!array_key_exists($tp->getProcName(), $sv_procs)) {
                $this->logMsg($supervisor, "Adding " . $tp->getProcName());
                $supervisor->addProgramToGroup(
                    $this->group->getName(),
                    $tp->getProcName(), [
                    "command" => $tp->getCommand(),
                    "autostart" => "true",
                    "autorestart" => "true",
                    "user" => $this->group->getUsername(),
                    "exitcodes" => "",
                    "redirect_stderr" => "true"
                    ]
                );

                // Treat starting the process as if it were the first heartbeat.
                // That way we can detect a timeout that occurs if the process
                // never even sends one heartbeat message.
                $this->gotHeartbeat($tp->getProcName());
            }
        }
    }

    public function gotHeartbeat($procName)
    {
        $this->lastHeartbeats[$procName] = time();
    }

    public function checkHeartbeats()
    {
        $timedOut = [];
        foreach (array_keys($this->lastHeartbeats) as $procName) {
            $secs = time() - $this->lastHeartbeats[$procName];
            if ($secs > $this->group->getHeartbeatTimeout()) {
                $timedOut[] = $procName;
            }
        }

        if (empty($timedOut)) { return; }

        $supervisor = $this->getSupervisorClient();
        foreach ($timedOut as $procName) {
            // TODO: Log with a higher priority
            $this->logMsg($supervisor, "$procName timed out, going to restart it");
        }
        $sv_procs = $this->getGroupSupervisorProcs($supervisor);
        $sv_procs = array_filter($sv_procs, function($sp) use ($timedOut) {
            return in_array($sp["name"], $timedOut);
        });
        $this->stopProcs($supervisor, $sv_procs);
        foreach ($sv_procs as $sp) {
            $this->logMsg($supervisor, "Restarting $procName");
            $supervisor->startProcess($sp["group"] . ":" . $sp["name"]);
        }
    }

    private function getSupervisorClient()
    {
        // TODO Specify via config file
        return new SupervisorClient("unix:///var/run/supervisor.sock", 0, 10);
    }

    private function logMsg($supervisor, $msg)
    {
        // TODO Should do this with monolog instead
        $supervisor->logMessage("(Fiendish) " . $this->group->getName() . " Master: $msg");
        print(date(\DateTime::W3C) . " " . $msg . "\n");
    }

    private static function isStoppedState($statename)
    {
        return in_array($statename, ["STOPPED", "FATAL", "EXITED", "UNKNOWN"]);
    }
}
