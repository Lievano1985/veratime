<?php

namespace App\Domains\Scheduling\Actions;

use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ListPersonalScheduleAction
{
    /**
     * @param  array{date_from?: string|null, date_to?: string|null}  $filters
     * @return Collection<int, DailyScheduleAssignment>
     */
    public function handle(Company $company, Worker $worker, array $filters): Collection
    {
        $relationship = $worker->activeEmploymentRelationship()
            ->where('company_id', $company->id)
            ->first();

        if (! $relationship) {
            return collect();
        }

        $dateFrom = $filters['date_from'] ?? CarbonImmutable::now($company->timezone)->toDateString();
        $dateTo = $filters['date_to'] ?? CarbonImmutable::parse($dateFrom, $company->timezone)->addDays(13)->toDateString();

        return DailyScheduleAssignment::query()
            ->with(['employmentRelationship.center', 'shiftTemplate', 'segments'])
            ->where('company_id', $company->id)
            ->where('employment_relationship_id', $relationship->id)
            ->whereDate('work_date', '>=', $dateFrom)
            ->whereDate('work_date', '<=', $dateTo)
            ->whereHas('scheduleBatch', fn ($query) => $query->where('status', 'published'))
            ->orderBy('work_date')
            ->orderBy('id')
            ->get();
    }
}
