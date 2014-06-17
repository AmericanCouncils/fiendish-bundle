<?php

namespace AC\FiendishBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class ProcessEntity
{
    public function __construct($groupName, $procName, $command)
    {
        $this->groupName = $groupName;
        $this->procName = $procName;
        $this->command = $command;
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
        return $this->getGroupName() . ":" . $this->getProcName();
    }

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
    protected $command;
    public function getCommand()
    {
        return $this->command;
    }
}
