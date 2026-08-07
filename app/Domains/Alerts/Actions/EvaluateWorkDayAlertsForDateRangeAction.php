<?php

namespace App\Domains\Alerts\Actions;

use App\Models\Center;
use App\Models\Company;
use App\Models\WorkDay;
use Illuminate\Support\Collection;

class EvaluateWorkDayAlertsForDateRangeAction
{
    public function __construct(
        private readonly EvaluateWorkDayAlertsAction $evaluateWorkDayAlerts,
    ) {}

    /**
     * @return array{total: int, created_or_updated: int, closed: int, open: int}
     */
    public function handle(Company $company, string $startDate, string $endDate, ?Center $center = null): array
    {
        $summary = [
            'total' => 0,
            'created_or_updated' => 0,
            'closed' => 0,
            'open' => 0,
        ];

        WorkDay::query()
            ->where('company_id', $company->id)
            ->whereDate('work_date', '>=', $startDate)
            ->whereDate('work_date', '<=', $endDate)
            ->when($center, fn ($query) => $query->where('center_id', $center->id))
            ->orderBy('work_date')
            ->orderBy('worker_id')
            ->chunkById(200, function (Collection $workDays) use ($company, &$summary): void {
                foreach ($workDays as $workDay) {
                    $summary['total']++;
                    $result = $this->evaluateWorkDayAlerts->handle($company, $workDay);
                    $summary['created_or_updated'] += $result['created_or_updated'];
                    $summary['closed'] += $result['closed'];
                    $summary['open'] += $result['open'];
                }
            });

        return $summary;
    }
}
