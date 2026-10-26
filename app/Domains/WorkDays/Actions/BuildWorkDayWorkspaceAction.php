<?php

namespace App\Domains\WorkDays\Actions;

use App\Domains\LegalRules\Actions\ResolveLegalRuleVersionForDateAction;
use App\Domains\Organization\Support\ScopedOperationalAccess;
use App\Domains\WorkDays\Support\WorkDayTimeBreakdown;
use App\Domains\WorkDays\Support\WorkDayWorkflow;
use App\Models\Alert;
use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\User;
use App\Models\WorkDay;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class BuildWorkDayWorkspaceAction
{
    public function __construct(private readonly ListWorkDaysAction $list, private readonly ResolveLegalRuleVersionForDateAction $rules) {}

    public function handle(Company $company, User $user, array $filters, string $incidentStatus = 'open', bool $includePrevention = true): array
    {
        Gate::forUser($user)->authorize('viewAny', [WorkDay::class, $company]);
        $filters['viewer'] = $user;
        $filters['workflow_view'] = true;
        $base = $this->list->query($company, $filters);
        $complete = (clone $base)->tap(fn (Builder $query) => WorkDayWorkflow::completed($query));
        $weeklyFilters = $filters;
        if (! empty($filters['date_from'])) {
            $weeklyFilters['date_from'] = CarbonImmutable::parse($filters['date_from'])->startOfWeek()->toDateString();
        }
        if (! empty($filters['date_to'])) {
            $weeklyFilters['date_to'] = CarbonImmutable::parse($filters['date_to'])->endOfWeek()->toDateString();
        }
        $weeklyBase = $this->list->query($company, $weeklyFilters);
        $incidents = Alert::query()->with(['worker', 'workDay.center', 'workDay.activeCalculation', 'alertType', 'resolver'])
            ->where('company_id', $company->id)->whereNotIn('rule_code', WorkDayWorkflow::ATTENDANCE_RULES)
            ->where(function (Builder $query) use ($complete, $weeklyBase): void {
                $query->where(fn (Builder $weekly) => $weekly->whereIn('rule_code', WorkDayWorkflow::WEEKLY_RULES)
                    ->whereIn('work_day_id', (clone $weeklyBase)->reorder()->select('id')))
                    ->orWhereIn('work_day_id', (clone $complete)->reorder()->select('id'));
            });
        $allIncidents = clone $incidents;
        $incidentCases = app(BuildIncidentCasesAction::class)->handle($incidents, $incidentStatus, $filters['incident_rule'] ?? null);
        $incidents->when($filters['incident_rule'] ?? null, fn (Builder $query, $rule) => $query->where('rule_code', $rule));
        $incidents->when($incidentStatus === 'open', fn (Builder $query) => $query->whereIn('status', Alert::OPEN_STATUSES))
            ->when($incidentStatus === 'reviewed', fn (Builder $query) => $query->whereNotIn('status', Alert::OPEN_STATUSES));

        $incidentRows = $incidents->orderByDesc('detected_at')->orderBy('id')->paginate(15, ['*'], 'incidentPage');
        foreach ($incidentRows as $alert) {
            $weekly = in_array($alert->rule_code, WorkDayWorkflow::WEEKLY_RULES, true);
            $weekStart = CarbonImmutable::parse($alert->workDay->work_date)->startOfWeek();
            $weekFilters = array_replace($filters, ['date_from' => $weekStart->toDateString(), 'date_to' => $weekStart->endOfWeek()->toDateString(), 'worker_id' => $alert->worker_id]);
            $weekDays = $weekly ? $this->list->query($company, $weekFilters)->get() : collect();
            $alert->setAttribute('weekly_case', $weekly);
            $alert->setAttribute('case_period', $weekly ? $weekStart->format('d/m/Y').' – '.$weekStart->endOfWeek()->format('d/m/Y') : $alert->workDay->work_date->format('d/m/Y'));
            $alert->setAttribute('provisional', $weekly && ($weekStart->endOfWeek()->gt(CarbonImmutable::now($company->timezone))
                || $weekDays->contains(fn (WorkDay $day) => ! WorkDayWorkflow::isCompleted($day))));
        }

        return [
            'total' => (clone $base)->count(),
            'completed' => (clone $complete)->count(),
            'pending' => (clone $base)->count() - (clone $complete)->count(),
            'open_incidents' => $allIncidents->whereIn('status', Alert::OPEN_STATUSES)->count(),
            'incidents' => $incidentRows,
            'incident_cases' => $incidentCases,
            'prevention' => $includePrevention ? $this->prevention($company, $user, $filters) : null,
        ];
    }

    private function prevention(Company $company, User $user, array $filters): array
    {
        $timezone = $company->setting?->default_timezone ?: $company->timezone;
        $now = CarbonImmutable::now($timezone);
        $reference = CarbonImmutable::parse(($filters['date_to'] ?? null) ?: $now->toDateString(), $timezone)->min($now);
        $start = $reference->startOfWeek();
        $end = $start->endOfWeek();
        $weekFilters = array_replace($filters, ['date_from' => $start->toDateString(), 'date_to' => $reference->toDateString()]);
        $days = $this->list->query($company, $weekFilters)->get()->filter(fn (WorkDay $day) => Gate::forUser($user)->allows('view', $day));
        $ordinaryLimit = (int) ($this->rules->handle('maximum_weekly_hours', $start)?->value['minutes'] ?? 0);
        $extraLimit = (int) ($this->rules->handle('weekly_overtime_double_limit', $start)?->value['minutes'] ?? 0);
        $rows = [];
        foreach ($days->groupBy('worker_id') as $workerDays) {
            $first = $workerDays->first();
            $ordinary = (int) $workerDays->sum(fn (WorkDay $day) => WorkDayTimeBreakdown::weeklyBase($day->activeCalculation));
            $extra = (int) $workerDays->sum(fn (WorkDay $day) => $day->activeCalculation?->overtime_minutes ?? 0);
            $remaining = DailyScheduleAssignment::query()->with('employmentRelationship')
                ->where('company_id', $company->id)->whereIn('employment_relationship_id', $workerDays->pluck('employment_relationship_id'))
                ->whereDate('work_date', '>', $reference->toDateString())->whereDate('work_date', '<=', $end->toDateString())
                ->whereHas('scheduleBatch', fn (Builder $query) => $query->where('status', 'published'))
                ->whereNotExists(function ($absence) use ($company): void {
                    $absence->selectRaw('1')->from('attendance_incidents')->where('company_id', $company->id)
                        ->whereColumn('employment_relationship_id', 'daily_schedule_assignments.employment_relationship_id')
                        ->where('status', 'approved')->whereColumn('start_date', '<=', 'daily_schedule_assignments.work_date')
                        ->whereColumn('end_date', '>=', 'daily_schedule_assignments.work_date');
                })
                ->whereIn('day_type', ['shift', 'flexible', 'on_call'])
                ->when($filters['center_id'] ?? null, fn (Builder $query, $id) => $query->whereHas('employmentRelationship', fn (Builder $relationship) => $relationship->where('center_id', $id)))
                ->get()->filter(fn ($assignment) => $assignment->employmentRelationship
                    && app(ScopedOperationalAccess::class)->canConsultRelationship($user, $company, $assignment->employmentRelationship, $assignment->work_date->toDateString()));
            $planned = (int) $remaining->sum('required_minutes');
            $observed = (int) $workerDays->sum(fn (WorkDay $day) => $day->activeCalculation?->total_work_minutes ?? 0);
            $nearExtra = $extraLimit > 0 && $extra >= $extraLimit * .8 && $extra <= $extraLimit;
            $nearOrdinary = $ordinaryLimit > 0 && $ordinary >= $ordinaryLimit * .8 && $ordinary <= $ordinaryLimit;
            // Total scheduled work is a projection, never an authorization or a legal split.
            $unknownDays = $remaining->whereNull('required_minutes')->count();
            $projected = $planned > 0 && $unknownDays === 0 ? $observed + $planned : null;
            $projectedRisk = $ordinaryLimit > 0 && $projected !== null && $projected > $ordinaryLimit;
            $exceeded = ($extraLimit > 0 && $extra > $extraLimit) || ($ordinaryLimit > 0 && $ordinary > $ordinaryLimit);
            if ($exceeded || (! $nearExtra && ! $nearOrdinary && ! $projectedRisk)) {
                continue;
            }
            $rows[] = [
                'worker_id' => $first->worker_id, 'name' => $first->worker?->full_name,
                'code' => $first->worker?->employee_code,
                'ordinary_minutes' => $ordinary, 'overtime_minutes' => $extra,
                'ordinary_limit_minutes' => $ordinaryLimit, 'overtime_limit_minutes' => $extraLimit,
                'ordinary_margin_minutes' => $ordinaryLimit > 0 ? max(0, $ordinaryLimit - $ordinary) : null,
                'overtime_margin_minutes' => $extraLimit > 0 ? max(0, $extraLimit - $extra) : null,
                'remaining_days' => $remaining->pluck('work_date')->map(fn ($date) => $date->toDateString())->unique()->count(),
                'planned_minutes' => $planned, 'projected_total_minutes' => $projected,
                'unknown_schedule_days' => $unknownDays,
                'pending_days' => $workerDays->filter(fn (WorkDay $day) => ! WorkDayWorkflow::isCompleted($day))->count(),
                'already_exceeded' => $exceeded,
            ];
        }

        return ['start' => $start->toDateString(), 'end' => $end->toDateString(), 'as_of' => $reference->toDateString(), 'people' => $rows];
    }
}
