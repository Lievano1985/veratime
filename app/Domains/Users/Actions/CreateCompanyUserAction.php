<?php

namespace App\Domains\Users\Actions;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateCompanyUserAction
{
    use ManageCompanyUserRole;

    /**
     * @param array{name: string, email: string, password: string, role_key: string, status?: string} $data
     */
    public function handle(Company $company, User $actor, array $data): User
    {
        Gate::authorize('create', [User::class, $company]);

        return DB::transaction(function () use ($company, $actor, $data): User {
            $email = Str::lower(trim($data['email']));
            $role = $this->roleForKey($actor, $company, (string) $data['role_key']);
            $status = (string) ($data['status'] ?? 'active');

            if (! in_array($status, ['active', 'inactive'], true)) {
                throw ValidationException::withMessages(['form.status' => 'Selecciona un estado valido.']);
            }

            $user = User::query()->where('email', $email)->first();

            if ($user && $user->companies()->whereKey($company->id)->exists()) {
                throw ValidationException::withMessages(['form.email' => 'Este usuario ya pertenece a la empresa activa.']);
            }

            if (! $user) {
                $user = User::query()->create([
                    'name' => trim($data['name']),
                    'email' => $email,
                    'password' => Hash::make($data['password']),
                    'status' => $status,
                ]);
            }

            $user->companies()->attach($company, [
                'role_id' => $role->id,
                'status' => $status,
                'is_default' => $user->companies()->count() === 0,
            ]);

            return $user;
        });
    }
}
