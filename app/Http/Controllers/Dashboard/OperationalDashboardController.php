<?php

namespace App\Http\Controllers\Dashboard;

use App\Domains\Dashboard\Actions\BuildOperationalDashboardAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\WorkDay;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OperationalDashboardController
{
    public function __invoke(Request $request, CurrentCompany $currentCompany, BuildOperationalDashboardAction $dashboard): View
    {
        $company = $currentCompany->get();
        abort_unless($company, 403);
        Gate::authorize('viewAny', [WorkDay::class, $company]);

        $filters = $request->validate([
            'date' => ['nullable', 'date'],
            'center' => ['nullable', 'integer'],
        ]);
        $date = $filters['date'] ?? now($company->setting?->default_timezone ?: $company->timezone)->toDateString();
        $summary = $dashboard->handle($company, $request->user(), $date, isset($filters['center']) ? (int) $filters['center'] : null);
        $visibleCenterIds = $summary['access']['filter_center_ids'];

        return view('dashboard', [
            'company' => $company,
            'summary' => $summary,
            'centers' => $company->centers()
                ->where('status', 'active')
                ->when($visibleCenterIds !== null, fn ($query) => $query->whereIn('id', $visibleCenterIds))
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }
}
