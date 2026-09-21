<?php

namespace App\Domains\Alerts\Actions;

use App\Models\Alert;
use App\Models\Company;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListAlertsAction
{
    /**
     * @param  array{date_from?: ?string, date_to?: ?string, worker_id?: ?int, center_id?: ?int, status?: ?string, severity?: ?string, alert_type?: ?string, assigned_to?: ?int, search?: ?string}  $filters
     * @return LengthAwarePaginator<int, Alert>
     */
    public function handle(Company $company, array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return Alert::query()
            ->with(['alertType', 'worker', 'workDay.center', 'workDay.activeCalculation'])
            ->where('company_id', $company->id)
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('detected_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('detected_at', '<=', $date))
            ->when(
                $filters['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status),
                fn ($query) => $query->whereIn('status', Alert::OPEN_STATUSES),
            )
            ->when($filters['severity'] ?? null, fn ($query, $severity) => $query->where('severity', $severity))
            ->when($filters['worker_id'] ?? null, fn ($query, $workerId) => $query->where('worker_id', $workerId))
            ->when($filters['center_id'] ?? null, fn ($query, $centerId) => $query->whereHas('workDay', fn ($workDayQuery) => $workDayQuery->where('center_id', $centerId)))
            ->when($filters['alert_type'] ?? null, fn ($query, $code) => $query->whereHas('alertType', fn ($types) => $types->where('code', $code)))
            ->when($filters['assigned_to'] ?? null, fn ($query, $userId) => $query->where('assigned_to', $userId))
            ->when($filters['search'] ?? null, function ($query, $search): void {
                $term = trim((string) $search);

                if ($term === '') {
                    return;
                }

                $query->where(function ($query) use ($term): void {
                    $query->where('title', 'like', "%{$term}%")
                        ->orWhereHas('worker', function ($workerQuery) use ($term): void {
                            $workerQuery
                                ->where('full_name', 'like', "%{$term}%")
                                ->orWhere('employee_code', 'like', "%{$term}%");
                        });
                });
            })
            ->orderByRaw("case severity when 'critical' then 1 when 'high' then 2 when 'warning' then 3 else 4 end")
            ->orderByDesc('detected_at')
            ->paginate($perPage);
    }
}
