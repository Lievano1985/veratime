<?php

namespace App\Domains\Workers\Actions;

use App\Models\Company;
use App\Models\MobileDeviceBinding;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Collection;

class ListPersonalMobileDeviceBindingsAction
{
    /** @return Collection<int, MobileDeviceBinding> */
    public function handle(Company $company, User $user, Worker $worker): Collection
    {
        return MobileDeviceBinding::query()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->where('worker_id', $worker->id)
            ->latest('activated_at')
            ->get();
    }
}
