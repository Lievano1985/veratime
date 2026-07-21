<?php

namespace App\Domains\Scheduling\Data;

use App\Models\ImportBatch;

class DailyScheduleCsvApplyResult
{
    public function __construct(
        public readonly ImportBatch $importBatch,
        public readonly int $appliedRows,
        public readonly int $skippedRows,
    ) {
    }
}
