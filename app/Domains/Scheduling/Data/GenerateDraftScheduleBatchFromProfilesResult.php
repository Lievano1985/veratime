<?php

namespace App\Domains\Scheduling\Data;

use App\Models\ScheduleBatch;

class GenerateDraftScheduleBatchFromProfilesResult
{
    /**
     * @param list<string> $warnings
     */
    public function __construct(
        public ScheduleBatch $scheduleBatch,
        public string $regenerationMode,
        public int $relationshipsConsidered = 0,
        public int $datesConsidered = 0,
        public int $assignmentsCreated = 0,
        public int $assignmentsRefreshed = 0,
        public int $assignmentsPreserved = 0,
        public int $assignmentsUnassigned = 0,
        public int $assignmentsShift = 0,
        public int $assignmentsRest = 0,
        public int $assignmentsFlexible = 0,
        public int $assignmentsOnCall = 0,
        public int $relationshipsSkipped = 0,
        public array $warnings = [],
    ) {
    }

    public function countDayType(string $dayType): void
    {
        match ($dayType) {
            'shift' => $this->assignmentsShift++,
            'rest' => $this->assignmentsRest++,
            'flexible' => $this->assignmentsFlexible++,
            'on_call' => $this->assignmentsOnCall++,
            'unassigned' => $this->assignmentsUnassigned++,
            default => null,
        };
    }
}
