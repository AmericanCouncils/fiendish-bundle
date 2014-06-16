<?php

namespace AC\FiendishBundle\Daemon;

use AC\FiendishBundle\Supervisor\Manager;

class MasterDaemon extends BaseDaemon
{
    private $manager;

    public function run($arg)
    {
        $this->manager = new Manager($this->getGroup());

        $rabbit = $this->getGroup()->getMasterRabbit();
        $ch = $rabbit->channel();
        $queue = $ch->queue_declare($this->getGroup()->getName() . "_master")[0];
        $ch->basic_consume($queue, "master",
            false, false, true, false, // 3rd true: Exclusive consumer
            function($msg) {
                // TODO Log both valid and invalid messages
                if ($msg->body == 'sync') {
                    $this->manager->sync();
                } elseif (preg_match('/^heartbeat\.(\S+)$/', $msg->body, $matches)) {
                    $this->manager->gotHeartbeat($matches[1]);
                }
                $msg->delivery_info['channel']->
                    basic_ack($msg->delivery_info['delivery_tag']);
            }
        );

        $this->manager->sync();

        while (true) {
            $this->manager->checkHeartbeats();

            $read = [$rabbit->getSocket()];
            $write = null;
            $except = null;
            // Wait up to 1 second for messages
            $changes = stream_select($read, $write, $except, 1);
            if ($changes === false) {
                throw new \Exception("stream_select failed");
            } elseif ($changes > 0) {
                // TODO If a bunch of syncs queue up, should only run once.
                $ch->wait();
            }

            // TODO If we've been up a while, restart ourselves to avoid
            // any possible PHP weirdness/leaks.
        }
    }
};
