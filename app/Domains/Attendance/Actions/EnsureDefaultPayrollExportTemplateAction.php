<?php

namespace App\Domains\Attendance\Actions;

use App\Models\Company;
use App\Models\PayrollExportTemplate;
use Illuminate\Support\Facades\DB;

class EnsureDefaultPayrollExportTemplateAction
{
    public function handle(Company $company): PayrollExportTemplate
    {
        return DB::transaction(function () use ($company): PayrollExportTemplate {
            $template = $company->payrollExportTemplates()->firstOrNew(['name' => 'CSV de períodos']);

            if (! $template->exists) {
                $template->forceFill([
                    'status' => PayrollExportTemplate::STATUS_ACTIVE,
                    'is_system' => true,
                    'is_default' => true,
                    'delimiter' => ',',
                ])->save();
            }

            if (! $template->columns()->exists()) {
                $template->columns()->createMany(array_map(
                    fn (array $column, int $position): array => [
                        'source_key' => $column['key'],
                        'header' => $column['header'],
                        'position' => $position + 1,
                    ],
                    ExportAttendancePeriodPayrollCsvAction::defaultColumns(),
                    array_keys(ExportAttendancePeriodPayrollCsvAction::defaultColumns()),
                ));
            }

            return $template->load('columns');
        });
    }
}
