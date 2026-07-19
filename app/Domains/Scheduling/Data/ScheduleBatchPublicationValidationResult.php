<?php

namespace App\Domains\Scheduling\Data;

use App\Models\ScheduleBatch;

class ScheduleBatchPublicationValidationResult
{
    /**
     * @param list<string> $errors
     * @param list<string> $warnings
     */
    public function __construct(
        public ScheduleBatch $scheduleBatch,
        public array $errors = [],
        public array $warnings = [],
        public int $relationshipsExpected = 0,
        public int $datesExpected = 0,
        public int $assignmentsExpected = 0,
        public int $assignmentsFound = 0,
        public int $assignmentsMissing = 0,
        public int $assignmentsUnassigned = 0,
        public int $assignmentsShift = 0,
        public int $assignmentsRest = 0,
        public int $assignmentsFlexible = 0,
        public int $assignmentsOnCall = 0,
        public int $conflictingAssignments = 0,
        public bool $snapshotReady = false,
    ) {
    }

    public function valid(): bool
    {
        return $this->errors === [];
    }

    public function addError(string $message): void
    {
        if (! in_array($message, $this->errors, true)) {
            $this->errors[] = $message;
        }
    }

    public function addWarning(string $message): void
    {
        if (! in_array($message, $this->warnings, true)) {
            $this->warnings[] = $message;
        }
    }

    public function countDayType(string $dayType): void
    {
        match ($dayType) {
            'shift' => $this->assignmentsShift++,
            'rest' => $this->assignmentsRest++,
            'flexible' => $this->assignmentsFlexible++,
            'on_call' => $this->assignmentsOnCall++,
            default => null,
        };
    }

    /**
     * @return array<string, int|string|null|bool|list<string>>
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->valid(),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'schedule_batch_id' => $this->scheduleBatch->id,
            'relationships_expected' => $this->relationshipsExpected,
            'dates_expected' => $this->datesExpected,
            'assignments_expected' => $this->assignmentsExpected,
            'assignments_found' => $this->assignmentsFound,
            'assignments_missing' => $this->assignmentsMissing,
            'assignments_unassigned' => $this->assignmentsUnassigned,
            'assignments_shift' => $this->assignmentsShift,
            'assignments_rest' => $this->assignmentsRest,
            'assignments_flexible' => $this->assignmentsFlexible,
            'assignments_on_call' => $this->assignmentsOnCall,
            'conflicting_assignments' => $this->conflictingAssignments,
            'snapshot_ready' => $this->snapshotReady,
        ];
    }
}
