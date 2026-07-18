<?php

namespace App\Domains\Scheduling\Actions;

use App\Models\Company;
use App\Models\ScheduleProfile;
use App\Models\ScheduleProfileAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReplaceScheduleProfileAssignmentAction
{
    public function __construct(private AssignScheduleProfileAction $assignScheduleProfile)
    {
    }

    public function handle(Company $company, ScheduleProfileAssignment $assignment, ScheduleProfile $newProfile, array $data, ?User $createdBy = null): ScheduleProfileAssignment
    {
        if ($company->status !== 'active' || $assignment->company_id !== $company->id || $newProfile->company_id !== $company->id || $newProfile->status !== 'active') {
            throw new InvalidArgumentException('La asignacion y el perfil deben pertenecer a la empresa activa.');
        }

        if ($assignment->status !== 'active') {
            throw new InvalidArgumentException('Solo se reemplazan asignaciones activas.');
        }

        $effectiveFrom = CarbonImmutable::parse($data['effective_from'] ?? now()->toDateString())->toDateString();
        if ($effectiveFrom <= $assignment->effective_from->toDateString()) {
            throw new InvalidArgumentException('La nueva vigencia debe iniciar despues de la asignacion anterior.');
        }

        return DB::transaction(function () use ($company, $assignment, $newProfile, $data, $createdBy, $effectiveFrom): ScheduleProfileAssignment {
            $lockedAssignment = ScheduleProfileAssignment::query()
                ->where('company_id', $company->id)
                ->whereKey($assignment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedAssignment->forceFill([
                'effective_to' => CarbonImmutable::parse($effectiveFrom)->subDay()->toDateString(),
                'status' => 'replaced',
            ])->save();

            $replacement = $this->assignScheduleProfile->handle($company, $newProfile, [
                'assignment_scope' => $lockedAssignment->assignment_scope,
                'center_id' => $lockedAssignment->center_id,
                'organizational_unit_id' => $lockedAssignment->organizational_unit_id,
                'employment_relationship_id' => $lockedAssignment->employment_relationship_id,
                'effective_from' => $effectiveFrom,
                'effective_to' => $data['effective_to'] ?? null,
                'source' => $data['source'] ?? 'manual',
                'reason' => $data['reason'] ?? null,
                'metadata' => $data['metadata'] ?? [],
            ], $createdBy);

            $lockedAssignment->forceFill([
                'replaced_by_id' => $replacement->id,
            ])->save();

            return $replacement->refresh()->load(['scheduleProfile', 'center', 'organizationalUnit', 'employmentRelationship']);
        });
    }
}
