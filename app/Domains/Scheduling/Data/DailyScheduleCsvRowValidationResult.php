<?php

namespace App\Domains\Scheduling\Data;

use App\Models\DailyScheduleAssignment;
use App\Models\EmploymentRelationship;

class DailyScheduleCsvRowValidationResult
{
    /**
     * @param array<string, mixed>|null $normalizedData
     * @param list<string> $errors
     * @param list<string> $warnings
     */
    public function __construct(
        public readonly int $rowNumber,
        public readonly string $status,
        public readonly array $rawData,
        public readonly ?array $normalizedData = null,
        public readonly array $errors = [],
        public readonly array $warnings = [],
        public readonly ?EmploymentRelationship $relationship = null,
        public readonly ?DailyScheduleAssignment $existingAssignment = null,
        public readonly ?string $rowFingerprint = null,
    ) {
    }
}
