<?php

namespace App\Domains\TimeRecords\Actions;

use App\Models\Center;
use App\Models\Company;
use App\Models\TimeEvent;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

class ResolveCurrentTimeRecordStateAction
{
    /**
     * @return array{state: string, allowed_actions: array<int, string>, local_date: string, timezone: string, last_event: ?TimeEvent}
     */
    public function handle(Company $company, Worker $worker, ?CarbonInterface $now = null, ?Center $center = null): array
    {
        $this->assertCompanyAndWorker($company, $worker);

        $timezone = $center?->timezone ?: $company->timezone;
        $localNow = $now
            ? CarbonImmutable::instance($now)->setTimezone($timezone)
            : CarbonImmutable::now($timezone);

        $events = TimeEvent::query()
            ->where('company_id', $company->id)
            ->where('worker_id', $worker->id)
            ->where(function ($query) use ($localNow): void {
                $query->whereDate('occurred_local_date', $localNow->toDateString())
                    ->orWhere('occurred_local_date', 'like', $localNow->toDateString().'%');
            })
            ->where('status', 'valid')
            ->orderBy('occurred_at_utc')
            ->orderBy('id')
            ->get();

        $state = 'sin_entrada';
        $lastEvent = null;

        foreach ($events as $event) {
            if (! in_array($event->event_type, ['clock_in', 'clock_out', 'break_start', 'break_end'], true)) {
                continue;
            }

            $lastEvent = $event;

            $state = match ($event->event_type) {
                'clock_in' => in_array($state, ['sin_entrada', 'jornada_cerrada'], true) ? 'trabajando' : $state,
                'break_start' => $state === 'trabajando' ? 'en_pausa' : $state,
                'break_end' => $state === 'en_pausa' ? 'trabajando' : $state,
                'clock_out' => in_array($state, ['trabajando'], true) ? 'jornada_cerrada' : $state,
                default => $state,
            };
        }

        return [
            'state' => $state,
            'allowed_actions' => $this->allowedActionsFor($state),
            'local_date' => $localNow->toDateString(),
            'timezone' => $timezone,
            'last_event' => $lastEvent,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function allowedActionsFor(string $state): array
    {
        return match ($state) {
            'sin_entrada' => ['clock_in'],
            'trabajando' => ['break_start', 'clock_out'],
            'en_pausa' => ['break_end'],
            default => [],
        };
    }

    private function assertCompanyAndWorker(Company $company, Worker $worker): void
    {
        if ($company->status !== 'active') {
            throw new InvalidArgumentException('Time registration requires an active company.');
        }

        if ($worker->company_id !== $company->id || $worker->status !== 'active') {
            throw new InvalidArgumentException('Worker must be active and belong to the active company.');
        }
    }
}