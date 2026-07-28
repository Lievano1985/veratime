<?php

namespace App\Domains\Scheduling\Actions;

use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\ScheduleProfileAssignment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DeleteScheduleProfileAssignmentIfUnusedAction
{
    public function handle(Company $company, ScheduleProfileAssignment $assignment): void
    {
        if ($company->status !== 'active' || $assignment->company_id !== $company->id) {
            throw new InvalidArgumentException('La asignacion no pertenece a la empresa activa.');
        }

        DB::transaction(function () use ($company, $assignment): void {
            $lockedAssignment = ScheduleProfileAssignment::query()
                ->where('company_id', $company->id)
                ->whereKey($assignment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedAssignment->replaced_by_id
                || ScheduleProfileAssignment::query()->where('replaced_by_id', $lockedAssignment->id)->exists()
                || $this->hasGeneratedDailySchedules($company, $lockedAssignment)) {
                throw new InvalidArgumentException('No se puede eliminar la asignacion porque ya tiene historial o genero horarios. Puedes finalizarla.');
            }

            $lockedAssignment->delete();
        });
    }

    private function hasGeneratedDailySchedules(Company $company, ScheduleProfileAssignment $assignment): bool
    {
        return DailyScheduleAssignment::query()
            ->where('company_id', $company->id)
            ->where('source_type', 'profile')
            ->where('source_reference->schedule_profile_assignment_id', $assignment->id)
            ->exists();
    }
}
