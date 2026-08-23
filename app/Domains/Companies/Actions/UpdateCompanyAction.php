<?php

namespace App\Domains\Companies\Actions;

use App\Models\Company;
use App\Models\CustomerAccount;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class UpdateCompanyAction
{
    public function handle(Company $company, array $data, ?User $actor = null): Company
    {
        if ($actor && ! $actor->isSuperAdmin() && in_array($data['status'] ?? $company->status, ['suspended', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'editForm.status' => 'Solo el super administrador puede suspender o cancelar una empresa.',
            ]);
        }

        $company->fill([
            'name' => $data['name'],
            'legal_name' => $data['legal_name'] ?? null,
            'tax_id' => $data['tax_id'] ?? null,
            'timezone' => $data['timezone'] ?? $company->timezone,
            'status' => $data['status'] ?? $company->status,
        ]);

        $company->save();

        if (array_key_exists('account_type', $data)) {
            if (! in_array($data['account_type'], ['single_company', 'multi_company'], true)) {
                throw ValidationException::withMessages([
                    'editForm.account_type' => 'Selecciona un tipo de cuenta valido.',
                ]);
            }

            $customerAccount = $company->customerAccount;

            if (! $customerAccount) {
                $customerAccount = CustomerAccount::query()->create([
                    'name' => $company->name,
                    'account_type' => $data['account_type'],
                    'status' => 'active',
                    'metadata' => [],
                ]);

                $company->forceFill(['customer_account_id' => $customerAccount->id])->save();
            } else {
                $customerAccount->forceFill([
                    'name' => $customerAccount->name ?: $company->name,
                    'account_type' => $data['account_type'],
                ])->save();
            }
        }

        return $company->refresh();
    }
}
