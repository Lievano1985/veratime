<?php

namespace App\Domains\Workers\Actions;

use App\Models\Company;
use App\Models\User;
use App\Models\UserWorkerLink;
use App\Models\Worker;
use InvalidArgumentException;

class LinkUserToWorkerAction
{
    public function handle(Company $company, User $user, Worker $worker): UserWorkerLink
    {
        if ($worker->company_id !== $company->id || ! $user->belongsToCompany($company)) {
            throw new InvalidArgumentException('La cuenta y la persona trabajadora deben pertenecer a la empresa activa.');
        }

        $linkedToAnotherUser = UserWorkerLink::query()
            ->where('company_id', $company->id)
            ->where('worker_id', $worker->id)
            ->where('status', 'active')
            ->where('user_id', '!=', $user->id)
            ->exists();

        if ($linkedToAnotherUser) {
            throw new InvalidArgumentException('La persona trabajadora ya tiene una cuenta activa vinculada.');
        }

        return UserWorkerLink::query()->updateOrCreate(
            ['company_id' => $company->id, 'user_id' => $user->id],
            ['worker_id' => $worker->id, 'status' => 'active'],
        );
    }
}
