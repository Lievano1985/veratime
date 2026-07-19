<?php

namespace App\Domains\Scheduling\Data;

use App\Models\ScheduleBatch;
use Carbon\CarbonInterface;

class PublishScheduleBatchResult
{
    /**
     * @param array<string, int> $countsByDayType
     * @param list<string> $warnings
     */
    public function __construct(
        public ScheduleBatch $scheduleBatch,
        public CarbonInterface $publishedAt,
        public int $publishedBy,
        public string $snapshotSchemaVersion,
        public string $snapshotSha256,
        public int $assignmentCount,
        public int $segmentCount,
        public int $relationshipCount,
        public int $workDateCount,
        public array $countsByDayType,
        public array $warnings = [],
    ) {
    }
}
