<?php

namespace App\Domains\Organization\Actions;

use App\Models\Company;
use App\Models\OperationalScopeAssignment;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class EndOperationalScopeAction
{
    public function handle(Company $company, OperationalScopeAssignment $scope, string $effectiveTo): OperationalScopeAssignment
    {
        if ($scope->company_id !== $company->id) {
            throw new InvalidArgumentException('El alcance operativo debe pertenecer a la empresa activa.');
        }

        $to = CarbonImmutable::parse($effectiveTo)->startOfDay();
        if ($to->lt($scope->effective_from)) {
            throw new InvalidArgumentException('La fecha final no puede ser anterior al inicio del alcance.');
        }

        $scope->forceFill([
            'effective_to' => $to->toDateString(),
            'status' => 'inactive',
        ])->save();

        return $scope->refresh();
    }
}