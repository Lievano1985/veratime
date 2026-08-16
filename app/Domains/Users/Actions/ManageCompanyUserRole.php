<?php

namespace App\Domains\Users\Actions;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleKey;
use Illuminate\Validation\ValidationException;

trait ManageCompanyUserRole
{
    /**
     * @return list<string>
     */
    private function assignableRoleKeys(User $actor, Company $company): array
    {
        return match ($actor->roleKeyForCompany($company)) {
            RoleKey::SUPER_ADMIN => RoleKey::companyRoleKeys(),
            RoleKey::ADMIN_EMPRESA => [
                RoleKey::ADMIN_EMPRESA,
                RoleKey::RH_ADMIN,
                RoleKey::RH_OPERATIVO,
                RoleKey::SUPERVISOR,
                RoleKey::TRABAJADOR,
            ],
            RoleKey::RH_ADMIN => [
                RoleKey::RH_OPERATIVO,
                RoleKey::SUPERVISOR,
                RoleKey::TRABAJADOR,
            ],
            default => [],
        };
    }

    private function roleForKey(User $actor, Company $company, string $roleKey): Role
    {
        if (! in_array($roleKey, $this->assignableRoleKeys($actor, $company), true)) {
            throw ValidationException::withMessages([
                'form.role_key' => 'No tienes permiso para asignar ese rol.',
            ]);
        }

        return Role::query()->where('key', $roleKey)->firstOrFail();
    }
}
