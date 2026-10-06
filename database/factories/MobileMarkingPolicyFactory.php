<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\MobileMarkingPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MobileMarkingPolicy>
 */
class MobileMarkingPolicyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'center_id' => null,
            'organizational_unit_id' => null,
            'status' => MobileMarkingPolicy::STATUS_DRAFT,
            'version' => 1,
            'mode' => MobileMarkingPolicy::MODE_FREE,
            'requires_device_binding' => false,
            'requires_biometric_unlock' => false,
            'center_latitude' => null,
            'center_longitude' => null,
            'radius_meters' => null,
            'max_accuracy_meters' => null,
            'max_location_age_seconds' => null,
            'offline_authorization_duration_minutes' => null,
            'valid_from' => null,
            'valid_until' => null,
            'offline_valid_until' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => MobileMarkingPolicy::STATUS_ACTIVE,
            'valid_from' => now()->subMinute(),
        ]);
    }
}
