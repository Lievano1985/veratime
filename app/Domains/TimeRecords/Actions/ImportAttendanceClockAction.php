<?php

namespace App\Domains\TimeRecords\Actions;

use App\Domains\TimeRecords\Exceptions\AttendanceClockImportException;
use App\Models\AttendanceClock;
use App\Models\Company;
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
            foreach ($lines as $index => $line) {
                $row = $index + 1;
                $fields = explode("\t", $line, 4);
                if (count($fields) < 3) {
                    throw new AttendanceClockImportException('formato_de_fila_invalido', $row);
                }
                if (! isset(self::TYPES[$fields[2]])) {
                    $state = preg_match('/\A[0-9]{1,3}\z/', $fields[2]) ? $fields[2] : null;
                    throw new AttendanceClockImportException('estado_no_admitido', $row, state: $state);
                }

                [$deviceUser, $localTime, $state] = $fields;
                if (str_contains($localTime, "\0")) {
                    throw new AttendanceClockImportException('hora_invalida', $row);
                }
                $time = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $localTime, $zone);
                if (! $time || $time->format('Y-m-d H:i:s') !== $localTime) {
                    throw new AttendanceClockImportException('hora_invalida', $row);
                }

                $worker = $clock->workers()->wherePivot('clock_user_id', $deviceUser)
                    ->where('workers.company_id', $company->id)->where('workers.status', 'active')->first();
                $safeClockUserId = preg_match('/\A[0-9]{1,32}\z/', $deviceUser) ? $deviceUser : null;
                if (! $worker) {
                    throw new AttendanceClockImportException('sin_vinculo_con_trabajador_activo', $row, $safeClockUserId);
                }
                $date = $time->format('Y-m-d');
                $relationship = $worker->employmentRelationships()->where('company_id', $company->id)
                    ->where('center_id', $center->id)->whereDate('started_at', '<=', $date)
                    ->where(fn ($query) => $query->whereDate('ended_at', '>=', $date)
                        ->orWhere(fn ($open) => $open->whereNull('ended_at')->where('status', 'active')))
                    ->latest('started_at')->first();
                if (! $relationship) {
                    throw new AttendanceClockImportException('sin_relacion_laboral_vigente_en_centro', $row, $safeClockUserId);
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
