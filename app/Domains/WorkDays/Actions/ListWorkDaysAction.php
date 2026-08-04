<?php

namespace App\Domains\WorkDays\Actions;

use App\Models\Company;
use App\Models\WorkDay;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListWorkDaysAction
{
    /**
     * @param array{date_from?: ?string, date_to?: ?string, center_id?: ?int, status?: ?string, schedule_status?: ?string, search?: ?string} $filters
     * @return LengthAwarePaginator<int, WorkDay>
     */
    public function handle(Company $company, array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return WorkDay::query()
            ->with(['worker', 'center', 'employmentRelationship', 'scheduleBatch', 'activeCalculation'])
            ->where('company_id', $company->id)
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('work_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('work_date', '<=', $date))
            ->when($filters['center_id'] ?? null, fn ($query, $centerId) => $query->where('center_id', $centerId))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['schedule_status'] ?? null, fn ($query, $status) => $query->where('schedule_status', $status))
            ->when($filters['search'] ?? null, function ($query, $search): void {
                $term = trim((string) $search);

                if ($term === '') {
                    return;
                }

                $query->whereHas('worker', function ($workerQuery) use ($term): void {
                    $workerQuery
                        ->where('full_name', 'like', "%{$term}%")
                        ->orWhere('employee_code', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('work_date')
            ->orderBy('worker_id')
            ->paginate($perPage);
    }
}
