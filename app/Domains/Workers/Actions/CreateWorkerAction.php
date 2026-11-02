<?php

namespace App\Domains\Workers\Actions;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\UserWorkerLink;
use App\Models\Worker;
use App\Support\RoleKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateWorkerAction
{
    public function handle(Company $company, array $data): Worker
    {
        return DB::transaction(function () use ($company, $data): Worker {
            $data['email'] = trim((string) ($data['email'] ?? ''));
            Validator::make($data, ['email' => ['required', 'email', 'max:255']])->validate();
            $email = Str::lower(trim($data['email']));
            if ($email && User::query()->where('email', $email)->exists()) {
                throw ValidationException::withMessages(['form.email' => 'Este correo ya tiene una cuenta. Usa otro correo o vincula la cuenta existente desde Usuarios.']);
            }
            if (isset($data['account_password']) && strlen($data['account_password']) < 8) {
                throw ValidationException::withMessages(['form.account_password' => 'La contraseña debe tener al menos 8 caracteres.']);
            }
            $worker = $company->workers()->create([
                'employee_code' => $data['employee_code'],
                'full_name' => $data['full_name'],
                'birth_date' => filled($data['birth_date'] ?? null) ? $data['birth_date'] : null,
                'email' => $email,
                'phone' => $data['phone'] ?? null,
                'curp' => $data['curp'] ?? null,
                'rfc' => $data['rfc'] ?? null,
                'status' => $data['status'] ?? 'active',
                'source' => $data['source'] ?? 'web',
                'external_id' => $data['external_id'] ?? null,
                'metadata' => $data['metadata'] ?? [],
            ]);
            $role = Role::query()->firstOrCreate(['key' => RoleKey::TRABAJADOR], ['name' => 'Trabajador', 'is_system' => true]);
            $user = User::query()->create([
                'name' => $worker->full_name,
                'email' => $email,
                'password' => $data['account_password'] ?? Str::password(24),
                'status' => $worker->status === 'active' ? 'active' : 'inactive',
            ]);
            $user->companies()->attach($company, ['role_id' => $role->id, 'status' => $user->status, 'is_default' => true]);
            UserWorkerLink::query()->create(['company_id' => $company->id, 'worker_id' => $worker->id, 'user_id' => $user->id, 'status' => 'active']);

            return $worker;
        });
    }
}
