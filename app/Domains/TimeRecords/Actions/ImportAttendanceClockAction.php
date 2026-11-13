<?php

namespace App\Domains\TimeRecords\Actions;

use App\Models\AttendanceClock;
use App\Models\Company;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

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
                throw new InvalidArgumentException('Reloj, empresa o centro sin autorización.');
            }

            $zone = new DateTimeZone($center->timezone ?: $company->timezone);
            $lines = preg_split('/\r?\n/', rtrim($body, "\r\n"));
            foreach ($lines as $line) {
                $fields = explode("\t", $line, 4);
                if (count($fields) < 3 || ! isset(self::TYPES[$fields[2]])) {
                    throw new InvalidArgumentException('Fila de asistencia o estado inválido.');
                }

                [$deviceUser, $localTime, $state] = $fields;
                $time = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $localTime, $zone);
                if (! $time || $time->format('Y-m-d H:i:s') !== $localTime) {
                    throw new InvalidArgumentException('Hora de asistencia inválida.');
                }

                $worker = $clock->workers()->wherePivot('clock_user_id', $deviceUser)
                    ->where('workers.company_id', $company->id)->where('workers.status', 'active')->first();
                $date = $time->format('Y-m-d');
                $relationship = $worker?->employmentRelationships()->where('company_id', $company->id)
                    ->where('center_id', $center->id)->whereDate('started_at', '<=', $date)
                    ->where(fn ($query) => $query->whereDate('ended_at', '>=', $date)
                        ->orWhere(fn ($open) => $open->whereNull('ended_at')->where('status', 'active')))
                    ->latest('started_at')->first();
                if (! $relationship) {
                    throw new InvalidArgumentException('La checada no tiene trabajador vinculado con relación laboral en el centro.');
                }

                $this->createTimeEvent->handle($company, $worker, [
                    'event_type' => self::TYPES[$state],
                    'occurred_at_utc' => $time->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM),
                    'source' => 'integration',
                    'external_id' => 'adms:'.hash('sha256', implode('|', [$clock->id, $deviceUser, $localTime, $state])),
                    'metadata' => ['channel' => 'adms', 'attendance_clock_id' => $clock->id],
                ], $relationship, $center);
            }

            return count($lines);
        }, 3);
    }
}
