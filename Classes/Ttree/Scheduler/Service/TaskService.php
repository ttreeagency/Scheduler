<?php
namespace Ttree\Scheduler\Service;
/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Ttree.Scheduler".       *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3 of the   *
 * License, or (at your option) any later version.                        *
 *                                                                        */

use Assert\Assertion;
use Ttree\Scheduler\Annotations\Schedule;
use Ttree\Scheduler\Domain\Model\Task;
use Ttree\Scheduler\Domain\Repository\TaskRepository;
use Ttree\Scheduler\Task\TaskInterface;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Cache\Frontend\VariableFrontend;
use TYPO3\Flow\Object\ObjectManagerInterface;
use TYPO3\Flow\Persistence\PersistenceManagerInterface;
use TYPO3\Flow\Reflection\ReflectionService;
use TYPO3\Flow\Utility\Now;

/**
 * Task Service
 */
class TaskService {

	const TASK_INTERFACE = 'Ttree\Scheduler\Task\TaskInterface';

	/**
	 * @Flow\Inject
	 * @var VariableFrontend
	 */
	protected $dynamicTaskLastExecutionCache;

	/**
	 * @Flow\Inject
	 * @var TaskRepository
	 */
	protected $taskRepository;

	/**
	 * @Flow\Inject
	 * @var PersistenceManagerInterface
	 */
	protected $persistenceManager;

	/**
	 * @Flow\Inject
	 * @var ReflectionService
	 */
	protected $reflexionService;

	/**
	 * @Flow\Inject
	 * @var ObjectManagerInterface
	 */
	protected $objectManager;

	/**
	 * @param ObjectManagerInterface $objectManager
	 * @return array
	 * @Flow\CompileStatic
	 */
	static public function getAllTaskImplementations(ObjectManagerInterface $objectManager) {
		/** @var ReflectionService $reflectionService */
		$reflectionService = $objectManager->get('TYPO3\Flow\Reflection\ReflectionService');
		return $reflectionService->getAllImplementationClassNamesForInterface(self::TASK_INTERFACE);
	}

	/**
	 * @param ObjectManagerInterface $objectManager
	 * @return array
	 * @Flow\CompileStatic
	 */
	static public function getAllDynamicTaskImplementations(ObjectManagerInterface $objectManager) {
		$tasks = array();
		/** @var ReflectionService $reflectionService */
		$reflectionService = $objectManager->get('TYPO3\Flow\Reflection\ReflectionService');
		foreach (self::getAllTaskImplementations($objectManager) as $className) {
			if (!$reflectionService->isClassAnnotatedWith($className, 'Ttree\Scheduler\Annotations\Schedule')) {
				continue;
			}
			/** @var Schedule $scheduleAnnotation */
			$scheduleAnnotation = $reflectionService->getClassAnnotation($className, 'Ttree\Scheduler\Annotations\Schedule');
			$tasks[] = array(
				'implementation' => $className,
				'expression' => $scheduleAnnotation->expression
			);
		}

		return $tasks;
	}

	/**
	 * @return array
	 */
	public function getDueTasks() {
		$tasks = array_merge($this->getDuePersistedTasks(), $this->getDynamicTasks(TRUE));

		return $this->cleanupAndSortTaskList($tasks);
	}

	/**
	 * @return array
	 */
	public function getDuePersistedTasks() {
		$tasks = array();
		foreach ($this->taskRepository->findDueTasks() as $task) {
			/** @var Task $task */
			$tasks[] = $this->getTaskDescriptor(TaskInterface::TYPE_PERSISTED, $task);
		}
		return $tasks;
	}

	/**
	 * @return array
	 */
	public function getTasks() {
		$tasks = array_merge($this->getPersistedTasks(), $this->getDynamicTasks());

		return $this->cleanupAndSortTaskList($tasks);
	}

	/**
	 * @return array
	 */
	public function getPersistedTasks() {
		$tasks = array();
		foreach ($this->taskRepository->findAll() as $task) {
			/** @var Task $task */
			$tasks[] = $this->getTaskDescriptor(TaskInterface::TYPE_PERSISTED, $task);
		}
		return $tasks;
	}

	/**
	 * @param boolean
	 * @return array
	 */
	public function getDynamicTasks($dueOnly = FALSE) {
		$tasks = array();
		$now = new Now();

		foreach (self::getAllDynamicTaskImplementations($this->objectManager) as $dynamicTask) {
			$task = new Task($dynamicTask['expression'], $dynamicTask['implementation']);
			$cacheKey = md5($dynamicTask['implementation']);
			$lastExecution = $this->dynamicTaskLastExecutionCache->get($cacheKey);
			if ($dueOnly && ($lastExecution instanceof \DateTime && $now < $task->getNextExecution($lastExecution))) {
				continue;
			}
			$task->enable();
			$taskDecriptor = $this->getTaskDescriptor(TaskInterface::TYPE_DYNAMIC, $task);

			$taskDecriptor['lastExecution'] = $lastExecution instanceof \DateTime ? $lastExecution->format(\DateTime::ISO8601) : '';
			$taskDecriptor['identifier'] = '';
			$tasks[] = $taskDecriptor;
		}
		return $tasks;
	}

	/**
	 * @param string $type
	 * @param Task $task
	 * @return array
	 */
	public function getTaskDescriptor($type, Task $task) {
		Assertion::string($type, 'Type must be a string');
		return array(
			'type' => $type,
			'enabled' => $task->isEnabled() ? 'On' : 'Off',
			'identifier' => $this->persistenceManager->getIdentifierByObject($task),
			'expression' => $task->getExpression(),
			'implementation' => $task->getImplementation(),
			'nextExecution' => $task->getNextExecution() ? $task->getNextExecution()->format(\DateTime::ISO8601) : '',
			'nextExecutionTimestamp' => $task->getNextExecution() ? $task->getNextExecution()->getTimestamp() : 0,
			'lastExecution' => $task->getLastExecution() ? $task->getLastExecution()->format(\DateTime::ISO8601) : '',
			'object' => $task
		);
	}

	/**
	 * @param string $expression
	 * @param string $task
	 * @param array $arguments
	 * @return Task
	 */
	public function create($expression, $task, array $arguments) {
		$task = new Task($expression, $task, $arguments);
		$this->assertValidTask($task);
		$this->taskRepository->add($task);
		return $task;
	}

	/**
	 * @param Task $task
	 */
	public function remove(Task $task)
	{
		$this->taskRepository->remove($task);
	}

	/**
	 * @param Task $task
	 * @param string $type
	 */
	public function update(Task $task, $type) {
		switch ($type) {
			case TaskInterface::TYPE_DYNAMIC:
				$cacheKey = md5($task->getImplementation());
				$this->dynamicTaskLastExecutionCache->set($cacheKey, $task->getLastExecution());
				break;
			case TaskInterface::TYPE_PERSISTED:
				$this->taskRepository->update($task);
				break;
		}
	}

	/**
	 * @param array $tasks
	 * @return array
	 */
	protected function cleanupAndSortTaskList(array $tasks) {
		usort($tasks, function($a, $b) {
			return $a['nextExecutionTimestamp'] > $b['nextExecutionTimestamp'];
		});

		array_walk($tasks, function(&$task) {
			unset($task['nextExecutionTimestamp']);
		});

		return $tasks;
	}

	/**
	 * @param Task $task
	 * @return boolean
	 */
	protected function assertValidTask(Task $task) {
		if (!class_exists($task->getImplementation())) {
			throw new \InvalidArgumentException(sprintf('Task implementation "%s" must exist', $task->getImplementation()), 1419296545);
		}
		if (!$this->reflexionService->isClassImplementationOf($task->getImplementation(), self::TASK_INTERFACE)) {
			throw new \InvalidArgumentException('Task must implement TaskInterface', 1419296485);
		}
	}

}
