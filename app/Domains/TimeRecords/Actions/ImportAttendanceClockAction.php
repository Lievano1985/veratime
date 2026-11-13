<?php

namespace App\Domains\TimeRecords\Actions;

use App\Domains\TimeRecords\Exceptions\AttendanceClockImportException;
use App\Models\AttendanceClock;
use App\Models\Company;
use App\Models\TimeEvent;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

class ImportAttendanceClockAction
{
    private const TYPES = ['0' => 'clock_in', '1' => 'clock_out', '4' => 'break_start', '5' => 'break_end'];

    public function __construct(private readonly CreateTimeEventAction $createTimeEvent) {}

    public function handle(string $serial, string $body): int
    {
        return DB::transaction(function () use ($serial, $body): int {
            $clock = AttendanceClock::query()->where('serial', strtoupper($serial))->lockForUpdate()->first();
            $company = $clock ? Company::query()->find($clock->company_id) : null;
            $center = $company?->centers()->where('status', 'active')->find($clock->center_id);
            if ($company?->status !== 'active' || ! $center) {
                throw new AttendanceClockImportException('reloj_empresa_o_centro_no_autorizado');
            }

            $zone = new DateTimeZone($center->timezone ?: $company->timezone);
            $lines = preg_split('/\r?\n/', rtrim($body, "\r\n"));
            $marks = [];
            foreach ($lines as $index => $line) {
                $row = $index + 1;
                $fields = explode("\t", $line, 4);
                if (count($fields) < 3) {
                    throw new AttendanceClockImportException('formato_de_fila_invalido', $row);
                }
                if (! preg_match('/\A[0-9]{1,3}\z/', $fields[2])) {
                    throw new AttendanceClockImportException('estado_no_admitido', $row);
                }

                [$deviceUser, $localTime, $state] = $fields;
                $eventType = self::TYPES[$state] ?? null;
                if (! preg_match('/\A[0-9]{1,32}\z/', $deviceUser)) {
                    throw new AttendanceClockImportException('id_en_reloj_invalido', $row);
                }
                if (str_contains($localTime, "\0")) {
                    throw new AttendanceClockImportException('hora_invalida', $row);
                }
                $time = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $localTime, $zone);
                if (! $time || $time->format('Y-m-d H:i:s') !== $localTime) {
                    throw new AttendanceClockImportException('hora_invalida', $row);
                }

                $marks[] = [$deviceUser, $localTime, $state, $eventType, $time];
            }

            usort($marks, fn (array $left, array $right) => $left[4] <=> $right[4]);
            foreach ($marks as [$deviceUser, $localTime, $state, $eventType, $time]) {
                $externalId = 'adms:'.hash('sha256', implode('|', [$clock->id, $deviceUser, $localTime, $state]));
                $pending = DB::table('attendance_clock_pending_events')->where('attendance_clock_id', $clock->id);
                if ((clone $pending)->where('external_id', $externalId)->exists()
                    || TimeEvent::query()->where('company_id', $company->id)->where('source', 'integration')
                        ->where('external_id', $externalId)->exists()) {
                    continue;
                }

                $worker = $eventType === null ? null : $clock->workers()->wherePivot('clock_user_id', $deviceUser)
                    ->where('workers.company_id', $company->id)->where('workers.status', 'active')->first();
                $date = $time->format('Y-m-d');
                $relationship = $worker?->employmentRelationships()->where('company_id', $company->id)
                    ->where('center_id', $center->id)->whereDate('started_at', '<=', $date)
                    ->where(fn ($query) => $query->whereDate('ended_at', '>=', $date)
                        ->orWhere(fn ($open) => $open->whereNull('ended_at')->where('status', 'active')))
                    ->latest('started_at')->lockForUpdate()->first();
                $utc = $time->setTimezone(new DateTimeZone('UTC'));
                $sequenceReason = $relationship ? $this->sequenceReason($company->id, $relationship->id, $eventType, $time) : null;
                if (! $relationship || $sequenceReason) {
                    $pending->insert([
                        'attendance_clock_id' => $clock->id,
                        'external_id' => $externalId,
                        'clock_user_id' => $deviceUser,
                        'occurred_local_date' => $date,
                        'occurred_local_time' => $time->format('H:i:s'),
                        'timezone' => $zone->getName(),
                        'event_type' => $eventType ?? 'adms:'.$state,
                        'reason' => match (true) {
                            $sequenceReason !== null => $sequenceReason,
                            $eventType === null => 'estado_no_admitido',
                            $worker === null => 'sin_vinculo_con_trabajador_activo',
                            default => 'sin_relacion_laboral_vigente_en_centro',
                        },
                        'received_at' => now('UTC'),
                    ]);

                    continue;
                }

                $this->createTimeEvent->handle($company, $worker, [
                    'event_type' => $eventType,
                    'occurred_at_utc' => $utc->format(DATE_ATOM),
                    'source' => 'integration',
                    'external_id' => $externalId,
                    'metadata' => ['channel' => 'adms', 'attendance_clock_id' => $clock->id],
                ], $relationship, $center);
            }

            return count($lines);
        }, 3);
    }

    private function sequenceReason(int $companyId, int $relationshipId, string $eventType, DateTimeImmutable $time): ?string
    {
        $date = $time->format('Y-m-d');
        $utc = $time->setTimezone(new DateTimeZone('UTC'));
        $events = TimeEvent::query()->where('company_id', $companyId)
            ->where('employment_relationship_id', $relationshipId)
            ->where('status', 'valid')->whereNull('voided_at')
            ->whereIn('event_type', self::TYPES)
            ->whereDate('occurred_local_date', '>=', $time->modify('-1 day')->format('Y-m-d'))
            ->whereDate('occurred_local_date', '<=', $time->modify('+1 day')->format('Y-m-d'))
            ->orderBy('occurred_at_utc')->orderBy('id')->lockForUpdate()->get();

        $state = 'waiting';
        $start = null;
        foreach ($events as $event) {
            if ($event->occurred_at_utc->greaterThan($utc)) {
                break;
            }
            $eventDate = $event->occurred_local_date->toDateString();
            if ($state === 'closed' && $start !== $eventDate) {
                $state = 'waiting';
            }
            $next = $this->nextState($state, $event->event_type);
            if ($next !== null) {
                if ($event->event_type === 'clock_in') {
                    $start = $eventDate;
                }
                $state = $next;
            }
        }

        if ($state === 'closed' && $start !== $date) {
            $state = 'waiting';
        }
        $next = $this->nextState($state, $eventType);
        if ($next === null) {
            return match (true) {
                $state === 'closed' => 'jornada_ya_cerrada',
                $eventType === 'clock_in' => 'entrada_ya_registrada',
                default => 'secuencia_de_marcaje_invalida',
            };
        }

        // A late arrival must not invalidate a sequence that was already recorded.
        $candidateStart = $eventType === 'clock_in' ? $date : $start;
        foreach ($events as $event) {
            if (! $event->occurred_at_utc->greaterThan($utc)) {
                continue;
            }
            $eventDate = $event->occurred_local_date->toDateString();
            if ($state === 'closed' && $start !== $eventDate) {
                $state = 'waiting';
            }
            if ($next === 'closed' && $candidateStart !== $eventDate) {
                $next = 'waiting';
            }
            $original = $this->nextState($state, $event->event_type);
            $withCandidate = $this->nextState($next, $event->event_type);
            if ($original !== null && $withCandidate === null) {
                return 'secuencia_de_marcaje_invalida';
            }
            if ($event->event_type === 'clock_in') {
                $start = $original !== null ? $eventDate : $start;
                $candidateStart = $withCandidate !== null ? $eventDate : $candidateStart;
            }
            $state = $original ?? $state;
            $next = $withCandidate ?? $next;
        }

        return null;
    }

    private function nextState(string $state, string $type): ?string
    {
        return match ([$state, $type]) {
            ['waiting', 'clock_in'], ['paused', 'break_end'] => 'working',
            ['working', 'break_start'] => 'paused',
            ['working', 'clock_out'] => 'closed',
            default => null,
        };
    }
}
