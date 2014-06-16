<?php

namespace AC\FiendishBundle\Tests\Fixtures\Daemon;

use AC\FiendishBundle\Daemon\BaseDaemon;

class SimpleDaemon extends BaseDaemon
{
    public function run($arg)
    {
        while (true) {
            print($arg->content . "omatic\n");
            sleep(2);
            if ($arg->content == "die") {
                die();
            }
        }
    }
}
