<?php

namespace App\Domains\Workers\Actions;

use App\Models\EmploymentRelationship;

class AssessEmploymentRelationshipEvidenceAction
{
    /**
     * @return array{has_protected_evidence: bool, latest_evidence_date: string|null, published_schedule_count: int, time_event_count: int}
     */
    public function handle(EmploymentRelationship $relationship): array
    {
        $latestPublishedScheduleDate = $relationship->dailyScheduleAssignments()
            ->whereHas('scheduleBatch', fn ($query) => $query->whereIn('status', ['published', 'superseded', 'cancelled']))
            ->max('work_date');

        $latestTimeEventDate = $relationship->timeEvents()
            ->max('occurred_local_date');

        $publishedScheduleCount = $relationship->dailyScheduleAssignments()
            ->whereHas('scheduleBatch', fn ($query) => $query->whereIn('status', ['published', 'superseded', 'cancelled']))
            ->count();

        $timeEventCount = $relationship->timeEvents()->count();

        $latestEvidenceDate = collect([$latestPublishedScheduleDate, $latestTimeEventDate])
            ->filter()
            ->sort()
            ->last();

        return [
            'has_protected_evidence' => $publishedScheduleCount > 0 || $timeEventCount > 0,
            'latest_evidence_date' => $latestEvidenceDate,
            'published_schedule_count' => $publishedScheduleCount,
            'time_event_count' => $timeEventCount,
        ];
    }
}
