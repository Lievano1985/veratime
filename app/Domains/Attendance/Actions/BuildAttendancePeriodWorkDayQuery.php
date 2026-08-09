<?php

namespace App\Domains\Attendance\Actions;

use App\Domains\Organization\Support\OrganizationalUnitTree;
use App\Models\AttendancePeriod;
use App\Models\OrganizationalUnit;
use App\Models\WorkDay;
use Illuminate\Database\Eloquent\Builder;

class BuildAttendancePeriodWorkDayQuery
{
    public function __construct(private readonly OrganizationalUnitTree $unitTree)
    {
    }

    /**
     * @return Builder<WorkDay>
     */
    public function handle(AttendancePeriod $period): Builder
    {
        $query = WorkDay::query()
            ->where('company_id', $period->company_id)
            ->where('center_id', $period->center_id)
            ->whereDate('work_date', '>=', $period->period_start)
            ->whereDate('work_date', '<=', $period->period_end);

        if ($period->scope_type === AttendancePeriod::SCOPE_ORGANIZATIONAL_UNITS) {
            $unitIds = $this->unitIdsIncludingDescendants($period);

            $query->whereHas('dailyScheduleAssignment', function (Builder $assignmentQuery) use ($unitIds): void {
                $assignmentQuery->whereIn('organizational_unit_id', $unitIds);
            });
        }

        return $query;
    }

    /**
     * @return list<int>
     */
    private function unitIdsIncludingDescendants(AttendancePeriod $period): array
    {
        $period->loadMissing('scopes.organizationalUnit');

        $ids = [];
        foreach ($period->scopes as $scope) {
            $unit = $scope->organizationalUnit;

            if (! $unit instanceof OrganizationalUnit) {
                continue;
            }

            $ids = [...$ids, ...$this->unitTree->descendantIds($unit)];
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }
}
