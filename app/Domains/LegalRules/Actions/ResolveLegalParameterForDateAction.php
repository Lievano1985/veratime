<?php

namespace App\Domains\LegalRules\Actions;

use App\Models\Company;
use App\Models\LegalParameter;
use Carbon\CarbonInterface;

class ResolveLegalParameterForDateAction
{
    public function handle(?Company $company, string $code, string|CarbonInterface $workDate): ?LegalParameter
    {
        $date = $workDate instanceof CarbonInterface
            ? $workDate->toDateString()
            : (string) $workDate;

        return LegalParameter::query()
            ->where('code', $code)
            ->where('status', LegalParameter::STATUS_ACTIVE)
            ->whereDate('effective_from', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query
                    ->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $date);
            })
            ->where(function ($query) use ($company): void {
                $query
                    ->whereNull('company_id')
                    ->when($company, fn ($scopedQuery) => $scopedQuery->orWhere('company_id', $company->id));
            })
            ->orderByRaw('case when company_id is null then 0 else 1 end desc')
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }
}
