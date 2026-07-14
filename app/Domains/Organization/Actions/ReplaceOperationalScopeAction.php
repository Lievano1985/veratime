<?php

namespace App\Domains\Organization\Actions;

use App\Models\Center;
use App\Models\Company;
use App\Models\OperationalScopeAssignment;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReplaceOperationalScopeAction
{
    public function __construct(private AssignOperationalScopeAction $assignAction)
    {
    }

    public function handle(Company $company, User $user, array $data, ?Center $center = null, ?OrganizationalUnit $unit = null): OperationalScopeAssignment
    {
        [$from, $to] = $this->assignAction->dates($data);
        $this->assignAction->assertValid($company, $user, $data, $center, $unit);

        return DB::transaction(function () use ($company, $user, $data, $center, $unit, $from, $to): OperationalScopeAssignment {
            $active = OperationalScopeAssignment::query()
                ->where('company_id', $company->id)
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->when($center, fn ($query) => $query->where('center_id', $center->id)->whereNull('organizational_unit_id'))
                ->when($unit, fn ($query) => $query->where('organizational_unit_id', $unit->id)->whereNull('center_id'))
                ->lockForUpdate()
                ->get();

            foreach ($active as $scope) {
                if ($scope->effective_from->gte($from)) {
                    throw new InvalidArgumentException('El nuevo alcance debe iniciar despues del alcance vigente.');
                }

                if ($scope->effective_to && $scope->effective_to->lt($from)) {
                    continue;
                }

                $scope->forceFill([
                    'effective_to' => $from->subDay()->toDateString(),
                    'status' => 'replaced',
                ])->save();
            }

            $new = $this->assignAction->create($company, $user, $data, $center, $unit, $from, $to);

            foreach ($active as $scope) {
                if ($scope->status === 'replaced') {
                    $scope->forceFill(['replaced_by_id' => $new->id])->save();
                }
            }

            return $new->refresh();
        });
    }
}