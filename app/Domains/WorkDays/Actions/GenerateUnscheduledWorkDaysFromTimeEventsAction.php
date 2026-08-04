<?php

namespace App\Domains\WorkDays\Actions;

use App\Domains\Scheduling\Actions\ResolveDailyScheduleForRelationshipDateAction;
use App\Domains\TimeRecords\Actions\ResolveValidTimeEventsForWorkDateAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\TimeEvent;
use App\Models\WorkDay;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class GenerateUnscheduledWorkDaysFromTimeEventsAction
{
    public function __construct(
        private readonly ResolveDailyScheduleForRelationshipDateAction $publishedSchedule,
        private readonly ResolveValidTimeEventsForWorkDateAction $validEvents,
    ) {}

    public function handle(Company $company, string $startDate, string $endDate, ?Center $center = null): int
    {
        $createdOrUpdated = 0;

        TimeEvent::query()
            ->select('employment_relationship_id', 'occurred_local_date')
            ->where('company_id', $company->id)
            ->where('status', 'valid')
            ->whereNull('voided_at')
            ->whereNotNull('employment_relationship_id')
            ->whereDate('occurred_local_date', '>=', $startDate)
            ->whereDate('occurred_local_date', '<=', $endDate)
            ->when($center, fn ($query) => $query->where('center_id', $center->id))
            ->groupBy('employment_relationship_id', 'occurred_local_date')
            ->orderBy('occurred_local_date')
            ->orderBy('employment_relationship_id')
            ->get()
            ->each(function (TimeEvent $eventGroup) use ($company, &$createdOrUpdated): void {
                $relationship = EmploymentRelationship::query()
                    ->with(['worker', 'center'])
                    ->where('company_id', $company->id)
                    ->find($eventGroup->employment_relationship_id);

                if (! $relationship) {
                    return;
                }

                $date = $eventGroup->occurred_local_date instanceof CarbonInterface
                    ? $eventGroup->occurred_local_date->toDateString()
                    : (string) $eventGroup->occurred_local_date;
                $schedule = $this->publishedSchedule->handle($company, $relationship, $date);

                if ($schedule['resolution_status'] !== 'missing') {
                    return;
                }

                $events = $this->validEvents->handle($company, $relationship, $date);

                if ($events->isEmpty()) {
                    return;
                }

                $this->upsertUnscheduled($company, $relationship, $date, $events);
                $createdOrUpdated++;
            });

        return $createdOrUpdated;
    }

    /**
     * @param Collection<int, TimeEvent> $events
     */
    private function upsertUnscheduled(Company $company, EmploymentRelationship $relationship, string $date, Collection $events): WorkDay
    {
        $first = $events->first();

        return WorkDay::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'worker_id' => $relationship->worker_id,
                'work_date' => $date,
            ],
            [
                'employment_relationship_id' => $relationship->id,
                'center_id' => $first?->center_id ?? $relationship->center_id,
                'schedule_batch_id' => null,
                'daily_schedule_assignment_id' => null,
                'timezone' => $first?->timezone ?? $relationship->center?->timezone ?? $company->timezone,
                'status' => WorkDay::STATUS_PENDING,
                'schedule_status' => WorkDay::SCHEDULE_STATUS_UNSCHEDULED,
                'day_type' => null,
                'expected_work_minutes' => null,
                'valid_time_event_count' => $events->count(),
                'first_event_at_utc' => $events->first()?->occurred_at_utc?->utc()->format('Y-m-d H:i:s'),
                'last_event_at_utc' => $events->last()?->occurred_at_utc?->utc()->format('Y-m-d H:i:s'),
                'valid_time_event_ids' => $events->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
                'metadata' => [
                    'schema_version' => 1,
                    'source' => 'time_events',
                    'reason' => 'valid_events_without_published_schedule',
                ],
            ],
        );
    }
}
