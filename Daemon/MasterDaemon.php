<?php

namespace DavidMikeSimon\FiendishBundle\Daemon;

use DavidMikeSimon\FiendishBundle\Supervisor\Manager;
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Daemon that manages a group of Fiendish processes in Supervisor.
 *
 * Start this daemon with Command\MasterDaemonCommand.
 */
class MasterDaemon extends BaseDaemon
{
    const HEARTBEAT_DELAY = 3;

    private $manager;
    private $groupName;

    public function run($initialState)
    {
        $groupName = $initialState->group;

        $this->manager = new Manager($groupName, $this->getContainer());

        $rabbit = self::getRabbit();
        $queue = $rabbit["ch"]->queue_declare($groupName . "_master")[0];

        $rabbit["ch"]->basic_consume($queue, "master",
            false, false, true, false, // 3rd true: Exclusive consumer
            function($msg) {
                // TODO Log both valid and invalid messages
                if ($msg->body == 'sync') {
                    $this->manager->sync();
                }
                $msg->delivery_info['channel']->
                    basic_ack($msg->delivery_info['delivery_tag']);
            }
        );

        $this->manager->sync();

        while (true) {
            $this->heartbeat();
            $this->manager->checkHeartbeats();

            $read = array($rabbit['conn']->getSocket());
            $write = null;
            $except = null;
            $changes = stream_select($read, $write, $except, self::HEARTBEAT_DELAY);
            if ($changes === false) {
                throw new \Exception("Stream_select failed");
            } elseif ($changes > 0) {
                // TODO If a bunch of syncs queue up, should only run once.
                $rabbit["ch"]->wait();
            }


            // TODO If we've been up a while, restart ourselves to avoid
            // any possible PHP weirdness/leaks.
        }
    }

    /**
     * Causes a running master daemon to implement any Process changes.
     *
     * Applies only to the daemon managing the given group.
     */
    public static function sendSyncRequest($groupName)
    {
        $rabbit = self::getRabbit();
        $queue = $rabbit["ch"]->queue_declare($groupName . "_master")[0];
        $msg = new AMQPMessage("sync");
        $rabbit["ch"]->basic_publish($msg, "", $groupName . "_master");
    }

    protected static function getRabbit()
    {
        // TODO Specify target server via a config file
        $conn = new AMQPConnection("localhost", 5672, "guest", "guest");
        $ch = $conn->channel();

        return ["conn" => $conn, "ch" => $ch];
    }
};
