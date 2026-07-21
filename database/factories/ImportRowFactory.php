<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportRow>
 */
class ImportRowFactory extends Factory
{
    protected $model = ImportRow::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'import_batch_id' => ImportBatch::factory(),
            'row_number' => $this->faker->unique()->numberBetween(2, 100),
            'status' => 'invalid',
            'raw_data' => [],
            'normalized_data' => null,
            'errors' => ['Fila demo invalida.'],
            'warnings' => [],
        ];
    }
}
