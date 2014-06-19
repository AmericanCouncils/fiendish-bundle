<?php

namespace AC\FiendishBundle\Tests\Fixtures\Daemon;

use AC\FiendishBundle\Daemon\BaseDaemon;

class TestDaemon extends BaseDaemon
{
    public function run($arg)
    {
        while (true) {
            print $arg['content'] . "omatic\n";
            if ($arg['content'] != "vampire") {
                $this->heartbeat();
            }
            sleep(1);
        }
    }
}
