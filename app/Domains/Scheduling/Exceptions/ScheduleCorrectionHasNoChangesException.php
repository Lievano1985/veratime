<?php

namespace App\Domains\Scheduling\Exceptions;

use InvalidArgumentException;

class ScheduleCorrectionHasNoChangesException extends InvalidArgumentException
{
    public function __construct()
    {
        parent::__construct('La correccion no contiene cambios respecto de la version publicada.');
    }
}
