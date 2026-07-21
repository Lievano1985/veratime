<?php

namespace App\Domains\Scheduling\Data;

class DailyScheduleCsvImportSummary
{
    public function __construct(
        public readonly int $totalRows,
        public readonly int $validRows,
        public readonly int $invalidRows,
        public readonly int $warningRows,
        public readonly int $appliedRows = 0,
        public readonly int $skippedRows = 0,
    ) {
    }
}
