<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Worker;
use App\Models\WorkerCredential;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<WorkerCredential>
 */
class WorkerCredentialFactory extends Factory
{
    public function definition(): array
    {
        $company = Company::factory();

        return [
            'company_id' => $company,
            'worker_id' => Worker::factory()->for($company),
            'pin_hash' => Hash::make('1234'),
            'access_code' => strtoupper(fake()->unique()->bothify('KIO-####')),
            'status' => 'active',
            'failed_attempts' => 0,
            'last_used_at' => null,
            'last_changed_at' => now(),
        ];
    }
}
