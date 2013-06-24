<?php

namespace AC\FiendishBundle\Supervisor;

use SupervisorClient\SupervisorClient;
use AC\FiendishBundle\Exception\RuntimeException;

// TODO Maybe should refactor much of this into MasterDaemon?

class Manager
{
    const MAX_REMOVAL_CYCLES = 200;
    const REMOVAL_CYCLE_DELAY_MS = 100;

    private $container;
    public function getContainer()
    {
        return $this->container;
    }

    private $groupName;
    public function getGroupName()
    {
        return $this->groupName;
    }

    public function __construct($groupName, $container)
    {
        $this->groupName = $groupName;
        $this->container = $container;
    }

    public function sync()
    {
        // TODO Check if we are the correct master process, die if not

        $supervisor = $this->getSupervisorClient();
        $this->logMsg($supervisor, "Syncing...");
        $sv_procs = [];
        foreach ($supervisor->getAllProcessInfo() as $sp) {
            if ($sp["group"] == $this->getGroupName()) {
                $sv_procs[$sp["name"]] = $sp;
            }
        }

        $em = $this->getContainer()->get('doctrine')->getManager();
        // TODO Get this through the group service instead
        $repo = $em->getRepository('ACFiendishBundle:ProcessEntity');
        $tgt_procs = [];
        foreach ($repo->findByGroupName($this->groupName) as $tp) {
            // Master is not really in the same Supervisor group as subdaemons.
            // If it were, it would be possible for us to stop ourself! And
            // then nobody would be around to bring us back online.
            // TODO Master proc name should include the group name, e.g. foo_master
            if ($tp->getProcName() != "master") {
                $tgt_procs[$tp->getProcName()] = $tp;
            }
        }
        // TODO Create the master daemon process row if it doesn't already exist

        $procs_to_remove = [];
        foreach ($sv_procs as $sp) {
            if (!array_key_exists($sp["name"], $tgt_procs)) {
                $sp["tgtName"] = $this->getGroupName() . ":" . $sp["name"];
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
                        $this->getGroupName(),
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
                    $this->getGroupName(),
                    $tp->getProcName(), [
                    "command" => $tp->getCommand(),
                    "autostart" => "true",
                    "user" => "www-data", // TODO Should be configurable
                    "redirect_stderr" => "true"
                    ]
                );
            }
        }

        // TODO Use multicall.
        $em->flush();
        $this->logMsg($supervisor, "Sync finished");
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
        $supervisor->logMessage("(Fiendish) " . $this->groupName . " Master: $msg");
        print(date(\DateTime::W3C) . " " . $msg . "\n");
    }

    private static function isStoppedState($statename)
    {
        return in_array($statename, ["STOPPED", "FATAL", "EXITED", "UNKNOWN"]);
    }
}
