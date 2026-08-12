<?php

namespace App\Domains\TimeRecords\Actions;

use App\Domains\WorkDays\Actions\RefreshWorkDaysForDateRangeAction;
use App\Models\Company;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\Worker;
use App\Support\RoleKey;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateQuickTestTimeEventsAction
{
    private const ALLOWED_SOURCE_MODES = ['web', 'assisted', 'kiosk'];

    public function __construct(
        private readonly CreateTimeEventAction $createTimeEvent,
        private readonly RefreshWorkDaysForDateRangeAction $refreshWorkDays,
    ) {
    }

    /**
     * @return array{events: int, refresh: array{scheduled: int, unscheduled: int, total: int}}
     */
    public function handle(Company $company, User $actor, Worker $worker, array $data): array
    {
        $this->authorize($company, $actor);

        if ($worker->company_id !== $company->id || $worker->status !== 'active') {
            throw new InvalidArgumentException('La persona trabajadora debe estar activa y pertenecer a la empresa activa.');
        }

        $relationship = $worker->activeEmploymentRelationship()->with('center')->first();
        if (! $relationship) {
            throw new InvalidArgumentException('La persona trabajadora necesita una relacion laboral activa.');
        }

        $center = $relationship->center;
        $timezone = $center?->timezone ?: $company->timezone;
        $workDate = CarbonImmutable::parse((string) ($data['work_date'] ?? ''))->toDateString();
        $sourceMode = $this->sourceMode($data['source_mode'] ?? null);
        $events = $this->eventPayloads($data, $workDate, $timezone, $sourceMode, $worker);

        return DB::transaction(function () use ($company, $actor, $worker, $relationship, $center, $events, $workDate): array {
            $created = 0;

            foreach ($events as $eventData) {
                $event = $this->createTimeEvent->handle(
                    $company,
                    $worker,
                    $eventData,
                    $relationship,
                    $center,
                    $eventData['source'] === 'kiosk' ? null : $actor,
                );

                if ($event instanceof TimeEvent) {
                    $created++;
                }
            }

            $refresh = $this->refreshWorkDays->handle($company, $workDate, $workDate, $center);

            return ['events' => $created, 'refresh' => $refresh];
        });
    }

    private function authorize(Company $company, User $actor): void
    {
        $role = $actor->roleKeyForCompany($company);

        if (! in_array($role, [...RoleKey::companyManagers(), RoleKey::SUPER_ADMIN], true)) {
            throw new InvalidArgumentException('Esta herramienta provisional solo esta disponible para administracion.');
        }
    }

    private function sourceMode(?string $sourceMode): string
    {
        if (! $sourceMode || ! in_array($sourceMode, self::ALLOWED_SOURCE_MODES, true)) {
            throw new InvalidArgumentException('Selecciona un metodo de ingreso valido.');
        }

        return $sourceMode;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function eventPayloads(array $data, string $workDate, string $timezone, string $sourceMode, Worker $worker): array
    {
        $clockIn = $this->time($data['clock_in'] ?? null, 'La entrada es requerida.');
        $clockOut = $this->time($data['clock_out'] ?? null, 'La salida es requerida.');
        $breaks = [
            ['break_start', $data['break1_start'] ?? null],
            ['break_end', $data['break1_end'] ?? null],
            ['break_start', $data['break2_start'] ?? null],
            ['break_end', $data['break2_end'] ?? null],
        ];

        $events = [
            ['clock_in', $clockIn],
        ];

        for ($index = 0; $index < count($breaks); $index += 2) {
            $start = $breaks[$index][1];
            $end = $breaks[$index + 1][1];

            if (blank($start) && blank($end)) {
                continue;
            }

            if (blank($start) || blank($end)) {
                throw new InvalidArgumentException('Cada pausa debe tener inicio y fin.');
            }

            $events[] = ['break_start', $this->time($start, 'El inicio de pausa no es valido.')];
            $events[] = ['break_end', $this->time($end, 'El fin de pausa no es valido.')];
        }

        $events[] = ['clock_out', $clockOut];

        return array_map(
            fn (array $event): array => $this->payload($event[0], $event[1], $workDate, $timezone, $sourceMode, $worker),
            $events,
        );
    }

    private function time(?string $time, string $message): string
    {
        $time = trim((string) $time);

        if ($time === '') {
            throw new InvalidArgumentException($message);
        }

        return CarbonImmutable::parse('2000-01-01 '.$time)->format('H:i:s');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $eventType, string $time, string $workDate, string $timezone, string $sourceMode, Worker $worker): array
    {
        $source = $sourceMode === 'kiosk' ? 'kiosk' : 'web';

        return [
            'event_type' => $eventType,
            'occurred_local_date' => $workDate,
            'occurred_local_time' => $time,
            'timezone' => $timezone,
            'received_at' => CarbonImmutable::now('UTC'),
            'source' => $source,
            'status' => 'valid',
            'idempotency_key' => implode(':', ['quick-test', $worker->id, $workDate, $sourceMode, $eventType, $time]),
            'metadata' => [
                'channel' => $sourceMode,
                'context' => 'quick_test_time_events',
                'provisional' => true,
            ],
        ];
    }
}
