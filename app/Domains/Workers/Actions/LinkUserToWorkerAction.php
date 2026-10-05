<?php

namespace App\Domains\Workers\Actions;

use App\Models\Company;
use App\Models\User;
use App\Models\UserWorkerLink;
use App\Models\Worker;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LinkUserToWorkerAction
{
    public function handle(Company $company, User $user, Worker $worker): UserWorkerLink
    {
        if ($worker->company_id !== $company->id || ! $user->belongsToCompany($company)) {
            throw new InvalidArgumentException('La cuenta y la persona trabajadora deben pertenecer a la empresa activa.');
        }

        return DB::transaction(function () use ($company, $user, $worker): UserWorkerLink {
            $workerLink = UserWorkerLink::query()
                ->where('company_id', $company->id)
                ->where('worker_id', $worker->id)
                ->lockForUpdate()
                ->first();

            $userLink = UserWorkerLink::query()
                ->where('company_id', $company->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($workerLink?->status === 'active' && $workerLink->user_id !== $user->id) {
                throw new InvalidArgumentException('La persona trabajadora ya tiene una cuenta activa vinculada. Revoca ese vinculo antes de transferirla.');
            }

            if ($userLink && $userLink->worker_id !== $worker->id) {
                throw new InvalidArgumentException('La cuenta ya esta vinculada a otra persona trabajadora en esta empresa.');
            }

            if ($workerLink) {
                $workerLink->forceFill([
                    'user_id' => $user->id,
                    'status' => 'active',
                ])->save();

                return $workerLink->refresh();
            }

            return UserWorkerLink::query()->create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'worker_id' => $worker->id,
                'status' => 'active',
            ]);
        });
    }
}
