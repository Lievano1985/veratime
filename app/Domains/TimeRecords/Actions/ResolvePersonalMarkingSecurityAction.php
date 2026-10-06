<?php

namespace App\Domains\TimeRecords\Actions;

use App\Domains\Organization\Actions\ResolveEmploymentUnitsForDateAction;
use App\Models\Company;
use App\Models\MobileMarkingPolicy;
use App\Models\MobileMarkingTimeReference;
use App\Models\User;
use App\Models\Worker;
use Carbon\CarbonImmutable;

class ResolvePersonalMarkingSecurityAction
{
    public function __construct(
        private readonly ResolveEmploymentUnitsForDateAction $resolveEmploymentUnits,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(Company $company, User $user, Worker $worker): array
    {
        $now = CarbonImmutable::now('UTC');
        $policy = $this->resolveEffectivePolicy($company, $worker, $now);
        $reference = $this->issueTimeReference($company, $user, $worker, $policy, $now);

        if (! $policy) {
            return $this->withoutEffectivePolicy($reference);
        }

        return [
            'security_version' => 1,
            'binding' => null,
            'policy' => [
                'id' => $policy->public_id,
                'status' => $policy->status,
                'version' => $policy->version,
                'mode' => $policy->mode,
                'device_binding_required' => $policy->requires_device_binding,
                'biometric_required' => $policy->requires_biometric_unlock,
                'center' => $policy->mode === MobileMarkingPolicy::MODE_CIRCLE ? [
                    'latitude' => (float) $policy->center_latitude,
                    'longitude' => (float) $policy->center_longitude,
                ] : null,
                'radius_meters' => $policy->mode === MobileMarkingPolicy::MODE_CIRCLE ? $policy->radius_meters : null,
                'valid_from' => $policy->valid_from?->toIso8601String(),
                'valid_until' => $policy->valid_until?->toIso8601String(),
                'offline_valid_until' => $policy->offline_valid_until?->toIso8601String(),
                'max_accuracy_meters' => $policy->max_accuracy_meters,
                'max_location_age_seconds' => $policy->max_location_age_seconds,
            ],
            'time_reference' => $this->timeReference($reference),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function withoutEffectivePolicy(MobileMarkingTimeReference $reference): array
    {
        return [
            'security_version' => 1,
            'binding' => null,
            'policy' => null,
            'time_reference' => $this->timeReference($reference),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function timeReference(MobileMarkingTimeReference $reference): array
    {
        return [
            'id' => $reference->public_id,
            'server_time' => $reference->issued_at->toIso8601String(),
            'expires_at' => $reference->expires_at->toIso8601String(),
        ];
    }

    public function resolveEffectivePolicy(Company $company, Worker $worker, CarbonImmutable $now): ?MobileMarkingPolicy
    {
        $relationship = $worker->activeEmploymentRelationship()->with('center')->first();
        $centerId = $relationship?->center_id;
        $unitId = $relationship
            ? $this->resolveEmploymentUnits->handle($company, $relationship, $now->toDateString())['primary']?->id
            : null;

        return MobileMarkingPolicy::query()
            ->where('company_id', $company->id)
            ->where('status', MobileMarkingPolicy::STATUS_ACTIVE)
            ->where(function ($query) use ($now): void {
                $query->whereNull('valid_from')->orWhere('valid_from', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('valid_until')->orWhere('valid_until', '>=', $now);
            })
            ->where(function ($query) use ($centerId, $unitId): void {
                if ($unitId) {
                    $query->where('organizational_unit_id', $unitId)
                        ->orWhere(function ($query) use ($centerId): void {
                            $query->whereNull('organizational_unit_id')
                                ->when($centerId, fn ($query) => $query->where('center_id', $centerId), fn ($query) => $query->whereNull('center_id'));
                        })
                        ->orWhere(function ($query): void {
                            $query->whereNull('organizational_unit_id')->whereNull('center_id');
                        });

                    return;
                }

                $query->whereNull('organizational_unit_id')
                    ->when($centerId, fn ($query) => $query->where('center_id', $centerId), fn ($query) => $query->whereNull('center_id'))
                    ->orWhere(function ($query): void {
                        $query->whereNull('organizational_unit_id')->whereNull('center_id');
                    });
            })
            ->orderByRaw('CASE WHEN organizational_unit_id = ? THEN 0 WHEN center_id = ? THEN 1 ELSE 2 END', [$unitId ?? 0, $centerId ?? 0])
            ->orderByDesc('version')
            ->first();
    }

    private function issueTimeReference(Company $company, User $user, Worker $worker, ?MobileMarkingPolicy $policy, CarbonImmutable $now): MobileMarkingTimeReference
    {
        $existing = MobileMarkingTimeReference::query()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->where('worker_id', $worker->id)
            ->where('policy_version', $policy?->version)
            ->when($policy, fn ($query) => $query->where('mobile_marking_policy_id', $policy->id), fn ($query) => $query->whereNull('mobile_marking_policy_id'))
            ->where('expires_at', '>', $now)
            ->latest('issued_at')
            ->first();

        if ($existing) {
            return $existing;
        }

        return MobileMarkingTimeReference::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'worker_id' => $worker->id,
            'mobile_marking_policy_id' => $policy?->id,
            'policy_version' => $policy?->version,
            'issued_at' => $now,
            'expires_at' => $now->addMinutes(5),
            'schema_version' => 1,
        ]);
    }
}
