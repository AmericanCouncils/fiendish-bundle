<?php

namespace AC\FiendishBundle\Daemon;

use Symfony\Component\HttpKernel\Kernel;

/**
 * Base class for daemons implemented by non-PHP programs
 */
abstract class ExternalDaemon extends BaseDaemon implements ExternalDaemonInterface
{
    public static function toCommand(Kernel $kernel)
    {
        $cmd = static::getExternalCommand();
        if ($cmd[0] == '@') { $cmd = $kernel->locateResource($cmd); }
        return $cmd;
    }

    public function run($arg)
    {
        throw new \LogicException(
            "Cannot run ExternalDaemons with the internal-daemon command!"
        );
    }
}
