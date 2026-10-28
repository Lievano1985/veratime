<?php

namespace App\Domains\ExportLayouts\Actions;

use App\Domains\Attendance\Actions\ResolveAttendancePeriodPayrollRowsAction;
use App\Models\AttendancePeriod;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class BuildPeriodLayoutAction
{
    public function __construct(private readonly ResolveAttendancePeriodPayrollRowsAction $closedRows, private readonly BuildLayoutMatrixAction $matrix) {}

    public function handle(User $actor, Company $company, AttendancePeriod $period, array $definition): array
    {
        abort_unless($period->company_id === $company->id, 404);
        Gate::forUser($actor)->authorize('exportPayrollXlsx', $period);
        $rows = $this->closedRows->handle($period);

        return $this->matrix->handle($definition, $rows, $period->period_start->toDateString(), $period->period_end->toDateString());
    }
}
