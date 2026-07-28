<?php

namespace App\Domains\Workers\Actions;

use App\Models\Worker;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DeleteWorkerIfUnusedAction
{
    public function handle(Worker $worker): void
    {
        DB::transaction(function () use ($worker): void {
            $lockedWorker = Worker::query()
                ->whereKey($worker->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedWorker->timeEvents()->exists()
                || $lockedWorker->scheduleAssignments()->exists()
                || $lockedWorker->employmentRelationships()->whereHas('timeEvents')->exists()
                || $lockedWorker->employmentRelationships()->whereHas('scheduleAssignments')->exists()
                || $lockedWorker->employmentRelationships()->whereHas('employmentUnitAssignments')->exists()
                || $lockedWorker->employmentRelationships()->whereHas('dailyScheduleAssignments')->exists()
                || $lockedWorker->employmentRelationships()->whereHas('scheduleProfileAssignments')->exists()) {
                throw new InvalidArgumentException('No se puede eliminar el trabajador porque ya tiene horarios, asistencias o asignaciones. Puedes darlo de baja.');
            }

            $lockedWorker->credential()->delete();
            $lockedWorker->employmentRelationships()->delete();
            $lockedWorker->delete();
        });
    }
}
