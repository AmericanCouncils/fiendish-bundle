<?php

namespace DavidMikeSimon\FiendishBundle\Daemon;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;

/**
 * Base class for all Fiendish daemon implementations
 */
abstract class BaseDaemon implements ContainerAwareInterface
{
    private $container;

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

    private $name;

    /**
     * Returns the name of this daemon process.
     *
     * This is the same as the daemonName parameter
     * in Entity\Process.
     */
    public function getName()
    {
        return $this->name;
    }

    public function __construct($name, $container)
    {
        $this->name = $name;
        $this->setContainer($container);
    }

    /**
     * Implement this abstract method with your daemon's functionality.
     *
     * This method should never return.
     *
     * @param $initialState Arguments for this daemon instance from the Process.
     */
    abstract public function run($initialState);

    protected function heartbeat()
    {
        // TODO
    }
}
