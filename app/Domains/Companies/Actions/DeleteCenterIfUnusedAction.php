<?php

namespace App\Domains\Companies\Actions;

use App\Models\Center;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DeleteCenterIfUnusedAction
{
    public function handle(Center $center): void
    {
        DB::transaction(function () use ($center): void {
            $lockedCenter = Center::query()
                ->whereKey($center->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedCenter->employmentRelationships()->exists()
                || $lockedCenter->timeEvents()->exists()
                || $lockedCenter->organizationalUnits()->exists()
                || $lockedCenter->operationalScopeAssignments()->exists()
                || $lockedCenter->scheduleProfileAssignments()->exists()
                || $lockedCenter->scheduleBatches()->exists()) {
                throw new InvalidArgumentException('No se puede eliminar el centro porque ya tiene uso. Puedes inactivarlo para ocultarlo de la operacion activa.');
            }

            $lockedCenter->delete();
        });
    }
}
