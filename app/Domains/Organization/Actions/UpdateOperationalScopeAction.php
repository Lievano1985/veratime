<?php

namespace App\Domains\Organization\Actions;

use App\Models\Center;
use App\Models\Company;
use App\Models\OperationalScopeAssignment;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdateOperationalScopeAction
{
    public function __construct(private AssignOperationalScopeAction $assignAction)
    {
    }

    public function handle(Company $company, OperationalScopeAssignment $scope, User $user, array $data, ?Center $center = null, ?OrganizationalUnit $unit = null): OperationalScopeAssignment
    {
        if ($scope->company_id !== $company->id) {
            throw new InvalidArgumentException('El alcance operativo debe pertenecer a la empresa activa.');
        }

        [$from, $to] = $this->assignAction->dates($data);
        $this->assignAction->assertValid($company, $user, $data, $center, $unit);

        return DB::transaction(function () use ($company, $scope, $user, $data, $center, $unit, $from, $to): OperationalScopeAssignment {
            if ($scope->status === 'active' && $this->assignAction->hasOverlap($company, $user, $center, $unit, $from, $to, $scope->id)) {
                throw new InvalidArgumentException('Ya existe un alcance operativo vigente para el mismo usuario y alcance.');
            }

            $scope->forceFill([
                'user_id' => $user->id,
                'center_id' => $center?->id,
                'organizational_unit_id' => $unit?->id,
                'responsibility_type' => $data['responsibility_type'] ?? 'supervisor',
                'effective_from' => $from->toDateString(),
                'effective_to' => $to?->toDateString(),
                'source' => $data['source'] ?? $scope->source,
                'reason' => $data['reason'] ?? null,
                'created_by' => $data['created_by'] ?? $scope->created_by,
                'metadata' => $data['metadata'] ?? $scope->metadata,
            ])->save();

            return $scope->refresh();
        });
    }
}
