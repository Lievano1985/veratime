<?php

namespace App\Domains\WorkDays\Actions;

use App\Domains\TimeRecords\Actions\ResolveValidTimeEventsForWorkDateAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\TimeEvent;
use App\Models\WorkDay;
use Illuminate\Support\Collection;

class GenerateWorkDaysFromPublishedSchedulesAction
{
    public function __construct(
        private readonly ResolveValidTimeEventsForWorkDateAction $validEvents,
    ) {}

    public function handle(Company $company, string $startDate, string $endDate, ?Center $center = null): int
    {
        $count = 0;

        DailyScheduleAssignment::query()
            ->with(['scheduleBatch', 'employmentRelationship.worker', 'employmentRelationship.center', 'segments'])
            ->where('company_id', $company->id)
            ->whereDate('work_date', '>=', $startDate)
            ->whereDate('work_date', '<=', $endDate)
            ->when($center, fn ($query) => $query->whereHas('scheduleBatch', fn ($batchQuery) => $batchQuery->where('center_id', $center->id)))
            ->whereHas('scheduleBatch', fn ($query) => $query->where('status', 'published'))
            ->orderBy('work_date')
            ->orderBy('employment_relationship_id')
            ->chunkById(200, function (Collection $assignments) use ($company, &$count): void {
                foreach ($assignments as $assignment) {
                    $this->upsertFromAssignment($company, $assignment);
                    $count++;
                }
            });

        return $count;
    }

    private function upsertFromAssignment(Company $company, DailyScheduleAssignment $assignment): WorkDay
    {
        $relationship = $assignment->employmentRelationship;
        $worker = $relationship->worker;
        $date = $assignment->work_date->toDateString();
        $events = $this->validEvents->handle($company, $relationship, $date);
        $eventSummary = $this->eventSummary($events);

        return WorkDay::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'worker_id' => $worker->id,
                'work_date' => $date,
            ],
            [
                'employment_relationship_id' => $relationship->id,
                'center_id' => $assignment->scheduleBatch->center_id,
                'schedule_batch_id' => $assignment->schedule_batch_id,
                'daily_schedule_assignment_id' => $assignment->id,
                'timezone' => $assignment->timezone,
                'status' => WorkDay::STATUS_PENDING,
                'schedule_status' => WorkDay::SCHEDULE_STATUS_SCHEDULED,
                'day_type' => $assignment->day_type,
                'expected_work_minutes' => $this->expectedWorkMinutes($assignment),
                'valid_time_event_count' => $eventSummary['count'],
                'first_event_at_utc' => $eventSummary['first_event_at_utc'],
                'last_event_at_utc' => $eventSummary['last_event_at_utc'],
                'valid_time_event_ids' => $eventSummary['ids'],
                'metadata' => [
                    'schema_version' => 1,
                    'source' => 'published_schedule',
                    'snapshot_sha256' => $assignment->scheduleBatch->snapshot_sha256,
                    'batch_version' => $assignment->scheduleBatch->version,
                ],
            ],
        );
    }

    private function expectedWorkMinutes(DailyScheduleAssignment $assignment): ?int
    {
        if ($assignment->day_type === 'shift') {
            $minutes = $assignment->segments
                ->where('segment_type', 'work')
                ->sum(fn ($segment): int => (int) $segment->duration_minutes);

            return $minutes > 0 ? $minutes : null;
        }

        if ($assignment->day_type === 'flexible') {
            return $assignment->required_minutes;
        }

        return null;
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
}
