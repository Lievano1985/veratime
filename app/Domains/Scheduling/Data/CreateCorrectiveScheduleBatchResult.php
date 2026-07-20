<?php

namespace App\Domains\Scheduling\Data;

use App\Models\ScheduleBatch;

class CreateCorrectiveScheduleBatchResult
{
    public function __construct(
        public ScheduleBatch $previousBatch,
        public ScheduleBatch $correctiveBatch,
        public int $assignmentsCloned,
        public int $segmentsCloned,
    ) {
    }
}
