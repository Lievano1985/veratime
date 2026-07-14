<?php

namespace App\Domains\Organization\Actions;

use App\Domains\Organization\Support\OrganizationalUnitTree;
use App\Models\Company;
use App\Models\User;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class ResolveUserOperationalScopeAction
{
    public function __construct(private OrganizationalUnitTree $tree)
    {
    }

    public function handle(Company $company, User $user, string $date): array
    {
        if ($company->status !== 'active' || $user->status !== 'active' || ! $user->belongsToCompany($company)) {
            throw new InvalidArgumentException('El usuario debe tener membresia activa en la empresa activa.');
        }

        $date = CarbonImmutable::parse($date)->toDateString();
        $scopes = $user->operationalScopeAssignments()
            ->with(['center', 'organizationalUnit'])
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->whereDate('effective_from', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date);
            })
            ->get();

        $centerIds = $scopes->pluck('center_id')->filter()->values()->all();
        $unitIds = [];

        foreach ($scopes->whereNotNull('organizational_unit_id') as $scope) {
            if ($scope->organizationalUnit) {
                $unitIds = [...$unitIds, ...$this->tree->descendantIds($scope->organizationalUnit)];
            }
        }

        return [
            'date' => $date,
            'scopes' => $scopes,
            'center_ids' => array_values(array_unique($centerIds)),
            'organizational_unit_ids' => array_values(array_unique($unitIds)),
        ];
    }
}