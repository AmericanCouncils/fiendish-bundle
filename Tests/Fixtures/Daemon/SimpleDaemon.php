<?php

namespace DavidMikeSimon\FiendishBundle\Tests\Fixtures\Daemon;

use DavidMikeSimon\FiendishBundle\Daemon\BaseDaemon;

class SimpleDaemon extends BaseDaemon
{
    public function run($arg)
    {
        while (true) {
            print($arg->content . "omatic\n");
            sleep(5);
        }
    }
}
