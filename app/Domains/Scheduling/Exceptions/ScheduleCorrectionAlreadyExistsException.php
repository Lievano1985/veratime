<?php

namespace App\Domains\Scheduling\Exceptions;

use InvalidArgumentException;

class ScheduleCorrectionAlreadyExistsException extends InvalidArgumentException
{
    public function __construct(public ?int $existingBatchId = null)
    {
        parent::__construct('Ya existe una correccion vinculada a esta publicacion.');
    }
}
