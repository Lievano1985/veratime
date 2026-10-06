<?php

namespace App\Domains\TimeRecords\Exceptions;

use InvalidArgumentException;

class PersonalTimeEventConflictException extends InvalidArgumentException
{
    public function __construct()
    {
        parent::__construct('El identificador del marcaje ya fue usado con contenido distinto. Conserva el registro local y solicita revisión.');
    }
}
