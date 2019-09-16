<?php
namespace Ttree\Scheduler\Aspect;

/*                                                                        *
 * This script belongs to the Neos Flow package "Ttree.Scheduler".       *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3 of the   *
 * License, or (at your option) any later version.                        *
 *                                                                        */

use Neos\Flow\Log\ThrowableStorageInterface;
use Psr\Log\LoggerInterface;
use Ttree\Scheduler\Domain\Model\Task;
use Ttree\Scheduler\Task\TaskInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPoint;
use Neos\Flow\Persistence\PersistenceManagerInterface;

/**
 * Task Execution Logger
 *
 * @Flow\Aspect
 * @Flow\Scope("singleton")
 */
class LoggingAspect
{

    /**
     * @Flow\Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var ThrowableStorageInterface
     * @Flow\Inject
     */
    protected $throwableStorage;

    /**
     * @Flow\Inject
     * @var PersistenceManagerInterface
     */
    protected $persistenceManager;

    /**
     * @Flow\Pointcut("within(Ttree\Scheduler\Task\TaskInterface) && method(.*->execute())")
     */
    public function allTasks()
    {
    }

    /**
     * @Flow\Before("Ttree\Scheduler\Aspect\LoggingAspect->allTasks")
     * @param JoinPoint $jointPoint
     */
    public function logTaskExecutionBegin(JoinPoint $jointPoint)
    {
        /** @var TaskInterface $task */
        $task = $jointPoint->getProxy();
        $this->logger->info(sprintf('Task "%s" execution started', get_class($task)));
    }

    /**
     * @Flow\After("Ttree\Scheduler\Aspect\LoggingAspect->allTasks")
     * @param JoinPoint $jointPoint
     */
    public function logTaskExecutionEnd(JoinPoint $jointPoint)
    {
        /** @var Task $task */
        $task = $jointPoint->getProxy();
        $this->logger->info(sprintf('Task "%s" execution finished', get_class($task)));
    }

    /**
     * @Flow\AfterThrowing("Ttree\Scheduler\Aspect\LoggingAspect->allTasks")
     * @param JoinPoint $jointPoint
     * @throws \Exception
     */
    public function logTaskException(JoinPoint $jointPoint)
    {
        $message = $this->throwableStorage->logThrowable($jointPoint->getException());
        $this->logger->error($message, [
            'task' => $jointPoint->getClassName()
        ]);
    }
}
