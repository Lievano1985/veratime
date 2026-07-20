<?php

namespace App\Domains\Scheduling\Data;

use App\Models\ScheduleBatch;

class CorrectiveScheduleBatchPublicationValidationResult
{
    /**
     * @param list<string> $errors
     * @param list<string> $warnings
     * @param array<string, int> $countsByDayType
     */
    public function __construct(
        public ScheduleBatch $correctiveBatch,
        public ?ScheduleBatch $previousBatch = null,
        public array $errors = [],
        public array $warnings = [],
        public int $assignmentsExpected = 0,
        public int $assignmentsFound = 0,
        public int $assignmentsMissing = 0,
        public int $assignmentsAdded = 0,
        public int $assignmentsUnassigned = 0,
        public int $changedDays = 0,
        public int $unchangedDays = 0,
        public array $countsByDayType = [],
        public int $conflictingBatches = 0,
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

    public function toArray(): array
    {
        return [
            'valid' => $this->valid(),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'previous_batch_id' => $this->previousBatch?->id,
            'corrective_batch_id' => $this->correctiveBatch->id,
            'version' => $this->correctiveBatch->version,
            'correction_reason' => $this->correctiveBatch->correction_reason,
            'assignments_expected' => $this->assignmentsExpected,
            'assignments_found' => $this->assignmentsFound,
            'assignments_missing' => $this->assignmentsMissing,
            'assignments_added' => $this->assignmentsAdded,
            'assignments_unassigned' => $this->assignmentsUnassigned,
            'changed_days' => $this->changedDays,
            'unchanged_days' => $this->unchangedDays,
            'counts_by_day_type' => $this->countsByDayType,
            'conflicting_batches' => $this->conflictingBatches,
            'snapshot_ready' => $this->snapshotReady,
        ];
    }
}
