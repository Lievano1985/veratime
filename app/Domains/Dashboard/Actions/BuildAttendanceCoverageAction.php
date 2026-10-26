<?php

namespace App\Domains\Dashboard\Actions;

use App\Domains\Organization\Support\ScopedOperationalAccess;
use App\Models\AttendanceIncident;
use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\TimeEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class BuildAttendanceCoverageAction
{
    public function __construct(private readonly ScopedOperationalAccess $access) {}

    public function handle(Company $company, User $user, string $date, ?int $centerId = null): array
    {
        $assignments = DailyScheduleAssignment::query()->with('employmentRelationship')
            ->where('company_id', $company->id)->whereDate('work_date', $date)
            ->whereIn('day_type', ['shift', 'flexible', 'on_call'])
            ->whereHas('scheduleBatch', fn (Builder $query) => $query->where('company_id', $company->id)
                ->where('status', 'published')->when($centerId, fn (Builder $center) => $center->where('center_id', $centerId)))
            ->whereHas('employmentRelationship', fn (Builder $query) => $query->where('company_id', $company->id)
                ->whereDate('started_at', '<=', $date)
                ->where(fn (Builder $end) => $end->whereNull('ended_at')->orWhereDate('ended_at', '>=', $date)))
            ->get()->filter(fn ($assignment) => $user->isSuperAdmin() || $this->access->canConsultRelationship(
                $user, $company, $assignment->employmentRelationship, $date));

        $absences = AttendanceIncident::query()->where('company_id', $company->id)
            ->where('status', AttendanceIncident::STATUS_APPROVED)
            ->where('incident_type', '!=', AttendanceIncident::TYPE_UNJUSTIFIED_ABSENCE)
            ->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)
            ->whereIn('worker_id', $assignments->pluck('employmentRelationship.worker_id'))->get();
        $expected = $assignments->reject(fn ($assignment) => $absences->contains(fn ($absence) => $absence->worker_id === $assignment->employmentRelationship->worker_id
            && ($absence->employment_relationship_id === null || $absence->employment_relationship_id === $assignment->employment_relationship_id)));
        $expectedWorkers = $expected->pluck('employmentRelationship.worker_id')->unique();
        $attended = TimeEvent::query()->where('company_id', $company->id)
            ->whereIn('employment_relationship_id', $expected->pluck('employment_relationship_id'))
            ->whereIn('worker_id', $expectedWorkers)->where('event_type', 'clock_in')->where('status', 'valid')
            ->whereDate('occurred_local_date', $date)->where('occurred_at_utc', '<=', CarbonImmutable::now('UTC'))
            ->distinct()->count('worker_id');
        $total = $expectedWorkers->count();

        return [
            'scheduled_workers' => $assignments->pluck('employmentRelationship.worker_id')->unique()->count(),
            'expected_workers' => $total,
            'attended_workers' => $attended,
            'percentage' => $total > 0 ? (int) round($attended / $total * 100) : null,
        ];
    }
}
