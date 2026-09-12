<?php

namespace App\Domains\Products\Support;

use App\Models\Company;
use App\Models\CustomerAccountProduct;

class ProductAccess
{
    public function companyHasProduct(Company $company, string $productKey): bool
    {
        return $this->productAssignmentForCompany($company, $productKey) !== null;
    }

    public function companyHasOperationalProduct(Company $company, string $productKey): bool
    {
        return $this->productAssignmentForCompany($company, $productKey)?->isOperational() ?? false;
    }

    public function statusForCompany(Company $company, string $productKey): ?string
    {
        return $this->productAssignmentForCompany($company, $productKey)?->status;
    }

    private function productAssignmentForCompany(Company $company, string $productKey): ?CustomerAccountProduct
    {
        $customerAccount = $company->customerAccount;

        if (! $customerAccount) {
            return null;
        }

        return CustomerAccountProduct::query()
            ->where('customer_account_id', $customerAccount->id)
            ->whereHas('product', fn ($query) => $query->where('key', $productKey))
            ->first();
    }
}
