<?php
namespace Ttree\Scheduler\Domain\Repository;
/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Ttree.Scheduler".       *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3 of the   *
 * License, or (at your option) any later version.                        *
 *                                                                        */

use Ttree\Scheduler\Domain\Model\Task;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Persistence\QueryInterface;
use TYPO3\Flow\Persistence\Repository;
use TYPO3\Flow\Utility\Now;

/**
 * Schedule Task
 *
 * @Flow\Scope("singleton")
 */
class TaskRepository extends Repository {

	/**
	 * @var array
	 */
	protected $defaultOrderings = array(
		'status' => QueryInterface::ORDER_ASCENDING,
		'nextExecution' => QueryInterface::ORDER_ASCENDING
	);

	/**
	 * @param string $identifier
	 * @return Task
	 */
	public function findByIdentifier($identifier) {
		return parent::findByIdentifier($identifier);
	}

	/**
	 * @return \TYPO3\Flow\Persistence\QueryResultInterface
	 */
	public function findDueTasks() {
		$query = $this->createQuery();

		$now = new Now();

		$query->matching($query->logicalAnd(
			$query->equals('status', Task::STATUS_ENABLED),
			$query->lessThanOrEqual('nextExecution', $now)
		));

		return $query->execute();
	}

	/**
	 * @param boolean $showDisabled
	 * @return \TYPO3\Flow\Persistence\QueryResultInterface
	 */
	public function findAllTasks($showDisabled = FALSE) {
		$query = $this->createQuery();

		if (!$showDisabled) {
			$query->matching($query->equals('status', Task::STATUS_ENABLED));
		}

		return $query->execute();
	}

	/**
	 * @param string $implementation
	 * @param array $arguments
	 * @return Task
	 */
	public function findOneByImplementationAndArguments($implementation, array $arguments) {
		$argumentsHash = sha1(serialize($arguments));
		$query = $this->createQuery();

		$query->matching($query->logicalAnd(
			$query->equals('implementation', $implementation),
			$query->equals('argumentsHash', $argumentsHash)
		));

		return $query->execute()->getFirst();
	}

}