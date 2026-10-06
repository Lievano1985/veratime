<?php

namespace App\Domains\TimeRecords\Exceptions;

use InvalidArgumentException;

class PersonalTimeEventSecurityException extends InvalidArgumentException
{
    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly bool $retryable = false,
    ) {
        parent::__construct($message);
    }
}
