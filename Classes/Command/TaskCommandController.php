<?php
namespace Ttree\Scheduler\Command;

/*                                                                        *
 * This script belongs to the Neos Flow package "Ttree.Scheduler".       *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3 of the   *
 * License, or (at your option) any later version.                        *
 *                                                                        */

use Assert\Assertion;
use Assert\AssertionFailedException;
use DateTime;
use Exception;
use Neos\Flow\Persistence\Exception\IllegalObjectTypeException;
use Neos\Flow\Persistence\Exception\InvalidQueryException;
use Ttree\Scheduler\Domain\Model\Task;
use Ttree\Scheduler\Service\TaskService;
use Ttree\Scheduler\Task\TaskInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Utility\Lock\Lock;
use Neos\Utility\Lock\LockNotAcquiredException;

/**
 * Task Command Controller
 */
class TaskCommandController extends CommandController
{

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
     * @Flow\InjectConfiguration(package="Ttree.Scheduler", path="allowParallelExecution")
     * @var boolean
     */
    protected $allowParallelExecution = true;

    /**
     * @var Lock
     */
    protected $parallelExecutionLock;

    /**
     * Run all pending task
     *
     * @param boolean $dryRun do not execute tasks
     * @throws AssertionFailedException
     * @throws InvalidQueryException
     */
    public function runCommand($dryRun = false)
    {

        if ($this->allowParallelExecution !== true) {
            try {
                $this->parallelExecutionLock = new Lock('Ttree.Scheduler.ParallelExecutionLock');
            } catch (LockNotAcquiredException $exception) {
                $this->tellStatus('The scheduler is already running and parallel execution is disabled.');
                $this->sendAndExit(0);
            }
        }

        foreach ($this->taskService->getDueTasks() as $taskDescriptor) {
            /** @var Task $task */
            $task = $taskDescriptor['object'];
            $arguments = [$task->getImplementation(), $taskDescriptor['identifier']];

            try {
                if (!$dryRun) {
                    $task->execute($this->objectManager);
                    $this->tellStatus('[Success] Run "%s" (%s)', $arguments);
                } else {
                    $this->tellStatus('[Skipped, dry run] Skipped "%s" (%s)', $arguments);
                }
            } catch (Exception $exception) {
                $task->markAsRun();
            }
            $this->taskService->update($task, $taskDescriptor['type']);
        }

        if ($this->parallelExecutionLock instanceof Lock) {
            $this->parallelExecutionLock->release();
        }
    }

    /**
     * List all tasks
     * @throws AssertionFailedException
     */
    public function listCommand()
    {
        $tasks = [];
        foreach ($this->taskService->getTasks() as $task) {
            $taskDescriptor = $task;
            unset($taskDescriptor['object']);
            $tasks[] = $taskDescriptor;
        }
        if (count($tasks)) {
            $this->output->outputTable($tasks, [
                'Type',
                'Status',
                'Identifier',
                'Interval',
                'Implementation',
                'Next Execution Date',
                'Last Execution Date',
                'Description'
            ]);
        } else {
            $this->outputLine('Empty task list ...');
        }
    }

    /**
     * Run a single persisted task ignoring status and schedule.
     *
     * @param string $taskIdentifier
     * @throws AssertionFailedException
     */
    public function runSingleCommand($taskIdentifier)
    {

        $taskDescriptors = $this->taskService->getTasks();
        Assertion::keyExists($taskDescriptors, $taskIdentifier, sprintf('Task with identifier %s does not exist.', $taskIdentifier));

        $taskDescriptor = $taskDescriptors[$taskIdentifier];
        /** @var Task $task */
        $task = $taskDescriptor['object'];
        $arguments = [$task->getImplementation(), $taskDescriptor['identifier']];

        try {
            $taskDescriptor['object']->execute($this->objectManager);
            $this->tellStatus('[Success] Run "%s" (%s)', $arguments);
        } catch (Exception $exception) {
            $task->markAsRun();
        }

        $this->taskService->update($task, $taskDescriptor['type']);
    }

    /**
     * @param Task $task
     * @throws IllegalObjectTypeException
     */
    public function removeCommand(Task $task)
    {
        $this->taskService->remove($task);
    }

    /**
     * Enable the given persistent class
     *
     * @param Task $task persistent task identifier, see task:list
     * @throws IllegalObjectTypeException
     * @throws \Neos\Cache\Exception
     */
    public function enableCommand(Task $task)
    {
        $task->enable();
        $this->taskService->update($task, TaskInterface::TYPE_PERSISTED);
    }

    /**
     * Disable the given persistent class
     *
     * @param Task $task persistent task identifier, see task:list
     * @throws IllegalObjectTypeException
     * @throws \Neos\Cache\Exception
     */
    public function disableCommand(Task $task)
    {
        $task->disable();
        $this->taskService->update($task, TaskInterface::TYPE_PERSISTED);
    }

    /**
     * Register a persistent task
     *
     * @param string $expression cron expression for the task scheduling
     * @param string $task task class implementation
     * @param string $arguments task arguments, can be a valid JSON array
     * @param string $description task description
     * @throws AssertionFailedException
     * @throws IllegalObjectTypeException
     */
    public function registerCommand($expression, $task, $arguments = null, $description = '')
    {
        if ($arguments !== null) {
            $arguments = json_decode($arguments, true);
            Assertion::isArray($arguments, 'Arguments is not a valid JSON array');
        }
        $this->taskService->create($expression, $task, $arguments ?: [], $description);
    }

    /**
     * @param string $message
     * @param array $arguments
     */
    protected function tellStatus($message, array $arguments = null)
    {
        $message = vsprintf($message, $arguments);
        $this->outputLine('%s: %s', [date(DateTime::ISO8601), $message]);
    }

    /**
     * @param Task $task
     * @param string $taskType
     */
    protected function markFailedTaskAsRun(Task $task, $taskType)
    {
        $task->setLastExecution(new \DateTime());
        $task->initializeNextExecution();
        $this->taskService->update($task, $taskType);
    }
}
