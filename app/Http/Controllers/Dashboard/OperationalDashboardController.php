<?php

namespace App\Http\Controllers\Dashboard;

use App\Domains\Dashboard\Actions\BuildOperationalDashboardAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\WorkDay;
use Carbon\CarbonImmutable;
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
        $timezone = $company->setting?->default_timezone ?: $company->timezone;
        $lastClosedWeek = CarbonImmutable::now($timezone)->startOfWeek()->subWeek()->toDateString();

        $filters = $request->validate([
            'date' => ['nullable', 'date'],
            'center' => ['nullable', 'integer'],
            'compliance_week' => ['bail', 'nullable', 'date_format:Y-m-d', 'before_or_equal:'.$lastClosedWeek,
                function ($attribute, $value, $fail) use ($timezone): void {
                    if (! CarbonImmutable::parse($value, $timezone)->isMonday()) {
                        $fail('Selecciona una semana válida de cumplimiento.');
                    }
                }],
        ]);
        $date = $filters['date'] ?? now($company->setting?->default_timezone ?: $company->timezone)->toDateString();
        $summary = $dashboard->handle($company, $request->user(), $date, isset($filters['center']) ? (int) $filters['center'] : null, $filters['compliance_week'] ?? null);
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
