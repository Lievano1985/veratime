<?php

namespace App\Domains\Tenancy\Support;

use App\Models\Company;
use Illuminate\Support\Facades\Auth;

class CurrentCompany
{
    private ?Company $company = null;

    public function get(): ?Company
    {
        $user = Auth::user();

        if (! $user) {
            $this->clear();

            return null;
        }

        if ($this->company && $user->belongsToCompany($this->company)) {
            return $this->company;
        }

        $companyId = session('current_company_id');

        if (! $companyId) {
            return null;
        }

        $company = $user->activeCompanies()->whereKey($companyId)->first();

        if (! $company) {
            session()->forget('current_company_id');
            $this->clear();

            return null;
        }

        return $this->company = $company;
    }

    public function set(?Company $company): void
    {
        $this->company = $company;
    }

    public function id(): ?int
    {
        return $this->get()?->id;
    }

    public function clear(): void
    {
        $this->company = null;
    }
}
