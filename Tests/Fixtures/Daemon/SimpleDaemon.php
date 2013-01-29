<?php

namespace DavidMikeSimon\FiendishBundle\Tests\Fixtures\Daemon;

use DavidMikeSimon\FiendishBundle\Daemon\BaseDaemon;

class SimpleDaemon extends BaseDaemon
{
    public function run($initialState = null)
    {
        while (true) {
            print($initialState->content . "omatic\n");
            sleep(5);
        }
    }
}
