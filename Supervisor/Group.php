<?php

namespace DavidMikeSimon\FiendishBundle\Supervisor;

use PhpAmqpLib\Message\AMQPMessage;

/**
 * Dynamically controlled Supervisor process group.
 */
class Group
{
    /**
     * Cause a running master daemon to implement any Process changes.
     */
    public function sendSyncRequest()
    {
        $ch = $this->rabbitConn->channel();
        // TODO Get connection name from config
        $queue = $ch->queue_declare($this->name . "_master")[0];
        $msg = new AMQPMessage("sync");
        $ch->basic_publish($msg, "", $this->name . "_master");
    }

    private $name;

    /**
     * Returns the name of the group.
     */
    public function getName()
    {
        return $this->name;
    }

    private $rabbitConn;

    public function __construct($name, $rabbitConn)
    {
        $this->name = $name;
        $this->rabbitConn = $rabbitConn;
    }

}
