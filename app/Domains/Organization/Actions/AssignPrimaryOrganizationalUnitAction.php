<?php

namespace App\Domains\Organization\Actions;

use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\EmploymentUnitAssignment;
use App\Models\OrganizationalUnit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AssignPrimaryOrganizationalUnitAction
{
    public function handle(Company $company, EmploymentRelationship $relationship, OrganizationalUnit $unit, array $data): EmploymentUnitAssignment
    {
        if (blank($data['effective_from'] ?? null)) {
            $data['effective_from'] = $relationship->started_at?->toDateString() ?? now()->toDateString();
        }

        $data['effective_to'] = null;

        [$from, $to] = $this->dates($data);
        $this->assertValid($company, $relationship, $unit, $from, $to, $data);

        return DB::transaction(function () use ($company, $relationship, $unit, $data, $from, $to): EmploymentUnitAssignment {
            if ($this->hasPrimaryOverlap($company, $relationship, $from, $to)) {
                throw new InvalidArgumentException('La relacion laboral ya tiene una unidad principal activa.');
            }

            return $this->create($company, $relationship, $unit, 'primary', $data, $from, $to);
        });
    }

    public function create(Company $company, EmploymentRelationship $relationship, OrganizationalUnit $unit, string $type, array $data, CarbonImmutable $from, ?CarbonImmutable $to): EmploymentUnitAssignment
    {
        $assignment = new EmploymentUnitAssignment([
            'employment_relationship_id' => $relationship->id,
            'organizational_unit_id' => $unit->id,
            'assignment_type' => $type,
            'effective_from' => $from->toDateString(),
            'effective_to' => $to?->toDateString(),
            'status' => 'active',
            'source' => $data['source'] ?? 'manual',
            'reason' => $data['reason'] ?? null,
            'created_by' => $data['created_by'] ?? null,
            'metadata' => $data['metadata'] ?? [],
        ]);
        $assignment->company()->associate($company);
        $assignment->save();

        return $assignment->refresh();
    }

    public function dates(array $data): array
    {
        if (blank($data['effective_from'] ?? null)) {
            throw new InvalidArgumentException('La fecha inicial de asignacion organizacional es requerida.');
        }

        $from = CarbonImmutable::parse($data['effective_from'])->startOfDay();
        $to = filled($data['effective_to'] ?? null) ? CarbonImmutable::parse($data['effective_to'])->startOfDay() : null;

        if ($to && $to->lt($from)) {
            throw new InvalidArgumentException('La fecha final no puede ser anterior a la fecha inicial.');
        }

        return [$from, $to];
    }

    public function assertValid(Company $company, EmploymentRelationship $relationship, OrganizationalUnit $unit, CarbonImmutable $from, ?CarbonImmutable $to, array $data = []): void
    {
        if ($company->status !== 'active' || $relationship->company_id !== $company->id || $unit->company_id !== $company->id) {
            throw new InvalidArgumentException('La relacion laboral y la unidad deben pertenecer a la empresa activa.');
        }

        if ($relationship->status !== 'active' || $unit->status !== 'active') {
            throw new InvalidArgumentException('La relacion laboral y la unidad deben estar activas.');
        }

        if ($unit->center_id !== $relationship->center_id) {
            throw new InvalidArgumentException('La unidad principal debe pertenecer al centro de la relacion laboral.');
        }

        if (! in_array($data['source'] ?? 'manual', ['manual', 'import', 'api', 'system'], true)) {
            throw new InvalidArgumentException('El origen de la asignacion organizacional no es valido.');
        }
    }

    public function hasPrimaryOverlap(Company $company, EmploymentRelationship $relationship, CarbonImmutable $from, ?CarbonImmutable $to, ?int $exceptId = null): bool
    {
        return EmploymentUnitAssignment::query()
            ->where('company_id', $company->id)
            ->where('employment_relationship_id', $relationship->id)
            ->where('assignment_type', 'primary')
            ->where('status', 'active')
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->lockForUpdate()
            ->exists();
    }
}
