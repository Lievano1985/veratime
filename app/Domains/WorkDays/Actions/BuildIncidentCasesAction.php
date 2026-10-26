<?php

namespace App\Domains\WorkDays\Actions;

use App\Domains\WorkDays\Support\WorkDayWorkflow;
use App\Models\Alert;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

class BuildIncidentCasesAction
{
    public function handle(Builder $query, string $status, ?string $rule): LengthAwarePaginator
    {
        $cases = (clone $query)->orderByDesc('detected_at')->orderBy('id')->get()
            ->groupBy(fn (Alert $alert) => in_array($alert->rule_code, WorkDayWorkflow::WEEKLY_RULES, true)
                ? 'weekly:'.$alert->id : 'day:'.$alert->work_day_id)
            ->map(function ($alerts, $key) {
                $pending = $alerts->whereIn('status', Alert::OPEN_STATUSES);
                $historical = $alerts->filter(fn (Alert $alert) => $alert->isClosedByRecalculation());
                $current = $alerts->reject(fn (Alert $alert) => $alert->isClosedByRecalculation());

                return ['key' => $key, 'alert' => $pending->first() ?? $current->first() ?? $alerts->first(), 'alerts' => $alerts,
                    'current_alerts' => $current, 'historical_alerts' => $historical,
                    'pending' => $pending->count(), 'weekly' => str_starts_with($key, 'weekly:')];
            })
            ->filter(fn ($case) => ($status !== 'open' || $case['pending'] > 0)
                && ($status !== 'reviewed' || $case['pending'] === 0)
                && (! $rule || $case['alerts']->contains('rule_code', $rule)))
            ->sortBy([
                fn ($a, $b) => $a['weekly'] <=> $b['weekly'],
                fn ($a, $b) => ($a['pending'] === 0) <=> ($b['pending'] === 0),
                fn ($a, $b) => $a['alert']->workDay->work_date <=> $b['alert']->workDay->work_date,
                fn ($a, $b) => $a['alert']->id <=> $b['alert']->id,
            ])->values();
        $page = Paginator::resolveCurrentPage('incidentPage');

        return new LengthAwarePaginator($cases->forPage($page, 15)->values(), $cases->count(), 15, $page,
            ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'incidentPage']);
    }
}
