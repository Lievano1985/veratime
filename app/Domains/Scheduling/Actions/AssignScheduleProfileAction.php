<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Organization\Actions\EnsureUserCanManageWorkerAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\OrganizationalUnit;
use App\Models\ScheduleProfile;
use App\Models\ScheduleProfileAssignment;
use App\Models\User;
use App\Support\RoleKey;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AssignScheduleProfileAction
{
    public function __construct(private EnsureUserCanManageWorkerAction $ensureUserCanManageWorker)
    {
    }

    public function handle(Company $company, ScheduleProfile $profile, array $data, ?User $createdBy = null): ScheduleProfileAssignment
    {
        $this->assertCompanyAndProfile($company, $profile);

        $normalized = $this->normalizeAssignment($company, $data);
        $this->authorizeSupervisorScope($company, $normalized, $createdBy);

        return DB::transaction(function () use ($company, $profile, $normalized, $createdBy): ScheduleProfileAssignment {
            $this->lockScopeRows($company, $normalized);
            $this->assertNoOverlap($company, $normalized);

            $assignment = new ScheduleProfileAssignment([
                ...$normalized,
                'status' => 'active',
                'source' => in_array($normalized['source'] ?? 'manual', ['manual', 'import', 'api', 'system'], true)
                    ? ($normalized['source'] ?? 'manual')
                    : 'manual',
                'created_by' => $createdBy?->id,
            ]);
            $assignment->company()->associate($company);
            $assignment->scheduleProfile()->associate($profile);
            $assignment->save();

            return $assignment->refresh()->load(['scheduleProfile', 'center', 'organizationalUnit', 'employmentRelationship']);
        });
    }

    private function assertCompanyAndProfile(Company $company, ScheduleProfile $profile): void
    {
        if ($company->status !== 'active' || $profile->company_id !== $company->id || $profile->status !== 'active') {
            throw new InvalidArgumentException('El perfil activo debe pertenecer a la empresa activa.');
        }
    }

    private function normalizeAssignment(Company $company, array $data): array
    {
        $scope = $data['assignment_scope'] ?? null;
        if (! in_array($scope, ['company', 'center', 'organizational_unit', 'employment_relationship'], true)) {
            throw new InvalidArgumentException('El alcance de asignacion no es valido.');
        }

        $effectiveFrom = CarbonImmutable::parse($data['effective_from'] ?? now()->toDateString())->toDateString();
        $effectiveTo = blank($data['effective_to'] ?? null) ? null : CarbonImmutable::parse($data['effective_to'])->toDateString();
        if ($effectiveTo !== null && $effectiveTo < $effectiveFrom) {
            throw new InvalidArgumentException('La vigencia final no puede ser anterior a la inicial.');
        }

        $normalized = [
            'assignment_scope' => $scope,
            'center_id' => null,
            'organizational_unit_id' => null,
            'employment_relationship_id' => null,
            'effective_from' => $effectiveFrom,
            'effective_to' => $effectiveTo,
            'source' => $data['source'] ?? 'manual',
            'reason' => blank($data['reason'] ?? null) ? null : trim((string) $data['reason']),
            'metadata' => $data['metadata'] ?? [],
        ];

        if ($scope === 'center') {
            $center = Center::query()->where('company_id', $company->id)->where('status', 'active')->find($data['center_id'] ?? null);
            if (! $center) {
                throw new InvalidArgumentException('El centro debe pertenecer a la empresa activa.');
            }
            $normalized['center_id'] = $center->id;
        }

        if ($scope === 'organizational_unit') {
            $unit = OrganizationalUnit::query()->where('company_id', $company->id)->where('status', 'active')->find($data['organizational_unit_id'] ?? null);
            if (! $unit) {
                throw new InvalidArgumentException('La unidad organizacional debe estar activa y pertenecer a la empresa.');
            }
            $normalized['organizational_unit_id'] = $unit->id;
        }

        if ($scope === 'employment_relationship') {
            $relationship = EmploymentRelationship::query()->where('company_id', $company->id)->find($data['employment_relationship_id'] ?? null);
            if (! $relationship || ! $this->relationshipCoversRange($relationship, $effectiveFrom, $effectiveTo)) {
                throw new InvalidArgumentException('La relacion laboral debe pertenecer a la empresa y ser valida para la vigencia.');
            }
            $normalized['employment_relationship_id'] = $relationship->id;
        }

        $provided = array_filter([
            'center' => $data['center_id'] ?? null,
            'unit' => $data['organizational_unit_id'] ?? null,
            'relationship' => $data['employment_relationship_id'] ?? null,
        ], fn ($value): bool => filled($value));

        $expectedCount = $scope === 'company' ? 0 : 1;
        if (count($provided) !== $expectedCount) {
            throw new InvalidArgumentException('El alcance debe informar exactamente las columnas permitidas.');
        }

        return $normalized;
    }

    private function relationshipCoversRange(EmploymentRelationship $relationship, string $from, ?string $to): bool
    {
        if ($relationship->started_at->toDateString() > $from) {
            return false;
        }

        if ($relationship->ended_at === null) {
            return true;
        }

        return $to !== null && $relationship->ended_at->toDateString() >= $to;
    }

    private function authorizeSupervisorScope(Company $company, array $normalized, ?User $user): void
    {
        if (! $user) {
            return;
        }

        if ($user->status !== 'active' || ! $user->belongsToCompany($company)) {
            throw new InvalidArgumentException('El usuario no puede asignar perfiles en esta empresa.');
        }

        $role = $user->roleKeyForCompany($company);
        if (in_array($role, RoleKey::companyManagers(), true)) {
            return;
        }

        if ($role !== RoleKey::SUPERVISOR || $normalized['assignment_scope'] !== 'employment_relationship') {
            throw new InvalidArgumentException('El usuario solo puede asignar perfiles dentro de su alcance permitido.');
        }

        $relationship = EmploymentRelationship::query()->findOrFail($normalized['employment_relationship_id']);
        $this->ensureUserCanManageWorker->handle($user, $company, $relationship, $normalized['effective_from']);
    }

    private function lockScopeRows(Company $company, array $normalized): void
    {
        ScheduleProfileAssignment::query()
            ->where('company_id', $company->id)
            ->where('assignment_scope', $normalized['assignment_scope'])
            ->when($normalized['assignment_scope'] === 'company', fn ($query) => $query->whereNull('center_id')->whereNull('organizational_unit_id')->whereNull('employment_relationship_id'))
            ->when($normalized['assignment_scope'] === 'center', fn ($query) => $query->where('center_id', $normalized['center_id']))
            ->when($normalized['assignment_scope'] === 'organizational_unit', fn ($query) => $query->where('organizational_unit_id', $normalized['organizational_unit_id']))
            ->when($normalized['assignment_scope'] === 'employment_relationship', fn ($query) => $query->where('employment_relationship_id', $normalized['employment_relationship_id']))
            ->lockForUpdate()
            ->get();
    }

    private function assertNoOverlap(Company $company, array $normalized, ?int $ignoreId = null): void
    {
        $from = $normalized['effective_from'];
        $to = $normalized['effective_to'] ?? '9999-12-31';

        $overlap = ScheduleProfileAssignment::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->where('assignment_scope', $normalized['assignment_scope'])
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->when($normalized['assignment_scope'] === 'company', fn ($query) => $query->whereNull('center_id')->whereNull('organizational_unit_id')->whereNull('employment_relationship_id'))
            ->when($normalized['assignment_scope'] === 'center', fn ($query) => $query->where('center_id', $normalized['center_id']))
            ->when($normalized['assignment_scope'] === 'organizational_unit', fn ($query) => $query->where('organizational_unit_id', $normalized['organizational_unit_id']))
            ->when($normalized['assignment_scope'] === 'employment_relationship', fn ($query) => $query->where('employment_relationship_id', $normalized['employment_relationship_id']))
            ->whereDate('effective_from', '<=', $to)
            ->where(function ($query) use ($from): void {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from);
            })
            ->exists();

        if ($overlap) {
            throw new InvalidArgumentException('Ya existe una asignacion activa solapada para el mismo alcance.');
        }
    }
}
