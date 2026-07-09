<?php

namespace App\Domains\Companies\Actions;

use App\Models\Company;

class UpdateCompanyAction
{
    public function handle(Company $company, array $data): Company
    {
        $company->fill([
            'name' => $data['name'],
            'legal_name' => $data['legal_name'] ?? null,
            'tax_id' => $data['tax_id'] ?? null,
            'timezone' => $data['timezone'] ?? $company->timezone,
            'status' => $data['status'] ?? $company->status,
        ]);

        $company->save();

        return $company->refresh();
    }
}
