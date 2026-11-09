<?php

namespace App\Domains\Companies\Support;

use App\Models\Company;
use Carbon\CarbonImmutable;

class CompanyTrialStatus
{
    public static function forCompany(Company $company): ?array
    {
        if (! $company->is_trial || ! $company->trial_ends_at) {
            return null;
        }
        $endsAt = CarbonImmutable::instance($company->trial_ends_at);
        $seconds = CarbonImmutable::now('UTC')->diffInSeconds($endsAt, false);

        return [
            'days' => max(0, (int) ceil($seconds / 86400)),
            'expired' => $seconds <= 0, 'urgent' => $seconds <= 3 * 86400,
            'endsAt' => $endsAt->setTimezone($company->timezone),
        ];
    }
}
