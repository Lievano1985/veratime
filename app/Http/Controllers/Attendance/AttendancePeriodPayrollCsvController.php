<?php

namespace App\Http\Controllers\Attendance;

use App\Domains\Attendance\Actions\ExportAttendancePeriodPayrollCsvAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Http\Controllers\Controller;
use App\Models\AttendancePeriod;
use Illuminate\Support\Facades\Gate;

class AttendancePeriodPayrollCsvController extends Controller
{
    public function __invoke(AttendancePeriod $attendancePeriod, CurrentCompany $currentCompany, ExportAttendancePeriodPayrollCsvAction $action)
    {
        $company = $currentCompany->get();
        abort_unless($company, 403);
        abort_unless($attendancePeriod->company_id === $company->id, 404);

        Gate::authorize('exportPayrollCsv', $attendancePeriod);

        return $action->handle($attendancePeriod);
    }
}
