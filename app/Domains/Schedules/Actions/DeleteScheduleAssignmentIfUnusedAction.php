<?php

namespace App\Domains\Schedules\Actions;

use App\Models\Company;
use App\Models\ScheduleAssignment;
use App\Models\TimeEvent;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DeleteScheduleAssignmentIfUnusedAction
{
    public function handle(Company $company, ScheduleAssignment $assignment): void
    {
        if ($assignment->company_id !== $company->id) {
            throw new InvalidArgumentException('La asignacion de horario debe pertenecer a la empresa activa.');
        }

        DB::transaction(function () use ($company, $assignment): void {
            $lockedAssignment = ScheduleAssignment::query()
                ->where('company_id', $company->id)
                ->whereKey($assignment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->hasTimeEventsInAssignmentPeriod($company, $lockedAssignment)) {
                throw new InvalidArgumentException('No se puede eliminar la asignacion porque ya hay asistencias en su vigencia. Puedes inactivarla.');
            }

            $lockedAssignment->delete();
        });
    }

    private function hasTimeEventsInAssignmentPeriod(Company $company, ScheduleAssignment $assignment): bool
    {
        return TimeEvent::query()
            ->where('company_id', $company->id)
            ->where('worker_id', $assignment->worker_id)
            ->whereDate('occurred_local_date', '>=', $assignment->effective_from->toDateString())
            ->when($assignment->effective_to, fn ($query) => $query->whereDate('occurred_local_date', '<=', $assignment->effective_to->toDateString()))
            ->exists();
    }
}
