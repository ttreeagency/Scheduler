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
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Flow\Utility\Environment;
use Neos\Utility\Files;
use Neos\Utility\Lock\LockManager;
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
     * @var PersistenceManagerInterface
     * @Flow\Inject
     */
    protected $persistenceManager;

    /**
     * @var Environment
     * @Flow\Inject
     */
    protected $environment;

    /**
     * @Flow\InjectConfiguration(package="Ttree.Scheduler", path="allowParallelExecution")
     * @var boolean
     */
    protected $allowParallelExecution = true;

    /**
     * @Flow\InjectConfiguration(package="Ttree.Scheduler", path="lockStrategyClassName")
     * @var boolean
     */
    protected $lockStrategyClassName = '';

    /**
     * @var Lock
     */
    protected $parallelExecutionLock;

    /**
     * @throws \Neos\Flow\Utility\Exception
     * @throws \Neos\Utility\Exception\FilesException
     */
    public function initializeObject(): void
    {
        $lockManager = new LockManager($this->lockStrategyClassName, ['lockDirectory' => Files::concatenatePaths([
            $this->environment->getPathToTemporaryDirectory(),
            'Lock'
        ])]);
        Lock::setLockManager($lockManager);
    }

    /**
     * Run all pending task
     *
     * @param boolean $dryRun do not execute tasks
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

            $this->markTaskAsRun($task, $taskDescriptor['type']);
            try {
                if (!$dryRun) {
                    $task->execute($this->objectManager);
                    $this->tellStatus('[Success] Run "%s" (%s)', $arguments);
                } else {
                    $this->tellStatus('[Skipped, dry run] Skipped "%s" (%s)', $arguments);
                }
            } catch (\Exception $exception) {
                $this->tellStatus('[Error] Task "%s" (%s) throw an exception, check your log', $arguments);
            }
        }

        if ($this->parallelExecutionLock instanceof Lock) {
            $this->parallelExecutionLock->release();
        }
    }

    /**
     * List all tasks
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
     */
    public function runSingleCommand($taskIdentifier)
    {

        $taskDescriptors = $this->taskService->getTasks();
        Assertion::keyExists($taskDescriptors, $taskIdentifier, sprintf('Task with identifier %s does not exist.', $taskIdentifier));

        $taskDescriptor = $taskDescriptors[$taskIdentifier];
        /** @var Task $task */
        $task = $taskDescriptor['object'];
        $arguments = [$task->getImplementation(), $taskDescriptor['identifier']];

        $this->markTaskAsRun($task, $taskDescriptor['type']);
        try {
            $taskDescriptor['object']->execute($this->objectManager);
            $this->tellStatus('[Success] Run "%s" (%s)', $arguments);
        } catch (\Exception $exception) {
            $this->tellStatus('[Error] Task "%s" (%s) throw an exception, check your log', $arguments);
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
     * Enable the given persistent class
     *
     * @param Task $task persistent task identifier, see task:list
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
        $this->outputLine('%s: %s', [date(\DateTime::ISO8601), $message]);
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

    /**
     * @param Task $task
     * @param bool $taskType
     */
    protected function markTaskAsRun(Task $task, $taskType)
    {
        $task->markAsRun();
        $this->taskService->update($task, $taskType);
        if ($taskType === TaskInterface::TYPE_PERSISTED) {
            $this->persistenceManager->whitelistObject($task);
            $this->persistenceManager->persistAll(true);
        }
    }
}
