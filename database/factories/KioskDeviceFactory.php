<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\KioskDevice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KioskDevice>
 */
class KioskDeviceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'center_id' => null,
            'name' => 'Kiosco '.fake()->unique()->bothify('###'),
            'status' => 'pending',
            'pairing_code_hash' => hash('sha256', Str::random(48)),
            'pairing_expires_at' => now()->addHour(),
            'paired_at' => null,
            'device_token_hash' => null,
            'created_by_user_id' => null,
            'revoked_at' => null,
            'revoked_by_user_id' => null,
            'last_seen_at' => null,
            'last_seen_ip' => null,
            'last_seen_user_agent' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => 'active',
            'pairing_code_hash' => null,
            'pairing_expires_at' => null,
            'paired_at' => now(),
            'device_token_hash' => hash('sha256', Str::random(64)),
        ]);
    }
}
