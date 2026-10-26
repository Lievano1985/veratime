<?php

namespace App\Domains\Dashboard\Actions;

use App\Domains\LegalRules\Actions\ResolveLegalRuleVersionForDateAction;
use App\Domains\Organization\Support\ScopedOperationalAccess;
use App\Domains\WorkDays\Actions\BuildWorkDayWorkspaceAction;
use App\Domains\WorkDays\Support\ComplianceCalculationReadiness;
use App\Domains\WorkDays\Support\WorkDayTimeBreakdown;
use App\Domains\WorkDays\Support\WorkDayWorkflow;
use App\Models\Alert;
use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\EmploymentRelationship;
use App\Models\EmploymentUnitAssignment;
use App\Models\LegalRuleVersion;
use App\Models\OrganizationalUnit;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\WorkDay;
use App\Support\RoleKey;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BuildOperationalDashboardAction
{
    private const WEEKLY_RULE_CODES = [
        'weekly_rest_missing',
        'weekly_hours_exceeded',
        'weekly_overtime_exceeded',
        'weekly_overtime_days_exceeded',
        'weekly_sunday_work',
    ];

    public function __construct(
        private readonly ScopedOperationalAccess $scopedAccess,
        private readonly ResolveLegalRuleVersionForDateAction $legalRules,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(Company $company, User $user, string $date, ?int $centerId = null, ?string $complianceWeek = null): array
    {
        $timezone = $company->setting?->default_timezone ?: $company->timezone;
        $localDate = CarbonImmutable::parse($date, $timezone)->toDateString();
        $currentWeekStart = CarbonImmutable::now($timezone)->startOfWeek();
        $currentWeekEnd = $currentWeekStart->endOfWeek();
        $currentYearStart = $currentWeekStart->startOfYear();
        $trendWeekStarts = $this->weekStarts($currentYearStart->startOfWeek(CarbonInterface::MONDAY), $currentWeekStart);
        $analysisStart = $currentYearStart;
        $analysisEnd = $currentWeekEnd->toDateString();
        $access = $this->visibleAccess($company, $user, $localDate);
        $coverageCenterId = $centerId;

        if ($centerId && $access['filter_center_ids'] !== null && ! in_array($centerId, $access['filter_center_ids'], true)) {
            $centerId = null;
            $access['center_ids'] = [];
            $access['relationship_ids'] = [];
            $access['organizational_unit_ids'] = [];
        }

        $relationships = EmploymentRelationship::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->whereHas('worker', fn (Builder $query) => $query->where('status', 'active'))
            ->whereDate('started_at', '<=', $localDate)
            ->where(fn (Builder $query) => $query->whereNull('ended_at')->orWhereDate('ended_at', '>=', $localDate));
        $this->applyAccess($relationships, $access, $centerId);

        $activeWorkerCount = (clone $relationships)->distinct('worker_id')->count('worker_id');
        $relationshipIds = (clone $relationships)->pluck('id');
        $now = CarbonImmutable::now($timezone);
        $isCurrentOperationalDate = $localDate === $now->toDateString();

        $todayLastEvents = TimeEvent::query()
            ->where('company_id', $company->id)
            ->whereIn('employment_relationship_id', $relationshipIds)
            ->where('status', 'valid')
            ->whereIn('event_type', ['clock_in', 'clock_out', 'break_start', 'break_end'])
            ->whereDate('occurred_local_date', $localDate)
            ->orderByDesc('occurred_at_utc')
            ->get(['worker_id', 'event_type'])
            ->unique('worker_id');

        // An event from the preceding day only remains relevant if a published work
        // segment started yesterday and is still in progress (an overnight shift).
        // This prevents unfinished daytime records from being presented as working now.
        $overnightRelationshipIds = DailyScheduleAssignment::query()
            ->where('company_id', $company->id)
            ->whereIn('employment_relationship_id', $relationshipIds)
            ->whereDate('work_date', CarbonImmutable::parse($localDate, $timezone)->subDay()->toDateString())
            ->whereIn('day_type', ['shift', 'flexible', 'on_call'])
            ->whereHas('segments', fn (Builder $segments) => $segments
                ->where('segment_type', 'work')
                ->where('starts_at_utc', '<=', $now->utc())
                ->where('ends_at_utc', '>', $now->utc()))
            ->pluck('employment_relationship_id');
        $overnightLastEvents = TimeEvent::query()
            ->where('company_id', $company->id)
            ->whereIn('employment_relationship_id', $overnightRelationshipIds)
            ->where('status', 'valid')
            ->whereIn('event_type', ['clock_in', 'clock_out', 'break_start', 'break_end'])
            ->whereDate('occurred_local_date', CarbonImmutable::parse($localDate, $timezone)->subDay()->toDateString())
            ->orderByDesc('occurred_at_utc')
            ->get(['worker_id', 'event_type'])
            ->unique('worker_id');
        $lastEvents = $todayLastEvents->concat($overnightLastEvents)->unique('worker_id');

        $workingNow = $isCurrentOperationalDate
            ? $lastEvents->whereIn('event_type', ['clock_in', 'break_end'])->count()
            : 0;
        $onBreak = $isCurrentOperationalDate
            ? $lastEvents->where('event_type', 'break_start')->count()
            : 0;

        $scheduled = DailyScheduleAssignment::query()
            ->where('company_id', $company->id)
            ->whereDate('work_date', $localDate)
            ->whereIn('day_type', ['shift', 'flexible', 'on_call'])
            ->whereIn('employment_relationship_id', $relationshipIds)
            ->get(['employment_relationship_id', 'window_start_local_time']);
        $clockedInRelationships = TimeEvent::query()
            ->where('company_id', $company->id)
            ->whereIn('employment_relationship_id', $scheduled->pluck('employment_relationship_id'))
            ->where('status', 'valid')
            ->where('event_type', 'clock_in')
            ->whereDate('occurred_local_date', $localDate)
            ->pluck('employment_relationship_id')
            ->flip();
        $tolerance = max(0, (int) ($company->setting?->late_arrival_tolerance_minutes ?? 0));
        $absencePending = 0;
        $pendingEntry = 0;

        if ($isCurrentOperationalDate) {
            foreach ($scheduled as $assignment) {
                if ($clockedInRelationships->has($assignment->employment_relationship_id)) {
                    continue;
                }

                if (! $assignment->window_start_local_time) {
                    $pendingEntry++;

                    continue;
                }

                $threshold = CarbonImmutable::parse("{$localDate} {$assignment->window_start_local_time}", $timezone)->addMinutes($tolerance);
                $now->greaterThan($threshold) ? $absencePending++ : $pendingEntry++;
            }
        }

        $openAlerts = fn (): Builder => Alert::query()
            ->with('alertType:id,category')
            ->where('company_id', $company->id)
            ->whereIn('status', Alert::OPEN_STATUSES)
            ->tap(fn (Builder $query) => $this->applyAlertScope($query, $centerId, $access));

        $pendingAlerts = $openAlerts()
            ->whereNotIn('rule_code', self::WEEKLY_RULE_CODES)
            ->where(fn (Builder $query) => $query->whereIn('rule_code', WorkDayWorkflow::ATTENDANCE_RULES)
                ->orWhereHas('workDay', fn (Builder $day) => WorkDayWorkflow::completed($day)))
            ->get(['id', 'alert_type_id', 'rule_code', 'severity']);
        $currentAlerts = $openAlerts()
            ->whereHas('workDay', fn (Builder $workDay) => $workDay->whereDate('work_date', $localDate))
            ->get(['id', 'alert_type_id', 'rule_code', 'severity']);
        $historicalAlerts = Alert::query()
            ->with(['alertType:id,name', 'workDay:id,work_date'])
            ->where('company_id', $company->id)
            ->where(function (Builder $query) use ($analysisStart, $analysisEnd): void {
                $query->whereHas('workDay', fn (Builder $workDay) => $workDay
                    ->whereDate('work_date', '>=', $analysisStart->toDateString())
                    ->whereDate('work_date', '<=', $analysisEnd))
                    ->orWhere(function (Builder $withoutWorkDay) use ($analysisStart, $analysisEnd): void {
                        $withoutWorkDay
                            ->whereDoesntHave('workDay')
                            ->whereDate('detected_at', '>=', $analysisStart->toDateString())
                            ->whereDate('detected_at', '<=', $analysisEnd);
                    });
            })
            ->tap(fn (Builder $query) => $this->applyAlertScope($query, $centerId, $access))
            ->get(['id', 'alert_type_id', 'work_day_id', 'rule_code', 'status', 'title', 'detected_at']);
        $incidenceTrends = $this->incidenceTrends($historicalAlerts, $trendWeekStarts, $currentYearStart, $timezone);
        $laborCompliance = $this->laborCompliance($company, $centerId, $access, $localDate, $timezone, $complianceWeek);

        $pendingCountsByRule = $pendingAlerts->countBy('rule_code');
        $manualEntries = TimeEvent::query()
            ->where('company_id', $company->id)
            ->whereIn('employment_relationship_id', $relationshipIds)
            ->where('source', 'admin_manual')
            ->whereNotIn('status', ['voided', 'ignored'])
            ->whereDate('occurred_local_date', $localDate)
            ->count();
        $segments = [
            'attendance' => $currentAlerts->filter(fn (Alert $alert) => in_array($alert->alertType?->category, ['attendance', 'event'], true))->count(),
            'work_day' => $currentAlerts->filter(fn (Alert $alert) => in_array($alert->alertType?->category, ['daily', 'weekly'], true))->count(),
            'rest' => $currentAlerts->filter(fn (Alert $alert) => $alert->alertType?->category === 'rest')->count(),
            'evidence' => $manualEntries,
        ];

        $workspace = app(BuildWorkDayWorkspaceAction::class)->handle($company, $user, [
            'date_from' => $localDate, 'date_to' => $localDate,
            'center_ids' => $access['center_ids'], 'relationship_ids' => $access['relationship_ids'],
            'center_id' => $centerId,
        ]);

        return [
            'attendance_coverage' => app(BuildAttendanceCoverageAction::class)->handle($company, $user, $localDate, $coverageCenterId),
            'workspace' => $workspace,
            'date' => $localDate,
            'timezone' => $timezone,
            'generated_at' => CarbonImmutable::now($timezone)->toIso8601String(),
            'access' => $access,
            'metrics' => [
                'active_workers' => $activeWorkerCount,
                'working_now' => $workingNow,
                'on_break' => $onBreak,
                'absence_pending' => $absencePending,
                'pending_entry' => $pendingEntry,
                'completed_work_days' => $workspace['completed'],
            ],
            'pending_alerts' => [
                ['key' => 'late_arrival_detected', 'title' => 'Entradas tardías', 'description' => 'Incidencias pendientes de revisión por entrada posterior al horario.', 'level' => 'warning', 'count' => (int) ($pendingCountsByRule['late_arrival_detected'] ?? 0)],
                ['key' => 'early_departure_detected', 'title' => 'Salidas anticipadas', 'description' => 'Incidencias pendientes de revisión por salida previa al horario.', 'level' => 'warning', 'count' => (int) ($pendingCountsByRule['early_departure_detected'] ?? 0)],
                ['key' => 'incomplete_work_day', 'title' => 'Jornadas incompletas', 'description' => 'Incidencias pendientes por registros de entrada o salida.', 'level' => 'high', 'count' => (int) ($pendingCountsByRule['incomplete_work_day'] ?? 0)],
                ['key' => 'overtime_detected', 'title' => 'Tiempo extra detectado', 'description' => 'Incidencias pendientes con minutos extraordinarios calculados.', 'level' => 'warning', 'count' => (int) ($pendingCountsByRule['overtime_detected'] ?? 0)],
                ['key' => 'minimum_break_missing', 'title' => 'Pausa mínima no identificada', 'description' => 'Incidencias pendientes por pausa no identificada.', 'level' => 'warning', 'count' => (int) ($pendingCountsByRule['minimum_break_missing'] ?? 0)],
                ['key' => 'daily_limit_exceeded', 'title' => 'Duración de jornada por revisar', 'description' => 'Jornadas pendientes de revisión por superar uno o más límites de duración.', 'level' => 'critical', 'count' => (int) ($pendingCountsByRule['daily_limit_exceeded'] ?? 0)],
                ['key' => 'sunday_work', 'title' => 'Trabajo en domingo', 'description' => 'Incidencias pendientes que incluyen trabajo en domingo.', 'level' => 'warning', 'count' => (int) ($pendingCountsByRule['sunday_work'] ?? 0)],
                ['key' => 'mandatory_rest_work', 'title' => 'Trabajo en descanso obligatorio', 'description' => 'Incidencias pendientes por trabajo en descanso obligatorio.', 'level' => 'high', 'count' => (int) ($pendingCountsByRule['mandatory_rest_work'] ?? 0)],
                ['key' => 'scheduled_rest_work', 'title' => 'Trabajo en día de descanso asignado', 'description' => 'Incidencias pendientes por trabajo en un descanso asignado.', 'level' => 'high', 'count' => (int) ($pendingCountsByRule['scheduled_rest_work'] ?? 0)],
            ],
            'incidence_trends' => $incidenceTrends,
            'worker_incidents' => app(BuildWorkerIncidentRankingAction::class)->handle(
                Alert::query()->where('company_id', $company->id)
                    ->tap(fn (Builder $query) => $this->applyAlertScope($query, $centerId, $access)),
                CarbonImmutable::parse($localDate, $timezone)->min(CarbonImmutable::now($timezone))->startOfDay(),
            ),
            'labor_compliance' => $laborCompliance,
            'segments' => $segments,
        ];
    }

    /**
     * @param  array{center_ids: list<int>|null, relationship_ids: list<int>|null}  $access
     * @return array<string, mixed>
     */
    private function laborCompliance(Company $company, ?int $centerId, array $access, string $localDate, string $timezone, ?string $selectedWeek = null): array
    {
        $referenceDate = CarbonImmutable::parse($localDate, $timezone);
        $weekEnd = $referenceDate->startOfWeek(CarbonInterface::MONDAY)->subDay();
        $weekStart = $weekEnd->startOfWeek(CarbonInterface::MONDAY);
        if ($selectedWeek !== null) {
            $weekStart = CarbonImmutable::parse($selectedWeek, $timezone)->startOfWeek(CarbonInterface::MONDAY);
            $weekEnd = $weekStart->endOfWeek(CarbonInterface::SUNDAY);
        }
        $weeklyRule = $this->legalRules->handle('maximum_weekly_hours', $weekStart);
        $weeklyLimitMinutes = (int) ($weeklyRule?->value['minutes'] ?? 0);
        $weeklyOvertimeRule = $this->legalRules->handle('weekly_overtime_double_limit', $weekStart);
        $weeklyOvertimeLimitMinutes = (int) ($weeklyOvertimeRule?->value['minutes'] ?? 0);
        $currentWeeklyRule = $this->legalRules->handle('maximum_weekly_hours', $referenceDate);
        $nextWeeklyRule = LegalRuleVersion::query()
            ->whereHas('legalRule', fn (Builder $rule) => $rule
                ->where('code', 'maximum_weekly_hours')->where('status', 'active'))
            ->where('status', LegalRuleVersion::STATUS_ACTIVE)
            ->whereDate('effective_from', '>', $referenceDate->toDateString())
            ->orderBy('effective_from')
            ->first(['value', 'effective_from']);
        $calculations = WorkDay::query()
            ->with(['activeCalculation', 'alerts', 'worker:id,full_name,employee_code', 'center:id,name'])
            ->where('company_id', $company->id)
            ->when($centerId, fn (Builder $query) => $query->where('center_id', $centerId))
            ->tap(fn (Builder $query) => $this->applyWorkDayScope($query, $access))
            ->whereDate('work_date', '>=', $weekStart->toDateString())
            ->whereDate('work_date', '<=', $weekEnd->toDateString())
            ->where(fn (Builder $query) => $query
                ->where('expected_work_minutes', '>', 0)
                ->orWhere('valid_time_event_count', '>', 0)
                ->orWhereHas('activeCalculation', fn (Builder $calculation) => $calculation
                    ->where('total_work_minutes', '>', 0)))
            ->get()
            ->groupBy('worker_id');
        $workerMetrics = $calculations->map(fn (Collection $workDays): array => [
            'pending' => $weeklyLimitMinutes <= 0 || $weeklyOvertimeLimitMinutes <= 0
                || $workDays->contains(fn (WorkDay $workDay): bool => ! $this->isComplianceCalculationReady($workDay)),
            'ordinary_minutes' => (int) $workDays->sum(fn (WorkDay $workDay): int => WorkDayTimeBreakdown::weeklyBase($workDay->activeCalculation)),
            'overtime_minutes' => (int) $workDays->sum(fn (WorkDay $workDay): int => (int) $workDay->activeCalculation?->overtime_minutes),
            'has_over_twelve_hour_day' => $workDays
                ->contains(fn (WorkDay $workDay): bool => (int) $workDay->activeCalculation?->total_work_minutes > 720),
            'over_twelve_hour_days' => $workDays
                ->filter(fn (WorkDay $workDay): bool => (int) $workDay->activeCalculation?->total_work_minutes > 720)->count(),
        ]);
        $totalWorkers = $workerMetrics->count();
        $pendingWorkers = $workerMetrics->where('pending', true)->count();
        $evaluatedWorkers = $totalWorkers - $pendingWorkers;
        $hasExcess = fn (array $worker): bool => ($weeklyLimitMinutes > 0 && $worker['ordinary_minutes'] > $weeklyLimitMinutes)
            || ($weeklyOvertimeLimitMinutes > 0 && $worker['overtime_minutes'] > $weeklyOvertimeLimitMinutes)
            || $worker['has_over_twelve_hour_day'];
        $workersWithExcess = $workerMetrics->filter($hasExcess);
        $compliantWorkers = $weeklyLimitMinutes > 0
            ? $workerMetrics->filter(fn (array $worker): bool => ! $worker['pending']
                && $worker['ordinary_minutes'] <= $weeklyLimitMinutes
                && $worker['overtime_minutes'] <= $weeklyOvertimeLimitMinutes
                && ! $worker['has_over_twelve_hour_day'])
                ->count()
            : 0;
        $exceededWorkers = $weeklyLimitMinutes > 0
            ? $workerMetrics->filter(fn (array $worker): bool => $worker['ordinary_minutes'] > $weeklyLimitMinutes)->count()
            : 0;
        $olderOpenAlerts = Alert::query()
            ->with(['worker:id,full_name', 'workDay:id,work_date'])
            ->where('company_id', $company->id)
            ->whereIn('status', Alert::OPEN_STATUSES)
            ->where(function (Builder $query) use ($weekStart): void {
                $query->whereHas('workDay', fn (Builder $workDay) => $workDay
                    ->whereDate('work_date', '<', $weekStart->toDateString()))
                    ->orWhere(function (Builder $withoutWorkDay) use ($weekStart): void {
                        $withoutWorkDay
                            ->whereDoesntHave('workDay')
                            ->whereDate('detected_at', '<', $weekStart->toDateString());
                    });
            })
            ->tap(fn (Builder $query) => $this->applyAlertScope($query, $centerId, $access))
            ->get();
        $people = function (callable $predicate, string $field, int $limit) use ($workerMetrics, $calculations, $weekStart, $weekEnd, $centerId): array {
            return $workerMetrics->filter($predicate)->map(function (array $metrics, int $workerId) use ($calculations, $field, $limit, $weekStart, $weekEnd, $centerId): array {
                $days = $calculations->get($workerId)->sortBy('work_date');
                if ($field === 'daily_minutes') {
                    $days = $days->filter(fn (WorkDay $day): bool => (int) $day->activeCalculation?->total_work_minutes > 720);
                }
                $minutes = $field === 'daily_minutes'
                    ? (int) $days->max(fn (WorkDay $day): int => (int) $day->activeCalculation?->total_work_minutes)
                    : $metrics[$field];

                return [
                    'worker_id' => $workerId,
                    'name' => $days->first()->worker?->full_name ?? 'Trabajador no disponible',
                    'period' => $weekStart->format('d/m/Y').' – '.$weekEnd->format('d/m/Y'),
                    'work_days_url' => route('work-days.index', [
                        'tab' => 'jornadas', 'from' => $weekStart->toDateString(), 'to' => $weekEnd->toDateString(),
                        'worker' => $workerId, 'search' => $days->first()->worker?->employee_code, 'center' => $centerId,
                        'conflicto' => $field === 'daily_minutes' ? 'twelve_hours_exceeded' : null,
                    ]),
                    'minutes' => $minutes,
                    'limit_minutes' => $limit,
                    'excess_minutes' => max(0, $minutes - $limit),
                    'pending' => $metrics['pending'],
                    'days_caption' => $field === 'daily_minutes' ? 'Jornadas mayores a 12 horas de la semana' : 'Jornadas de la semana analizada',
                    'days' => $days->map(fn (WorkDay $day): array => [
                        'id' => $day->id,
                        'date' => $day->work_date->format('d/m/Y'),
                        'center' => $day->center?->name ?? 'Sin centro',
                        'total_minutes' => (int) $day->activeCalculation?->total_work_minutes,
                        'ordinary_minutes' => (int) $day->activeCalculation?->ordinary_minutes,
                        'overtime_minutes' => (int) $day->activeCalculation?->overtime_minutes,
                        'pending' => ! $this->isComplianceCalculationReady($day),
                    ])->values()->all(),
                    'alerts' => [],
                ];
            })->sortBy('name')->values()->all();
        };
        $workerDetails = [
            'ordinary' => $people(fn (array $worker): bool => $weeklyLimitMinutes > 0 && $worker['ordinary_minutes'] > $weeklyLimitMinutes, 'ordinary_minutes', $weeklyLimitMinutes),
            'overtime' => $people(fn (array $worker): bool => $weeklyOvertimeLimitMinutes > 0 && $worker['overtime_minutes'] > $weeklyOvertimeLimitMinutes, 'overtime_minutes', $weeklyOvertimeLimitMinutes),
            'long_days' => $people(fn (array $worker): bool => $worker['has_over_twelve_hour_day'], 'daily_minutes', 720),
            'older_alerts' => $olderOpenAlerts->groupBy(fn (Alert $alert): string => (string) ($alert->worker_id ?? 'unassigned'))
                ->map(fn (Collection $alerts): array => [
                    'worker_id' => $alerts->first()->worker_id,
                    'name' => $alerts->first()->worker?->full_name ?? 'Sin trabajador asociado',
                    'period' => 'Anteriores al '.$weekStart->format('d/m/Y'),
                    'minutes' => null, 'limit_minutes' => null, 'excess_minutes' => null,
                    'pending' => false, 'days' => [],
                    'alerts' => $alerts->map(fn (Alert $alert): array => [
                        'title' => $alert->title,
                        'date' => ($alert->workDay?->work_date ?? $alert->detected_at)?->format('d/m/Y'),
                    ])->values()->all(),
                ])->sortBy('name')->values()->all(),
        ];
        $nextEffectiveFrom = $nextWeeklyRule?->effective_from?->toDateString();

        return [
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekEnd->toDateString(),
            'week_options' => $this->complianceWeekOptions($company, $centerId, $access, $weekStart, $timezone),
            'total_workers' => $totalWorkers,
            'pending_workers' => $pendingWorkers,
            'pending_work_days_url' => route('work-days.index', ['tab' => 'jornadas', 'from' => $weekStart->toDateString(),
                'to' => $weekEnd->toDateString(), 'center' => $centerId, 'conflicto' => 'pending_evaluation']),
            'evaluated_workers' => $evaluatedWorkers,
            'compliant_workers' => $compliantWorkers,
            'review_workers' => $workersWithExcess->count(),
            'pending_workers_with_excess' => $workersWithExcess->where('pending', true)->count(),
            'exceeded_workers' => $exceededWorkers,
            'compliance_percentage' => $evaluatedWorkers > 0 ? (int) round(($compliantWorkers / $totalWorkers) * 100) : null,
            'overtime_limit_exceeded_workers' => $weeklyOvertimeLimitMinutes > 0
                ? $workerMetrics->filter(fn (array $worker): bool => $worker['overtime_minutes'] > $weeklyOvertimeLimitMinutes)
                    ->count()
                : 0,
            'weekly_overtime_limit_minutes' => $weeklyOvertimeLimitMinutes,
            'over_twelve_hours_workers' => $workerMetrics->filter(fn (array $worker): bool => $worker['has_over_twelve_hour_day'])->count(),
            'over_twelve_hour_days' => (int) $workerMetrics->sum('over_twelve_hour_days'),
            'older_open_alerts' => $olderOpenAlerts->count(),
            'worker_details' => $workerDetails,
            'weekly_limit_minutes' => $weeklyLimitMinutes,
            'current_weekly_limit_minutes' => (int) ($currentWeeklyRule?->value['minutes'] ?? 0),
            'next_weekly_limit_minutes' => $nextWeeklyRule ? (int) ($nextWeeklyRule->value['minutes'] ?? 0) : null,
            'next_weekly_limit_effective_from' => $nextEffectiveFrom,
            'days_until_next_limit' => $nextEffectiveFrom
                ? max(0, $referenceDate->startOfDay()->diffInDays(CarbonImmutable::parse($nextEffectiveFrom, $timezone), false))
                : null,
        ];
    }

    private function complianceWeekOptions(Company $company, ?int $centerId, array $access, CarbonImmutable $selected, string $timezone): array
    {
        $latest = CarbonImmutable::now($timezone)->startOfWeek(CarbonInterface::MONDAY)->subWeek();
        $earliestDate = WorkDay::query()->where('company_id', $company->id)
            ->when($centerId, fn (Builder $query) => $query->where('center_id', $centerId))
            ->tap(fn (Builder $query) => $this->applyWorkDayScope($query, $access))
            ->whereDate('work_date', '<=', $latest->endOfWeek()->toDateString())->min('work_date');
        $earliest = $earliestDate ? CarbonImmutable::parse($earliestDate, $timezone)->startOfWeek() : $latest;
        $earliest = $earliest->min($latest->subWeeks(11));
        $options = [];
        for ($week = $latest; $week->gte($earliest); $week = $week->subWeek()) {
            $options[] = ['value' => $week->toDateString(),
                'label' => 'Semana '.$week->isoWeek().' · '.$week->format('d/m/Y').' – '.$week->endOfWeek()->format('d/m/Y')];
        }
        if ($selected->lt($earliest)) {
            $options[] = ['value' => $selected->toDateString(),
                'label' => 'Semana '.$selected->isoWeek().' · '.$selected->format('d/m/Y').' – '.$selected->endOfWeek()->format('d/m/Y')];
        }

        return $options;
    }

    private function isComplianceCalculationReady(WorkDay $workDay): bool
    {
        return ComplianceCalculationReadiness::isReady($workDay);
    }

    /** @param array{center_ids: list<int>|null, relationship_ids: list<int>|null} $access */
    private function applyWorkDayScope(Builder $query, array $access): void
    {
        if ($access['center_ids'] === null) {
            return;
        }

        $query->where(fn (Builder $visible) => $visible
            ->whereIn('center_id', $access['center_ids'])
            ->orWhereExists(fn ($assignments) => $assignments
                ->selectRaw('1')
                ->from('employment_unit_assignments')
                ->whereColumn('employment_unit_assignments.company_id', 'work_days.company_id')
                ->whereColumn('employment_unit_assignments.employment_relationship_id', 'work_days.employment_relationship_id')
                ->whereIn('employment_unit_assignments.organizational_unit_id', $access['organizational_unit_ids'])
                ->where('employment_unit_assignments.status', 'active')
                ->whereColumn('employment_unit_assignments.effective_from', '<=', 'work_days.work_date')
                ->where(fn ($dates) => $dates
                    ->whereNull('employment_unit_assignments.effective_to')
                    ->orWhereColumn('employment_unit_assignments.effective_to', '>=', 'work_days.work_date'))));
    }

    /**
     * @param  array{center_ids: list<int>|null, relationship_ids: list<int>|null}  $access
     */
    private function applyAlertScope(Builder $query, ?int $centerId, array $access): void
    {
        if ($centerId) {
            $query->whereHas('workDay', fn (Builder $workDays) => $workDays->where('center_id', $centerId));
        }

        if ($access['center_ids'] === null) {
            return;
        }

        $query->whereHas('workDay', function (Builder $workDays) use ($access): void {
            $workDays->where(function (Builder $visible): void {
                $visible->whereRaw('1 = 0');
            });

            if ($access['center_ids'] !== []) {
                $workDays->orWhereIn('center_id', $access['center_ids']);
            }
            if ($access['relationship_ids'] !== []) {
                $workDays->orWhereIn('employment_relationship_id', $access['relationship_ids']);
            }
        });
    }

    /**
     * @return list<CarbonImmutable>
     */
    private function weekStarts(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $weeks = [];

        for ($week = $start; $week->lessThanOrEqualTo($end); $week = $week->addWeek()) {
            $weeks[] = $week;
        }

        return $weeks;
    }

    /**
     * @param  Collection<int, Alert>  $alerts
     * @param  list<CarbonImmutable>  $weekStarts
     * @return array{year: int, weeks: list<array{key: string, label: string}>, series: list<array{key: string, label: string, color: string, total: int, values: list<int>}>, maximum: int}
     */
    private function incidenceTrends($alerts, array $weekStarts, CarbonImmutable $yearStart, string $timezone): array
    {
        $trendAlerts = $alerts->filter(fn (Alert $alert): bool => CarbonImmutable::parse($alert->detected_at)
            ->setTimezone($timezone)
            ->greaterThanOrEqualTo($yearStart));
        $visibleWeekStarts = collect($weekStarts);
        $firstRecordedWeek = $visibleWeekStarts->first(fn (CarbonImmutable $weekStart): bool => $trendAlerts
            ->contains(fn (Alert $alert): bool => $this->alertWeekKey($alert, $timezone) === $weekStart->toDateString()));
        $lastRecordedWeek = $visibleWeekStarts->last(fn (CarbonImmutable $weekStart): bool => $trendAlerts
            ->contains(fn (Alert $alert): bool => $this->alertWeekKey($alert, $timezone) === $weekStart->toDateString()));

        if ($firstRecordedWeek && $lastRecordedWeek) {
            $visibleWeekStarts = $visibleWeekStarts
                ->filter(fn (CarbonImmutable $weekStart): bool => $weekStart->greaterThanOrEqualTo($firstRecordedWeek) && $weekStart->lessThanOrEqualTo($lastRecordedWeek))
                ->values();
        } else {
            $visibleWeekStarts = collect();
        }

        $alertsByRule = $trendAlerts->groupBy(fn (Alert $alert): string => $alert->rule_code ?: 'alert_type:'.$alert->alert_type_id);
        $colors = ['#4962F6', '#6D83FF', '#8FA4FF', '#B0BFFF'];
        $series = $alertsByRule
            ->sortByDesc(fn ($ruleAlerts) => $ruleAlerts->count())
            ->take(4)
            ->values()
            ->map(function ($ruleAlerts, int $index) use ($visibleWeekStarts, $timezone, $colors): array {
                $byWeek = $ruleAlerts->groupBy(fn (Alert $alert): string => $this->alertWeekKey($alert, $timezone));
                $first = $ruleAlerts->first();

                return [
                    'key' => $first->rule_code ?: 'alert_type:'.$first->alert_type_id,
                    'label' => $first->alertType?->name ?: $first->title,
                    'color' => $colors[$index],
                    'total' => $ruleAlerts->count(),
                    'values' => $visibleWeekStarts
                        ->map(fn (CarbonImmutable $weekStart): int => $byWeek->get($weekStart->toDateString(), collect())->count())
                        ->all(),
                ];
            })
            ->all();
        $values = collect($series)->flatMap(fn (array $line) => $line['values'])->all();
        $maximum = $values === [] ? 1 : max($values);

        return [
            'year' => $yearStart->year,
            'weeks' => $visibleWeekStarts->map(fn (CarbonImmutable $weekStart): array => [
                'key' => $weekStart->toDateString(),
                'label' => 'S'.$weekStart->isoWeek(),
            ])->all(),
            'series' => $series,
            'maximum' => $maximum,
        ];
    }

    private function alertWeekKey(Alert $alert, string $timezone): string
    {
        if ($alert->workDay?->work_date) {
            return CarbonImmutable::parse($alert->workDay->work_date, $timezone)
                ->startOfWeek(CarbonInterface::MONDAY)
                ->toDateString();
        }

        return CarbonImmutable::parse($alert->detected_at)
            ->setTimezone($timezone)
            ->startOfWeek(CarbonInterface::MONDAY)
            ->toDateString();
    }

    /** @return array{center_ids: list<int>|null, relationship_ids: list<int>|null, filter_center_ids: list<int>|null, organizational_unit_ids: list<int>} */
    private function visibleAccess(Company $company, User $user, string $date): array
    {
        if (in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true)) {
            return ['center_ids' => null, 'relationship_ids' => null, 'filter_center_ids' => null, 'organizational_unit_ids' => []];
        }

        $scope = $this->scopedAccess->scope($user, $company, $date);
        $unitIds = $scope['organizational_unit_ids'];
        $relationshipIds = $unitIds === [] ? [] : EmploymentUnitAssignment::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->whereIn('organizational_unit_id', $unitIds)
            ->whereDate('effective_from', '<=', $date)
            ->where(fn (Builder $query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))
            ->pluck('employment_relationship_id')->filter()->unique()->values()->all();
        $unitCenterIds = $unitIds === [] ? [] : OrganizationalUnit::query()
            ->where('company_id', $company->id)->whereIn('id', $unitIds)
            ->pluck('center_id')->filter()->unique()->values()->all();

        return [
            'center_ids' => $scope['center_ids'],
            'relationship_ids' => $relationshipIds,
            'organizational_unit_ids' => $unitIds,
            'filter_center_ids' => array_values(array_unique([...$scope['center_ids'], ...$unitCenterIds])),
        ];
    }

    /** @param array{center_ids: list<int>|null, relationship_ids: list<int>|null} $access */
    private function applyAccess(Builder $query, array $access, ?int $centerId): void
    {
        if ($access['center_ids'] !== null) {
            $query->where(function (Builder $visible) use ($access): void {
                if ($access['center_ids'] !== []) {
                    $visible->whereIn('center_id', $access['center_ids']);
                }
                if ($access['relationship_ids'] !== []) {
                    $method = $access['center_ids'] === [] ? 'whereIn' : 'orWhereIn';
                    $visible->{$method}('id', $access['relationship_ids']);
                }
                if ($access['center_ids'] === [] && $access['relationship_ids'] === []) {
                    $visible->whereRaw('1 = 0');
                }
            });
        }

        if ($centerId) {
            $query->where('center_id', $centerId);
        }
    }
}
