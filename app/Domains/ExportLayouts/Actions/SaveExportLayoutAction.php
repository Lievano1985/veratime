<?php

namespace App\Domains\ExportLayouts\Actions;

use App\Domains\ExportLayouts\Support\LayoutDefinition;
use App\Models\Company;
use App\Models\ExportLayout;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveExportLayoutAction
{
    public function handle(User $actor, Company $company, array $data, ?ExportLayout $layout = null, ?int $revision = null): ExportLayout
    {
        abort_if($layout && $layout->company_id !== $company->id, 404);
        $layout ? Gate::forUser($actor)->authorize('update', $layout) : Gate::forUser($actor)->authorize('create', [ExportLayout::class, $company]);
        $data = LayoutDefinition::validate($data);

        return DB::transaction(function () use ($actor, $company, $data, $layout, $revision): ExportLayout {
            Company::whereKey($company->id)->lockForUpdate()->firstOrFail();
            $record = $layout ? ExportLayout::where('company_id', $company->id)->lockForUpdate()->findOrFail($layout->id) : new ExportLayout;
            if ($layout && $revision !== $record->revision) {
                throw ValidationException::withMessages(['form.name' => 'Este layout cambió en otra sesión. Vuelve a abrirlo antes de guardar.']);
            }
            if (ExportLayout::where('company_id', $company->id)->where('name', $data['name'])->when($layout, fn ($query) => $query->whereKeyNot($layout->id))->exists()) {
                throw ValidationException::withMessages(['form.name' => 'Ya existe un layout con este nombre en la empresa.']);
            }
            $record->forceFill([
                'is_default' => $data['status'] === 'active' && ($record->is_default || (! $layout && ! ExportLayout::where('company_id', $company->id)->exists())),
                'company_id' => $company->id, 'name' => $data['name'], 'application' => $data['application'] ?? null,
                'status' => $data['status'], 'configuration' => $data,
                'revision' => $layout ? $record->revision + 1 : 1,
                'created_by' => $record->created_by ?? $actor->id,
            ])->save();

            return $record;
        });
    }
}
