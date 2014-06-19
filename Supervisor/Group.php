<?php

namespace AC\FiendishBundle\Supervisor;

use PhpAmqpLib\Message\AMQPMessage;
use AC\FiendishBundle\Entity\ProcessEntity;

/**
 * Dynamically controlled Supervisor process group.
 *
 * Access a Group via its service name, e.g.
 * `fiendish.groups.mygroupname`.
 */
class Group
{
    /**
     * Creates a new Process.
     *
     * New processes will not actually be started until you call flush().
     *
     * Processes will be passed an additional argument on the command line,
     * a JSON-encoded data structure with the following keys:
     * * groupName - The name of the containing Supervisor group.
     * * procName - The name of the process within Supervisor, not including
     *   the group prefix.
     * * arg - The $arg argument passed to newProcess.
     *
     * @param $namePrefix A short string describing this Process; can be non-unique.
     *        The generated process's name will also include a unique random
     *        suffix.
     * @param $command The shell command that the Process will run. If you are
     *        running a daemon implemented in PHP as a subclass of
     *        Daemon\BaseDaemon then you can get an appropriate command
     *        by calling the static method getCommand() on your class.
     * @param $arg (Optional) JSON-encodable object to pass to process as an argument.
     *        Note that associative arrays are converted into objects due to passing
     *        through PHP's somewhat odd JSON decoding conventions.
     * @return The new Process object. It's also tracked internally by the Group,
     *         so you don't need to worry about saving this return value. However,
     *         you will want need to preserve the name if you want to get this
     *         specific Process out of the Group later with getProcess().
     */
    public function newProcess($namePrefix, $command, $arg = null)
    {
        $uuid = strtr(base64_encode(openssl_random_pseudo_bytes(12)), "/+", "12");
        $procName = "$namePrefix.$uuid";
        $jsonSpec = json_encode([
            "groupName" => $this->getName(),
            "procName" => $procName,
            "arg" => $arg
        ]);
        $procEntity = new ProcessEntity(
            $this->getName(),
            $procName,
            $command . " " . escapeshellarg($jsonSpec)
        );
        $em = $this->doctrine->getManager();
        $em->persist($procEntity);

        return new Process($procEntity);
    }

    /**
     * Removes a Process.
     *
     * The process will not actually be stopped until you call flush().
     */
    public function removeProcess(Process $proc)
    {
        $em = $this->doctrine->getManager();
        $em->remove($proc->getEntity());
    }

    /**
     * Retrieves a Process by name.
     *
     * @return Process instance, or null if not found.
     */
    public function getProcess($procName)
    {
        $em = $this->doctrine->getManager();
        $repo = $em->getRepository("ACFiendishBundle:ProcessEntity");
        $procEntity = $repo->findOneBy([
            "groupName" => $this->getName(),
            "procName" => $procName
        ]);

        if (is_null($procEntity)) {
            return null;
        } else {
            return new Process($procEntity);
        }
    }

    /**
     * Returns a list of all Processes in the group.
     *
     * @return Array of Process instances
     */
    public function getAllProcesses()
    {
        $em = $this->doctrine->getManager();
        $repo = $em->getRepository("ACFiendishBundle:ProcessEntity");
        $entities = $repo->findByGroupName($this->getName());
        return array_map(function ($e) { return new Process($e); }, $entities);
    }

    /**
     * Apply all Process changes made via newProcess() and removeProcess().
     *
     * This method does not block; the process changes
     * are implemented in the background by the group's master daemon.
     */
    public function applyChanges()
    {
        $em = $this->doctrine->getManager();
        $em->flush();

        // TODO: Can we be sure that the changes are available in the DB now?
        $ch = $this->getMasterRabbit()->channel();
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

    private $heartbeatTimeout;

    /**
     * Returns the maximum number of seconds allowed between heartbeats.
     *
     * If a process in this group doesn't send a heartbeat within this time,
     * it will be forcibly restarted.
     */
    public function getHeartbeatTimeout()
    {
        return $this->heartbeatTimeout;
    }

    private $username;

    /**
     * Returns the user that processes in this group run as.
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * Returns a Rabbit connection for sending messages to the group master.
     */
    public function getMasterRabbit()
    {
        return $this->rabbitConn;
    }

    private $rabbitConn;
    private $doctrine;

    public function __construct($name, $rabbitConn, $doctrine, $username, $heartbeatTimeout)
    {
        $this->name = $name;
        $this->rabbitConn = $rabbitConn;
        $this->doctrine = $doctrine;
        $this->username = $username;
        $this->heartbeatTimeout = $heartbeatTimeout;
    }
}
