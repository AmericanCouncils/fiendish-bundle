<?php

namespace AC\FiendishBundle\Tests\Fixtures\Daemon;

use AC\FiendishBundle\Daemon\BaseDaemon;

class SimpleDaemon extends BaseDaemon
{
    public function run($arg)
    {
        while (true) {
            if ($arg['content'] != "vampire") {
                $this->heartbeat();
            }
            print($arg['content'] . "omatic\n");
            sleep(1);
        }
    }
}
