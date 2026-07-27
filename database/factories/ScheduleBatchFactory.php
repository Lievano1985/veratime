<?php

namespace Database\Factories;

use App\Models\Center;
use App\Models\Company;
use App\Models\ScheduleBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduleBatch>
 */
class ScheduleBatchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'center_id' => Center::factory(),
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-15',
            'version' => null,
            'status' => 'draft',
            'creation_source' => 'manual',
            'notes' => null,
            'correction_reason' => null,
            'snapshot_schema_version' => null,
            'snapshot_canonical_json' => null,
            'snapshot_sha256' => null,
        ];
    }
}
