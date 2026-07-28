<?php

namespace App\Domains\Organization\Actions;

use App\Models\Company;
use App\Models\OrganizationalUnit;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DeleteOrganizationalUnitIfUnusedAction
{
    public function handle(Company $company, OrganizationalUnit $unit): void
    {
        if ($unit->company_id !== $company->id) {
            throw new InvalidArgumentException('La unidad organizacional debe pertenecer a la empresa activa.');
        }

        DB::transaction(function () use ($company, $unit): void {
            $lockedUnit = OrganizationalUnit::query()
                ->where('company_id', $company->id)
                ->whereKey($unit->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedUnit->children()->exists()
                || $lockedUnit->employmentUnitAssignments()->exists()
                || $lockedUnit->operationalScopeAssignments()->exists()
                || $lockedUnit->scheduleProfileAssignments()->exists()
                || $lockedUnit->dailyScheduleAssignments()->exists()) {
                throw new InvalidArgumentException('No se puede eliminar la unidad porque ya tiene uso. Puedes inactivarla para ocultarla de la operacion activa.');
            }

            $lockedUnit->delete();
        });
    }
}
