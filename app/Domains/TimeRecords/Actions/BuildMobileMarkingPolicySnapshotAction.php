<?php

namespace App\Domains\TimeRecords\Actions;

use App\Models\MobileMarkingPolicy;

class BuildMobileMarkingPolicySnapshotAction
{
    /** @return array<string, mixed> */
    public function handle(MobileMarkingPolicy $policy): array
    {
        return [
            'public_id' => $policy->public_id,
            'version' => $policy->version,
            'mode' => $policy->mode,
            'requires_device_binding' => $policy->requires_device_binding,
            'requires_biometric_unlock' => $policy->requires_biometric_unlock,
            'center_latitude' => $policy->center_latitude,
            'center_longitude' => $policy->center_longitude,
            'radius_meters' => $policy->radius_meters,
            'max_accuracy_meters' => $policy->max_accuracy_meters,
            'max_location_age_seconds' => $policy->max_location_age_seconds,
            'offline_authorization_duration_minutes' => $policy->offline_authorization_duration_minutes,
            'valid_from' => $policy->valid_from?->toIso8601String(),
            'valid_until' => $policy->valid_until?->toIso8601String(),
        ];
    }
}
