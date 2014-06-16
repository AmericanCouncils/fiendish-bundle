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

    public function __construct($group)
    {
        $this->group = $group;
    }

    public function sync()
    {
        $supervisor = $this->getSupervisorClient();
        $this->logMsg($supervisor, "Syncing...");
        $sv_procs = [];
        foreach ($supervisor->getAllProcessInfo() as $sp) {
            if ($sp["group"] == $this->group->getName()) {
                $sv_procs[$sp["name"]] = $sp;
            }
        }

        // TODO Get this through the group service instead
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
        $removal_cycles = 0;
        while (count($procs_to_remove) > 0) {
            foreach ($procs_to_remove as $idx=>$sp) {
                $cur_status = $supervisor->getProcessInfo($sp["tgtName"]);
                if ($cur_status["statename"] == "RUNNING") {
                    // The second boolean argument sets it to non-blocking,
                    // due to an issue where blocking stopProcess sometimes
                    // never returns...
                    $this->logMsg($supervisor, "Stopping " . $sp["name"]);
                    $supervisor->stopProcess($sp["tgtName"], false);
                } elseif (self::isStoppedState($cur_status["statename"])) {
                    $this->logMsg($supervisor, "Removing " . $sp["name"]);
                    $supervisor->removeProcessFromGroup(
                        $this->group->getName(),
                        $sp["name"]
                    );
                    unset($procs_to_remove[$idx]);
                }
            }

            ++$removal_cycles;
            if ($removal_cycles > self::MAX_REMOVAL_CYCLES) {
                throw new RuntimeException("Supervisor unable to stop processes");
            }

            usleep(self::REMOVAL_CYCLE_DELAY_MS * 1000);
        }

        foreach ($tgt_procs as $tp) {
            if (!array_key_exists($tp->getProcName(), $sv_procs)) {
                $this->logMsg($supervisor, "Adding " . $tp->getProcName());
                $supervisor->addProgramToGroup(
                    $this->group->getName(),
                    $tp->getProcName(), [
                    "command" => $tp->getCommand(),
                    "autostart" => "true",
                    "user" => $this->group->getUsername(),
                    "exitcodes" => "",
                    "redirect_stderr" => "true"
                    ]
                );
            }
        }
    }

    public function checkHeartbeats()
    {
        // TODO
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
