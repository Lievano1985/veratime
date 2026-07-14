<?php

namespace App\Domains\Organization\Actions;

use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\EmploymentUnitAssignment;
use App\Models\OrganizationalUnit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AssignTemporarySupportAction
{
    public function handle(Company $company, EmploymentRelationship $relationship, OrganizationalUnit $unit, array $data): EmploymentUnitAssignment
    {
        [$from, $to] = app(AssignPrimaryOrganizationalUnitAction::class)->dates($data);
        $this->assertValid($company, $relationship, $unit, $from, $to);

        return DB::transaction(function () use ($company, $relationship, $unit, $data, $from, $to): EmploymentUnitAssignment {
            if ($this->hasDuplicateSupport($company, $relationship, $unit, $from, $to)) {
                throw new InvalidArgumentException('Ya existe un apoyo temporal vigente para la misma unidad y periodo.');
            }

            return app(AssignPrimaryOrganizationalUnitAction::class)->create($company, $relationship, $unit, 'temporary_support', $data, $from, $to);
        });
    }

    private function assertValid(Company $company, EmploymentRelationship $relationship, OrganizationalUnit $unit, CarbonImmutable $from, ?CarbonImmutable $to): void
    {
        if ($company->status !== 'active' || $relationship->company_id !== $company->id || $unit->company_id !== $company->id) {
            throw new InvalidArgumentException('La relacion laboral y la unidad de apoyo deben pertenecer a la empresa activa.');
        }

        if ($relationship->status !== 'active' || $unit->status !== 'active') {
            throw new InvalidArgumentException('La relacion laboral y la unidad de apoyo deben estar activas.');
        }

        if ($relationship->started_at && $from->lt(CarbonImmutable::parse($relationship->started_at)->startOfDay())) {
            throw new InvalidArgumentException('El apoyo no puede iniciar antes de la relacion laboral.');
        }

        if ($relationship->ended_at) {
            $endedAt = CarbonImmutable::parse($relationship->ended_at)->startOfDay();
            if ($from->gt($endedAt) || ($to && $to->gt($endedAt))) {
                throw new InvalidArgumentException('La relacion laboral no esta vigente para el apoyo temporal.');
            }
        }

        if (! in_array($data['source'] ?? 'manual', ['manual', 'import', 'api', 'system'], true)) {
            throw new InvalidArgumentException('El origen del apoyo temporal no es valido.');
        }
    }

    private function hasDuplicateSupport(Company $company, EmploymentRelationship $relationship, OrganizationalUnit $unit, CarbonImmutable $from, ?CarbonImmutable $to): bool
    {
        $periodEnd = $to?->toDateString() ?? '9999-12-31';

        return EmploymentUnitAssignment::query()
            ->where('company_id', $company->id)
            ->where('employment_relationship_id', $relationship->id)
            ->where('organizational_unit_id', $unit->id)
            ->where('assignment_type', 'temporary_support')
            ->where('status', 'active')
            ->whereDate('effective_from', '<=', $periodEnd)
            ->where(function ($query) use ($from): void {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from->toDateString());
            })
            ->lockForUpdate()
            ->exists();
    }
}