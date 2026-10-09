<?php

namespace App\Domains\Dashboard\Actions;

use App\Domains\Organization\Support\ScopedOperationalAccess;
use App\Models\Alert;
use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\EmploymentRelationship;
use App\Models\EmploymentUnitAssignment;
use App\Models\OrganizationalUnit;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use App\Support\RoleKey;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class BuildOperationalDashboardAction
{
    private const WEEKLY_RULE_CODES = [
        'weekly_rest_missing',
        'weekly_hours_exceeded',
        'weekly_overtime_exceeded',
        'weekly_overtime_days_exceeded',
        'weekly_sunday_work',
    ];

    public function __construct(private readonly ScopedOperationalAccess $scopedAccess) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(Company $company, User $user, string $date, ?int $centerId = null): array
    {
        $timezone = $company->setting?->default_timezone ?: $company->timezone;
        $localDate = CarbonImmutable::parse($date, $timezone)->toDateString();
        $weekStart = CarbonImmutable::parse($localDate, $timezone)->startOfWeek()->toDateString();
        $weekEnd = CarbonImmutable::parse($localDate, $timezone)->endOfWeek()->toDateString();
        $access = $this->visibleAccess($company, $user, $localDate);

        if ($centerId && $access['filter_center_ids'] !== null && ! in_array($centerId, $access['filter_center_ids'], true)) {
            $centerId = null;
            $access['center_ids'] = [];
            $access['relationship_ids'] = [];
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

        $lastEvents = TimeEvent::query()
            ->where('company_id', $company->id)
            ->whereIn('employment_relationship_id', $relationshipIds)
            ->where('status', 'valid')
            ->whereIn('event_type', ['clock_in', 'clock_out', 'break_start', 'break_end'])
            ->whereDate('occurred_local_date', '>=', CarbonImmutable::parse($localDate)->subDay()->toDateString())
            ->whereDate('occurred_local_date', '<=', $localDate)
            ->orderByDesc('occurred_at_utc')
            ->get(['worker_id', 'event_type'])
            ->unique('worker_id');

        $workingNow = $lastEvents->whereIn('event_type', ['clock_in', 'break_end'])->count();
        $onBreak = $lastEvents->where('event_type', 'break_start')->count();

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
        $now = CarbonImmutable::now($timezone);
        $tolerance = max(0, (int) ($company->setting?->late_arrival_tolerance_minutes ?? 0));
        $absencePending = 0;
        $pendingEntry = 0;

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

        $alerts = Alert::query()
            ->with('alertType:id,category')
            ->where('company_id', $company->id)
            ->whereIn('status', Alert::OPEN_STATUSES)
            ->where(function (Builder $query) use ($localDate, $weekStart, $weekEnd): void {
                $query->whereDate('detected_at', $localDate)
                    ->orWhere(function (Builder $weekly) use ($weekStart, $weekEnd): void {
                        $weekly->whereIn('rule_code', self::WEEKLY_RULE_CODES)
                            ->whereHas('workDay', fn (Builder $workDays) => $workDays
                                ->whereDate('work_date', '>=', $weekStart)
                                ->whereDate('work_date', '<=', $weekEnd));
                    });
            })
            ->when($centerId, fn (Builder $query) => $query->whereHas('workDay', fn (Builder $workDays) => $workDays->where('center_id', $centerId)))
            ->when($access['center_ids'] !== null, function (Builder $query) use ($access): void {
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
            })
            ->get(['id', 'alert_type_id', 'rule_code', 'severity']);

        $countsByRule = $alerts->countBy('rule_code');
        $weeklyWorkDays = WorkDay::query()
            ->with('activeCalculation:id,work_day_id,status,classification,total_work_minutes,result_snapshot')
            ->where('company_id', $company->id)
            ->whereIn('employment_relationship_id', $relationshipIds)
            ->whereDate('work_date', '>=', $weekStart)
            ->whereDate('work_date', '<=', $weekEnd)
            ->when($centerId, fn (Builder $query) => $query->where('center_id', $centerId))
            ->whereNotNull('active_calculation_id')
            ->get(['id', 'worker_id', 'employment_relationship_id', 'center_id', 'work_date', 'active_calculation_id']);
        $weeklyMinutesByWorker = $weeklyWorkDays
            ->map(fn (WorkDay $workDay): array => [
                'worker_id' => $workDay->worker_id,
                'minutes' => $workDay->activeCalculation instanceof WorkDayCalculation
                    && $workDay->activeCalculation->status === WorkDayCalculation::STATUS_ACTIVE
                    && (($workDay->activeCalculation->result_snapshot['issues'] ?? []) === [])
                    ? $workDay->activeCalculation->total_work_minutes
                    : 0,
            ])
            ->groupBy('worker_id')
            ->map(fn ($days): int => (int) $days->sum('minutes'))
            ->filter(fn (int $minutes): bool => $minutes > 0);
        $weeklyAverageWorkMinutes = $weeklyMinutesByWorker->isEmpty()
            ? 0
            : (int) round($weeklyMinutesByWorker->avg());
        $manualEntries = TimeEvent::query()
            ->where('company_id', $company->id)
            ->whereIn('employment_relationship_id', $relationshipIds)
            ->where('source', 'admin_manual')
            ->whereNotIn('status', ['voided', 'ignored'])
            ->whereDate('occurred_local_date', $localDate)
            ->count();
        $segments = [
            'attendance' => $alerts->filter(fn (Alert $alert) => in_array($alert->alertType?->category, ['attendance', 'event'], true))->count(),
            'work_day' => $alerts->filter(fn (Alert $alert) => in_array($alert->alertType?->category, ['daily', 'weekly'], true))->count(),
            'rest' => $alerts->filter(fn (Alert $alert) => $alert->alertType?->category === 'rest')->count(),
            'evidence' => $manualEntries,
        ];

        return [
            'date' => $localDate,
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
            'timezone' => $timezone,
            'generated_at' => CarbonImmutable::now($timezone)->toIso8601String(),
            'access' => $access,
            'metrics' => [
                'active_workers' => $activeWorkerCount,
                'working_now' => $workingNow,
                'on_break' => $onBreak,
                'absence_pending' => $absencePending,
                'pending_entry' => $pendingEntry,
            ],
            'daily_alerts' => [
                ['key' => 'daily_limit_exceeded', 'title' => 'Jornada excedida', 'description' => 'El tiempo trabajado supera el límite diario aplicable.', 'level' => 'high', 'count' => (int) ($countsByRule['daily_limit_exceeded'] ?? 0)],
                ['key' => 'daily_overtime_over_three_hours', 'title' => 'Tiempo extra diario superior a tres horas', 'description' => 'La jornada acumula más de tres horas extraordinarias.', 'level' => 'high', 'count' => (int) ($countsByRule['daily_overtime_over_three_hours'] ?? 0)],
                ['key' => 'overtime_detected', 'title' => 'Tiempo extra detectado', 'description' => 'La jornada tiene minutos extraordinarios calculados.', 'level' => 'warning', 'count' => (int) ($countsByRule['overtime_detected'] ?? 0)],
                ['key' => 'long_work_day', 'title' => 'Jornada larga', 'description' => 'La jornada trabajada supera diez horas.', 'level' => 'warning', 'count' => (int) ($countsByRule['long_work_day'] ?? 0)],
                ['key' => 'minimum_break_missing', 'title' => 'Pausa mínima no identificada', 'description' => 'No se identificó una pausa acumulada de al menos treinta minutos.', 'level' => 'warning', 'count' => (int) ($countsByRule['minimum_break_missing'] ?? 0)],
                ['key' => 'twelve_hours_exceeded', 'title' => 'Jornada mayor a 12 horas', 'description' => 'El total trabajado supera doce horas en una jornada.', 'level' => 'critical', 'count' => (int) ($countsByRule['twelve_hours_exceeded'] ?? 0)],
                ['key' => 'sunday_work', 'title' => 'Trabajo en domingo', 'description' => 'La jornada incluye tiempo trabajado en domingo.', 'level' => 'warning', 'count' => (int) ($countsByRule['sunday_work'] ?? 0)],
                ['key' => 'mandatory_rest_work', 'title' => 'Trabajo en descanso obligatorio', 'description' => 'La jornada incluye tiempo trabajado en un descanso obligatorio.', 'level' => 'high', 'count' => (int) ($countsByRule['mandatory_rest_work'] ?? 0)],
                ['key' => 'scheduled_rest_work', 'title' => 'Trabajo en día de descanso asignado', 'description' => 'La jornada registra trabajo en un día publicado como descanso.', 'level' => 'high', 'count' => (int) ($countsByRule['scheduled_rest_work'] ?? 0)],
                ['key' => 'minor_daily_hours_exceeded', 'title' => 'Persona menor con jornada superior a seis horas', 'description' => 'Revisión preventiva según la edad registrada.', 'level' => 'critical', 'count' => (int) ($countsByRule['minor_daily_hours_exceeded'] ?? 0)],
                ['key' => 'minor_restricted_work', 'title' => 'Persona menor con tiempo extra o jornada nocturna', 'description' => 'Revisión preventiva según la edad registrada.', 'level' => 'critical', 'count' => (int) ($countsByRule['minor_restricted_work'] ?? 0)],
            ],
            'weekly_alerts' => [
                ['key' => 'weekly_hours_exceeded', 'title' => 'Horas semanales superiores al máximo', 'description' => 'La suma semanal trabajada supera el límite aplicable.', 'level' => 'high', 'count' => (int) ($countsByRule['weekly_hours_exceeded'] ?? 0)],
                ['key' => 'weekly_overtime_exceeded', 'title' => 'Tiempo extra semanal superior a nueve horas', 'description' => 'La suma semanal de tiempo extraordinario supera nueve horas.', 'level' => 'high', 'count' => (int) ($countsByRule['weekly_overtime_exceeded'] ?? 0)],
                ['key' => 'weekly_overtime_days_exceeded', 'title' => 'Más de tres días con tiempo extra', 'description' => 'La semana concentra tiempo extraordinario en más de tres días.', 'level' => 'warning', 'count' => (int) ($countsByRule['weekly_overtime_days_exceeded'] ?? 0)],
                ['key' => 'weekly_rest_missing', 'title' => 'Semana sin descanso detectado', 'description' => 'La semana natural no muestra un día de descanso y requiere revisión.', 'level' => 'high', 'count' => (int) ($countsByRule['weekly_rest_missing'] ?? 0)],
                ['key' => 'weekly_sunday_work', 'title' => 'Domingos trabajados en la semana', 'description' => 'La semana incluye trabajo en domingo.', 'level' => 'warning', 'count' => (int) ($countsByRule['weekly_sunday_work'] ?? 0)],
            ],
            'kpi_alerts' => [
                ['key' => 'late_arrival_detected', 'count' => (int) ($countsByRule['late_arrival_detected'] ?? 0)],
                ['key' => 'early_departure_detected', 'count' => (int) ($countsByRule['early_departure_detected'] ?? 0)],
                ['key' => 'incomplete_work_day', 'count' => (int) ($countsByRule['incomplete_work_day'] ?? 0)],
            ],
            'weekly_average_work_minutes' => $weeklyAverageWorkMinutes,
            'segments' => $segments,
        ];
    }

    /** @return array{center_ids: list<int>|null, relationship_ids: list<int>|null, filter_center_ids: list<int>|null} */
    private function visibleAccess(Company $company, User $user, string $date): array
    {
        if (in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true)) {
            return ['center_ids' => null, 'relationship_ids' => null, 'filter_center_ids' => null];
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
