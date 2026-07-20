<?php

namespace App\Domains\Scheduling\Data;

use App\Models\ScheduleBatch;

class ScheduleBatchVersionComparisonResult
{
    /**
     * @param array<string, int> $changesByDayType
     * @param list<int> $changedRelationships
     * @param list<array<string, mixed>> $differences
     */
    public function __construct(
        public ScheduleBatch $previousBatch,
        public ScheduleBatch $correctedBatch,
        public int $totalDays,
        public int $unchangedDays,
        public int $changedDays,
        public int $addedDays,
        public int $removedDays,
        public array $changesByDayType,
        public array $changedRelationships,
        public array $differences,
    ) {
    }

    public function toArray(): array
    {
        return [
            'previous_batch_id' => $this->previousBatch->id,
            'corrected_batch_id' => $this->correctedBatch->id,
            'total_days' => $this->totalDays,
            'unchanged_days' => $this->unchangedDays,
            'changed_days' => $this->changedDays,
            'added_days' => $this->addedDays,
            'removed_days' => $this->removedDays,
            'changes_by_day_type' => $this->changesByDayType,
            'changed_relationships' => $this->changedRelationships,
            'differences' => $this->differences,
        ];
    }
}
