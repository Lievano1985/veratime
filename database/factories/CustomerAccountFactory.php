<?php

namespace Database\Factories;

use App\Models\CustomerAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerAccount>
 */
class CustomerAccountFactory extends Factory
{
    protected $model = CustomerAccount::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'account_type' => 'single_company',
            'status' => 'active',
            'metadata' => [],
        ];
    }
}
