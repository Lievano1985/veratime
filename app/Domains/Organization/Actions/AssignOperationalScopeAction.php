<?php

namespace App\Domains\Organization\Actions;

use App\Models\Center;
use App\Models\Company;
use App\Models\OperationalScopeAssignment;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Support\RoleKey;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AssignOperationalScopeAction
{
    public function handle(Company $company, User $user, array $data, ?Center $center = null, ?OrganizationalUnit $unit = null): OperationalScopeAssignment
    {
        [$from, $to] = $this->dates($data);
        $this->assertValid($company, $user, $data, $center, $unit);

        return DB::transaction(function () use ($company, $user, $data, $center, $unit, $from, $to): OperationalScopeAssignment {
            if ($this->hasOverlap($company, $user, $center, $unit, $from, $to)) {
                throw new InvalidArgumentException('Ya existe un alcance operativo vigente para el mismo usuario y alcance.');
            }

            return $this->create($company, $user, $data, $center, $unit, $from, $to);
        });
    }

    public function create(Company $company, User $user, array $data, ?Center $center, ?OrganizationalUnit $unit, CarbonImmutable $from, ?CarbonImmutable $to): OperationalScopeAssignment
    {
        $scope = new OperationalScopeAssignment([
            'user_id' => $user->id,
            'center_id' => $center?->id,
            'organizational_unit_id' => $unit?->id,
            'responsibility_type' => $data['responsibility_type'] ?? 'supervisor',
            'effective_from' => $from->toDateString(),
            'effective_to' => $to?->toDateString(),
            'status' => 'active',
            'source' => $data['source'] ?? 'manual',
            'reason' => $data['reason'] ?? null,
            'created_by' => $data['created_by'] ?? null,
            'metadata' => $data['metadata'] ?? [],
        ]);
        $scope->company()->associate($company);
        $scope->save();

        return $scope->refresh();
    }

    public function dates(array $data): array
    {
        if (blank($data['effective_from'] ?? null)) {
            throw new InvalidArgumentException('La fecha inicial del alcance operativo es requerida.');
        }

        $from = CarbonImmutable::parse($data['effective_from'])->startOfDay();
        $to = filled($data['effective_to'] ?? null) ? CarbonImmutable::parse($data['effective_to'])->startOfDay() : null;

        if ($to && $to->lt($from)) {
            throw new InvalidArgumentException('La fecha final no puede ser anterior a la fecha inicial.');
        }

        return [$from, $to];
    }

    public function assertValid(Company $company, User $user, array $data, ?Center $center, ?OrganizationalUnit $unit): void
    {
        if ($company->status !== 'active' || $user->status !== 'active' || ! $user->belongsToCompany($company)) {
            throw new InvalidArgumentException('El usuario debe tener membresia activa en la empresa activa.');
        }

        if (! in_array($user->roleKeyForCompany($company), RoleKey::scopeAssignableRoles(), true)) {
            throw new InvalidArgumentException('Solo RH operativo o supervisor pueden recibir alcances operativos.');
        }

        if (($center === null && $unit === null) || ($center !== null && $unit !== null)) {
            throw new InvalidArgumentException('El alcance requiere exactamente un centro o una unidad organizacional.');
        }

        if ($unit && $user->roleKeyForCompany($company) === RoleKey::RH_OPERATIVO) {
            throw new InvalidArgumentException('RH operativo debe recibir alcance por centro completo.');
        }

        if (! in_array($data['responsibility_type'] ?? 'supervisor', ['supervisor', 'responsible'], true)) {
            throw new InvalidArgumentException('El tipo de responsabilidad no es valido.');
        }

        if (! in_array($data['source'] ?? 'manual', ['manual', 'import', 'api', 'system'], true)) {
            throw new InvalidArgumentException('El origen del alcance operativo no es valido.');
        }

        if ($center && ($center->company_id !== $company->id || $center->status !== 'active')) {
            throw new InvalidArgumentException('El centro del alcance debe pertenecer a la empresa activa.');
        }

        if ($unit && ($unit->company_id !== $company->id || $unit->status !== 'active')) {
            throw new InvalidArgumentException('La unidad del alcance debe pertenecer a la empresa activa.');
        }
    }

    public function hasOverlap(Company $company, User $user, ?Center $center, ?OrganizationalUnit $unit, CarbonImmutable $from, ?CarbonImmutable $to, ?int $exceptId = null): bool
    {
        $periodEnd = $to?->toDateString() ?? '9999-12-31';

        return OperationalScopeAssignment::query()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->when($center, fn ($query) => $query->where('center_id', $center->id)->whereNull('organizational_unit_id'))
            ->when($unit, fn ($query) => $query->where('organizational_unit_id', $unit->id)->whereNull('center_id'))
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->whereDate('effective_from', '<=', $periodEnd)
            ->where(function ($query) use ($from): void {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from->toDateString());
            })
            ->lockForUpdate()
            ->exists();
    }
}
