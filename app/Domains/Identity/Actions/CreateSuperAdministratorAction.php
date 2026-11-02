<?php

namespace App\Domains\Identity\Actions;

use App\Models\User;
use App\Support\RoleKey;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class CreateSuperAdministratorAction
{
    public function handle(array $data): User
    {
        $data['email'] = Str::lower(trim((string) ($data['email'] ?? '')));
        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'max:100', 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ])->validate();

        return User::query()->create([
            'name' => trim($validated['name']), 'email' => $validated['email'],
            'password' => $validated['password'], 'status' => 'active', 'global_role' => RoleKey::SUPER_ADMIN,
        ]);
    }
}
