<?php

namespace AC\FiendishBundle\Daemon;

use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Exception\AMQPProtocolException;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;

/**
 * Base class for all Fiendish daemon implementations
 */
abstract class BaseDaemon implements ContainerAwareInterface
{
    private $container;
    private $groupName;
    private $procName;

    public function __construct($groupName, $procName, $container)
    {
        $this->groupName = $groupName;
        $this->procName = $procName;
        $this->setContainer($container);
    }

    /**
     * Returns the Symfony service container.
     */
    protected function getContainer()
    {
        return $this->container;
    }

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * Returns the name of the Supervisor group this daemon process is running in.
     *
     * This is the same as what Supervisor\Process::getGroupName() returns.
     */
    public function getGroupName()
    {
        return $this->groupName;
    }

    /**
     * Returns the Group instance that this daemon is running in.
     */
    public function getGroup()
    {
        return $this->getContainer()->get("fiendish.groups." . $this->groupName);
    }

    /**
     * Returns the unique name of this daemon process.
     *
     * This is the same as what Supervisor\Process::getProcName() returns.
     */
    public function getProcName()
    {
        return $this->procName;
    }

    /**
     * Returns the unique name of this daemon process with group.
     *
     * This is the same as what Supervisor\Process::getFullProcName() returns.
     */
    public function getFullProcName()
    {
        return $this->getGroupName() . ":" . $this->getProcName();
    }

    protected function getHeartbeatQueueName()
    {
        return $this->groupName . "_master";
    }

    protected function heartbeat()
    {
        try {
            $ch = $this->getGroup()->getMasterRabbit()->channel();
            $ch->queue_declare($this->getHeartbeatQueueName());
            $msg = new AMQPMessage("heartbeat." . $this->getFullProcName());
            $ch->basic_publish($msg, "", $this->getHeartbeatQueueName());
        } catch (AMQPProtocolException $e) {
            # Ignore transient heartbeat problems
        }
    }

    /**
     * Returns a shell command that can start this daemon.
     */
    public static function toCommand($container, $spec)
    {
        $kernel = $container->get('kernel');

        $phpExec = (new PhpExecutableFinder)->find();
        if (!$phpExec) { throw new \RuntimeException("Cannot find php executable"); }

        $consolePath = realpath($kernel->getRootDir()) . "/console";
        return implode(" ", [
            escapeshellarg($phpExec),
            escapeshellarg($consolePath),
            escapeshellarg("--env=" . $kernel->getEnvironment()),
            "-v",
            "fiendish:internal-daemon",
            escapeshellarg(get_called_class()),
            escapeshellarg(json_encode($spec))
        ]);
    }

    /**
     * Implement this abstract method with your daemon's functionality.
     *
     * @param $arg Arguments for this daemon, from the Process constructor.
     */
    abstract public function run($arg);
}
