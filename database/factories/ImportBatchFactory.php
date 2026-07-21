<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\ImportBatch;
use App\Models\ScheduleBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportBatch>
 */
class ImportBatchFactory extends Factory
{
    protected $model = ImportBatch::class;

    public function definition(): array
    {
        $company = Company::factory();
        $batch = ScheduleBatch::factory()->for($company);

        return [
            'company_id' => $company,
            'import_type' => 'daily_schedule',
            'target_type' => 'schedule_batch',
            'target_id' => $batch,
            'status' => 'uploaded',
            'existing_assignment_policy' => 'replace_existing',
            'original_filename' => 'programacion.csv',
            'storage_disk' => 'local',
            'storage_path' => 'imports/demo/programacion.csv',
            'file_sha256' => str_repeat('a', 64),
            'file_size_bytes' => 128,
            'encoding' => 'UTF-8',
            'delimiter' => ',',
            'header_schema_version' => 1,
            'reason' => 'Importacion demo.',
            'metadata' => [],
        ];
    }
}
