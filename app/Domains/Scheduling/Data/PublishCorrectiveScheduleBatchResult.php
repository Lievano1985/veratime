<?php

namespace App\Domains\Scheduling\Data;

use App\Models\ScheduleBatch;
use Carbon\CarbonInterface;

class PublishCorrectiveScheduleBatchResult
{
    public function __construct(
        public ScheduleBatch $previousBatch,
        public ScheduleBatch $correctiveBatch,
        public CarbonInterface $publishedAt,
        public string $snapshotSchemaVersion,
        public string $snapshotSha256,
        public int $changedDays,
        public int $assignmentCount,
        public int $segmentCount,
    ) {
    }
}
