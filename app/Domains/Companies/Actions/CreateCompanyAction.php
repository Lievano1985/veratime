<?php

namespace App\Domains\Companies\Actions;

use App\Models\Company;
use App\Models\CustomerAccount;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleKey;
use Illuminate\Support\Facades\DB;

class CreateCompanyAction
{
    public function handle(User $user, array $data): Company
    {
        return DB::transaction(function () use ($user, $data): Company {
            $customerAccount = $this->resolveCustomerAccountFor($user, $data);

            $company = Company::query()->create([
                'customer_account_id' => $customerAccount->id,
                'name' => $data['name'],
                'legal_name' => $data['legal_name'] ?? null,
                'tax_id' => $data['tax_id'] ?? null,
                'timezone' => $data['timezone'] ?? 'America/Mexico_City',
                'status' => $data['status'] ?? 'active',
                'settings' => [],
            ]);

            $company->setting()->create(Company::defaultSettings());

            if (! $user->isSuperAdmin()) {
                $adminRole = Role::query()->where('key', RoleKey::ADMIN_EMPRESA)->first();

                $user->companies()->attach($company, [
                    'role_id' => $adminRole?->id,
                    'status' => 'active',
                    'is_default' => false,
                ]);
            }

            return $company;
        });
    }

    private function resolveCustomerAccountFor(User $user, array $data): CustomerAccount
    {
        $currentCompany = session('current_company_id')
            ? $user->activeCompanies()->whereKey(session('current_company_id'))->first()
            : null;

        $currentCompany ??= $user->defaultCompany();

        if ($currentCompany?->customerAccount?->account_type === 'multi_company') {
            return $currentCompany->customerAccount;
        }

        return CustomerAccount::query()->create([
            'name' => $data['name'],
            'account_type' => 'single_company',
            'status' => 'active',
            'metadata' => [],
        ]);
    }
}
