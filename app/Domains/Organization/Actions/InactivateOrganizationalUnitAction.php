<?php

namespace App\Domains\Organization\Actions;

use App\Models\Company;
use App\Models\EmploymentUnitAssignment;
use App\Models\OperationalScopeAssignment;
use App\Models\OrganizationalUnit;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class InactivateOrganizationalUnitAction
{
    public function handle(Company $company, OrganizationalUnit $unit, ?string $date = null): OrganizationalUnit
    {
        if ($unit->company_id !== $company->id) {
            throw new InvalidArgumentException('La unidad organizacional debe pertenecer a la empresa activa.');
        }

        $date = CarbonImmutable::parse($date ?? now()->toDateString())->toDateString();

        if ($unit->children()->where('status', 'active')->exists()) {
            throw new InvalidArgumentException('No se puede inactivar una unidad con unidades hijas activas.');
        }

        if ($this->hasActiveEmploymentAssignments($unit, $date) || $this->hasActiveScopes($unit, $date)) {
            throw new InvalidArgumentException('No se puede inactivar una unidad con asignaciones o alcances vigentes.');
        }

        $unit->forceFill(['status' => 'inactive'])->save();

        return $unit->refresh();
    }

    private function hasActiveEmploymentAssignments(OrganizationalUnit $unit, string $date): bool
    {
        return EmploymentUnitAssignment::query()
            ->where('company_id', $unit->company_id)
            ->where('organizational_unit_id', $unit->id)
            ->where('status', 'active')
            ->whereDate('effective_from', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date);
            })
            ->exists();
    }

    private function hasActiveScopes(OrganizationalUnit $unit, string $date): bool
    {
        return OperationalScopeAssignment::query()
            ->where('company_id', $unit->company_id)
            ->where('organizational_unit_id', $unit->id)
            ->where('status', 'active')
            ->whereDate('effective_from', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date);
            })
            ->exists();
    }
}