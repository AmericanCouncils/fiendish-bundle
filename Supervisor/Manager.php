<?php

namespace DavidMikeSimon\FiendishBundle\Supervisor;

use DavidMikeSimon\FiendishBundle\Entity\Process;
use SupervisorClient\SupervisorClient;

class Manager
{
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
        // TODO Logging

        $supervisor = $this->getSupervisorClient();
        $sv_procs = [];
        foreach ($supervisor->getAllProcessInfo() as $sp) {
            if ($sp["group"] == $this->getGroupName()) {
                $sv_procs[$sp["name"]] = $sp;
            }
        }

        $em = $this->getContainer()->get('doctrine')->getManager();
        $repo = $em->getRepository('DavidMikeSimonFiendishBundle:Process');
        $tgt_procs = [];
        foreach ($repo->findByGroupName($this->groupName) as $tp) {
            if (!$tp->isSetup()) {
                $tp->initialSetup($this->getContainer()->get('kernel')->getRootDir());
            }
            // Master is not really in the same Supervisor group as subdaemons.
            // If it were, it would be possible for us to stop ourself! And
            // then nobody would be around to bring us back online.
            // TODO Master proc name should include the group name, e.g. foo_master
            if ($tp->getProcName() != "master") {
                $tgt_procs[$tp->getProcName()] = $tp;
            }
        }
        // TODO Create the master daemon process row if it doesn't already exist

        foreach ($sv_procs as $sp) {
            if (!array_key_exists($sp["name"], $tgt_procs)) {
                print("Removing " . $sp["name"] . "\n");
                // TODO What if already stopped?
                // FIXME If we're stopping a lot of processes, this might block
                // for quite a while.

                // This blocks until the process is stopped...
                $supervisor->stopProcess($this->getGroupName() . ":" . $sp["name"]);
                // ...because this does not work on running processes:
                $supervisor->removeProcessFromGroup(
                    $this->getGroupName(),
                    $sp["name"]
                );
            }
        }

        foreach ($tgt_procs as $tp) {
            if (!array_key_exists($tp->getProcName(), $sv_procs)) {
                print("Adding " . $tp->getProcName() . "\n");
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

        $em->flush();
    }

    public function check_heartbeats()
    {
        // TODO
    }

    private function getSupervisorClient()
    {
        // TODO Specify via config file
        return new SupervisorClient("unix:///var/run/supervisor.sock", 0, 10);
    }
}
