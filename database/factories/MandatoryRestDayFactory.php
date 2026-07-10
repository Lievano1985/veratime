<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\MandatoryRestDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MandatoryRestDay>
 */
class MandatoryRestDayFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'center_id' => null,
            'name' => 'Descanso obligatorio',
            'date' => now()->toDateString(),
            'scope' => 'company',
            'source' => 'manual',
            'status' => 'active',
            'metadata' => [],
        ];
    }

    public function global(): static
    {
        return $this->state(fn (): array => [
            'company_id' => null,
            'center_id' => null,
            'scope' => 'global',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => 'inactive',
        ]);
    }
}
