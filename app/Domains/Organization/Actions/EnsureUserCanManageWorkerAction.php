<?php

namespace App\Domains\Organization\Actions;

use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\User;
use App\Models\Worker;
use App\Support\RoleKey;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

class EnsureUserCanManageWorkerAction
{
    public function __construct(
        private ResolveEmploymentUnitsForDateAction $resolveEmploymentUnits,
        private ResolveUserOperationalScopeAction $resolveUserScope,
    ) {
    }

    /**
     * @throws AuthorizationException
     */
    public function handle(User $user, Company $company, EmploymentRelationship|Worker $target, ?string $date = null): void
    {
        $date = CarbonImmutable::parse($date ?? now()->toDateString())->toDateString();
        $relationship = $target instanceof EmploymentRelationship
            ? $target
            : $target->employmentRelationships()
                ->where('company_id', $company->id)
                ->where('status', 'active')
                ->whereDate('started_at', '<=', $date)
                ->where(function ($query) use ($date): void {
                    $query->whereNull('ended_at')->orWhereDate('ended_at', '>=', $date);
                })
                ->latest('started_at')
                ->first();

        if (! $relationship) {
            throw new AuthorizationException('No existe relacion laboral vigente para gestionar.');
        }

        if ($company->status !== 'active' || $user->status !== 'active' || ! $user->belongsToCompany($company) || $relationship->company_id !== $company->id) {
            throw new AuthorizationException('No autorizado para gestionar esta persona trabajadora.');
        }

        $role = $user->roleKeyForCompany($company);
        if (in_array($role, RoleKey::companyManagers(), true)) {
            return;
        }

        if ($role !== RoleKey::SUPERVISOR) {
            throw new AuthorizationException('No autorizado para gestionar esta persona trabajadora.');
        }

        $scope = $this->resolveUserScope->handle($company, $user, $date);
        if (in_array($relationship->center_id, $scope['center_ids'], true)) {
            return;
        }

        $units = $this->resolveEmploymentUnits->handle($company, $relationship, $date);
        $candidateUnitIds = [];
        if ($units['primary']) {
            $candidateUnitIds[] = $units['primary']->id;
        }
        foreach ($units['temporary_supports'] as $support) {
            $candidateUnitIds[] = $support->id;
        }

        if (array_intersect($candidateUnitIds, $scope['organizational_unit_ids']) !== []) {
            return;
        }

        throw new AuthorizationException('No autorizado para gestionar esta persona trabajadora fuera de alcance.');
    }
}