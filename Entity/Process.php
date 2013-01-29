<?php

namespace DavidMikeSimon\FiendishBundle\Entity;

use Symfony\Component\Process\PhpExecutableFinder;
use Doctrine\ORM\Mapping as ORM;
use DavidMikeSimon\FiendishBundle\Exception\LogicException;

/**
 * @ORM\Entity
 */
class Process
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    public function getId()
    {
        return $this->id;
    }

    /**
     * @ORM\Column(type="text")
     */
    protected $initialState;

    public function getInitialState()
    {
        return json_decode($this->initialState);
    }

    /**
     * @ORM\Column(type="string")
     */
    protected $daemonName;

    public function getDaemonName()
    {
        return $this->daemonName;
    }

    /**
     * @ORM\Column(type="string")
     */
    protected $daemonClass;

    public function getDaemonClass()
    {
        return $this->daemonClass;
    }

    /**
     * @ORM\Column(type="string")
     */
    protected $groupName;

    public function getGroupName()
    {
        return $this->groupName;
    }

    /**
     * @ORM\Column(type="string")
     */
    protected $procName;

    public function getProcName()
    {
        return $this->procName;
    }

    public function getFullProcName()
    {
        return $this->groupName . ":" . $this->procName;
    }

    /**
     * @ORM\Column(type="text")
     */
    protected $command;

    public function getCommand()
    {
        return $this->command;
    }

    public function __construct($groupName, $daemonName, $daemonClass, $initialState)
    {
        $this->groupName = $groupName;
        $this->daemonName = $daemonName;
        $this->daemonClass = $daemonClass;
        $this->initialState = json_encode($initialState);
    }

    public function initialSetup($appPath)
    {
        if ($this->isSetup()) { return; }
        if (is_null($this->id)) {
            throw new LogicException("Called initialSetup on un-persisted object");
        }

        $this->procName = sprintf("%s.%u", $this->daemonName, $this->id);
        $this->command = $this->buildPhpCommand($appPath);
    }

    public function isSetup()
    {
        return !is_null($this->procName);
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
