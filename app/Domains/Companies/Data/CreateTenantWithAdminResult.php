<?php

namespace App\Domains\Companies\Data;

use App\Models\Company;

readonly class CreateTenantWithAdminResult
{
    public function __construct(
        public Company $company,
        public bool $reusedExistingAdministrator,
    ) {}
}
