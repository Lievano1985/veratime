<?php

namespace App\Domains\Workers\Actions;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ImportWorkerWorkbookAction
{
    public function handle(User $actor, Company $company, string $path, string $expectedHash): int
    {
        if (! is_file($path) || ! hash_equals($expectedHash, hash_file('sha256', $path))) {
            throw ValidationException::withMessages(['file' => 'El archivo cambió. Genera otra vista previa.']);
        }

        return DB::transaction(function () use ($actor, $company, $path) {
            $company = Company::query()->lockForUpdate()->findOrFail($company->id);
            $rows = app(ReadWorkerWorkbookAction::class)->handle($actor, $company, $path);
            if (collect($rows)->contains(fn ($row) => $row['errors'] !== [])) {
                throw ValidationException::withMessages(['file' => 'Hay errores o los datos cambiaron. Revisa nuevamente el archivo antes de importar.']);
            }
            foreach ($rows as $row) {
                $center = $company->centers()->where('status', 'active')->findOrFail($row['center_id']);
                app(SaveWorkerWithEmploymentRelationshipAction::class)->handle($company, null, $center, array_merge($row['data'], [
                    'source' => 'excel', 'relationship_source' => 'excel',
                    'metadata' => ['imported_by' => $actor->id, 'imported_at' => now()->toIso8601String()],
                ]), $actor);
            }

            return count($rows);
        });
    }
}
