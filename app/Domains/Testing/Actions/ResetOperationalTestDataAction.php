<?php

namespace App\Domains\Testing\Actions;

use App\Models\Alert;
use App\Models\AttendancePeriod;
use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\ScheduleBatch;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use App\Support\RoleKey;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ResetOperationalTestDataAction
{
    /**
     * @return array<string, int>
     */
    public function deletePublishedSchedules(Company $company, User $actor): array
    {
        $this->authorize($company, $actor);

        return DB::transaction(function () use ($company): array {
            $batchIds = ScheduleBatch::query()
                ->where('company_id', $company->id)
                ->where('status', 'published')
                ->pluck('id');

            if ($batchIds->isEmpty()) {
                return [
                    'schedule_batches' => 0,
                    'daily_schedule_assignments' => 0,
                ];
            }

            $dependentBatches = ScheduleBatch::query()
                ->where('company_id', $company->id)
                ->where(function ($query) use ($batchIds): void {
                    $query
                        ->whereIn('previous_batch_id', $batchIds)
                        ->orWhereIn('superseded_by', $batchIds);
                })
                ->exists();

            if ($dependentBatches) {
                throw new InvalidArgumentException('Existen borradores o versiones correctivas ligadas a una publicacion. No se borraron horarios publicados.');
            }

            $assignmentIds = DailyScheduleAssignment::query()
                ->where('company_id', $company->id)
                ->whereIn('schedule_batch_id', $batchIds)
                ->pluck('id');

            WorkDay::query()
                ->where('company_id', $company->id)
                ->where(function ($query) use ($batchIds, $assignmentIds): void {
                    $query
                        ->whereIn('schedule_batch_id', $batchIds)
                        ->orWhereIn('daily_schedule_assignment_id', $assignmentIds);
                })
                ->update([
                    'schedule_batch_id' => null,
                    'daily_schedule_assignment_id' => null,
                ]);

            $assignments = DailyScheduleAssignment::query()
                ->where('company_id', $company->id)
                ->whereIn('id', $assignmentIds)
                ->count();
            DailyScheduleAssignment::query()
                ->where('company_id', $company->id)
                ->whereIn('id', $assignmentIds)
                ->delete();

            $batches = ScheduleBatch::query()
                ->where('company_id', $company->id)
                ->whereIn('id', $batchIds)
                ->count();
            ScheduleBatch::query()
                ->where('company_id', $company->id)
                ->whereIn('id', $batchIds)
                ->delete();

            return [
                'schedule_batches' => $batches,
                'daily_schedule_assignments' => $assignments,
            ];
        });
    }

    /**
     * @return array<string, int>
     */
    public function deleteTimeEvents(Company $company, User $actor): array
    {
        $this->authorize($company, $actor);

        return DB::transaction(function () use ($company): array {
            $events = TimeEvent::query()->where('company_id', $company->id)->count();
            TimeEvent::query()->where('company_id', $company->id)->delete();

            return ['time_events' => $events];
        });
    }

    /**
     * @return array<string, int>
     */
    public function deleteWorkDays(Company $company, User $actor): array
    {
        $this->authorize($company, $actor);

        return DB::transaction(function () use ($company): array {
            $workDayIds = WorkDay::query()->where('company_id', $company->id)->pluck('id');
            $calculationIds = WorkDayCalculation::query()->where('company_id', $company->id)->pluck('id');

            $alerts = Alert::query()
                ->where('company_id', $company->id)
                ->where(function ($query) use ($workDayIds, $calculationIds): void {
                    $query
                        ->whereIn('work_day_id', $workDayIds)
                        ->orWhereIn('work_day_calculation_id', $calculationIds);
                })
                ->count();
            Alert::query()
                ->where('company_id', $company->id)
                ->where(function ($query) use ($workDayIds, $calculationIds): void {
                    $query
                        ->whereIn('work_day_id', $workDayIds)
                        ->orWhereIn('work_day_calculation_id', $calculationIds);
                })
                ->delete();

            WorkDay::query()->where('company_id', $company->id)->update(['active_calculation_id' => null]);

            $calculations = WorkDayCalculation::query()->where('company_id', $company->id)->count();
            WorkDayCalculation::query()->where('company_id', $company->id)->delete();

            $workDays = WorkDay::query()->where('company_id', $company->id)->count();
            WorkDay::query()->where('company_id', $company->id)->delete();

            return [
                'work_days' => $workDays,
                'work_day_calculations' => $calculations,
                'alerts' => $alerts,
            ];
        });
    }

    /**
     * @return array<string, int>
     */
    public function deleteAttendancePeriods(Company $company, User $actor): array
    {
        $this->authorize($company, $actor);

        return DB::transaction(function () use ($company): array {
            $periodIds = AttendancePeriod::query()->where('company_id', $company->id)->pluck('id');

            $scopes = DB::table('attendance_period_scopes')
                ->where('company_id', $company->id)
                ->whereIn('attendance_period_id', $periodIds)
                ->count();
            DB::table('attendance_period_scopes')
                ->where('company_id', $company->id)
                ->whereIn('attendance_period_id', $periodIds)
                ->delete();

            $periods = AttendancePeriod::query()->where('company_id', $company->id)->count();
            AttendancePeriod::query()->where('company_id', $company->id)->delete();

            return [
                'attendance_periods' => $periods,
                'attendance_period_scopes' => $scopes,
            ];
        });
    }

    private function authorize(Company $company, User $actor): void
    {
        $role = $actor->roleKeyForCompany($company);

        if (! in_array($role, [...RoleKey::companyManagers(), RoleKey::SUPER_ADMIN], true)) {
            throw new InvalidArgumentException('Esta limpieza provisional solo esta disponible para administracion.');
        }
    }
}
