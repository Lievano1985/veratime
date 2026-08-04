<?php

namespace App\Domains\LegalRules\Actions;

use App\Models\LegalRule;
use App\Models\LegalRuleVersion;
use Carbon\CarbonInterface;

class ResolveLegalRuleVersionForDateAction
{
    public function handle(string $code, string|CarbonInterface $workDate): ?LegalRuleVersion
    {
        $date = $workDate instanceof CarbonInterface
            ? $workDate->toDateString()
            : (string) $workDate;

        return LegalRuleVersion::query()
            ->with('legalRule')
            ->whereHas('legalRule', function ($query) use ($code): void {
                $query
                    ->where('code', $code)
                    ->where('status', LegalRule::STATUS_ACTIVE);
            })
            ->where('status', LegalRuleVersion::STATUS_ACTIVE)
            ->whereDate('effective_from', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query
                    ->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $date);
            })
            ->orderByDesc('effective_from')
            ->orderByDesc('version')
            ->first();
    }
}
