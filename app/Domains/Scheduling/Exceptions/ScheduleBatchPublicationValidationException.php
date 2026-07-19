<?php

namespace App\Domains\Scheduling\Exceptions;

use App\Domains\Scheduling\Data\ScheduleBatchPublicationValidationResult;
use InvalidArgumentException;

class ScheduleBatchPublicationValidationException extends InvalidArgumentException
{
    public function __construct(public ScheduleBatchPublicationValidationResult $result)
    {
        parent::__construct($result->errors[0] ?? 'El lote de programacion diaria no puede publicarse.');
    }
}
