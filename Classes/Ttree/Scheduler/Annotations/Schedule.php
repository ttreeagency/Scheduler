<?php
namespace Ttree\Scheduler\Annotations;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Ttree.Scheduler".       *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3 of the   *
 * License, or (at your option) any later version.                        *
 *                                                                        */

use Ttree\Scheduler\Domain\Model\Task;
use Ttree\Scheduler\Task\TaskInterface;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Aop\JoinPoint;
use TYPO3\Flow\Log\SystemLoggerInterface;
use TYPO3\Flow\Persistence\PersistenceManagerInterface;

/**
 * @Annotation
 * @Target("CLASS")
 */
final class Schedule
{

    /**
     * @var string
     */
    public $expression;

    /**
     * @param array $values
     */
    public function __construct(array $values)
    {
        if (isset($values['expression'])) {
            $this->expression = stripslashes((string)$values['expression']);
        } else {
            $this->expression = '* * * * *';
        }
    }
}
