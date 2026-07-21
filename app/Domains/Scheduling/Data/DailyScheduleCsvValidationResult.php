<?php

namespace App\Domains\Scheduling\Data;

use App\Models\ImportBatch;

class DailyScheduleCsvValidationResult
{
    public function __construct(
        public readonly ImportBatch $importBatch,
    ) {
    }
}
