<?php

namespace App\Domains\WorkDays\Actions;

use App\Models\Center;
use App\Models\Company;
use App\Models\User;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use Illuminate\Support\Collection;

class CalculateWorkDaysForDateRangeAction
{
    public function __construct(
        private readonly CalculateWorkDayAction $calculateWorkDay,
    ) {}

    /**
     * @return array{total: int, calculated: int, under_review: int, skipped: int}
     */
    public function handle(Company $company, string $startDate, string $endDate, ?Center $center = null, ?User $actor = null, string $generatedByType = WorkDayCalculation::GENERATED_BY_SYSTEM, ?string $reason = null): array
    {
        $summary = [
            'total' => 0,
            'calculated' => 0,
            'under_review' => 0,
            'skipped' => 0,
        ];

        WorkDay::query()
            ->with(['employmentRelationship'])
            ->where('company_id', $company->id)
            ->whereDate('work_date', '>=', $startDate)
            ->whereDate('work_date', '<=', $endDate)
            ->when($center, fn ($query) => $query->where('center_id', $center->id))
            ->orderBy('work_date')
            ->orderBy('worker_id')
            ->chunkById(200, function (Collection $workDays) use ($company, $actor, $generatedByType, $reason, &$summary): void {
                foreach ($workDays as $workDay) {
                    $summary['total']++;

                    $calculation = $this->calculateWorkDay->handle($company, $workDay, $actor, $generatedByType, $reason);

                    if (! $calculation) {
                        $summary['skipped']++;
                        continue;
                    }

                    $workDay->refresh();

                    if ($workDay->status === WorkDay::STATUS_CALCULATED) {
                        $summary['calculated']++;
                    } elseif ($workDay->status === WorkDay::STATUS_UNDER_REVIEW) {
                        $summary['under_review']++;
                    }
                }
            });

        return $summary;
    }
}
