<?php

namespace App\Domains\Attendance\Actions;

use App\Models\Company;
use App\Models\PayrollExportTemplate;
use Illuminate\Support\Facades\DB;

class SavePayrollExportTemplateAction
{
    /**
     * @param  array{name:string,description?:string|null,status:string,is_default:bool,delimiter:string,columns:list<array{source_key:string,header:string}>}  $data
     */
    public function handle(Company $company, array $data, ?PayrollExportTemplate $template = null): PayrollExportTemplate
    {
        return DB::transaction(function () use ($company, $data, $template): PayrollExportTemplate {
            $template ??= $company->payrollExportTemplates()->make();
            $template->fill([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'],
                'is_default' => $data['is_default'],
                'delimiter' => $data['delimiter'],
            ]);
            $template->save();

            if ($template->is_default) {
                PayrollExportTemplate::query()
                    ->where('company_id', $company->id)
                    ->whereKeyNot($template->id)
                    ->update(['is_default' => false]);
            }

            $template->columns()->delete();
            $template->columns()->createMany(array_map(
                fn (array $column, int $position): array => [
                    'source_key' => $column['source_key'],
                    'header' => $column['header'],
                    'position' => $position + 1,
                ],
                $data['columns'],
                array_keys($data['columns']),
            ));

            return $template->fresh('columns');
        });
    }
}
