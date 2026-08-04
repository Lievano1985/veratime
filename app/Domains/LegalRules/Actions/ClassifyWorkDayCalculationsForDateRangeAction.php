<?php

namespace App\Domains\LegalRules\Actions;

use App\Models\Center;
use App\Models\Company;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use Illuminate\Support\Collection;

class ClassifyWorkDayCalculationsForDateRangeAction
{
    public function __construct(
        private readonly ClassifyWorkDayCalculationAction $classifyWorkDayCalculation,
    ) {}

    /**
     * @return array{total: int, classified: int, pending: int}
     */
    public function handle(Company $company, string $startDate, string $endDate, ?Center $center = null): array
    {
        $summary = [
            'total' => 0,
            'classified' => 0,
            'pending' => 0,
        ];

        WorkDay::query()
            ->with(['activeCalculation.workDay.company'])
            ->where('company_id', $company->id)
            ->whereDate('work_date', '>=', $startDate)
            ->whereDate('work_date', '<=', $endDate)
            ->when($center, fn ($query) => $query->where('center_id', $center->id))
            ->whereNotNull('active_calculation_id')
            ->orderBy('work_date')
            ->orderBy('worker_id')
            ->chunkById(200, function (Collection $workDays) use (&$summary): void {
                foreach ($workDays as $workDay) {
                    $calculation = $workDay->activeCalculation;

                    if (! $calculation) {
                        continue;
                    }

                    $summary['total']++;

                    $classified = $this->classifyWorkDayCalculation->handle($calculation);

                    if ($classified->classification === WorkDayCalculation::CLASSIFICATION_PENDING) {
                        $summary['pending']++;
                    } else {
                        $summary['classified']++;
                    }
                }
            });

        return $summary;
    }
}
