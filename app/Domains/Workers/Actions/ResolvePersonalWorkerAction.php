<?php

namespace App\Domains\Workers\Actions;

use App\Models\Company;
use App\Models\User;
use App\Models\UserWorkerLink;
use App\Models\Worker;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ResolvePersonalWorkerAction
{
    public function handle(User $user, Company $company): Worker
    {
        $link = UserWorkerLink::query()->with('worker')
            ->where('company_id', $company->id)->where('user_id', $user->id)->where('status', 'active')->first();

        if (! $link || ! $link->worker || $link->worker->company_id !== $company->id || $link->worker->status !== 'active') {
            throw new HttpException(403, 'La cuenta no tiene una persona trabajadora activa vinculada en esta empresa.');
        }

        return $link->worker;
    }
}
