<?php
namespace Ttree\Scheduler\Annotations;

/*                                                                        *
 * This script belongs to the Neos Flow package "Ttree.Scheduler".       *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3 of the   *
 * License, or (at your option) any later version.                        *
 *                                                                        */

use Neos\Flow\Annotations as Flow;

/**
 * @Annotation
 * @Target("CLASS")
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Meta
{

    /**
     * @var string
     */
    public $description = '';

    /**
     * @param array $values
     */
    public function __construct(array $values)
    {
        if (isset($values['description'])) {
            $this->description = (string)$values['description'];
        }
    }
}