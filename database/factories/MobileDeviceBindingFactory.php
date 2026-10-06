<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\MobileDeviceBinding;
use App\Models\MobileDeviceBindingAuthorization;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MobileDeviceBinding> */
class MobileDeviceBindingFactory extends Factory
{
    public function definition(): array
    {
        $publicKeySpki = base64_encode(random_bytes(91));

        return [
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
            'worker_id' => Worker::factory(),
            'mobile_device_binding_authorization_id' => MobileDeviceBindingAuthorization::factory(),
            'device_name' => fake()->words(2, true),
            'algorithm' => 'ES256',
            'public_key_spki' => $publicKeySpki,
            'key_fingerprint' => hash('sha256', $publicKeySpki),
            'status' => MobileDeviceBinding::STATUS_ACTIVE,
            'activated_at' => now(),
        ];
    }
}
