<?php

namespace App\Policies;

use App\Domains\Organization\Actions\ResolveUserOperationalScopeAction;
use App\Models\Company;
use App\Models\OrganizationalUnit;
use App\Models\ScheduleBatch;
use App\Models\User;
use App\Support\RoleKey;
use InvalidArgumentException;

class ScheduleBatchPolicy
{
    public function viewAny(User $user, Company $company): bool
    {
        return $this->canManageCompanyScheduling($user, $company)
            || $this->hasSupervisorScope($user, $company);
    }

    public function view(User $user, ScheduleBatch $batch): bool
    {
        if ($batch->company?->status !== 'active' || ! $user->belongsToCompany($batch->company)) {
            return false;
        }

        if ($this->canManageCompanyScheduling($user, $batch->company)) {
            return true;
        }

        return $this->supervisorCanSeeCenter($user, $batch->company, $batch->center_id, $batch->period_start->toDateString());
    }

    public function create(User $user, Company $company): bool
    {
        return $this->canManageCompanyScheduling($user, $company);
    }

    public function update(User $user, ScheduleBatch $batch): bool
    {
        return $batch->status === 'draft' && $this->canManageCompanyScheduling($user, $batch->company);
    }

    public function generate(User $user, ScheduleBatch $batch): bool
    {
        return $this->update($user, $batch);
    }

    public function publish(User $user, ScheduleBatch $batch): bool
    {
        return $this->update($user, $batch);
    }

    public function createCorrection(User $user, ScheduleBatch $batch): bool
    {
        return $batch->status === 'published'
            && $this->canManageCompanyScheduling($user, $batch->company);
    }

    public function compareVersions(User $user, ScheduleBatch $batch): bool
    {
        return $this->view($user, $batch);
    }

    public function publishCorrection(User $user, ScheduleBatch $batch): bool
    {
        return $batch->status === 'draft'
            && $batch->previous_batch_id !== null
            && $this->canManageCompanyScheduling($user, $batch->company);
    }

    public function viewVersionHistory(User $user, ScheduleBatch $batch): bool
    {
        return $this->view($user, $batch);
    }

    public function deleteDraft(User $user, ScheduleBatch $batch): bool
    {
        return $this->update($user, $batch);
    }

    private function canManageCompanyScheduling(User $user, Company $company): bool
    {
        return $company->status === 'active'
            && $user->status === 'active'
            && $user->belongsToCompany($company)
            && in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true);
    }

    private function hasSupervisorScope(User $user, Company $company): bool
    {
        if ($company->status !== 'active'
            || $user->status !== 'active'
            || ! $user->belongsToCompany($company)
            || $user->roleKeyForCompany($company) !== RoleKey::SUPERVISOR) {
            return false;
        }

        try {
            $scope = app(ResolveUserOperationalScopeAction::class)->handle($company, $user, now()->toDateString());
        } catch (InvalidArgumentException) {
            return false;
        }

        return $scope['center_ids'] !== [] || $scope['organizational_unit_ids'] !== [];
    }

    private function supervisorCanSeeCenter(User $user, Company $company, int $centerId, string $date): bool
    {
        if ($company->status !== 'active'
            || $user->status !== 'active'
            || ! $user->belongsToCompany($company)
            || $user->roleKeyForCompany($company) !== RoleKey::SUPERVISOR) {
            return false;
        }

        $scope = app(ResolveUserOperationalScopeAction::class)->handle($company, $user, $date);

        if (in_array($centerId, $scope['center_ids'], true)) {
            return true;
        }

        if ($scope['organizational_unit_ids'] === []) {
            return false;
        }

        return OrganizationalUnit::query()
            ->where('company_id', $company->id)
            ->where('center_id', $centerId)
            ->whereIn('id', $scope['organizational_unit_ids'])
            ->exists();
    }
}
