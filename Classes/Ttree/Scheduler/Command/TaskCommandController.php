<?php
namespace Ttree\Scheduler\Command;
/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Ttree.Scheduler".       *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3 of the   *
 * License, or (at your option) any later version.                        *
 *                                                                        */

use Assert\Assertion;
use Ttree\Scheduler\Domain\Model\Task;
use Ttree\Scheduler\Service\TaskService;
use Ttree\Scheduler\Task\TaskInterface;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Cli\CommandController;
use TYPO3\Flow\Object\ObjectManagerInterface;

/**
 * Task Command Controller
 */
class TaskCommandController extends CommandController {

	/**
	 * @Flow\Inject
	 * @var TaskService
	 */
	protected $taskService;

	/**
	 * @Flow\Inject(lazy=false)
	 * @var ObjectManagerInterface
	 */
	protected $objectManager;

	/**
	 * Run all pending task
	 *
	 * @param boolean $dryRun do not execute tasks
	 */
	public function runCommand($dryRun = FALSE) {
		foreach ($this->taskService->getDueTasks() as $taskDescriptor) {
			/** @var Task $task */
			$task = $taskDescriptor['object'];
			$arguments = array($task->getImplementation(), $taskDescriptor['identifier'] ?: $taskDescriptor['type']);
			try {
				if (!$dryRun) {
					$task->execute($this->objectManager);
					$this->taskService->update($task, $taskDescriptor['type']);
					$this->tellStatus('[Success] Run "%s" (%s)', $arguments);
				} else {
					$this->tellStatus('[Skipped, dry run] Skipped "%s" (%s)', $arguments);
				}
			} catch (\Exception $exception) {
				$this->tellStatus('[Error] Task "%s" (%s) throw an exception, check your log', $arguments);
			}
		}
	}

	/**
	 * List all tasks
	 */
	public function listCommand() {
		$tasks = array();
		foreach ($this->taskService->getTasks() as $task) {
			$taskDescriptor = $task;
			unset($taskDescriptor['object']);
			$tasks[] = $taskDescriptor;
		}
		if (count($tasks)) {
			$this->output->outputTable($tasks, array(
				'Type',
				'Status',
				'Identifier',
				'Interval',
				'Implementation',
				'Next Execution Date',
				'Last Execution Date',
			));
		} else {
			$this->outputLine('Empty task list ...');
		}
	}

	/**
	 * @param Task $task
	 */
	public function removeCommand(Task $task)
	{
		$this->taskService->remove($task);
	}

    /**
     * Run a single persisted task ignoring status and schedule.
     *
     * @param Task $task
     */
    public function runSingleCommand(Task $task) {
        $taskDescriptor = $this->taskService->getTaskDescriptor(TaskInterface::TYPE_PERSISTED, $task);
        $arguments = [$task->getImplementation(), $taskDescriptor['identifier'] ?: $taskDescriptor['type']];

        try {
            $taskDescriptor['object']->execute($this->objectManager);
            $this->taskService->update($task, $taskDescriptor['type']);
            $this->tellStatus('[Success] Run "%s" (%s)', $arguments);
        } catch (\Exception $exception) {
            $this->tellStatus('[Error] Task "%s" (%s) throw an exception, check your log', $arguments);
        }
    }

	/**
	 * Enable the given persistent class
	 *
	 * @param Task $task persistent task identifier, see task:list
	 */
	public function enableCommand(Task $task) {
		$task->enable();
		$this->taskService->update($task, TaskInterface::TYPE_PERSISTED);
	}

	/**
	 * Disable the given persistent class
	 *
	 * @param Task $task persistent task identifier, see task:list
	 */
	public function disableCommand(Task $task) {
		$task->disable();
		$this->taskService->update($task, TaskInterface::TYPE_PERSISTED);
	}

	/**
	 * Register a persistent task
	 *
	 * @param string $expression cron expression for the task scheduling
	 * @param string $task task class implementation
	 * @param string $arguments task arguments, can be a valid JSON array
	 */
	public function registerCommand($expression, $task, $arguments = NULL) {
		if ($arguments !== NULL) {
			$arguments = json_decode($arguments, TRUE);
			Assertion::isArray($arguments, 'Arguments is not a valid JSON array');
		}
		$this->taskService->create($expression, $task, $arguments ?: array());
	}

	/**
	 * @param string $message
	 * @param array $arguments
	 */
	protected function tellStatus($message, array $arguments = NULL) {
		$message = vsprintf($message, $arguments);
		$this->outputLine('%s: %s', array(date(\DateTime::ISO8601), $message));
	}

}
