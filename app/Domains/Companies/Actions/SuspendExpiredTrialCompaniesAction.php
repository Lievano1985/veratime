<?php

namespace App\Domains\Companies\Actions;

use App\Models\Company;

class SuspendExpiredTrialCompaniesAction
{
    public function handle(): int
    {
        $now = now();

        return Company::query()
            ->where('is_trial', true)
            ->where('status', 'active')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', $now)
            ->update([
                'status' => 'suspended',
                'updated_at' => $now,
            ]);
    }
}
