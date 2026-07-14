<?php

namespace App\Domains\MandatoryRestDays\Actions;

use App\Models\Center;
use App\Models\Company;
use App\Models\MandatoryRestDay;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class ResolveMandatoryRestDaysForDateAction
{
    public function handle(Company $company, ?Center $center, CarbonInterface|string $date): Collection
    {
        if ($center && $center->company_id !== $company->id) {
            throw new InvalidArgumentException('El centro debe pertenecer a la empresa activa.');
        }

        $dateString = is_string($date) ? $date : $date->toDateString();
        $countryCode = $this->countryCodeFromCenter($center);
        $jurisdictionCode = $this->jurisdictionCodeFromCenter($center);

        return MandatoryRestDay::query()
            ->where('status', 'active')
            ->whereDate('date', $dateString)
            ->where(function ($query) use ($company, $countryCode, $jurisdictionCode): void {
                $query->where(function ($query) use ($countryCode): void {
                    $query->where('scope', 'national')
                        ->where('country_code', $countryCode)
                        ->whereNull('company_id')
                        ->whereNull('jurisdiction_code');
                })
                    ->orWhere(function ($query) use ($company): void {
                        $query->where('scope', 'company')
                            ->where('company_id', $company->id);
                    })
                    ->orWhere(function ($query) use ($countryCode, $jurisdictionCode): void {
                        $query->where('scope', 'subnational')
                            ->where('country_code', $countryCode)
                            ->whereNull('company_id')
                            ->when($jurisdictionCode, fn ($query) => $query->where('jurisdiction_code', $jurisdictionCode), fn ($query) => $query->whereRaw('1 = 0'));
                    });
            })
            ->orderBy('scope')
            ->orderBy('name')
            ->get();
    }

    private function countryCodeFromCenter(?Center $center): string
    {
        $countryCode = strtoupper(trim((string) data_get($center?->address, 'country_code', 'MX')));

        return preg_match('/^[A-Z]{2}$/', $countryCode) === 1 ? $countryCode : 'MX';
    }

    private function jurisdictionCodeFromCenter(?Center $center): ?string
    {
        $jurisdictionCode = strtoupper(trim((string) data_get($center?->address, 'jurisdiction_code')));

        if ($jurisdictionCode === '') {
            return null;
        }

        return preg_match('/^[A-Z]{2}-[A-Z0-9]{2,8}$/', $jurisdictionCode) === 1 ? $jurisdictionCode : null;
    }
}
