<?php

namespace AC\FiendishBundle\Daemon;

use Functional as F;
use AC\FiendishBundle\Supervisor\Client;

class MasterDaemon extends BaseDaemon
{
    private $lastHeartbeats = [];

    private function logMsg($msg)
    {
        // TODO Should do this with monolog instead
        print(date(\DateTime::W3C) . " " . $msg . "\n");
    }

    private function getSupervisorDaemonConfig($proc)
    {
        $heartbeat_queue = $this->getHeartbeatQueueName();
        $heartbeat_message = "heartbeat." . $proc->getFullProcName();

        // TODO Allow more customization here
        return [
            "command" => $proc->getCommand(),
            "autostart" => "true",
            "autorestart" => "true",
            "user" => $this->getGroup()->getUsername(),
            "exitcodes" => "",
            "stdout_syslog" => "local1.info",
            "stdout_syslog" => "local1.notice",
            "environment" =>
                "FIENDISH_HEARTBEAT_ROUTING_KEY=\"$heartbeat_queue\"," .
                "FIENDISH_HEARTBEAT_MESSAGE=\"$heartbeat_message\""
        ];
    }

    private function sync()
    {
        $supervisor = $this->getSupervisorClient();
        $groupName = $this->getGroup()->getName();
        $this->logMsg("Syncing...");

        $sProcs = $supervisor->getGroupProcessInfo($groupName);
        $sProcNames = F\pluck($sProcs, "fullName");
        $tProcs = $this->getGroup()->getAllProcesses();
        $tProcNames = F\map($tProcs, function ($tp) { return $tp->getFullProcName(); });

        // Remove processes that are in supervisor but not in the target list
        $toRemove = F\reject($sProcs, function ($sp) use ($tProcNames) {
            return in_array($sp["fullName"], $tProcNames);
        });
        $supervisor->stopProcessesParallel(F\pluck($toRemove, "fullName"));
        $supervisor->removeProcessesFromGroup($groupName, F\pluck($toRemove, "name"));

        // Add processes that are in the target list but not in supervisor
        $toAdd = F\reject($tProcs, function ($tp) use ($sProcNames) {
            return in_array($tp->getFullProcName(), $sProcNames);
        });
        foreach ($toAdd as $tp) {
            $this->logMsg("Adding " . $tp->getFullProcName());
            $supervisor->addProgramToGroup(
                $groupName,
                $tp->getProcName(),
                $this->getSupervisorDaemonConfig($tp)
            );

            // Treat starting the process as if it were the first heartbeat.
            // That way we can detect the timeout that occurs if the process
            // never even sends one heartbeat message.
            $this->gotHeartbeat($tp->getFullProcName());
        }
    }

    private function gotHeartbeat($procName)
    {
        $this->lastHeartbeats[$procName] = time();
    }

    private function checkHeartbeats()
    {
        $timedOut = [];
        foreach (array_keys($this->lastHeartbeats) as $procName) {
            $secs = time() - $this->lastHeartbeats[$procName];
            if ($secs > $this->getGroup()->getHeartbeatTimeout()) {
                $timedOut[] = $procName;
            }
        }

        if (!empty($timedOut)) {
            foreach ($timedOut as $procName) {
                // TODO: Log with a higher priority
                $this->logMsg("$procName timed out, going to restart it");
            }
            $this->getSupervisorClient()->restartProcesses($timedOut);
            foreach ($timedOut as $procName) {
                $this->gotHeartbeat($procName);
            }
        }
    }

    private function getSupervisorClient()
    {
        // TODO Get connection info (socket path, username, etc.) from config
        return new Client("unix:///var/run/supervisor.sock", 0, 10);
    }

    public function run($arg)
    {
        $rabbit = $this->getGroup()->getMasterRabbit();
        $ch = $rabbit->channel();
        $queue = $ch->queue_declare($this->getHeartbeatQueueName())[0];
        $ch->basic_consume($queue, "master",
            false, false, true, false, // 3rd true: Exclusive consumer
            function($msg) {
                // TODO Log both valid and invalid messages
                if ($msg->body == 'sync') {
                    $this->sync();
                } elseif (preg_match('/^heartbeat\.(.+)$/', $msg->body, $matches)) {
                    $this->gotHeartbeat($matches[1]);
                }
                $msg->delivery_info['channel']->
                    basic_ack($msg->delivery_info['delivery_tag']);
            }
        );

        $this->sync();

        while (true) {
            $this->checkHeartbeats();

            $read = [$rabbit->getSocket()];
            $write = null;
            $except = null;
            // Wait up to 1 second for messages
            $changes = stream_select($read, $write, $except, 1);
            if ($changes === false) {
                throw new \RuntimeException("stream_select failed");
            } elseif ($changes > 0) {
                // TODO If a bunch of syncs queue up, should only run once.
                $ch->wait();
            }

            // TODO If we've been up a while, restart ourselves to avoid
            // any possible PHP weirdness/leaks.
        }
    }
};
