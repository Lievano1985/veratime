<?php

namespace App\Domains\TimeRecords\Exceptions;

use InvalidArgumentException;

final class AttendanceClockImportException extends InvalidArgumentException
{
    public function __construct(
        string $reason,
        public readonly ?int $row = null,
        public readonly ?string $clockUserId = null,
        public readonly ?string $state = null,
    ) {
        parent::__construct($reason);
    }
}
