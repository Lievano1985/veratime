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
            throw new InvalidArgumentException('Center must belong to the active company.');
        }

        $dateString = is_string($date) ? $date : $date->toDateString();

        return MandatoryRestDay::query()
            ->where('status', 'active')
            ->whereDate('date', $dateString)
            ->where(function ($query) use ($company, $center): void {
                $query->where('scope', 'global')
                    ->whereNull('company_id')
                    ->whereNull('center_id')
                    ->orWhere(function ($query) use ($company): void {
                        $query->where('scope', 'company')
                            ->where('company_id', $company->id)
                            ->whereNull('center_id');
                    })
                    ->orWhere(function ($query) use ($company, $center): void {
                        $query->where('scope', 'center')
                            ->where('company_id', $company->id)
                            ->when($center, fn ($query) => $query->where('center_id', $center->id), fn ($query) => $query->whereRaw('1 = 0'));
                    });
            })
            ->orderBy('scope')
            ->orderBy('name')
            ->get();
    }
}
