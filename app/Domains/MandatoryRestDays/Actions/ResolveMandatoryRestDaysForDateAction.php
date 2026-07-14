<?php

namespace App\Domains\MandatoryRestDays\Actions;

use App\Models\Center;
use App\Models\Company;
use App\Models\MandatoryRestDay;
use Carbon\CarbonInterface;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Collection;

class ResolveMandatoryRestDaysForDateAction
{
    public function handle(Company $company, ?Center $center, CarbonInterface|string $date): Collection
    {
        if ($center && $center->company_id !== $company->id) {
            throw new InvalidArgumentException('El centro debe pertenecer a la empresa activa.');
        }

        $dateString = is_string($date) ? $date : $date->toDateString();
        $stateCode = $this->stateCodeFromCenter($center);

        return MandatoryRestDay::query()
            ->where('status', 'active')
            ->whereDate('date', $dateString)
            ->where(function ($query) use ($company, $stateCode): void {
                $query->where('scope', 'national')
                    ->whereNull('company_id')
                    ->orWhere(function ($query) use ($company): void {
                        $query->where('scope', 'company')
                            ->where('company_id', $company->id);
                    })
                    ->orWhere(function ($query) use ($stateCode): void {
                        $query->where('scope', 'state')
                            ->whereNull('company_id')
                            ->when($stateCode, fn ($query) => $query->where('state_code', $stateCode), fn ($query) => $query->whereRaw('1 = 0'));
                    });
            })
            ->orderBy('scope')
            ->orderBy('name')
            ->get();
    }

    private function stateCodeFromCenter(?Center $center): ?string
    {
        $stateCode = trim((string) data_get($center?->address, 'state_code'));
        $stateCode = strtoupper($stateCode);

        if ($stateCode === '') {
            return null;
        }

        return preg_match('/^[A-Z]{2}-[A-Z0-9]{2,5}$/', $stateCode) === 1 ? $stateCode : null;
    }
}
