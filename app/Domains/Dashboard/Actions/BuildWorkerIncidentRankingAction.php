<?php

namespace App\Domains\Dashboard\Actions;

use App\Domains\Alerts\Support\AlertTypeCatalog;
use App\Models\Alert;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class BuildWorkerIncidentRankingAction
{
    public function handle(Builder $scopedAlerts, CarbonImmutable $end): array
    {
        $start = $end->subDays(29);
        $alerts = (clone $scopedAlerts)->with(['worker', 'workDay'])->whereNotNull('worker_id')
            ->whereNotIn('rule_code', ['long_work_day', 'twelve_hours_exceeded', 'daily_overtime_over_three_hours', 'weekly_sunday_work', 'six_consecutive_days'])
            ->whereHas('workDay', fn ($day) => $day->whereDate('work_date', '>=', $start->toDateString())->whereDate('work_date', '<=', $end->toDateString()))
            ->get()->reject(fn (Alert $alert) => $alert->isClosedByRecalculation() || ! $alert->worker || $alert->worker->company_id !== $alert->company_id);
        $catalog = AlertTypeCatalog::entries();
        $workers = $alerts->groupBy('worker_id')->map(function ($items) use ($catalog) {
            $worker = $items->first()->worker;
            $types = $items->groupBy('rule_code')->map(fn ($group, $rule) => [
                'rule' => $rule, 'label' => $catalog[$rule]['name'] ?? $group->first()->title, 'count' => $group->count(),
            ])->sortByDesc('count')->values()->all();

            return ['id' => $worker->id, 'name' => $worker->full_name, 'code' => $worker->employee_code, 'total' => $items->count(), 'pending' => $items->whereIn('status', Alert::OPEN_STATUSES)->count(),
                'reviewed' => $items->whereNotIn('status', Alert::OPEN_STATUSES)->count(), 'types' => $types];
        })->sort(fn ($a, $b) => ($b['total'] <=> $a['total']) ?: strcmp($a['name'], $b['name']) ?: ($a['id'] <=> $b['id']))->values()->all();

        return ['start' => $start->toDateString(), 'end' => $end->toDateString(), 'workers' => $workers, 'total' => $alerts->count()];
    }
}
