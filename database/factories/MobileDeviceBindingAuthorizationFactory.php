<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\MobileDeviceBindingAuthorization;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<MobileDeviceBindingAuthorization> */
class MobileDeviceBindingAuthorizationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
            'worker_id' => Worker::factory(),
            'created_by_user_id' => User::factory(),
            'authorization_secret_hash' => hash('sha256', Str::random(32)),
            'status' => MobileDeviceBindingAuthorization::STATUS_CONSUMED,
            'expires_at' => now()->addMinutes(15),
            'consumed_at' => now(),
        ];
    }
}
