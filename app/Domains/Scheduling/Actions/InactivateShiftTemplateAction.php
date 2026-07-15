<?php

namespace App\Domains\Scheduling\Actions;

use App\Models\Company;
use App\Models\ShiftTemplate;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InactivateShiftTemplateAction
{
    public function handle(Company $company, ShiftTemplate $template): ShiftTemplate
    {
        $this->assertTenant($company, $template);

        return DB::transaction(function () use ($company, $template): ShiftTemplate {
            $lockedTemplate = ShiftTemplate::query()
                ->where('company_id', $company->id)
                ->whereKey($template->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedTemplate->forceFill(['status' => 'inactive'])->save();

            return $lockedTemplate->refresh();
        });
    }

    private function assertTenant(Company $company, ShiftTemplate $template): void
    {
        if ($company->status !== 'active' || $template->company_id !== $company->id) {
            throw new InvalidArgumentException('La plantilla no pertenece a la empresa activa.');
        }
    }
}
