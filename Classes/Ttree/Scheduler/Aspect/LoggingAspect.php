<?php
namespace Ttree\Scheduler\Aspect;
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
 * Task Execution Logger
 *
 * @Flow\Aspect
 * @Flow\Scope("singleton")
 */
class LoggingAspect {

	/**
	 * @Flow\Inject
	 * @var SystemLoggerInterface
	 */
	protected $systemLogger;

	/**
	 * @Flow\Inject
	 * @var PersistenceManagerInterface
	 */
	protected $persistenceManager;

	/**
	 * @Flow\PointCut("within(Ttree\Scheduler\Task\TaskInterface) && method(.*->execute())")
	 */
	public function allTasks() {}

	/**
	 * @Flow\Before("Ttree\Scheduler\Aspect\LoggingAspect->allTasks")
	 * @param JoinPoint $jointPoint
	 */
	public function logTaskExecutionBegin(JoinPoint $jointPoint) {
		/** @var TaskInterface $task */
		$task = $jointPoint->getProxy();
		$this->systemLogger->log(sprintf('Task "%s" execution started', get_class($task)));
	}

	/**
	 * @Flow\After("Ttree\Scheduler\Aspect\LoggingAspect->allTasks")
	 * @param JoinPoint $jointPoint
	 */
	public function logTaskExecutionEnd(JoinPoint $jointPoint) {
		/** @var Task $task */
		$task = $jointPoint->getProxy();
		$this->systemLogger->log(sprintf('Task "%s" execution finished', get_class($task)));
	}

	/**
	 * @Flow\AfterThrowing("Ttree\Scheduler\Aspect\LoggingAspect->allTasks")
	 * @param JoinPoint $jointPoint
	 * @throws \Exception
	 */
	public function logTaskException(JoinPoint $jointPoint) {
		/** @var Task $task */
		$exception = $jointPoint->getException();
		$this->systemLogger->logException($exception, array(
			'task' => $jointPoint->getClassName()
		));
	}

}