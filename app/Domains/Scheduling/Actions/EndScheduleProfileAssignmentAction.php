<?php

namespace App\Domains\Scheduling\Actions;

use App\Models\Company;
use App\Models\ScheduleProfileAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class EndScheduleProfileAssignmentAction
{
    public function handle(Company $company, ScheduleProfileAssignment $assignment, string $effectiveTo): ScheduleProfileAssignment
    {
        if ($company->status !== 'active' || $assignment->company_id !== $company->id) {
            throw new InvalidArgumentException('La asignacion no pertenece a la empresa activa.');
        }

        $effectiveTo = CarbonImmutable::parse($effectiveTo)->toDateString();
        if ($effectiveTo < $assignment->effective_from->toDateString()) {
            throw new InvalidArgumentException('La fecha de fin no puede ser anterior al inicio.');
        }

        return DB::transaction(function () use ($company, $assignment, $effectiveTo): ScheduleProfileAssignment {
            $lockedAssignment = ScheduleProfileAssignment::query()
                ->where('company_id', $company->id)
                ->whereKey($assignment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedAssignment->forceFill([
                'effective_to' => $effectiveTo,
                'status' => 'inactive',
            ])->save();

            return $lockedAssignment->refresh();
        });
    }
}
