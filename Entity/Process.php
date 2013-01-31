<?php

namespace DavidMikeSimon\FiendishBundle\Entity;

use Symfony\Component\Process\PhpExecutableFinder;
use Doctrine\ORM\Mapping as ORM;
use DavidMikeSimon\FiendishBundle\Exception\LogicException;

/**
 * Represents a daemon process that the application wants to be running.
 *
 * From your application, create and delete Processes in order start
 * and stop instances of your daemons.
 *
 * After saving a Process to the database, or removing one from it, you must
 * call the static method Daemon\MasterDaemon::sendSyncRequest to have your
 * changes come into effect.
 *
 * @ORM\Entity
 */
class Process
{
    /**
     * Creates a new Process.
     *
     * @param $groupName Name of the Supervisor group to put this process in.
     * @param $daemonName A short name describing this Process; can be non-unique.
     * @param $daemonClass Fully-qualified path to the Daemon class to be ran.
     * @param $initialState A JSON-encodable object to pass to the daemon's run method.
     */
    public function __construct($groupName, $daemonName, $daemonClass, $initialState)
    {
        $this->groupName = $groupName;
        $this->daemonName = $daemonName;
        $this->daemonClass = $daemonClass;
        $this->initialState = json_encode($initialState);
    }

    /**
     * @ORM\Column(type="string")
     */
    protected $groupName;

    /**
     * Returns the name of the Supervisor group that this process is in.
     */
    public function getGroupName()
    {
        return $this->groupName;
    }

    /**
     * @ORM\Column(type="string")
     */
    protected $daemonName;

    /**
     * Returns the daemon name that was given to this Process.
     */
    public function getDaemonName()
    {
        return $this->daemonName;
    }

    /**
     * @ORM\Column(type="string")
     */
    protected $daemonClass;

    /**
     * Returns the name of the daemon class that the process will be running.
     */
    public function getDaemonClass()
    {
        return $this->daemonClass;
    }

    /**
     * @ORM\Column(type="string")
     */
    protected $procName;

    /**
     * @ORM\Column(type="text")
     */
    protected $initialState;

    /**
     * Returns the initial state argument to be passed to the daemon.
     *
     * This function returns the state as the daemon's run() method will see it,
     * e.g. with associative arrays converted into objects due to passing
     * through PHP's somewhat odd JSON decoding conventions.
     */
    public function getInitialState()
    {
        return json_decode($this->initialState);
    }

    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * Returns the row ID of the Process.
     *
     * This number is also used in the Supervisor name of the Process, so
     * that for example if you have a process named 'foo' and its ID is 42,
     * then `supervisorctl status` will list it as `somegroup:foo.42`.
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Returns the name of this process as it appears in Supervisor's status list,
     * without any group name prefix.
     *
     * The Process must already have been set-up by the master daemon (i.e.
     * must return true when isSetup() is called) for this
     * method to work.
     */
    public function getProcName()
    {
        $this->requireSetup();
        return $this->procName;
    }

    /**
     * Returns the full name of this process as it appears in Supervisor's status list,
     * i.e. with any group name prefix included.
     *
     * The Process must already have been set-up by the master daemon (i.e.
     * must return true when isSetup() is called) for this
     * method to work.
     */
    public function getFullProcName()
    {
        $this->requireSetup();
        return $this->groupName . ":" . $this->procName;
    }

    /**
     * @ORM\Column(type="text")
     */
    protected $command;

    /**
     * Returns the full shell command used by Supervisor to start this process.
     *
     * The Process must already have been set-up by the master daemon (i.e.
     * must return true when isSetup() is called) for this
     * method to work.
     */
    public function getCommand()
    {
        $this->requireSetup();
        return $this->command;
    }

    // It's public, but only MasterDaemon is supposed to call this
    public function initialSetup($appPath)
    {
        if ($this->isSetup()) { return; }
        if (is_null($this->id)) {
            throw new LogicException("Called initialSetup on un-persisted object");
        }

        $this->procName = sprintf("%s.%u", $this->daemonName, $this->id);
        $this->command = $this->buildPhpCommand($appPath);
    }

    /**
     * If the master daemon has noticed and begun managing this process, returns true.
     */
    public function isSetup()
    {
        return !is_null($this->procName);
    }

    private function requireSetup()
    {
        if (!$this->isSetup()) {
            throw new LogicException("Invalid use of an un-setup Process");
        }
    }

    protected function buildPhpCommand($appPath)
    {
        $phpExec = (new PhpExecutableFinder)->find();
        if (!$phpExec) { throw new Exception("Cannot find php executable"); }

        $consolePath = realpath($appPath) . DIRECTORY_SEPARATOR . "console";
        $daemonSpec = json_encode([
            "groupName" => $this->groupName,
            "daemonClass" => $this->daemonClass,
            "daemonName" => $this->daemonName,
            "initialState" => json_decode($this->initialState)
            ]);
        return implode(" ", [
            $phpExec,
            $consolePath,
            "-v",
            "fiendish:internal-daemon",
            escapeshellarg($daemonSpec)
            ]);
    }
}
