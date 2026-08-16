<?php

namespace App\Domains\Users\Actions;

use App\Models\Company;
use App\Models\User;
use App\Support\RoleKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateCompanyUserAction
{
    use ManageCompanyUserRole;

    /**
     * @param array{name: string, role_key: string, user_status: string, membership_status: string} $data
     */
    public function handle(Company $company, User $actor, User $targetUser, array $data): User
    {
        Gate::authorize('update', [$targetUser, $company]);

        return DB::transaction(function () use ($company, $actor, $targetUser, $data): User {
            $role = $this->roleForKey($actor, $company, (string) $data['role_key']);
            $userStatus = (string) $data['user_status'];
            $membershipStatus = (string) $data['membership_status'];

            if (! in_array($userStatus, ['active', 'inactive'], true)) {
                throw ValidationException::withMessages(['editForm.user_status' => 'Selecciona un estado de usuario valido.']);
            }

            if (! in_array($membershipStatus, ['active', 'inactive'], true)) {
                throw ValidationException::withMessages(['editForm.membership_status' => 'Selecciona un estado de acceso valido.']);
            }

            if ($actor->is($targetUser) && ($membershipStatus !== 'active' || ! in_array($role->key, RoleKey::companyManagers(), true))) {
                throw ValidationException::withMessages(['editForm.membership_status' => 'No puedes quitar tu propio acceso administrativo.']);
            }

            $targetUser->forceFill([
                'name' => trim($data['name']),
                'status' => $userStatus,
            ])->save();

            $targetUser->companies()->updateExistingPivot($company->id, [
                'role_id' => $role->id,
                'status' => $membershipStatus,
            ]);

            return $targetUser->refresh();
        });
    }
}
