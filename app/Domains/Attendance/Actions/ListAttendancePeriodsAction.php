<?php

namespace App\Domains\Attendance\Actions;

use App\Models\AttendancePeriod;
use App\Models\Company;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListAttendancePeriodsAction
{
    public function handle(Company $company, array $filters = []): LengthAwarePaginator
    {
        return AttendancePeriod::query()
            ->with(['center', 'creator', 'validatedBy', 'closedBy', 'scopes.organizationalUnit'])
            ->where('company_id', $company->id)
            ->when(filled($filters['center_id'] ?? null), fn ($query) => $query->where('center_id', (int) $filters['center_id']))
            ->when(filled($filters['status'] ?? null), fn ($query) => $query->where('status', $filters['status']))
            ->when(filled($filters['date_from'] ?? null), fn ($query) => $query->whereDate('period_end', '>=', $filters['date_from']))
            ->when(filled($filters['date_to'] ?? null), fn ($query) => $query->whereDate('period_start', '<=', $filters['date_to']))
            ->orderByDesc('period_start')
            ->orderBy('center_id')
            ->paginate(10);
    }
}
