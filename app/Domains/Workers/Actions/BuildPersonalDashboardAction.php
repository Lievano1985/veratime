<?php

namespace App\Domains\Workers\Actions;

use App\Domains\Scheduling\Actions\ListPersonalScheduleAction;
use App\Models\Alert;
use App\Models\Company;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use Carbon\CarbonImmutable;

class BuildPersonalDashboardAction
{
    public function handle(User $user, Company $company): array
    {
        $worker = app(ResolvePersonalWorkerAction::class)->handle($user, $company);
        $today = CarbonImmutable::now($company->timezone)->startOfDay();
        $start = $today->startOfWeek();
        $end = $start->addDays(6);
        // Corrective publications can coexist; display only the latest assignment per date.
        $schedule = app(ListPersonalScheduleAction::class)->handle($company, $worker, [
            'date_from' => $start->toDateString(), 'date_to' => $end->toDateString(),
        ])->keyBy(fn ($day) => $day->work_date->toDateString());
        $days = WorkDay::query()->where('company_id', $company->id)->where('worker_id', $worker->id)
            ->whereDate('work_date', '>=', $start->toDateString())->whereDate('work_date', '<=', $today->toDateString())
            ->with(['activeCalculation' => fn ($query) => $query->where('company_id', $company->id)->where('status', WorkDayCalculation::STATUS_ACTIVE)])
            ->orderByDesc('work_date')->get();
        $events = TimeEvent::query()->where('company_id', $company->id)->where('worker_id', $worker->id)
            ->whereIn('status', ['valid', 'pending_review'])->whereNull('voided_at')
            ->orderByDesc('occurred_at_utc')->limit(6)->get();
        $alerts = Alert::query()->where('company_id', $company->id)->where('worker_id', $worker->id)
            ->whereIn('status', Alert::OPEN_STATUSES)->orderByDesc('detected_at')->limit(5)->get();

        return [
            'worker' => $worker, 'today' => $today, 'weekStart' => $start, 'weekEnd' => $end,
            'todaySchedule' => $schedule->get($today->toDateString()),
            'todayEvent' => TimeEvent::query()->where('company_id', $company->id)->where('worker_id', $worker->id)
                ->where('status', 'valid')->whereNull('voided_at')->whereDate('occurred_local_date', $today->toDateString())
                ->orderByDesc('occurred_at_utc')->first(),
            'plannedMinutes' => $schedule->sum('required_minutes'),
            'calculatedMinutes' => $days->sum(fn ($day) => $day->activeCalculation?->total_work_minutes ?? 0),
            'pendingDays' => $days->whereIn('status', [WorkDay::STATUS_PENDING, WorkDay::STATUS_UNDER_REVIEW, WorkDay::STATUS_WITH_ALERTS])->count(),
            'events' => $events, 'alerts' => $alerts,
            'relationship' => $worker->activeEmploymentRelationship()->where('company_id', $company->id)->with('center')->first(),
        ];
    }
}
