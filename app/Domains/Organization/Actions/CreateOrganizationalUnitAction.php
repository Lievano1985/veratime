<?php

namespace App\Domains\Organization\Actions;

use App\Models\Center;
use App\Models\Company;
use App\Models\OrganizationalUnit;
use InvalidArgumentException;

class CreateOrganizationalUnitAction
{
    public function handle(Company $company, Center $center, array $data, ?OrganizationalUnit $parent = null): OrganizationalUnit
    {
        $this->assertValid($company, $center, $data, $parent);

        $unit = new OrganizationalUnit([
            'center_id' => $center->id,
            'parent_id' => $parent?->id,
            'code' => $data['code'],
            'name' => $data['name'],
            'type' => $data['type'],
            'status' => $data['status'] ?? 'active',
            'metadata' => $data['metadata'] ?? [],
        ]);
        $unit->company()->associate($company);
        $unit->save();

        return $unit->refresh();
    }

    private function assertValid(Company $company, Center $center, array $data, ?OrganizationalUnit $parent): void
    {
        if ($company->status !== 'active' || $center->company_id !== $company->id || $center->status !== 'active') {
            throw new InvalidArgumentException('La unidad organizacional requiere empresa y centro activos de la misma empresa.');
        }

        foreach (['code', 'name', 'type'] as $field) {
            if (blank($data[$field] ?? null)) {
                throw new InvalidArgumentException('Codigo, nombre y tipo de unidad son requeridos.');
            }
        }

        if (! in_array($data['type'], ['department', 'area', 'team'], true)) {
            throw new InvalidArgumentException('El tipo de unidad organizacional no es valido.');
        }

        if (! in_array($data['status'] ?? 'active', ['active', 'inactive'], true)) {
            throw new InvalidArgumentException('El estado de la unidad organizacional no es valido.');
        }

        if (OrganizationalUnit::query()
            ->where('company_id', $company->id)
            ->where('center_id', $center->id)
            ->where('code', $data['code'])
            ->exists()) {
            throw new InvalidArgumentException('Ya existe una unidad con el mismo codigo en este centro.');
        }

        $this->assertHierarchy($company, $center, $data['type'], $parent);
    }

    private function assertHierarchy(Company $company, Center $center, string $type, ?OrganizationalUnit $parent): void
    {
        if ($parent && ($parent->company_id !== $company->id || $parent->center_id !== $center->id)) {
            throw new InvalidArgumentException('La unidad padre debe pertenecer a la misma empresa y centro.');
        }

        if ($parent && $parent->status !== 'active') {
            throw new InvalidArgumentException('La unidad padre debe estar activa.');
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
    }
}