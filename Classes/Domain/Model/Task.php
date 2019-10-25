<?php
namespace Ttree\Scheduler\Domain\Model;

/*                                                                        *
 * This script belongs to the Neos Flow package "Ttree.Scheduler".       *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3 of the   *
 * License, or (at your option) any later version.                        *
 *                                                                        */

use Cron\CronExpression;
use Doctrine\ORM\Mapping as ORM;
use Ttree\Scheduler\Task\TaskInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Flow\Utility\Now;

/**
 * Schedule Task
 *
 * @Flow\Entity
 */
class Task
{

    const STATUS_DISABLED = 0;
    const STATUS_ENABLED = 1;

    /**
     * @var integer
     */
    protected $status;

    /**
     * @var string
     * @Flow\Identity
     */
    protected $expression;

    /**
     * @var string
     * @Flow\Identity
     */
    protected $implementation;

    /**
     * @var array
     */
    protected $arguments;

    /**
     * @var string
     * @Flow\Identity
     */
    protected $argumentsHash;

    /**
     * @var \DateTime
     */
    protected $creationDate;

    /**
     * @var \DateTime
     * @ORM\Column(nullable=true)
     */
    protected $lastExecution;

    /**
     * @var \DateTime
     * @ORM\Column(nullable=true)
     */
    protected $nextExecution;

    /**
     * @var CronExpression
     * @Flow\Transient
     */
    protected $cronExpression;

    /**
     * @var string
     * @ORM\Column(type="text")
     */
    protected $description;
    
    /**
     * @param string $expression
     * @param string $implementation
     * @param array $arguments
     * @param string $description
     */
    public function __construct($expression, $implementation, array $arguments = [], $description = '')
    {
        $this->disable();
        $this->setExpression($expression);
        $this->setImplementation($implementation);
        $this->setArguments($arguments);
        $this->setDescription($description);
        $this->creationDate = new \DateTime('now');
        $this->initializeNextExecution();
    }

    /**
     * Initialize Object
     */
    public function getCronExpression()
    {
        if ($this->cronExpression === null) {
            $this->cronExpression = CronExpression::factory($this->expression);
        }
        return $this->cronExpression;
    }

    /**
     * @return boolean
     */
    public function isDue()
    {
        $now = new Now();
        return $this->nextExecution <= $now;
    }

    /**
     * @return \DateTime
     */
    public function getPreviousRunDate()
    {
        return $this->getCronExpression()->getPreviousRunDate();
    }

    /**
     * @return boolean
     */
    public function isDisabled()
    {
        return $this->status === self::STATUS_DISABLED;
    }

    /**
     * @return boolean
     */
    public function isEnabled()
    {
        return $this->status === self::STATUS_ENABLED;
    }

    /**
     * @return void
     */
    public function enable()
    {
        $this->status = self::STATUS_ENABLED;
    }

    /**
     * @return void
     */
    public function disable()
    {
        $this->status = self::STATUS_DISABLED;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getExpression()
    {
        return $this->expression;
    }

    /**
     * @param string $expression
     */
    public function setExpression($expression)
    {
        /* Slashes in annotaion expressions of dynamic tasks have to be double-escaped due to proxy classes.
        For the cron expression, the remaining backslash needs to be removed here. */
        $this->expression = str_replace('\\', '', $expression);
        $this->initializeNextExecution();
    }

    /**
     * @return string
     */
    public function getImplementation()
    {
        return $this->implementation;
    }

    /**
     * @param string $implementation
     */
    public function setImplementation($implementation)
    {
        $this->implementation = $implementation;
    }

    /**
     * @return array
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * @param array $arguments
     */
    public function setArguments($arguments)
    {
        $this->arguments = $arguments;
        $this->argumentsHash = sha1(serialize($arguments));
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public function execute(ObjectManagerInterface $objectManager)
    {
        /** @var TaskInterface $task */
        $task = $objectManager->get($this->implementation, $this);
        $task->execute($this->arguments);
    }

    /**
     * @return void
     */
    public function initializeNextExecution()
    {
        $this->nextExecution = $this->getCronExpression()->getNextRunDate();
    }

    /**
     * @return \DateTime
     */
    public function getCreationDate()
    {
        return clone $this->creationDate;
    }

    /**
     * @return \DateTime
     */
    public function getLastExecution()
    {
        return $this->lastExecution ? clone $this->lastExecution : null;
    }

    /**
     * @param string
     * @return \DateTime
     */
    public function getNextExecution($currentTime = null)
    {
        if ($currentTime) {
            return $this->getCronExpression()->getNextRunDate($currentTime);
        } else {
            return clone $this->nextExecution;
        }
    }

    /**
     * @return String
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param String $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    public function markAsRun()
    {
        $this->lastExecution = new \DateTime('now');
        $this->initializeNextExecution();
    }
}
