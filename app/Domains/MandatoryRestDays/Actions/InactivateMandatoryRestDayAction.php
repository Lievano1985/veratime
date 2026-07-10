<?php

namespace App\Domains\MandatoryRestDays\Actions;

use App\Models\Company;
use App\Models\MandatoryRestDay;
use InvalidArgumentException;

class InactivateMandatoryRestDayAction
{
    public function handle(Company $company, MandatoryRestDay $restDay): MandatoryRestDay
    {
        if ($restDay->company_id !== $company->id) {
            throw new InvalidArgumentException('Mandatory rest day must belong to the active company.');
        }

        $restDay->forceFill([
            'status' => 'inactive',
        ])->save();

        return $restDay->refresh();
    }
}
