<?php

namespace App\Domains\TimeRecords\Actions;

use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\TimeEvent;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class ResolveValidTimeEventsForWorkDateAction
{
    /**
     * @return Collection<int, TimeEvent>
     */
    public function handle(Company $company, EmploymentRelationship $relationship, string|CarbonInterface $workDate): Collection
    {
        $this->assertRelationshipBelongsToCompany($company, $relationship);

        $date = $workDate instanceof CarbonInterface
            ? $workDate->toDateString()
            : (string) $workDate;

        $events = $this->baseQuery($company, $relationship)
            ->whereDate('occurred_local_date', $date)
            ->get();

        if ($events->isNotEmpty()
            && ! $events->contains(fn (TimeEvent $event): bool => $event->event_type === 'clock_in')
            && $this->belongsToPreviousOpenWorkDate($company, $relationship, $date)) {
            return new Collection;
        }

        if (! $this->hasOpenWorkInterval($events)) {
            return $events;
        }

        $nextDate = CarbonImmutable::parse($date)->addDay()->toDateString();
        $continuation = $this->baseQuery($company, $relationship)
            ->whereDate('occurred_local_date', $nextDate)
            ->get();

        if ($continuation->isEmpty()) {
            return $events;
        }

        return new Collection([
            ...$events->all(),
            ...$this->eventsUntilFirstClockOut($continuation)->all(),
        ]);
    }

    private function belongsToPreviousOpenWorkDate(Company $company, EmploymentRelationship $relationship, string $date): bool
    {
        $previousDate = CarbonImmutable::parse($date)->subDay()->toDateString();
        $previousEvents = $this->baseQuery($company, $relationship)
            ->whereDate('occurred_local_date', $previousDate)
            ->get();

        if (! $this->hasOpenWorkInterval($previousEvents)) {
            return false;
        }

        $currentEvents = $this->baseQuery($company, $relationship)
            ->whereDate('occurred_local_date', $date)
            ->get();

        return $this->eventsUntilFirstClockOut($currentEvents)->isNotEmpty();
    }

    private function baseQuery(Company $company, EmploymentRelationship $relationship)
    {
        return TimeEvent::query()
            ->where('company_id', $company->id)
            ->where('worker_id', $relationship->worker_id)
            ->where('employment_relationship_id', $relationship->id)
            ->where('status', 'valid')
            ->whereNull('voided_at')
            ->orderBy('occurred_at_utc')
            ->orderBy('received_at')
            ->orderByRaw("case event_type when 'clock_in' then 1 when 'break_start' then 2 when 'break_end' then 3 when 'clock_out' then 4 else 9 end")
            ->orderBy('source')
            ->orderBy('external_id')
            ->orderBy('idempotency_key');
    }

    /**
     * @param Collection<int, TimeEvent> $events
     */
    private function hasOpenWorkInterval(Collection $events): bool
    {
        $open = false;

        foreach ($events as $event) {
            if ($event->event_type === 'clock_in' && ! $open) {
                $open = true;
                continue;
            }

            if ($event->event_type === 'clock_out' && $open) {
                $open = false;
            }
        }

        return $open;
    }

    /**
     * @param Collection<int, TimeEvent> $events
     * @return Collection<int, TimeEvent>
     */
    private function eventsUntilFirstClockOut(Collection $events): Collection
    {
        $selected = new Collection;

        foreach ($events as $event) {
            if ($event->event_type === 'clock_in') {
                break;
            }

            $selected->push($event);

            if ($event->event_type === 'clock_out') {
                break;
            }
        }

        return $selected;
    }

    private function assertRelationshipBelongsToCompany(Company $company, EmploymentRelationship $relationship): void
    {
        if ($company->status !== 'active') {
            throw new InvalidArgumentException('La resolucion de eventos requiere una empresa activa.');
        }

        if ($relationship->company_id !== $company->id) {
            throw new InvalidArgumentException('La relacion laboral debe pertenecer a la empresa activa.');
        }
    }
}
