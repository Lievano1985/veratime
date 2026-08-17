<?php

namespace App\Domains\Organization\Actions;

use App\Models\Company;
use App\Models\OperationalScopeAssignment;
use InvalidArgumentException;

class DeleteOperationalScopeAction
{
    public function handle(Company $company, OperationalScopeAssignment $scope): void
    {
        if ($scope->company_id !== $company->id) {
            throw new InvalidArgumentException('El alcance operativo debe pertenecer a la empresa activa.');
        }

        if (OperationalScopeAssignment::query()->where('replaced_by_id', $scope->id)->exists()) {
            throw new InvalidArgumentException('No se puede borrar un alcance que forma parte del historial de reemplazo.');
        }

        $scope->delete();
    }
}
