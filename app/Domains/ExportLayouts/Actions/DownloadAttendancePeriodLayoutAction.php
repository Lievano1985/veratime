<?php

namespace App\Domains\ExportLayouts\Actions;

use App\Domains\ExportLayouts\Support\LayoutDefinition;
use App\Models\AttendancePeriod;
use App\Models\Company;
use App\Models\ExportLayout;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadAttendancePeriodLayoutAction
{
    public function __construct(private readonly BuildPeriodLayoutAction $builder, private readonly DownloadLayoutMatrixAction $download) {}

    public function handle(User $actor, Company $company, AttendancePeriod $period, ?int $layoutId = null): StreamedResponse
    {
        abort_unless($period->company_id === $company->id, 404);
        Gate::forUser($actor)->authorize('exportPayrollXlsx', $period);
        $query = ExportLayout::where('company_id', $company->id);
        $layout = $layoutId ? $query->findOrFail($layoutId) : $query->where('is_default', true)->first();
        if (! $layout || $layout->status !== 'active') {
            throw ValidationException::withMessages(['export' => 'Selecciona un layout activo o define uno como predeterminado en Layouts de exportación.']);
        }
        $definition = LayoutDefinition::validate($layout->configuration);
        $matrix = $this->builder->handle($actor, $company, $period, $definition);

        return $this->download->handle($definition, $matrix, 'incidencias-'.Str::slug($layout->name).'-'.$period->period_start->format('Ymd').'-r'.$layout->revision);
    }
}
