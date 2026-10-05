<?php

namespace App\Domains\TimeRecords\Actions;

use App\Models\Company;
use App\Models\MobileMarkingPolicy;
use App\Models\MobileMarkingTimeReference;
use App\Models\User;
use App\Models\Worker;
use Carbon\CarbonImmutable;

class ResolvePersonalMarkingSecurityAction
{
    /**
     * @return array<string, mixed>
     */
    public function handle(Company $company, User $user, Worker $worker): array
    {
        $now = CarbonImmutable::now('UTC');
        $policy = $this->effectivePolicy($company, $worker, $now);
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

    private function effectivePolicy(Company $company, Worker $worker, CarbonImmutable $now): ?MobileMarkingPolicy
    {
        $centerId = $worker->activeEmploymentRelationship()->value('center_id');

        return MobileMarkingPolicy::query()
            ->where('company_id', $company->id)
            ->where('status', MobileMarkingPolicy::STATUS_ACTIVE)
            ->where(function ($query) use ($now): void {
                $query->whereNull('valid_from')->orWhere('valid_from', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('valid_until')->orWhere('valid_until', '>=', $now);
            })
            ->when($centerId, function ($query, $centerId): void {
                $query->where(function ($query) use ($centerId): void {
                    $query->whereNull('center_id')->orWhere('center_id', $centerId);
                })->orderByRaw('CASE WHEN center_id = ? THEN 0 ELSE 1 END', [$centerId]);
            }, function ($query): void {
                $query->whereNull('center_id');
            })
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
