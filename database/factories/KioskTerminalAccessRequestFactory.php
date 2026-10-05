<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\KioskTerminalAccessRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KioskTerminalAccessRequest>
 */
class KioskTerminalAccessRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'public_id' => (string) Str::uuid(),
            'request_secret_hash' => hash('sha256', Str::random(64)),
            'requested_name' => 'Terminal '.fake()->unique()->bothify('###'),
            'status' => 'pending',
            'center_id' => null,
            'expires_at' => now()->addMinutes(15),
            'requested_ip' => '203.0.113.10',
            'requested_user_agent' => 'Vera terminal test',
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
            'claimed_at' => null,
            'kiosk_device_id' => null,
        ];
    }
}
