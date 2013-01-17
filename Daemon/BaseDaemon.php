<?php

namespace DavidMikeSimon\FiendishBundle\Daemon;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use PhpAmqpLib\Connection\AMQPConnection;

abstract class BaseDaemon implements ContainerAwareInterface
{
    private $container;
    protected function getContainer()
    {
        return $this->container;
    }
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    private $name;
    public function getName()
    {
        return $this->name;
    }

    public function __construct($name, $container)
    {
        $this->name = $name;
        $this->setContainer($container);
    }

    abstract public function run($initialState = null);

    protected function heartbeat()
    {
        // TODO
    }

    protected static function getRabbit()
    {
        // TODO Specify target server via a config file
        $conn = new AMQPConnection("localhost", 5672, "guest", "guest");
        $ch = $conn->channel();

        return ["conn" => $conn, "ch" => $ch];
    }
}
