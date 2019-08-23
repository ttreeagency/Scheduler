<?php
namespace Ttree\Scheduler\Service;

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
use InvalidArgumentException;
use Neos\Flow\Persistence\Exception\IllegalObjectTypeException;
use Neos\Flow\Persistence\Exception\InvalidQueryException;
use Ttree\Scheduler\Domain\Model\Task;
use Ttree\Scheduler\Domain\Repository\TaskRepository;
use Ttree\Scheduler\Task\TaskInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Cache\Frontend\VariableFrontend;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Flow\Reflection\ReflectionService;
use Neos\Flow\Utility\Now;
use Ttree\Scheduler\Annotations;

/**
 * Task Service
 */
class TaskService
{

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
    public static function getAllTaskImplementations(ObjectManagerInterface $objectManager)
    {
        /** @var ReflectionService $reflectionService */
        $reflectionService = $objectManager->get(ReflectionService::class);
        return $reflectionService->getAllImplementationClassNamesForInterface(self::TASK_INTERFACE);
    }

    /**
     * @param ObjectManagerInterface $objectManager
     * @return array
     * @Flow\CompileStatic
     */
    public static function getAllDynamicTaskImplementations(ObjectManagerInterface $objectManager)
    {
        $tasks = [];
        /** @var ReflectionService $reflectionService */
        $reflectionService = $objectManager->get(ReflectionService::class);

        foreach (self::getAllTaskImplementations($objectManager) as $className) {
            if (!$reflectionService->isClassAnnotatedWith($className, Annotations\Schedule::class)) {
                continue;
            }
            /** @var Annotations\Schedule $scheduleAnnotation */
            $scheduleAnnotation = $reflectionService->getClassAnnotation($className, Annotations\Schedule::class);
            $tasks[$className] = [
                'implementation' => $className,
                'expression' => $scheduleAnnotation->expression,
                'description' => ''
            ];

            if($reflectionService->isClassAnnotatedWith($className, Annotations\Meta::class)) {
                /** @var Annotations\Meta $metaAnnotation */
                $metaAnnotation = $reflectionService->getClassAnnotation($className, Annotations\Meta::class);
                $tasks[$className]['description'] = $metaAnnotation->description;
            }
        }

        return $tasks;
    }

    /**
     * @return array
     * @throws AssertionFailedException
     * @throws InvalidQueryException
     */
    public function getDueTasks()
    {
        $tasks = array_merge($this->getDuePersistedTasks(), $this->getDynamicTasks(true));

        return $this->cleanupAndSortTaskList($tasks);
    }

    /**
     * @return array
     * @throws AssertionFailedException
     * @throws InvalidQueryException
     */
    public function getDuePersistedTasks()
    {
        $tasks = [];
        foreach ($this->taskRepository->findDueTasks() as $task) {
            /** @var Task $task */
            $tasks[] = $this->getTaskDescriptor(TaskInterface::TYPE_PERSISTED, $task);
        }
        return $tasks;
    }

    /**
     * @return array
     * @throws AssertionFailedException
     */
    public function getTasks()
    {
        $tasks = array_merge($this->getPersistedTasks(), $this->getDynamicTasks());

        return $this->cleanupAndSortTaskList($tasks);
    }

    /**
     * @return array
     * @throws AssertionFailedException
     */
    public function getPersistedTasks()
    {
        $tasks = [];
        foreach ($this->taskRepository->findAll() as $task) {
            /** @var Task $task */
            $tasks[$this->persistenceManager->getIdentifierByObject($task)] = $this->getTaskDescriptor(TaskInterface::TYPE_PERSISTED, $task);
        }
        return $tasks;
    }

    /**
     * @param boolean
     * @return array
     * @throws AssertionFailedException
     * @throws Exception
     */
    public function getDynamicTasks($dueOnly = false)
    {
        $tasks = [];
        $now = new Now();

        foreach (self::getAllDynamicTaskImplementations($this->objectManager) as $dynamicTask) {
            $task = new Task($dynamicTask['expression'], $dynamicTask['implementation'], [], $dynamicTask['description']);
            $cacheKey = md5($dynamicTask['implementation']);
            $lastExecution = $this->dynamicTaskLastExecutionCache->get($cacheKey);
            if ($dueOnly && ($lastExecution instanceof DateTime && $now < $task->getNextExecution($lastExecution))) {
                continue;
            }
            $task->enable();
            $taskDecriptor = $this->getTaskDescriptor(TaskInterface::TYPE_DYNAMIC, $task);

            $taskDecriptor['lastExecution'] = $lastExecution instanceof DateTime ? $lastExecution->format(DateTime::ISO8601) : '';
            $taskDecriptor['identifier'] = md5($dynamicTask['implementation']);
            $tasks[$taskDecriptor['identifier']] = $taskDecriptor;
        }
        return $tasks;
    }

    /**
     * @param string $type
     * @param Task $task
     * @return array
     * @throws AssertionFailedException
     */
    public function getTaskDescriptor($type, Task $task)
    {
        Assertion::string($type, 'Type must be a string');
        return [
            'type' => $type,
            'enabled' => $task->isEnabled() ? 'On' : 'Off',
            'identifier' => $this->persistenceManager->getIdentifierByObject($task),
            'expression' => $task->getExpression(),
            'implementation' => $task->getImplementation(),
            'nextExecution' => $task->getNextExecution() ? $task->getNextExecution()->format(DateTime::ISO8601) : '',
            'nextExecutionTimestamp' => $task->getNextExecution() ? $task->getNextExecution()->getTimestamp() : 0,
            'lastExecution' => $task->getLastExecution() ? $task->getLastExecution()->format(DateTime::ISO8601) : '',
            'description' => $task->getDescription(),
            'object' => $task
        ];
    }

    /**
     * @param string $expression
     * @param string $task
     * @param array $arguments
     * @param string $description
     * @return Task
     * @throws IllegalObjectTypeException
     * @throws Exception
     */
    public function create($expression, $task, array $arguments, $description)
    {
        $task = new Task($expression, $task, $arguments, $description);
        $this->assertValidTask($task);
        $this->taskRepository->add($task);
        return $task;
    }

    /**
     * @param Task $task
     * @throws IllegalObjectTypeException
     */
    public function remove(Task $task)
    {
        $this->taskRepository->remove($task);
    }

    /**
     * @param Task $task
     * @param string $type
     * @throws IllegalObjectTypeException
     * @throws \Neos\Cache\Exception
     */
    public function update(Task $task, $type)
    {
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
    protected function cleanupAndSortTaskList(array $tasks)
    {
        uasort($tasks, function ($a, $b) {
            return $a['nextExecutionTimestamp'] > $b['nextExecutionTimestamp'];
        });

        array_walk($tasks, function (&$task) {
            unset($task['nextExecutionTimestamp']);
        });

        return $tasks;
    }

    /**
     * @param Task $task
     * @return void
     */
    protected function assertValidTask(Task $task)
    {
        if (!class_exists($task->getImplementation())) {
            throw new InvalidArgumentException(sprintf('Task implementation "%s" must exist', $task->getImplementation()), 1419296545);
        }
        if (!$this->reflexionService->isClassImplementationOf($task->getImplementation(), self::TASK_INTERFACE)) {
            throw new InvalidArgumentException('Task must implement TaskInterface', 1419296485);
        }
    }
}
