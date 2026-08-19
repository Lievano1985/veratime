<?php

namespace App\Domains\Organization\Support;

use App\Domains\Organization\Actions\ResolveEmploymentUnitsForDateAction;
use App\Domains\Organization\Actions\ResolveUserOperationalScopeAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Support\RoleKey;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class ScopedOperationalAccess
{
    public function __construct(
        private ResolveUserOperationalScopeAction $resolveUserScope,
        private ResolveEmploymentUnitsForDateAction $resolveEmploymentUnits,
    ) {
    }

    public function canOperateCompany(User $user, Company $company, ?string $date = null): bool
    {
        if (! $this->hasActiveContext($user, $company)) {
            return false;
        }

        if (in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true)) {
            return true;
        }

        return in_array($user->roleKeyForCompany($company), RoleKey::scopedOperators(), true)
            && $this->hasAnyScope($user, $company, $date);
    }

    public function canOperateCenter(User $user, Company $company, Center|int|null $center, ?string $date = null): bool
    {
        if (! $this->hasActiveContext($user, $company)) {
            return false;
        }

        if (in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true)) {
            return true;
        }

        if (! in_array($user->roleKeyForCompany($company), RoleKey::scopedOperators(), true) || ! $center) {
            return false;
        }

        $centerId = $center instanceof Center ? $center->id : $center;
        $scope = $this->scope($user, $company, $date);

        if (in_array($centerId, $scope['center_ids'], true)) {
            return true;
        }

        return OrganizationalUnit::query()
            ->where('company_id', $company->id)
            ->where('center_id', $centerId)
            ->whereIn('id', $scope['organizational_unit_ids'])
            ->exists();
    }

    public function canOperateFullCenter(User $user, Company $company, Center|int|null $center, ?string $date = null): bool
    {
        if (! $this->hasActiveContext($user, $company)) {
            return false;
        }

        if (in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true)) {
            return true;
        }

        if (! in_array($user->roleKeyForCompany($company), RoleKey::scopedOperators(), true) || ! $center) {
            return false;
        }

        $centerId = $center instanceof Center ? $center->id : $center;
        $scope = $this->scope($user, $company, $date);

        return in_array($centerId, $scope['center_ids'], true);
    }

    public function canOperateUnit(User $user, Company $company, OrganizationalUnit|int|null $unit, ?string $date = null): bool
    {
        if (! $this->hasActiveContext($user, $company)) {
            return false;
        }

        if (in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true)) {
            return true;
        }

        if (! in_array($user->roleKeyForCompany($company), RoleKey::scopedOperators(), true) || ! $unit) {
            return false;
        }

        $unit = $unit instanceof OrganizationalUnit
            ? $unit
            : OrganizationalUnit::query()->where('company_id', $company->id)->find($unit);

        if (! $unit || $unit->company_id !== $company->id || $unit->status !== 'active') {
            return false;
        }

        $scope = $this->scope($user, $company, $date);

        return in_array($unit->center_id, $scope['center_ids'], true)
            || in_array($unit->id, $scope['organizational_unit_ids'], true);
    }

    public function canOperateRelationship(User $user, Company $company, EmploymentRelationship $relationship, ?string $date = null): bool
    {
        if (! $this->hasActiveContext($user, $company) || $relationship->company_id !== $company->id) {
            return false;
        }

        if (in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true)) {
            return true;
        }

        if (! in_array($user->roleKeyForCompany($company), RoleKey::scopedOperators(), true)) {
            return false;
        }

        $date = CarbonImmutable::parse($date ?? now()->toDateString())->toDateString();
        $scope = $this->scope($user, $company, $date);

        if (in_array($relationship->center_id, $scope['center_ids'], true)) {
            return true;
        }

        $units = $this->resolveEmploymentUnits->handle($company, $relationship, $date);
        $unitIds = [];
        if ($units['primary']) {
            $unitIds[] = $units['primary']->id;
        }
        foreach ($units['temporary_supports'] as $support) {
            $unitIds[] = $support->id;
        }

        return array_intersect($unitIds, $scope['organizational_unit_ids']) !== [];
    }

    public function canConsultCompany(User $user, Company $company, ?string $date = null): bool
    {
        if (! $this->hasActiveContext($user, $company)) {
            return false;
        }

        if (in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true)) {
            return true;
        }

        return in_array($user->roleKeyForCompany($company), [...RoleKey::scopedOperators(), ...RoleKey::scopedViewers()], true)
            && $this->hasAnyScope($user, $company, $date);
    }

    public function canConsultCenter(User $user, Company $company, Center|int|null $center, ?string $date = null): bool
    {
        if (! $this->hasActiveContext($user, $company)) {
            return false;
        }

        if (in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true)) {
            return true;
        }

        if (! in_array($user->roleKeyForCompany($company), [...RoleKey::scopedOperators(), ...RoleKey::scopedViewers()], true) || ! $center) {
            return false;
        }

        $centerId = $center instanceof Center ? $center->id : $center;
        $scope = $this->scope($user, $company, $date);

        if (in_array($centerId, $scope['center_ids'], true)) {
            return true;
        }

        return OrganizationalUnit::query()
            ->where('company_id', $company->id)
            ->where('center_id', $centerId)
            ->whereIn('id', $scope['organizational_unit_ids'])
            ->exists();
    }

    public function canConsultRelationship(User $user, Company $company, EmploymentRelationship $relationship, ?string $date = null): bool
    {
        if (! $this->hasActiveContext($user, $company) || $relationship->company_id !== $company->id) {
            return false;
        }

        if (in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true)) {
            return true;
        }

        if (! in_array($user->roleKeyForCompany($company), [...RoleKey::scopedOperators(), ...RoleKey::scopedViewers()], true)) {
            return false;
        }

        $date = CarbonImmutable::parse($date ?? now()->toDateString())->toDateString();
        $scope = $this->scope($user, $company, $date);

        if (in_array($relationship->center_id, $scope['center_ids'], true)) {
            return true;
        }

        $units = $this->resolveEmploymentUnits->handle($company, $relationship, $date);
        $unitIds = [];
        if ($units['primary']) {
            $unitIds[] = $units['primary']->id;
        }
        foreach ($units['temporary_supports'] as $support) {
            $unitIds[] = $support->id;
        }

        return array_intersect($unitIds, $scope['organizational_unit_ids']) !== [];
    }
    public function scope(User $user, Company $company, ?string $date = null): array
    {
        return $this->resolveUserScope->handle($company, $user, CarbonImmutable::parse($date ?? now()->toDateString())->toDateString());
    }

    private function hasAnyScope(User $user, Company $company, ?string $date = null): bool
    {
        try {
            $scope = $this->scope($user, $company, $date);
        } catch (InvalidArgumentException) {
            return false;
        }

        return $scope['center_ids'] !== [] || $scope['organizational_unit_ids'] !== [];
    }

    private function hasActiveContext(User $user, Company $company): bool
    {
        return $company->status === 'active'
            && $user->status === 'active'
            && $user->belongsToCompany($company);
    }
}
