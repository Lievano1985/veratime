<?php

namespace App\Domains\Companies\Actions;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateCompanyAction
{
    public function handle(User $user, array $data): Company
    {
        return DB::transaction(function () use ($user, $data): Company {
            $company = Company::query()->create([
                'name' => $data['name'],
                'legal_name' => $data['legal_name'] ?? null,
                'tax_id' => $data['tax_id'] ?? null,
                'timezone' => $data['timezone'] ?? 'America/Mexico_City',
                'status' => $data['status'] ?? 'active',
                'settings' => [],
            ]);

            $company->setting()->create(Company::defaultSettings());

            $ownerRole = Role::query()->where('key', 'owner')->first();

            $user->companies()->attach($company, [
                'role_id' => $ownerRole?->id,
                'status' => 'active',
                'is_default' => false,
            ]);

            return $company;
        });
    }
}
