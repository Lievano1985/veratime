<?php

namespace App\Domains\Attendance\Actions;

use App\Models\AttendancePeriod;
use App\Models\Center;
use App\Models\Company;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class SuggestAttendancePeriodRangeAction
{
    /**
     * @return array{period_start: string, period_end: string}
     */
    public function handle(Company $company, Center $center): array
    {
        $lastEnd = AttendancePeriod::query()
            ->where('company_id', $company->id)
            ->where('center_id', $center->id)
            ->where('status', '!=', AttendancePeriod::STATUS_CANCELLED)
            ->orderByDesc('period_end')
            ->value('period_end');

        if ($lastEnd) {
            $start = CarbonImmutable::parse($lastEnd)->addDay();

            return [
                'period_start' => $start->toDateString(),
                'period_end' => $this->suggestEnd($company, $start)->toDateString(),
            ];
        }

        $start = CarbonImmutable::parse(now($center->timezone ?: $company->timezone))
            ->startOfWeek(CarbonInterface::MONDAY);

        return [
            'period_start' => $start->toDateString(),
            'period_end' => $this->suggestEnd($company, $start)->toDateString(),
        ];
    }

    private function suggestEnd(Company $company, CarbonImmutable $start): CarbonImmutable
    {
        $settings = $company->setting;
        $type = $settings?->payroll_period_type ?? 'weekly';

        return match ($type) {
            'weekly' => $start->addDays(6),
            'biweekly', 'semi_monthly' => $start->addDays(14),
            'monthly' => $start->addMonthNoOverflow()->subDay(),
            default => $start->addDays(6),
        };
    }
}
