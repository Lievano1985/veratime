<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Schedule>
 */
class ScheduleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'code' => strtoupper(fake()->unique()->bothify('HOR-###')),
            'name' => fake()->words(2, true),
            'legal_type' => 'diurnal',
            'timezone' => 'America/Mexico_City',
            'status' => 'active',
            'effective_from' => null,
            'effective_to' => null,
            'metadata' => [],
        ];
    }
}
