<?php

namespace Beatbox\DaemonBundle\Daemon;

use Beatbox\DaemonBundle\Supervisor\Manager;
use PhpAmqpLib\Message\AMQPMessage;

class MasterDaemon extends Daemon
{
    const CYCLE_DELAY = 3;

    private $manager;
    private $groupName;

    public function run($initialState = null)
    {
        // TODO Get group name from cmd line arguments here
        $groupName = 'beatbox';

        $this->manager = new Manager($groupName, $this->getContainer());

        $rabbit = self::getRabbit();
        $queue = $rabbit["ch"]->queue_declare($groupName . "_master")[0];

        $rabbit["ch"]->basic_consume($queue, "master",
            false, false, true, false, // 3rd true: Exclusive consumer
            function($msg) {
                print("GOT MSG " . $msg->body . "\n");
                // TODO Log both valid and invalid messages
                if ($msg->body == 'sync') {
                    print("SYNCING\n");
                    $this->manager->sync();
                }
                $msg->delivery_info['channel']->
                    basic_ack($msg->delivery_info['delivery_tag']);
            }
        );

        $this->manager->sync();

        while (true) {
            $this->heartbeat();
            $this->manager->check_heartbeats();

            $read = array($rabbit['conn']->getSocket());
            $write = null;
            $except = null;
            $changes = stream_select($read, $write, $except, self::CYCLE_DELAY);
            if ($changes === false) {
                throw new \Exception("Stream_select failed");
            } elseif ($changes > 0) {
                $rabbit["ch"]->wait();
            }

            print("Lub-dub\n");

            // TODO If we've been up a while, restart ourselves to avoid
            // any possible PHP weirdness/leaks.
        }
    }

    // To be called from outside the master daemon
    public static function sendSyncRequest($groupName)
    {
        $rabbit = self::getRabbit();
        $queue = $rabbit["ch"]->queue_declare($groupName . "_master")[0];
        $msg = new AMQPMessage("sync");
        $rabbit["ch"]->basic_publish($msg, "", $groupName . "_master");
    }
};
