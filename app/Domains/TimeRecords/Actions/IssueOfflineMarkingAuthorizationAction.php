<?php

namespace App\Domains\TimeRecords\Actions;

use App\Models\Company;
use App\Models\MobileDeviceBinding;
use App\Models\MobileOfflineMarkingAuthorization;
use App\Models\User;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class IssueOfflineMarkingAuthorizationAction
{
    public function __construct(
        private readonly ResolvePersonalMarkingSecurityAction $resolveSecurity,
        private readonly BuildMobileMarkingPolicySnapshotAction $snapshot,
    ) {}

    public function handle(Company $company, User $user, Worker $worker, string $bindingId, int $issuedMonotonicMilliseconds): MobileOfflineMarkingAuthorization
    {
        return DB::transaction(function () use ($company, $user, $worker, $bindingId, $issuedMonotonicMilliseconds): MobileOfflineMarkingAuthorization {
            $now = CarbonImmutable::now('UTC');
            $policy = $this->resolveSecurity->resolveEffectivePolicy($company, $worker, $now);

            if (! $policy || ! $policy->requires_device_binding || ! $policy->offline_authorization_duration_minutes) {
                throw new InvalidArgumentException('El marcaje sin conexión no está habilitado para la política vigente.');
            }

            $binding = MobileDeviceBinding::query()
                ->where('company_id', $company->id)
                ->where('user_id', $user->id)
                ->where('worker_id', $worker->id)
                ->where('public_id', $bindingId)
                ->where('status', MobileDeviceBinding::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();

            if (! $binding) {
                throw new InvalidArgumentException('El dispositivo no está autorizado para emitir una autorización sin conexión.');
            }

            return MobileOfflineMarkingAuthorization::query()->create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'worker_id' => $worker->id,
                'mobile_marking_policy_id' => $policy->id,
                'mobile_device_binding_id' => $binding->id,
                'status' => MobileOfflineMarkingAuthorization::STATUS_ACTIVE,
                'policy_version' => $policy->version,
                'issued_monotonic_milliseconds' => $issuedMonotonicMilliseconds,
                'max_clock_drift_seconds' => 120,
                'issued_at' => $now,
                'expires_at' => $now->addMinutes($policy->offline_authorization_duration_minutes),
                'policy_snapshot' => $this->snapshot->handle($policy),
            ]);
        });
    }
}
