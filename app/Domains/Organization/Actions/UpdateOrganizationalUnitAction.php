<?php

namespace App\Domains\Organization\Actions;

use App\Models\Center;
use App\Models\Company;
use App\Models\OrganizationalUnit;
use InvalidArgumentException;

class UpdateOrganizationalUnitAction
{
    public function handle(Company $company, OrganizationalUnit $unit, array $data, ?OrganizationalUnit $parent = null): OrganizationalUnit
    {
        if ($unit->company_id !== $company->id) {
            throw new InvalidArgumentException('La unidad organizacional debe pertenecer a la empresa activa.');
        }

        $center = Center::query()->findOrFail($data['center_id'] ?? $unit->center_id);
        $type = $data['type'] ?? $unit->type;
        $code = $data['code'] ?? $unit->code;
        $status = $data['status'] ?? $unit->status;

        if ($center->company_id !== $company->id) {
            throw new InvalidArgumentException('El centro debe pertenecer a la empresa activa.');
        }

        if (! in_array($type, ['department', 'area', 'team'], true) || ! in_array($status, ['active', 'inactive'], true)) {
            throw new InvalidArgumentException('Tipo o estado de unidad organizacional no valido.');
        }

        if (OrganizationalUnit::query()
            ->where('company_id', $company->id)
            ->where('center_id', $center->id)
            ->where('code', $code)
            ->whereKeyNot($unit->id)
            ->exists()) {
            throw new InvalidArgumentException('Ya existe una unidad con el mismo codigo en este centro.');
        }

        if ($parent && ($parent->company_id !== $company->id || $parent->center_id !== $center->id || $parent->id === $unit->id)) {
            throw new InvalidArgumentException('La unidad padre debe pertenecer a la misma empresa y centro.');
        }

        if ($type === 'department' && $parent !== null) {
            throw new InvalidArgumentException('Un departamento no puede tener unidad padre.');
        }
        if ($type === 'area' && $parent !== null && $parent->type !== 'department') {
            throw new InvalidArgumentException('Un area solo puede depender de un departamento o del centro.');
        }
        if ($type === 'team' && ($parent === null || $parent->type !== 'area')) {
            throw new InvalidArgumentException('Un equipo debe depender de un area.');
        }

        $unit->fill([
            'center_id' => $center->id,
            'parent_id' => $parent?->id,
            'code' => $code,
            'name' => $data['name'] ?? $unit->name,
            'type' => $type,
            'status' => $status,
            'metadata' => $data['metadata'] ?? $unit->metadata ?? [],
        ])->save();

        return $unit->refresh();
    }
}