<?php

namespace DavidMikeSimon\FiendishBundle\Daemon;

use DavidMikeSimon\FiendishBundle\Supervisor\Manager;

class MasterDaemon extends BaseDaemon
{
    const HEARTBEAT_DELAY = 3;

    private $manager;

    public function run($arg)
    {
        $this->manager = new Manager($this->getGroupName(), $this->getContainer());

        // TODO Get connection name from config
        $rabbit = $this->getContainer()->get('old_sound_rabbit_mq.connection.default');
        $ch = $rabbit->channel();
        $queue = $ch->queue_declare($this->getGroupName() . "_master")[0];

        $ch->basic_consume($queue, "master",
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

            $read = array($rabbit->getSocket());
            $write = null;
            $except = null;
            $changes = stream_select($read, $write, $except, self::HEARTBEAT_DELAY);
            if ($changes === false) {
                throw new \Exception("Stream_select failed");
            } elseif ($changes > 0) {
                // TODO If a bunch of syncs queue up, should only run once.
                $ch->wait();
            }


            // TODO If we've been up a while, restart ourselves to avoid
            // any possible PHP weirdness/leaks.
        }
    }
};
