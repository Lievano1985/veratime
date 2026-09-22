<?php

namespace App\Domains\Attendance\Actions;

use App\Models\Company;
use App\Models\PayrollExportTemplate;

class ResolvePayrollExportTemplateAction
{
    public function __construct(private readonly EnsureDefaultPayrollExportTemplateAction $ensureDefault) {}

    public function handle(Company $company, ?int $templateId = null): PayrollExportTemplate
    {
        $this->ensureDefault->handle($company);

        return PayrollExportTemplate::query()
            ->with('columns')
            ->where('company_id', $company->id)
            ->where('status', PayrollExportTemplate::STATUS_ACTIVE)
            ->when($templateId, fn ($query) => $query->whereKey($templateId))
            ->when(! $templateId, fn ($query) => $query->orderByDesc('is_default'))
            ->orderBy('name')
            ->firstOrFail();
    }
}
