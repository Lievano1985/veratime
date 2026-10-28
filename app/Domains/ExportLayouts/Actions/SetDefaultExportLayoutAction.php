<?php

namespace App\Domains\ExportLayouts\Actions;

use App\Models\Company;
use App\Models\ExportLayout;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SetDefaultExportLayoutAction
{
    public function handle(User $actor, Company $company, ExportLayout $layout): ExportLayout
    {
        abort_unless($layout->company_id === $company->id, 404);
        Gate::forUser($actor)->authorize('update', $layout);

        return DB::transaction(function () use ($company, $layout): ExportLayout {
            Company::whereKey($company->id)->lockForUpdate()->firstOrFail();
            $layout = ExportLayout::where('company_id', $company->id)->lockForUpdate()->findOrFail($layout->id);
            if ($layout->status !== 'active') {
                throw ValidationException::withMessages(['defaultLayout' => 'Activa y guarda el layout antes de usarlo como predeterminado.']);
            }
            ExportLayout::where('company_id', $company->id)->where('is_default', true)->update(['is_default' => false]);
            $layout->forceFill(['is_default' => true])->save();

            return $layout;
        });
    }
}
