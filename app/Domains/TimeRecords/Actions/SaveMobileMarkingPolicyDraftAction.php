<?php

namespace App\Domains\TimeRecords\Actions;

use App\Models\Center;
use App\Models\Company;
use App\Models\MobileMarkingPolicy;
use App\Models\OrganizationalUnit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveMobileMarkingPolicyDraftAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Company $company, array $attributes, ?MobileMarkingPolicy $policy = null): MobileMarkingPolicy
    {
        return DB::transaction(function () use ($company, $attributes, $policy): MobileMarkingPolicy {
            Company::query()->lockForUpdate()->findOrFail($company->id);

            if ($policy) {
                $policy = MobileMarkingPolicy::query()->lockForUpdate()->findOrFail($policy->id);

                if ($policy->company_id !== $company->id || $policy->status !== MobileMarkingPolicy::STATUS_DRAFT) {
                    throw ValidationException::withMessages(['policyForm' => 'Solo se pueden modificar borradores de la empresa activa.']);
                }
            }

            [$centerId, $unitId] = $this->resolveScope($company, $attributes);
            $payload = $this->payload($attributes, $centerId, $unitId);

            if ($policy) {
                $policy->update($payload);

                return $policy->refresh();
            }

            $payload['company_id'] = $company->id;
            $payload['status'] = MobileMarkingPolicy::STATUS_DRAFT;
            $payload['version'] = $this->nextVersion($company, $centerId, $unitId);

            return MobileMarkingPolicy::query()->create($payload);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{0: int|null, 1: int|null}
     */
    private function resolveScope(Company $company, array $attributes): array
    {
        $unitId = filled($attributes['organizational_unit_id'] ?? null) ? (int) $attributes['organizational_unit_id'] : null;
        $centerId = filled($attributes['center_id'] ?? null) ? (int) $attributes['center_id'] : null;

        if ($unitId) {
            $unit = OrganizationalUnit::query()
                ->where('company_id', $company->id)
                ->find($unitId);

            if (! $unit) {
                throw ValidationException::withMessages(['policyForm.organizational_unit_id' => 'La unidad organizacional no pertenece a la empresa activa.']);
            }

            return [$unit->center_id, $unit->id];
        }

        if ($centerId && ! Center::query()->where('company_id', $company->id)->whereKey($centerId)->exists()) {
            throw ValidationException::withMessages(['policyForm.center_id' => 'El centro no pertenece a la empresa activa.']);
        }

        return [$centerId, null];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function payload(array $attributes, ?int $centerId, ?int $unitId): array
    {
        $mode = (string) $attributes['mode'];
        $requiresBinding = (bool) ($attributes['requires_device_binding'] ?? false);
        $requiresBiometricUnlock = (bool) ($attributes['requires_biometric_unlock'] ?? false);

        if ($requiresBiometricUnlock && ! $requiresBinding) {
            throw ValidationException::withMessages([
                'policyForm.requires_biometric_unlock' => 'El desbloqueo biometrico local requiere un dispositivo vinculado.',
            ]);
        }

        if (filled($attributes['offline_authorization_duration_minutes'] ?? null) && ! $requiresBinding) {
            throw ValidationException::withMessages([
                'policyForm.offline_authorization_duration_minutes' => 'El marcaje sin conexión requiere un dispositivo autorizado.',
            ]);
        }

        return [
            'center_id' => $centerId,
            'organizational_unit_id' => $unitId,
            'mode' => $mode,
            'requires_device_binding' => $requiresBinding,
            'requires_biometric_unlock' => $requiresBiometricUnlock,
            'center_latitude' => $mode === MobileMarkingPolicy::MODE_CIRCLE ? $attributes['center_latitude'] : null,
            'center_longitude' => $mode === MobileMarkingPolicy::MODE_CIRCLE ? $attributes['center_longitude'] : null,
            'radius_meters' => $mode === MobileMarkingPolicy::MODE_CIRCLE ? (int) $attributes['radius_meters'] : null,
            'max_accuracy_meters' => filled($attributes['max_accuracy_meters'] ?? null) ? (int) $attributes['max_accuracy_meters'] : null,
            'max_location_age_seconds' => filled($attributes['max_location_age_seconds'] ?? null) ? (int) $attributes['max_location_age_seconds'] : null,
            'offline_authorization_duration_minutes' => filled($attributes['offline_authorization_duration_minutes'] ?? null) ? (int) $attributes['offline_authorization_duration_minutes'] : null,
            'valid_from' => null,
            'valid_until' => null,
            'offline_valid_until' => null,
        ];
    }

    private function nextVersion(Company $company, ?int $centerId, ?int $unitId): int
    {
        return (int) MobileMarkingPolicy::query()
            ->where('company_id', $company->id)
            ->where('center_id', $centerId)
            ->where('organizational_unit_id', $unitId)
            ->lockForUpdate()
            ->max('version') + 1;
    }
}
