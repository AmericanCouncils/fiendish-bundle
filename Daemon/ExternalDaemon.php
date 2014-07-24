<?php

namespace AC\FiendishBundle\Daemon;

use Symfony\Component\HttpKernel\Kernel;

/**
 * Base class for daemons implemented by non-PHP programs
 */
abstract class ExternalDaemon extends BaseDaemon implements ExternalDaemonInterface
{
    public static function toCommand($kernel, $spec)
    {
        $cmd = static::getExternalCommand();
        if ($cmd[0] == '@') { $cmd = $kernel->locateResource($cmd); }
        $cmd = escapeshellarg($cmd);

        if (is_array($spec['arg']) || $spec['arg'] instanceof \Traversable) {
            foreach ($spec['arg'] as $arg) {
                $cmd .= " " . escapeshellarg((string)$arg);
            }
        } elseif (!is_null($spec['arg'])) {
            throw new \InvalidArgumentException("Invalid 'arg' value in spec, must be iterable");
        }

        return $cmd;
    }

    public function run($arg)
    {
        throw new \LogicException(
            "Cannot run ExternalDaemons with the internal-daemon command!"
        );
    }
}
