<?php

namespace App\Domains\WorkDays\Actions;

use App\Domains\AttendanceIncidents\Actions\ResolveAttendanceIncidentForDateAction;
use App\Domains\TimeRecords\Actions\ResolveValidTimeEventsForWorkDateAction;
use App\Models\AttendanceIncident;
use App\Models\Company;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CalculateWorkDayAction
{
    public function __construct(
        private readonly ResolveValidTimeEventsForWorkDateAction $validEvents,
        private readonly ResolveAttendanceIncidentForDateAction $attendanceIncidentForDate,
    ) {}

    public function handle(Company $company, WorkDay $workDay, ?User $actor = null, string $generatedByType = WorkDayCalculation::GENERATED_BY_SYSTEM, ?string $reason = null): ?WorkDayCalculation
    {
        $this->assertWorkDayBelongsToCompany($company, $workDay);

        if (! $workDay->employmentRelationship) {
            return null;
        }

        $events = $this->validEvents->handle($company, $workDay->employmentRelationship, $workDay->work_date);

        if ($events->isEmpty()) {
            $attendanceIncident = $this->attendanceIncidentForDate->handle($company, $workDay->employmentRelationship, $workDay->work_date);

            if ($attendanceIncident && $this->canApplyAttendanceIncident($workDay)) {
                return $this->calculateFromAttendanceIncident($company, $workDay, $attendanceIncident, $actor, $generatedByType, $reason);
            }

            if ($workDay->schedule_status === WorkDay::SCHEDULE_STATUS_UNSCHEDULED
                && ($workDay->metadata['source'] ?? null) === 'time_events') {
                $workDay->forceFill(['active_calculation_id' => null])->save();
                $workDay->calculations()->delete();
                $workDay->delete();

                return null;
            }

            $workDay->forceFill([
                'status' => WorkDay::STATUS_PENDING,
                'active_calculation_id' => null,
            ])->save();

            return null;
        }

        return DB::transaction(function () use ($company, $workDay, $actor, $generatedByType, $reason, $events): WorkDayCalculation {
            $workDay = WorkDay::query()
                ->where('company_id', $company->id)
                ->lockForUpdate()
                ->findOrFail($workDay->id);

            WorkDayCalculation::query()
                ->where('work_day_id', $workDay->id)
                ->where('status', WorkDayCalculation::STATUS_ACTIVE)
                ->update(['status' => WorkDayCalculation::STATUS_SUPERSEDED]);

            $nextVersion = ((int) WorkDayCalculation::query()
                ->where('work_day_id', $workDay->id)
                ->max('version')) + 1;

            $result = $this->calculateFromEvents($events);
            $workDayStatus = $result['has_blocking_issues']
                ? WorkDay::STATUS_UNDER_REVIEW
                : WorkDay::STATUS_CALCULATED;
            $eventSummary = $this->eventSummary($events);

            $calculation = WorkDayCalculation::query()->create([
                'company_id' => $company->id,
                'work_day_id' => $workDay->id,
                'version' => $nextVersion,
                'status' => WorkDayCalculation::STATUS_ACTIVE,
                'calculated_at' => CarbonImmutable::now('UTC'),
                'generated_by_type' => $generatedByType,
                'generated_by_id' => $actor?->id,
                'reason' => $reason,
                'total_work_minutes' => $result['total_work_minutes'],
                'ordinary_minutes' => $result['total_work_minutes'],
                'night_minutes' => 0,
                'overtime_minutes' => 0,
                'break_minutes' => $result['break_minutes'],
                'paid_break_minutes' => 0,
                'sunday_minutes' => 0,
                'mandatory_rest_minutes' => 0,
                'classification' => WorkDayCalculation::CLASSIFICATION_PENDING,
                'rules_snapshot' => [
                    'schema_version' => 1,
                    'scope' => 'work_day_calculations_foundation',
                    'legal_engine_applied' => false,
                ],
                'inputs_snapshot' => $this->inputsSnapshot($workDay, $events),
                'result_snapshot' => [
                    'schema_version' => 1,
                    'work_intervals' => $result['work_intervals'],
                    'break_intervals' => $result['break_intervals'],
                    'issues' => $result['issues'],
                ],
                'explanation' => [
                    'schema_version' => 1,
                    'summary' => $result['has_blocking_issues']
                        ? 'La jornada tiene eventos validos, pero requiere revision por secuencia incompleta.'
                        : 'La jornada se calculo con pares entrada-salida y pausas completas.',
                    'legal_pending' => true,
                ],
            ]);

            $workDay->forceFill([
                'status' => $workDayStatus,
                'valid_time_event_count' => $eventSummary['count'],
                'first_event_at_utc' => $eventSummary['first_event_at_utc'],
                'last_event_at_utc' => $eventSummary['last_event_at_utc'],
                'valid_time_event_ids' => $eventSummary['ids'],
                'active_calculation_id' => $calculation->id,
            ])->save();

            return $calculation;
        });
    }

    private function canApplyAttendanceIncident(WorkDay $workDay): bool
    {
        return $workDay->schedule_status === WorkDay::SCHEDULE_STATUS_SCHEDULED
            && $workDay->day_type === 'shift'
            && (int) $workDay->expected_work_minutes > 0
            && (int) $workDay->valid_time_event_count === 0;
    }

    private function calculateFromAttendanceIncident(Company $company, WorkDay $workDay, AttendanceIncident $attendanceIncident, ?User $actor, string $generatedByType, ?string $reason): WorkDayCalculation
    {
        return DB::transaction(function () use ($company, $workDay, $attendanceIncident, $actor, $generatedByType, $reason): WorkDayCalculation {
            $workDay = WorkDay::query()
                ->where('company_id', $company->id)
                ->lockForUpdate()
                ->findOrFail($workDay->id);

            WorkDayCalculation::query()
                ->where('work_day_id', $workDay->id)
                ->where('status', WorkDayCalculation::STATUS_ACTIVE)
                ->update(['status' => WorkDayCalculation::STATUS_SUPERSEDED]);

            $nextVersion = ((int) WorkDayCalculation::query()
                ->where('work_day_id', $workDay->id)
                ->max('version')) + 1;

            $calculation = WorkDayCalculation::query()->create([
                'company_id' => $company->id,
                'work_day_id' => $workDay->id,
                'version' => $nextVersion,
                'status' => WorkDayCalculation::STATUS_ACTIVE,
                'calculated_at' => CarbonImmutable::now('UTC'),
                'generated_by_type' => $generatedByType,
                'generated_by_id' => $actor?->id,
                'reason' => $reason,
                'total_work_minutes' => 0,
                'ordinary_minutes' => 0,
                'night_minutes' => 0,
                'overtime_minutes' => 0,
                'break_minutes' => 0,
                'paid_break_minutes' => 0,
                'sunday_minutes' => 0,
                'mandatory_rest_minutes' => 0,
                'classification' => WorkDayCalculation::CLASSIFICATION_PENDING,
                'rules_snapshot' => [
                    'schema_version' => 1,
                    'scope' => 'attendance_incident',
                    'legal_engine_applied' => false,
                    'payroll_calculation' => false,
                ],
                'inputs_snapshot' => $this->attendanceIncidentInputsSnapshot($workDay, $attendanceIncident),
                'result_snapshot' => [
                    'schema_version' => 1,
                    'attendance_incident' => $this->attendanceIncidentSnapshot($attendanceIncident),
                    'issues' => [],
                ],
                'explanation' => [
                    'schema_version' => 1,
                    'summary' => 'La jornada no tiene eventos de asistencia, pero cuenta con una incidencia operativa aprobada para la fecha.',
                    'legal_pending' => true,
                ],
            ]);

            $metadata = $workDay->metadata ?: [];
            $metadata['attendance_incident'] = $this->attendanceIncidentSnapshot($attendanceIncident);

            $workDay->forceFill([
                'status' => WorkDay::STATUS_CALCULATED,
                'valid_time_event_count' => 0,
                'first_event_at_utc' => null,
                'last_event_at_utc' => null,
                'valid_time_event_ids' => [],
                'active_calculation_id' => $calculation->id,
                'metadata' => $metadata,
            ])->save();

            return $calculation;
        });
    }

    /**
     * @param Collection<int, TimeEvent> $events
     * @return array{count: int, first_event_at_utc: ?string, last_event_at_utc: ?string, ids: list<int>}
     */
    private function eventSummary(Collection $events): array
    {
        return [
            'count' => $events->count(),
            'first_event_at_utc' => $events->first()?->occurred_at_utc?->utc()->format('Y-m-d H:i:s'),
            'last_event_at_utc' => $events->last()?->occurred_at_utc?->utc()->format('Y-m-d H:i:s'),
            'ids' => $events->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
        ];
    }

    private function assertWorkDayBelongsToCompany(Company $company, WorkDay $workDay): void
    {
        if ($company->status !== 'active') {
            throw new InvalidArgumentException('El calculo de jornadas requiere una empresa activa.');
        }

        if ($workDay->company_id !== $company->id) {
            throw new InvalidArgumentException('La jornada debe pertenecer a la empresa activa.');
        }
    }

    /**
     * @param Collection<int, TimeEvent> $events
     * @return array{total_work_minutes: int, break_minutes: int, work_intervals: list<array<string, mixed>>, break_intervals: list<array<string, mixed>>, issues: list<string>, has_blocking_issues: bool}
     */
    private function calculateFromEvents(Collection $events): array
    {
        $workStartedAt = null;
        $breakStartedAt = null;
        $workIntervals = [];
        $breakIntervals = [];
        $issues = [];

        foreach ($events as $event) {
            if ($event->event_type === 'clock_in') {
                if ($workStartedAt !== null) {
                    $issues[] = 'duplicate_clock_in';
                    continue;
                }

                $workStartedAt = $event->occurred_at_utc;
                continue;
            }

            if ($event->event_type === 'break_start') {
                if ($workStartedAt === null || $breakStartedAt !== null) {
                    $issues[] = 'break_start_without_open_work_interval';
                    continue;
                }

                $breakStartedAt = $event->occurred_at_utc;
                continue;
            }

            if ($event->event_type === 'break_end') {
                if ($breakStartedAt === null) {
                    $issues[] = 'break_end_without_break_start';
                    continue;
                }

                $breakIntervals[] = $this->intervalSnapshot($breakStartedAt, $event->occurred_at_utc);
                $breakStartedAt = null;
                continue;
            }

            if ($event->event_type === 'clock_out') {
                if ($workStartedAt === null) {
                    $issues[] = 'clock_out_without_clock_in';
                    continue;
                }

                if ($breakStartedAt !== null) {
                    $issues[] = 'missing_break_end';
                    $breakStartedAt = null;
                }

                $workIntervals[] = $this->intervalSnapshot($workStartedAt, $event->occurred_at_utc);
                $workStartedAt = null;
            }
        }

        if ($workStartedAt !== null) {
            $issues[] = 'missing_clock_out';
        }

        if ($breakStartedAt !== null) {
            $issues[] = 'missing_break_end';
        }

        $grossWorkMinutes = collect($workIntervals)->sum('minutes');
        $breakMinutes = collect($breakIntervals)->sum('minutes');
        $totalWorkMinutes = max(0, $grossWorkMinutes - $breakMinutes);

        return [
            'total_work_minutes' => $totalWorkMinutes,
            'break_minutes' => $breakMinutes,
            'work_intervals' => $workIntervals,
            'break_intervals' => $breakIntervals,
            'issues' => array_values(array_unique($issues)),
            'has_blocking_issues' => $workIntervals === [] || $issues !== [],
        ];
    }

    /**
     * @return array{start_utc: string, end_utc: string, minutes: int}
     */
    private function intervalSnapshot(CarbonInterface $start, CarbonInterface $end): array
    {
        return [
            'start_utc' => $start->utc()->format('Y-m-d H:i:s'),
            'end_utc' => $end->utc()->format('Y-m-d H:i:s'),
            'minutes' => max(0, (int) $start->diffInMinutes($end, false)),
        ];
    }

    /**
     * @param Collection<int, TimeEvent> $events
     * @return array<string, mixed>
     */
    private function inputsSnapshot(WorkDay $workDay, Collection $events): array
    {
        return [
            'schema_version' => 1,
            'work_day' => [
                'id' => $workDay->id,
                'work_date' => $workDay->work_date?->toDateString(),
                'schedule_status' => $workDay->schedule_status,
                'day_type' => $workDay->day_type,
                'expected_work_minutes' => $workDay->expected_work_minutes,
            ],
            'events' => $events->map(fn (TimeEvent $event): array => [
                'id' => $event->id,
                'event_type' => $event->event_type,
                'occurred_at_utc' => $event->occurred_at_utc?->utc()->format('Y-m-d H:i:s'),
                'received_at' => $event->received_at?->utc()->format('Y-m-d H:i:s'),
                'source' => $event->source,
                'source_user_id' => $event->source_user_id,
                'external_id' => $event->external_id,
                'idempotency_key' => $event->idempotency_key,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function attendanceIncidentInputsSnapshot(WorkDay $workDay, AttendanceIncident $attendanceIncident): array
    {
        return [
            'schema_version' => 1,
            'work_day' => [
                'id' => $workDay->id,
                'work_date' => $workDay->work_date?->toDateString(),
                'schedule_status' => $workDay->schedule_status,
                'day_type' => $workDay->day_type,
                'expected_work_minutes' => $workDay->expected_work_minutes,
            ],
            'events' => [],
            'attendance_incident' => $this->attendanceIncidentSnapshot($attendanceIncident),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function attendanceIncidentSnapshot(AttendanceIncident $attendanceIncident): array
    {
        return [
            'id' => $attendanceIncident->id,
            'incident_type' => $attendanceIncident->incident_type,
            'payment_status' => $attendanceIncident->payment_status,
            'status' => $attendanceIncident->status,
            'start_date' => $attendanceIncident->start_date?->toDateString(),
            'end_date' => $attendanceIncident->end_date?->toDateString(),
            'reference' => $attendanceIncident->reference,
        ];
    }
}
