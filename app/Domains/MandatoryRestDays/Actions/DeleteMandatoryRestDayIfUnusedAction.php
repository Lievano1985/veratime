<?php

namespace App\Domains\MandatoryRestDays\Actions;

use App\Models\Company;
use App\Models\MandatoryRestDay;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DeleteMandatoryRestDayIfUnusedAction
{
    public function handle(Company $company, MandatoryRestDay $restDay): void
    {
        if ($restDay->company_id !== null && $restDay->company_id !== $company->id) {
            throw new InvalidArgumentException('El descanso obligatorio debe pertenecer a la empresa activa.');
        }

        DB::transaction(function () use ($restDay): void {
            $lockedRestDay = MandatoryRestDay::query()
                ->whereKey($restDay->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedRestDay->delete();
        });
    }
}
