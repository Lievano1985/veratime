<?php

namespace App\Domains\Alerts\Actions;

use App\Domains\Alerts\Support\AlertTypeCatalog;
use App\Domains\LegalRules\Actions\ResolveLegalRuleVersionForDateAction;
use App\Domains\WorkDays\Actions\ResolveOvertimeReviewMarginAction;
use App\Domains\WorkDays\Support\WorkDayTimeBreakdown;
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

    private const WEEKLY_OVERTIME_DAYS_REVIEW_LIMIT = 3;

    public function __construct(
        private readonly ResolveLegalRuleVersionForDateAction $rules,
        private readonly ResolveOvertimeReviewMarginAction $overtimeMargin,
    ) {}

    /**
     * @return array{created_or_updated: int, closed: int, open: int}
     */
    public function handle(Company $company, WorkDay $workDay, bool $includeCompanyCleanup = true): array
    {
        if ($workDay->company_id !== $company->id) {
            throw new \InvalidArgumentException('La jornada debe pertenecer a la empresa activa.');
        }

        return DB::transaction(function () use ($company, $workDay, $includeCompanyCleanup): array {
            $workDay = WorkDay::query()
                ->with(['activeCalculation'])
                ->where('company_id', $company->id)
                ->lockForUpdate()
                ->findOrFail($workDay->id);
            $calculation = $workDay->activeCalculation;
            $withinMargin = $this->overtimeMargin->handle($company, $workDay)['within_margin'];
            $triggered = $this->triggeredAlerts($workDay, $calculation, $withinMargin);
            $triggeredCodes = array_keys($triggered);
            $closed = $this->closeStaleAlerts($company, $workDay, $triggeredCodes);
            $closed += $this->closeStaleWeeklyAlerts($company, $workDay, $triggeredCodes);
            if ($includeCompanyCleanup) {
                $closed += $this->closeLegacyWeeklyRestAlerts($company);
            }
            $createdOrUpdated = 0;

            foreach ($triggered as $code => $payload) {
                $type = $this->alertType($code);
                $representativeWorkDay = $this->representativeWorkDay($company, $workDay, $code, $payload);
                $representativeCalculationId = $representativeWorkDay->active_calculation_id ?: $calculation?->id;

                $existing = Alert::query()->where('company_id', $company->id)
                    ->where('fingerprint', $this->fingerprint($workDay, $code))->lockForUpdate()->first();
                $previousMetadata = $existing?->metadata ?? [];
                $previousEvaluationMetadata = $previousMetadata;
                unset($previousEvaluationMetadata['resolution'], $previousEvaluationMetadata['resolution_history'], $previousEvaluationMetadata['evaluation_signature']);
                $sameResult = $existing && $existing->description === $payload['description']
                    && $previousEvaluationMetadata == $payload['metadata'];
                if ($existing && $code === 'overtime_detected' && ($payload['metadata']['weekly_overtime_minutes'] ?? 0) === 0) {
                    $comparable = $payload['metadata'];
                    foreach (['daily_overtime_minutes', 'weekly_overtime_minutes'] as $derived) {
                        if (! array_key_exists($derived, $previousEvaluationMetadata)) {
                            unset($comparable[$derived]);
                        }
                    }
                    $sameResult = $previousEvaluationMetadata == $comparable;
                }
                if ($existing && in_array($code, self::WEEKLY_RULES, true) && $code !== 'weekly_hours_exceeded') {
                    // New explanatory fields do not invalidate a review of unchanged weekly totals.
                    $comparable = $payload['metadata'];
                    foreach (['ordinary_minutes', 'excess_minutes', 'daily_overtime_minutes', 'weekly_overtime_minutes', 'rest_work_minutes'] as $derived) {
                        if (! array_key_exists($derived, $previousEvaluationMetadata)) {
                            unset($comparable[$derived]);
                        }
                    }
                    $sameResult = $previousEvaluationMetadata == $comparable;
                }
                $keepResolution = $sameResult && $existing->resolved_by && ! in_array($existing->status, Alert::OPEN_STATUSES, true);
                $history = $previousMetadata['resolution_history'] ?? [];
                if ($existing?->resolved_by && ! $keepResolution) {
                    $history[] = ['previous_status' => $existing->status, 'previous_resolution' => $existing->resolution,
                        'previous_resolved_by' => $existing->resolved_by, 'previous_resolved_at' => $existing->resolved_at?->toIso8601String(),
                        'reason' => 'Resultado modificado por recalculo', 'changed_at' => CarbonImmutable::now('UTC')->toIso8601String()];
                }
                $metadata = $payload['metadata'];
                $metadata['resolution_history'] = $history;
                if ($keepResolution && isset($previousMetadata['resolution'])) {
                    $metadata['resolution'] = $previousMetadata['resolution'];
                }
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
                        'status' => $keepResolution ? $existing->status : Alert::STATUS_NEW,
                        'title' => $payload['title'],
                        'description' => $payload['description'],
                        'rule_code' => $code,
                        'detected_at' => $sameResult ? $existing->detected_at : CarbonImmutable::now('UTC'),
                        'resolution' => $keepResolution ? $existing->resolution : null,
                        'resolved_by' => $keepResolution ? $existing->resolved_by : null,
                        'resolved_at' => $keepResolution ? $existing->resolved_at : null,
                        'metadata' => $metadata,
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
    private function triggeredAlerts(WorkDay $workDay, ?WorkDayCalculation $calculation, bool $withinMargin = false): array
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

        if ($calculation->overtime_minutes > 0 && ! $withinMargin) {
            $alerts['overtime_detected'] = [
                'title' => 'Tiempo extra detectado',
                'description' => "Se calcularon {$calculation->overtime_minutes} minutos extra: ".WorkDayTimeBreakdown::dailyExtra($calculation).' por prolongación diaria y '.WorkDayTimeBreakdown::weeklyExtra($calculation).' por límite semanal en días laborables. El descanso trabajado se identifica por separado.',
                'metadata' => ['overtime_minutes' => $calculation->overtime_minutes,
                    'daily_overtime_minutes' => WorkDayTimeBreakdown::dailyExtra($calculation),
                    'weekly_overtime_minutes' => WorkDayTimeBreakdown::weeklyExtra($calculation)],
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

        $durationReasons = [];
        if ($dailyLimit > 0 && $calculation->total_work_minutes > $dailyLimit && ! $withinMargin) {
            $durationReasons[] = 'Supera la jornada diaria de referencia ('.$dailyLimit.' minutos).';
        }
        if (WorkDayTimeBreakdown::dailyExtra($calculation) > self::DAILY_OVERTIME_REVIEW_MINUTES) {
            $durationReasons[] = 'Supera tres horas extra por prolongación diaria.';
        }
        if ($calculation->total_work_minutes > 720) {
            $durationReasons[] = 'Supera doce horas trabajadas.';
        } elseif ($calculation->total_work_minutes > self::LONG_WORK_DAY_REVIEW_MINUTES) {
            $durationReasons[] = 'Supera diez horas trabajadas.';
        }
        if ($durationReasons !== []) {
            $alerts['daily_limit_exceeded'] = [
                'title' => 'Duración de jornada por revisar',
                'description' => implode(' ', $durationReasons).' Revisar la duración es independiente de autorizar las horas extra.',
                'metadata' => [
                    'duration_review_version' => 1,
                    'total_work_minutes' => $calculation->total_work_minutes,
                    'daily_limit_minutes' => $dailyLimit,
                    'daily_overtime_minutes' => WorkDayTimeBreakdown::dailyExtra($calculation),
                    'exceeded_limits' => $durationReasons,
                ],
                'severity' => $calculation->total_work_minutes > 720 ? AlertType::SEVERITY_CRITICAL : AlertType::SEVERITY_WARNING,
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
        $ordinaryMinutes = (int) $calculations->sum(fn (WorkDayCalculation $calculation): int => WorkDayTimeBreakdown::weeklyBase($calculation));
        $overtimeMinutes = (int) $calculations->sum('overtime_minutes');
        $overtimeDays = $calculations->filter(fn (WorkDayCalculation $calculation): bool => $calculation->overtime_minutes > 0)->count();
        $sundaysWorked = $calculations->filter(fn (WorkDayCalculation $calculation): bool => $calculation->sunday_minutes > 0)->count();
        $weeklyLimit = (int) data_get($calculations->first()?->result_snapshot, 'ordinary_overtime.weekly_limit_minutes', 0);
        $weeklyOvertimeLimit = $this->weeklyOvertimeLimit($workDay, $calculations->first());
        $metadata = [
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
            'total_work_minutes' => $totalWorkMinutes,
            'ordinary_minutes' => $ordinaryMinutes,
            'daily_overtime_minutes' => (int) $calculations->sum(fn (WorkDayCalculation $calculation): int => WorkDayTimeBreakdown::dailyExtra($calculation)),
            'weekly_overtime_minutes' => (int) $calculations->sum(fn (WorkDayCalculation $calculation): int => WorkDayTimeBreakdown::weeklyExtra($calculation)),
            'rest_work_minutes' => (int) $calculations->sum(fn (WorkDayCalculation $calculation): int => (int) data_get($calculation->result_snapshot, 'ordinary_overtime.rest_work_minutes', 0)),
            'overtime_minutes' => $overtimeMinutes,
            'overtime_days' => $overtimeDays,
            'sundays_worked' => $sundaysWorked,
            'weekly_limit_minutes' => $weeklyLimit,
            'weekly_overtime_limit_minutes' => $weeklyOvertimeLimit,
        ];
        $alerts = [];

        if ($weeklyLimit > 0 && $ordinaryMinutes > $weeklyLimit) {
            $alerts['weekly_hours_exceeded'] = [
                'title' => 'Exceso del acumulado semanal de jornada',
                'description' => 'Se acumularon '.$this->durationLabel($ordinaryMinutes).' de jornada en días laborables, sin prolongación diaria; referencia semanal: '.$this->durationLabel($weeklyLimit).'; exceso: '.$this->durationLabel($ordinaryMinutes - $weeklyLimit).'. La parte extraordinaria por límite semanal se identifica con ese origen. El descanso trabajado se revisa por separado.',
                'metadata' => $metadata + ['excess_minutes' => $ordinaryMinutes - $weeklyLimit],
            ];
        }

        if ($weeklyOvertimeLimit > 0 && $overtimeMinutes > $weeklyOvertimeLimit) {
            $alerts['weekly_overtime_exceeded'] = [
                'title' => 'Horas extra semanales superiores al límite',
                'description' => 'Se acumularon '.$this->durationLabel($overtimeMinutes).' extra (prolongación diaria + límite semanal en días laborables); umbral de revisión: '.$this->durationLabel($weeklyOvertimeLimit).'; exceso: '.$this->durationLabel($overtimeMinutes - $weeklyOvertimeLimit).'. La jornada base en descanso no se convierte automáticamente en extra.',
                'metadata' => $metadata + ['threshold_minutes' => $weeklyOvertimeLimit, 'excess_minutes' => $overtimeMinutes - $weeklyOvertimeLimit],
            ];
        }

        if ($overtimeDays > self::WEEKLY_OVERTIME_DAYS_REVIEW_LIMIT) {
            $alerts['weekly_overtime_days_exceeded'] = [
                'title' => 'Más de tres días con tiempo extra',
                'description' => 'La semana concentra tiempo extraordinario en más de tres días y requiere revisión.',
                'metadata' => $metadata + ['threshold_days' => self::WEEKLY_OVERTIME_DAYS_REVIEW_LIMIT],
            ];
        }

        // Sunday work is reviewed on its daily case. Its weekly count remains
        // available in summary metadata without creating a second review task.

        return $alerts;
    }

    private function durationLabel(int $minutes): string
    {
        return intdiv($minutes, 60).' h '.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT).' min';
    }

    private function weeklyOvertimeLimit(WorkDay $workDay, ?WorkDayCalculation $calculation): int
    {
        $snapshotLimit = (int) data_get($calculation?->result_snapshot, 'ordinary_overtime.weekly_double_overtime_limit_minutes', 0);

        if ($snapshotLimit > 0) {
            return $snapshotLimit;
        }

        $version = $this->rules->handle('weekly_overtime_double_limit', $workDay->work_date);

        return (int) ($version?->value['minutes'] ?? 0);
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
