<?php
namespace Ttree\Scheduler\Task;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Ttree.Scheduler".       *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3 of the   *
 * License, or (at your option) any later version.                        *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;

/**
 * Schedule Task
 */
interface TaskInterface
{

    const TYPE_PERSISTED = 'Persisted';
    const TYPE_DYNAMIC = 'Dynamic';

    /**
     * @param array
     * @return void
     */
    public function execute(array $arguments = []);
}
