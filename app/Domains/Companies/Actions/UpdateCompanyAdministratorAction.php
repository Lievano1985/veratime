<?php

namespace App\Domains\Companies\Actions;

use App\Models\Company;
use App\Models\User;
use App\Support\RoleKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateCompanyAdministratorAction
{
    public function handle(User $actor, Company $company, int $userId, array $data): User
    {
        if (! $actor->isSuperAdmin()) {
            throw new AuthorizationException('Solo el superadministrador puede modificar este acceso.');
        }

        return DB::transaction(function () use ($company, $userId, $data): User {
            $user = $company->users()->where('users.id', $userId)
                ->whereNull('users.global_role')
                ->whereHas('companies', fn ($query) => $query->where('companies.id', $company->id)
                    ->where('company_user.role_id', DB::table('roles')->where('key', RoleKey::ADMIN_EMPRESA)->value('id')))
                ->lockForUpdate()->firstOrFail();
            $data['email'] = Str::lower(trim((string) ($data['email'] ?? '')));
            $validated = Validator::make(['administratorForm' => $data], [
                'administratorForm.name' => ['required', 'string', 'max:255'],
                'administratorForm.email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
                'administratorForm.password' => ['nullable', 'string', 'min:8', 'max:100'],
            ])->validate()['administratorForm'];
            $oldEmail = $user->email;
            $credentialsChanged = $oldEmail !== $validated['email'] || filled($validated['password'] ?? null);
            $user->name = trim($validated['name']);
            $user->email = $validated['email'];
            if ($oldEmail !== $user->email) {
                $user->email_verified_at = null;
            }
            if (filled($validated['password'] ?? null)) {
                $user->password = $validated['password'];
            }
            if ($credentialsChanged) {
                $user->remember_token = null;
                DB::table('sessions')->where('user_id', $user->id)->delete();
                DB::table('password_reset_tokens')->where('email', $oldEmail)->delete();
                $user->tokens()->delete();
            }
            $user->save();

            return $user->refresh();
        });
    }
}
