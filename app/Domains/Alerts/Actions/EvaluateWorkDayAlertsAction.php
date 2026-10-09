<?php

namespace App\Domains\Alerts\Actions;

use App\Domains\Alerts\Support\AlertTypeCatalog;
use App\Models\Alert;
use App\Models\AlertType;
use App\Models\Company;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class EvaluateWorkDayAlertsAction
{
    private const WEEKLY_REST_MISSING = 'weekly_rest_missing';

    private const LEGACY_SIX_CONSECUTIVE_DAYS = 'six_consecutive_days';

    private const WEEKLY_RULES = [
        self::WEEKLY_REST_MISSING,
        'weekly_hours_exceeded',
        'weekly_overtime_exceeded',
        'weekly_overtime_days_exceeded',
        'weekly_sunday_work',
    ];

    private const DAILY_OVERTIME_REVIEW_MINUTES = 180;

    private const LONG_WORK_DAY_REVIEW_MINUTES = 600;

    private const MINIMUM_BREAK_MINUTES = 30;

    private const BREAK_REVIEW_TRIGGER_MINUTES = 360;

    private const WEEKLY_OVERTIME_REVIEW_MINUTES = 540;

    private const WEEKLY_OVERTIME_DAYS_REVIEW_LIMIT = 3;

    /**
     * @return array{created_or_updated: int, closed: int, open: int}
     */
    public function handle(Company $company, WorkDay $workDay): array
    {
        if ($workDay->company_id !== $company->id) {
            throw new \InvalidArgumentException('La jornada debe pertenecer a la empresa activa.');
        }

        return DB::transaction(function () use ($company, $workDay): array {
            $workDay = WorkDay::query()
                ->with(['activeCalculation'])
                ->where('company_id', $company->id)
                ->lockForUpdate()
                ->findOrFail($workDay->id);
            $calculation = $workDay->activeCalculation;
            $triggered = $this->triggeredAlerts($workDay, $calculation);
            $triggeredCodes = array_keys($triggered);
            $closed = $this->closeStaleAlerts($company, $workDay, $triggeredCodes);
            $closed += $this->closeStaleWeeklyAlerts($company, $workDay, $triggeredCodes);
            $closed += $this->closeLegacyWeeklyRestAlerts($company);
            $createdOrUpdated = 0;

            foreach ($triggered as $code => $payload) {
                $type = $this->alertType($code);
                $representativeWorkDay = $this->representativeWorkDay($company, $workDay, $code, $payload);
                $representativeCalculationId = $representativeWorkDay->active_calculation_id ?: $calculation?->id;

                $alert = Alert::query()->updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'fingerprint' => $this->fingerprint($workDay, $code),
                    ],
                    [
                        'alert_type_id' => $type->id,
                        'worker_id' => $workDay->worker_id,
                        'work_day_id' => $representativeWorkDay->id,
                        'work_day_calculation_id' => $representativeCalculationId,
                        'severity' => $payload['severity'] ?? $type->default_severity,
                        'status' => Alert::STATUS_NEW,
                        'title' => $payload['title'],
                        'description' => $payload['description'],
                        'rule_code' => $code,
                        'detected_at' => CarbonImmutable::now('UTC'),
                        'resolution' => null,
                        'resolved_by' => null,
                        'resolved_at' => null,
                        'metadata' => $payload['metadata'],
                    ],
                );

                $alert->company()->associate($company);
                $alert->alertType()->associate($type);
                $alert->save();
                $createdOrUpdated++;
            }

            $open = Alert::query()
                ->where('company_id', $company->id)
                ->where('work_day_id', $workDay->id)
                ->whereIn('status', Alert::OPEN_STATUSES)
                ->count();

            if ($open > 0) {
                $workDay->forceFill(['status' => WorkDay::STATUS_WITH_ALERTS])->save();
            } elseif ($calculation instanceof WorkDayCalculation) {
                $workDay->forceFill(['status' => WorkDay::STATUS_CALCULATED])->save();
            }

            return [
                'created_or_updated' => $createdOrUpdated,
                'closed' => $closed,
                'open' => $open,
            ];
        });
    }

    /**
     * @return array<string, array{title: string, description: string, severity?: string, metadata: array<string, mixed>}>
     */
    private function triggeredAlerts(WorkDay $workDay, ?WorkDayCalculation $calculation): array
    {
        $alerts = [];

        if ($this->isScheduledAbsence($workDay, $calculation)) {
            $alerts['scheduled_absence'] = [
                'title' => 'Falta',
                'description' => 'La jornada estaba programada y no tiene eventos validos de asistencia.',
                'metadata' => [
                    'expected_work_minutes' => $workDay->expected_work_minutes,
                    'valid_time_event_count' => $workDay->valid_time_event_count,
                ],
            ];
        }

        if (! $calculation) {
            return $alerts;
        }

        $issues = $calculation->result_snapshot['issues'] ?? [];

        if ($workDay->status === WorkDay::STATUS_UNDER_REVIEW || $issues !== []) {
            $alerts['incomplete_work_day'] = [
                'title' => 'Jornada incompleta',
                'description' => 'La jornada tiene eventos validos, pero requiere revision por secuencia incompleta.',
                'metadata' => ['issues' => $issues],
            ];
        }

        if ($calculation->overtime_minutes > 0) {
            $alerts['overtime_detected'] = [
                'title' => 'Tiempo extra detectado',
                'description' => "Se calcularon {$calculation->overtime_minutes} minutos extraordinarios.",
                'metadata' => ['overtime_minutes' => $calculation->overtime_minutes],
            ];
        }

        if ($calculation->late_arrival_minutes > 0) {
            $alerts['late_arrival_detected'] = [
                'title' => 'Retardo',
                'description' => "Se calcularon {$calculation->late_arrival_minutes} minutos de retardo.",
                'metadata' => ['late_arrival_minutes' => $calculation->late_arrival_minutes],
            ];
        }

        if ($calculation->early_departure_minutes > 0) {
            $alerts['early_departure_detected'] = [
                'title' => 'Salida anticipada',
                'description' => "Se calcularon {$calculation->early_departure_minutes} minutos de salida anticipada.",
                'metadata' => ['early_departure_minutes' => $calculation->early_departure_minutes],
            ];
        }

        if ($calculation->total_work_minutes > 720) {
            $alerts['twelve_hours_exceeded'] = [
                'title' => 'Jornada mayor a 12 horas',
                'description' => "La jornada acumula {$calculation->total_work_minutes} minutos trabajados.",
                'metadata' => ['total_work_minutes' => $calculation->total_work_minutes],
            ];
        }

        if ($calculation->sunday_minutes > 0) {
            $alerts['sunday_work'] = [
                'title' => 'Trabajo en domingo',
                'description' => "Se calcularon {$calculation->sunday_minutes} minutos trabajados en domingo.",
                'metadata' => ['sunday_minutes' => $calculation->sunday_minutes],
            ];
        }

        if ($calculation->mandatory_rest_minutes > 0) {
            $alerts['mandatory_rest_work'] = [
                'title' => 'Trabajo en descanso obligatorio',
                'description' => "Se calcularon {$calculation->mandatory_rest_minutes} minutos en descanso obligatorio.",
                'metadata' => ['mandatory_rest_minutes' => $calculation->mandatory_rest_minutes],
            ];
        }

        $dailyLimit = (int) data_get($calculation->result_snapshot, 'ordinary_overtime.daily_limit_minutes', 0);

        if ($dailyLimit > 0 && $calculation->total_work_minutes > $dailyLimit) {
            $alerts['daily_limit_exceeded'] = [
                'title' => 'Jornada excedida',
                'description' => 'El tiempo trabajado supera el límite diario aplicable y requiere revisión.',
                'metadata' => [
                    'total_work_minutes' => $calculation->total_work_minutes,
                    'daily_limit_minutes' => $dailyLimit,
                ],
            ];
        }

        if ($calculation->overtime_minutes > self::DAILY_OVERTIME_REVIEW_MINUTES) {
            $alerts['daily_overtime_over_three_hours'] = [
                'title' => 'Tiempo extra diario superior a tres horas',
                'description' => 'La jornada acumula más de tres horas extraordinarias y requiere revisión.',
                'metadata' => [
                    'overtime_minutes' => $calculation->overtime_minutes,
                    'threshold_minutes' => self::DAILY_OVERTIME_REVIEW_MINUTES,
                ],
            ];
        }

        if ($calculation->total_work_minutes > self::LONG_WORK_DAY_REVIEW_MINUTES) {
            $alerts['long_work_day'] = [
                'title' => 'Jornada larga',
                'description' => 'La jornada supera diez horas trabajadas y requiere revisión preventiva.',
                'metadata' => [
                    'total_work_minutes' => $calculation->total_work_minutes,
                    'threshold_minutes' => self::LONG_WORK_DAY_REVIEW_MINUTES,
                ],
            ];
        }

        if ($calculation->total_work_minutes >= self::BREAK_REVIEW_TRIGGER_MINUTES
            && $calculation->break_minutes < self::MINIMUM_BREAK_MINUTES) {
            $alerts['minimum_break_missing'] = [
                'title' => 'Pausa mínima no identificada',
                'description' => 'No se identificó una pausa acumulada de al menos treinta minutos en la jornada.',
                'metadata' => [
                    'total_work_minutes' => $calculation->total_work_minutes,
                    'break_minutes' => $calculation->break_minutes,
                    'minimum_break_minutes' => self::MINIMUM_BREAK_MINUTES,
                ],
            ];
        }

        if ((bool) data_get($calculation->result_snapshot, 'special_legal_cases.scheduled_rest.worked')) {
            $alerts['scheduled_rest_work'] = [
                'title' => 'Trabajo en día de descanso asignado',
                'description' => 'La jornada registra trabajo en un día publicado como descanso.',
                'metadata' => (array) data_get($calculation->result_snapshot, 'special_legal_cases.scheduled_rest', []),
            ];
        }

        $alerts = array_merge($alerts, $this->minorAlerts($workDay, $calculation));

        $weeklyRest = data_get($calculation->result_snapshot, 'special_legal_cases.weekly_rest', []);

        if ((bool) data_get($weeklyRest, 'requires_review')) {
            $weekStart = $this->weekStart($workDay, $weeklyRest);
            $weekEnd = $this->weekEnd($weekStart);

            $alerts[self::WEEKLY_REST_MISSING] = [
                'title' => 'Semana sin descanso detectado',
                'description' => 'La semana natural muestra trabajo en todos los dias y requiere revision.',
                'metadata' => array_merge($weeklyRest, [
                    'week_start' => $weekStart,
                    'week_end' => $weekEnd,
                ]),
            ];
        }

        return array_merge($alerts, $this->weeklyAlerts($workDay));
    }

    private function isScheduledAbsence(WorkDay $workDay, ?WorkDayCalculation $calculation): bool
    {
        return ! $calculation
            && $workDay->valid_time_event_count === 0
            && $workDay->schedule_status === WorkDay::SCHEDULE_STATUS_SCHEDULED
            && $workDay->day_type === 'shift'
            && (int) $workDay->expected_work_minutes > 0;
    }

    private function closeLegacyWeeklyRestAlerts(Company $company): int
    {
        $typeId = AlertType::query()
            ->where('code', self::LEGACY_SIX_CONSECUTIVE_DAYS)
            ->value('id');

        if (! $typeId) {
            return 0;
        }

        return Alert::query()
            ->where('company_id', $company->id)
            ->where('alert_type_id', $typeId)
            ->whereIn('status', Alert::OPEN_STATUSES)
            ->update([
                'status' => Alert::STATUS_CLOSED,
                'resolution' => 'Cerrada automaticamente por cambio a alerta semanal unica.',
                'resolved_at' => CarbonImmutable::now('UTC'),
            ]);
    }

    /**
     * @param  list<string>  $triggeredCodes
     */
    private function closeStaleAlerts(Company $company, WorkDay $workDay, array $triggeredCodes): int
    {
        $typeIds = AlertType::query()
            ->whereIn('code', AlertTypeCatalog::managedCodes())
            ->whereNotIn('code', self::WEEKLY_RULES)
            ->when($triggeredCodes !== [], fn ($query) => $query->whereNotIn('code', $triggeredCodes))
            ->pluck('id');

        if ($typeIds->isEmpty()) {
            return 0;
        }

        return Alert::query()
            ->where('company_id', $company->id)
            ->where('work_day_id', $workDay->id)
            ->whereIn('alert_type_id', $typeIds)
            ->whereIn('status', Alert::OPEN_STATUSES)
            ->update([
                'status' => Alert::STATUS_CLOSED,
                'resolution' => 'Cerrada automaticamente por recalculo.',
                'resolved_at' => CarbonImmutable::now('UTC'),
            ]);
    }

    /**
     * @param  list<string>  $triggeredCodes
     */
    private function closeStaleWeeklyAlerts(Company $company, WorkDay $workDay, array $triggeredCodes): int
    {
        $weekStart = $this->weekStart($workDay, []);
        $weekEnd = $this->weekEnd($weekStart);
        $triggeredWeeklyCodes = array_values(array_intersect($triggeredCodes, self::WEEKLY_RULES));

        return Alert::query()
            ->where('company_id', $company->id)
            ->where('worker_id', $workDay->worker_id)
            ->whereIn('rule_code', self::WEEKLY_RULES)
            ->whereHas('workDay', fn ($query) => $query
                ->where('employment_relationship_id', $workDay->employment_relationship_id)
                ->whereDate('work_date', '>=', $weekStart)
                ->whereDate('work_date', '<=', $weekEnd))
            ->when($triggeredWeeklyCodes !== [], fn ($query) => $query->whereNotIn('rule_code', $triggeredWeeklyCodes))
            ->whereIn('status', Alert::OPEN_STATUSES)
            ->update([
                'status' => Alert::STATUS_CLOSED,
                'resolution' => 'Cerrada automáticamente por recálculo semanal.',
                'resolved_at' => CarbonImmutable::now('UTC'),
            ]);
    }

    private function alertType(string $code): AlertType
    {
        $entry = AlertTypeCatalog::entries()[$code];

        return AlertType::query()->updateOrCreate(
            ['code' => $code],
            $entry + ['status' => AlertType::STATUS_ACTIVE],
        );
    }

    private function fingerprint(WorkDay $workDay, string $code): string
    {
        if (in_array($code, self::WEEKLY_RULES, true)) {
            $weeklyRest = data_get($workDay->activeCalculation?->result_snapshot, 'special_legal_cases.weekly_rest', []);
            $weekStart = $this->weekStart($workDay, is_array($weeklyRest) ? $weeklyRest : []);

            return hash('sha256', implode(':', [
                $code,
                $workDay->company_id,
                $workDay->worker_id,
                $workDay->employment_relationship_id,
                $weekStart,
            ]));
        }

        return hash('sha256', "work_day:{$workDay->id}:{$code}");
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function representativeWorkDay(Company $company, WorkDay $workDay, string $code, array $payload): WorkDay
    {
        if (! in_array($code, self::WEEKLY_RULES, true)) {
            return $workDay;
        }

        $weekStart = data_get($payload, 'metadata.week_start') ?: $this->weekStart($workDay, []);

        return $this->representativeWorkDayForWeek($company, $workDay, $weekStart);
    }

    private function representativeWorkDayForWeek(Company $company, WorkDay $workDay, string $weekStart): WorkDay
    {
        return WorkDay::query()
            ->where('company_id', $company->id)
            ->where('worker_id', $workDay->worker_id)
            ->where('employment_relationship_id', $workDay->employment_relationship_id)
            ->whereDate('work_date', $weekStart)
            ->first() ?: $workDay;
    }

    /**
     * @return array<string, array{title: string, description: string, severity?: string, metadata: array<string, mixed>}>
     */
    private function minorAlerts(WorkDay $workDay, WorkDayCalculation $calculation): array
    {
        $workDay->loadMissing('worker');
        $birthDate = $workDay->worker?->birth_date;

        if (! $birthDate) {
            return [];
        }

        $age = CarbonImmutable::parse($birthDate)->diffInYears(CarbonImmutable::parse($workDay->work_date));
        $alerts = [];

        if ($age < 16 && $calculation->total_work_minutes > 360) {
            $alerts['minor_daily_hours_exceeded'] = [
                'title' => 'Persona menor con jornada superior a seis horas',
                'description' => 'La jornada supera seis horas para la edad registrada de la persona trabajadora y requiere revisión.',
                'metadata' => [
                    'age' => $age,
                    'total_work_minutes' => $calculation->total_work_minutes,
                    'maximum_minutes' => 360,
                ],
            ];
        }

        if ($age < 18 && ($calculation->overtime_minutes > 0 || $calculation->classification === WorkDayCalculation::CLASSIFICATION_NOCTURNAL)) {
            $alerts['minor_restricted_work'] = [
                'title' => 'Persona menor con tiempo extra o jornada nocturna',
                'description' => 'La jornada requiere revisión por la edad registrada y sus características de tiempo extra o turno nocturno.',
                'metadata' => [
                    'age' => $age,
                    'overtime_minutes' => $calculation->overtime_minutes,
                    'classification' => $calculation->classification,
                ],
            ];
        }

        return $alerts;
    }

    /**
     * @return array<string, array{title: string, description: string, severity?: string, metadata: array<string, mixed>}>
     */
    private function weeklyAlerts(WorkDay $workDay): array
    {
        $weekStart = $this->weekStart($workDay, []);
        $weekEnd = $this->weekEnd($weekStart);
        $calculations = WorkDay::query()
            ->with('activeCalculation')
            ->where('company_id', $workDay->company_id)
            ->where('worker_id', $workDay->worker_id)
            ->where('employment_relationship_id', $workDay->employment_relationship_id)
            ->whereDate('work_date', '>=', $weekStart)
            ->whereDate('work_date', '<=', $weekEnd)
            ->whereNotNull('active_calculation_id')
            ->orderBy('work_date')
            ->get()
            ->map(fn (WorkDay $day): ?WorkDayCalculation => $day->activeCalculation)
            ->filter(fn (?WorkDayCalculation $calculation): bool => $calculation instanceof WorkDayCalculation
                && $calculation->status === WorkDayCalculation::STATUS_ACTIVE
                && $calculation->classification !== WorkDayCalculation::CLASSIFICATION_PENDING
                && (($calculation->result_snapshot['issues'] ?? []) === [])
                && $calculation->total_work_minutes > 0)
            ->values();

        if ($calculations->isEmpty()) {
            return [];
        }

        $totalWorkMinutes = (int) $calculations->sum('total_work_minutes');
        $overtimeMinutes = (int) $calculations->sum('overtime_minutes');
        $overtimeDays = $calculations->filter(fn (WorkDayCalculation $calculation): bool => $calculation->overtime_minutes > 0)->count();
        $sundaysWorked = $calculations->filter(fn (WorkDayCalculation $calculation): bool => $calculation->sunday_minutes > 0)->count();
        $weeklyLimit = (int) data_get($calculations->first()?->result_snapshot, 'ordinary_overtime.weekly_limit_minutes', 0);
        $metadata = [
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
            'total_work_minutes' => $totalWorkMinutes,
            'overtime_minutes' => $overtimeMinutes,
            'overtime_days' => $overtimeDays,
            'sundays_worked' => $sundaysWorked,
            'weekly_limit_minutes' => $weeklyLimit,
        ];
        $alerts = [];

        if ($weeklyLimit > 0 && $totalWorkMinutes > $weeklyLimit) {
            $alerts['weekly_hours_exceeded'] = [
                'title' => 'Horas semanales superiores al máximo',
                'description' => 'La suma semanal trabajada supera el límite aplicable y requiere revisión.',
                'metadata' => $metadata,
            ];
        }

        if ($overtimeMinutes > self::WEEKLY_OVERTIME_REVIEW_MINUTES) {
            $alerts['weekly_overtime_exceeded'] = [
                'title' => 'Tiempo extra semanal superior a nueve horas',
                'description' => 'La suma semanal de tiempo extraordinario supera nueve horas y requiere revisión.',
                'metadata' => $metadata + ['threshold_minutes' => self::WEEKLY_OVERTIME_REVIEW_MINUTES],
            ];
        }

        if ($overtimeDays > self::WEEKLY_OVERTIME_DAYS_REVIEW_LIMIT) {
            $alerts['weekly_overtime_days_exceeded'] = [
                'title' => 'Más de tres días con tiempo extra',
                'description' => 'La semana concentra tiempo extraordinario en más de tres días y requiere revisión.',
                'metadata' => $metadata + ['threshold_days' => self::WEEKLY_OVERTIME_DAYS_REVIEW_LIMIT],
            ];
        }

        if ($sundaysWorked > 0) {
            $alerts['weekly_sunday_work'] = [
                'title' => 'Domingos trabajados en la semana',
                'description' => 'La semana registra trabajo en domingo para revisión operativa.',
                'metadata' => $metadata,
            ];
        }

        return $alerts;
    }

    /**
     * @param  array<string, mixed>  $weeklyRest
     */
    private function weekStart(WorkDay $workDay, array $weeklyRest): string
    {
        $weekStart = data_get($weeklyRest, 'week_start');

        if (is_string($weekStart) && $weekStart !== '') {
            return CarbonImmutable::parse($weekStart)->toDateString();
        }

        return CarbonImmutable::parse($workDay->work_date)
            ->startOfWeek(CarbonInterface::MONDAY)
            ->toDateString();
    }

    private function weekEnd(string $weekStart): string
    {
        return CarbonImmutable::parse($weekStart)->addDays(6)->toDateString();
    }
}
