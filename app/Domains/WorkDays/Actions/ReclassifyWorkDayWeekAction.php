<?php

namespace App\Domains\WorkDays\Actions;

use App\Domains\Alerts\Actions\EvaluateWorkDayAlertsAction;
use App\Domains\LegalRules\Actions\ApplyOrdinaryOvertimeForDateRangeAction;
use App\Domains\LegalRules\Actions\ApplySpecialLegalCasesForDateRangeAction;
use App\Models\Company;
use App\Models\User;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class ReclassifyWorkDayWeekAction
{
    /** @return Collection<int, WorkDay> */
    public function handle(Company $company, WorkDay $reference, User $actor): Collection
    {
        Gate::forUser($actor)->authorize('recalculate', $reference);
        if ($company->status !== 'active' || $reference->company_id !== $company->id || ! $reference->employment_relationship_id) {
            throw new InvalidArgumentException('La jornada debe pertenecer a la empresa activa y tener relación laboral.');
        }
        $start = $reference->work_date->copy()->startOfWeek()->toDateString();
        $end = $reference->work_date->copy()->endOfWeek()->toDateString();
        if ($end > CarbonImmutable::now($reference->timezone ?: $company->timezone)->toDateString()) {
            throw new InvalidArgumentException('Esta actualización requiere una semana terminada.');
        }

        return DB::transaction(function () use ($company, $reference, $actor, $start, $end): Collection {
            $days = WorkDay::with('activeCalculation')->where('company_id', $company->id)
                ->where('employment_relationship_id', $reference->employment_relationship_id)
                ->whereBetween('work_date', [$start, $end])->orderBy('work_date')->lockForUpdate()->get();
            foreach ($days as $day) {
                Gate::forUser($actor)->authorize('recalculate', $day);
                $previous = $day->activeCalculation;
                if (! $previous || $previous->status !== WorkDayCalculation::STATUS_ACTIVE) {
                    continue;
                }
                $next = $previous->replicate();
                $next->forceFill([
                    'version' => ((int) $day->calculations()->max('version')) + 1,
                    'status' => WorkDayCalculation::STATUS_ACTIVE,
                    'calculated_at' => CarbonImmutable::now('UTC'),
                    'generated_by_type' => WorkDayCalculation::GENERATED_BY_USER,
                    'generated_by_id' => $actor->id,
                    'reason' => 'Reclasificación de extra diario, extra semanal y descanso trabajado. Versión anterior: '.$previous->id,
                ])->save();
                WorkDayCalculation::where('company_id', $company->id)->whereKey($previous->id)
                    ->update(['status' => WorkDayCalculation::STATUS_SUPERSEDED, 'calculated_at' => $previous->getRawOriginal('calculated_at')]);
                $day->forceFill(['active_calculation_id' => $next->id])->save();
            }
            app(ApplyOrdinaryOvertimeForDateRangeAction::class)->handle($company, $start, $end, relationshipId: $reference->employment_relationship_id);
            app(ApplySpecialLegalCasesForDateRangeAction::class)->handle($company, $start, $end, relationshipId: $reference->employment_relationship_id);
            foreach ($days as $day) {
                app(EvaluateWorkDayAlertsAction::class)->handle($company, $day->fresh(), includeCompanyCleanup: false);
            }

            return $days->map(fn (WorkDay $day): WorkDay => $day->fresh(['activeCalculation', 'alerts']));
        });
    }
}
