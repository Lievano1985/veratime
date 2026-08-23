<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CustomerAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_account_id' => CustomerAccount::factory(),
            'name' => fake()->company(),
            'legal_name' => fake()->company().' SA de CV',
            'tax_id' => strtoupper(fake()->unique()->bothify('???######???')),
            'timezone' => 'America/Mexico_City',
            'status' => 'active',
            'settings' => [],
        ];
    }
}
