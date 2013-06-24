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
        $procName = $namePrefix . "." . uniqid(getmypid(), true);
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
        $em = $this->doctrine->getEntityManager();
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
        $em = $this->doctrine->getEntityManager();
        $em->remove($proc->getEntity());
    }

    /**
     * Retrieves a Process by name.
     *
     * @return Process instance, or null if not found.
     */
    public function getProcess($procName)
    {
        $em = $this->doctrine->getEntityManager();
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
     * Apply all Process changes made via newProcess() and removeProcess().
     *
     * This method does not block; the process changes
     * are implemented in the background by the group's master daemon.
     */
    public function applyChanges()
    {
        // TODO Lock table during this flush
        $em = $this->doctrine->getEntityManager();
        $em->flush();

        $ch = $this->rabbitConn->channel();
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
    private $doctrine;

    public function __construct($name, $rabbitConn, $doctrine)
    {
        $this->name = $name;
        $this->rabbitConn = $rabbitConn;
        $this->doctrine = $doctrine;
    }
}
