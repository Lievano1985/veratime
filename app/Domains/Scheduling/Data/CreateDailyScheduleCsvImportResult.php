<?php

namespace App\Domains\Scheduling\Data;

use App\Models\ImportBatch;

class CreateDailyScheduleCsvImportResult
{
    public function __construct(
        public readonly ImportBatch $importBatch,
        public readonly bool $wasExisting = false,
    ) {
    }
}
