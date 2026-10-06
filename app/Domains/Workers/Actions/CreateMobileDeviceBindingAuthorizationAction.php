<?php

namespace App\Domains\Workers\Actions;

use App\Models\Company;
use App\Models\MobileDeviceBindingAuthorization;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateMobileDeviceBindingAuthorizationAction
{
    /** @return array{authorization: MobileDeviceBindingAuthorization, authorization_code: string} */
    public function handle(Company $company, User $actor, User $user, Worker $worker): array
    {
        if (! $actor->belongsToCompany($company) || ! $user->hasActiveMembershipInCompany($company) || $user->status !== 'active' || $worker->company_id !== $company->id || $worker->status !== 'active') {
            throw new InvalidArgumentException('La autorización requiere una cuenta y una persona trabajadora activas de la empresa.');
        }

        $linked = $user->workerLinks()->where('company_id', $company->id)->where('worker_id', $worker->id)->where('status', 'active')->exists();
        if (! $linked) {
            throw new InvalidArgumentException('La cuenta debe estar vinculada con la persona trabajadora antes de autorizar un dispositivo.');
        }

        $code = Str::random(24);

        return DB::transaction(function () use ($company, $actor, $user, $worker, $code): array {
            MobileDeviceBindingAuthorization::query()
                ->where('company_id', $company->id)->where('user_id', $user->id)->where('worker_id', $worker->id)
                ->whereIn('status', [MobileDeviceBindingAuthorization::STATUS_PENDING, MobileDeviceBindingAuthorization::STATUS_CHALLENGED])
                ->lockForUpdate()->update(['status' => MobileDeviceBindingAuthorization::STATUS_REVOKED, 'updated_at' => now()]);

            $authorization = MobileDeviceBindingAuthorization::query()->create([
                'company_id' => $company->id, 'user_id' => $user->id, 'worker_id' => $worker->id,
                'created_by_user_id' => $actor->id, 'authorization_secret_hash' => hash('sha256', $code),
                'status' => MobileDeviceBindingAuthorization::STATUS_PENDING, 'expires_at' => now()->addMinutes(15),
            ]);

            return ['authorization' => $authorization, 'authorization_code' => $code];
        });
    }
}
