<?php

namespace App\Domains\Scheduling\Actions;

use App\Models\Company;
use App\Models\ShiftTemplate;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DeleteShiftTemplateIfUnusedAction
{
    public function handle(Company $company, ShiftTemplate $template): void
    {
        if ($company->status !== 'active' || $template->company_id !== $company->id) {
            throw new InvalidArgumentException('La plantilla no pertenece a la empresa activa.');
        }

        DB::transaction(function () use ($company, $template): void {
            $lockedTemplate = ShiftTemplate::query()
                ->where('company_id', $company->id)
                ->whereKey($template->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedTemplate->scheduleProfileWeeklyRules()->exists()
                || $lockedTemplate->scheduleProfileCycleRules()->exists()
                || $lockedTemplate->dailyScheduleAssignments()->exists()) {
                throw new InvalidArgumentException('No se puede eliminar la plantilla porque ya se usa en perfiles u horarios. Puedes inactivarla.');
            }

            $lockedTemplate->segments()->delete();
            $lockedTemplate->delete();
        });
    }
}
