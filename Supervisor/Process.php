<?php

namespace DavidMikeSimon\FiendishBundle\Supervisor;

use DavidMikeSimon\FiendishBundle\Entity\ProcessEntity;

/**
 * A running daemon process.
 *
 * Create, access and control these using the Group service, e.g.
 * `fiendish.groups.mygroupname`.
 */
class Process
{
    private $entity;

    // Public but should only be used internally by Fiendish.
    public function getEntity()
    {
        return $this->entity;
    }

    public function __construct(ProcessEntity $entity)
    {
        $this->entity = $entity;
    }

    /**
     * Returns the name of the Supervisor group that this process is in.
     */
    public function getGroupName()
    {
        return $this->entity->getGroupName();
    }

    /**
     * Returns the name of the process within Supervisor, not including
     *   the group prefix.
     */
    public function getProcName()
    {
        return $this->entity->getProcName();
    }

    /**
     * Returns the full name of this process as it appears in Supervisor's status list,
     * with group prefix included.
     */
    public function getFullProcName()
    {
        return $this->getGroupName() . ":" . $this->getProcName();
    }

    /**
     * Returns the full shell command used by Supervisor to start this process.
     */
    public function getCommand()
    {
        return $this->entity->getCommand();
    }
}
