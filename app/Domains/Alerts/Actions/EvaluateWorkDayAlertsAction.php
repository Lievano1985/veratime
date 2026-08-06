<?php

namespace App\Domains\Alerts\Actions;

use App\Domains\Alerts\Support\AlertTypeCatalog;
use App\Models\Alert;
use App\Models\AlertType;
use App\Models\Company;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class EvaluateWorkDayAlertsAction
{
    private const WEEKLY_REST_MISSING = 'weekly_rest_missing';
    private const LEGACY_SIX_CONSECUTIVE_DAYS = 'six_consecutive_days';

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

        return $alerts;
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
     * @param list<string> $triggeredCodes
     */
    private function closeStaleAlerts(Company $company, WorkDay $workDay, array $triggeredCodes): int
    {
        $typeIds = AlertType::query()
            ->whereIn('code', AlertTypeCatalog::managedCodes())
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
        if ($code === self::WEEKLY_REST_MISSING) {
            $weeklyRest = data_get($workDay->activeCalculation?->result_snapshot, 'special_legal_cases.weekly_rest', []);
            $weekStart = $this->weekStart($workDay, is_array($weeklyRest) ? $weeklyRest : []);

            return hash('sha256', implode(':', [
                'weekly_rest',
                $workDay->company_id,
                $workDay->worker_id,
                $workDay->employment_relationship_id,
                $weekStart,
            ]));
        }

        return hash('sha256', "work_day:{$workDay->id}:{$code}");
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function representativeWorkDay(Company $company, WorkDay $workDay, string $code, array $payload): WorkDay
    {
        if ($code !== self::WEEKLY_REST_MISSING) {
            return $workDay;
        }

        $weekStart = data_get($payload, 'metadata.week_start') ?: $this->weekStart($workDay, []);

        return WorkDay::query()
            ->where('company_id', $company->id)
            ->where('worker_id', $workDay->worker_id)
            ->where('employment_relationship_id', $workDay->employment_relationship_id)
            ->whereDate('work_date', $weekStart)
            ->first() ?: $workDay;
    }

    /**
     * @param array<string, mixed> $weeklyRest
     */
    private function weekStart(WorkDay $workDay, array $weeklyRest): string
    {
        $weekStart = data_get($weeklyRest, 'week_start');

        if (is_string($weekStart) && $weekStart !== '') {
            return CarbonImmutable::parse($weekStart)->toDateString();
        }

        return CarbonImmutable::parse($workDay->work_date)
            ->startOfWeek(\Carbon\CarbonInterface::MONDAY)
            ->toDateString();
    }

    private function weekEnd(string $weekStart): string
    {
        return CarbonImmutable::parse($weekStart)->addDays(6)->toDateString();
    }
}
