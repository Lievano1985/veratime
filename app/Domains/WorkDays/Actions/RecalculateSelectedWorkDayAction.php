<?php

namespace App\Domains\WorkDays\Actions;

use App\Domains\Alerts\Actions\EvaluateWorkDayAlertsAction;
use App\Domains\LegalRules\Actions\ApplyOrdinaryOvertimeForDateRangeAction;
use App\Domains\LegalRules\Actions\ApplySpecialLegalCasesForDateRangeAction;
use App\Domains\LegalRules\Actions\ClassifyWorkDayCalculationAction;
use App\Domains\TimeRecords\Actions\ResolveValidTimeEventsForWorkDateAction;
use App\Models\Company;
use App\Models\User;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class RecalculateSelectedWorkDayAction
{
    public function handle(Company $company, WorkDay $day, User $actor): WorkDay
    {
        Gate::forUser($actor)->authorize('recalculate', $day);
        if ($day->company_id !== $company->id || $company->status !== 'active' || ! $day->employment_relationship_id) {
            throw new InvalidArgumentException('La jornada no pertenece a la empresa activa o no tiene relación laboral.');
        }
        if ($day->work_date->toDateString() > CarbonImmutable::now($day->timezone ?: $company->timezone)->toDateString()) {
            throw new InvalidArgumentException('No se pueden recalcular jornadas futuras.');
        }

        return DB::transaction(function () use ($company, $day, $actor): WorkDay {
            $day = WorkDay::where('company_id', $company->id)->lockForUpdate()->findOrFail($day->id);
            // Avoid the legacy unscheduled-day cleanup that removes empty days and their history.
            if ($day->schedule_status === WorkDay::SCHEDULE_STATUS_UNSCHEDULED
                && ($day->metadata['source'] ?? null) === 'time_events'
                && app(ResolveValidTimeEventsForWorkDateAction::class)->handle($company, $day->employmentRelationship, $day->work_date)->isEmpty()) {
                throw new InvalidArgumentException('La jornada no tiene marcajes válidos. Se conserva su historial para revisión.');
            }
            $calculation = app(CalculateWorkDayAction::class)->handle($company, $day, $actor,
                WorkDayCalculation::GENERATED_BY_USER, 'Recálculo individual desde el panel de jornada.');
            if ($calculation) {
                app(ClassifyWorkDayCalculationAction::class)->handle($calculation);
            }
            $start = $day->work_date->copy()->startOfWeek()->toDateString();
            $end = $day->work_date->copy()->endOfWeek()->toDateString();
            app(ApplyOrdinaryOvertimeForDateRangeAction::class)->handle($company, $start, $end, relationshipId: $day->employment_relationship_id);
            app(ApplySpecialLegalCasesForDateRangeAction::class)->handle($company, $start, $end, relationshipId: $day->employment_relationship_id);
            $weekDays = WorkDay::where('company_id', $company->id)->where('employment_relationship_id', $day->employment_relationship_id)
                ->whereBetween('work_date', [$start, $end])->orderBy('work_date')->get();
            foreach ($weekDays as $weekDay) {
                app(EvaluateWorkDayAlertsAction::class)->handle($company, $weekDay, includeCompanyCleanup: false);
            }

            return $day->fresh(['activeCalculation', 'alerts', 'worker']);
        });
    }
}
