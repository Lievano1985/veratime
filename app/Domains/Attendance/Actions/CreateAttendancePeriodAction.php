<?php

namespace App\Domains\Attendance\Actions;

use App\Models\AttendancePeriod;
use App\Models\AttendancePeriodScope;
use App\Models\Center;
use App\Models\Company;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateAttendancePeriodAction
{
    /**
     * @param list<int> $organizationalUnitIds
     */
    public function handle(Company $company, Center $center, array $data, array $organizationalUnitIds = [], ?User $createdBy = null): AttendancePeriod
    {
        return DB::transaction(function () use ($company, $center, $data, $organizationalUnitIds, $createdBy): AttendancePeriod {
            if ($company->status !== 'active' || $center->company_id !== $company->id || $center->status !== 'active') {
                throw new InvalidArgumentException('El centro debe pertenecer a la empresa activa.');
            }

            $start = CarbonImmutable::parse((string) ($data['period_start'] ?? ''))->toDateString();
            $end = CarbonImmutable::parse((string) ($data['period_end'] ?? ''))->toDateString();

            if ($end < $start) {
                throw new InvalidArgumentException('La fecha final debe ser igual o posterior a la fecha inicial.');
            }

            $unitIds = $this->validateUnits($company, $center, $organizationalUnitIds);
            $scopeType = $unitIds === [] ? AttendancePeriod::SCOPE_CENTER : AttendancePeriod::SCOPE_ORGANIZATIONAL_UNITS;

            $this->ensureNoDuplicateOrOverlap($company, $center, $scopeType, $unitIds, $start, $end);

            $period = new AttendancePeriod([
                'scope_type' => $scopeType,
                'name' => blank($data['name'] ?? null) ? null : trim((string) $data['name']),
                'period_start' => $start,
                'period_end' => $end,
                'timezone' => $center->timezone ?: $company->timezone,
                'status' => AttendancePeriod::STATUS_OPEN,
                'notes' => blank($data['notes'] ?? null) ? null : trim((string) $data['notes']),
                'metadata' => ['created_from' => 'manual_range'],
            ]);
            $period->company()->associate($company);
            $period->center()->associate($center);
            $period->creator()->associate($createdBy);
            $period->save();

            foreach ($unitIds as $unitId) {
                $scope = new AttendancePeriodScope();
                $scope->company()->associate($company);
                $scope->attendancePeriod()->associate($period);
                $scope->organizationalUnit()->associate($unitId);
                $scope->save();
            }

            return $period->refresh()->load(['center', 'scopes.organizationalUnit']);
        });
    }

    /**
     * @param list<int> $unitIds
     * @return list<int>
     */
    private function validateUnits(Company $company, Center $center, array $unitIds): array
    {
        $unitIds = array_values(array_unique(array_map('intval', array_filter($unitIds))));

        if ($unitIds === []) {
            return [];
        }

        $count = OrganizationalUnit::query()
            ->where('company_id', $company->id)
            ->where('center_id', $center->id)
            ->where('status', 'active')
            ->whereIn('id', $unitIds)
            ->count();

        if ($count !== count($unitIds)) {
            throw new InvalidArgumentException('Todas las unidades deben estar activas y pertenecer al centro seleccionado.');
        }

        return $unitIds;
    }

    /**
     * @param list<int> $unitIds
     */
    private function ensureNoDuplicateOrOverlap(Company $company, Center $center, string $scopeType, array $unitIds, string $start, string $end): void
    {
        $query = AttendancePeriod::query()
            ->where('company_id', $company->id)
            ->where('center_id', $center->id)
            ->where('status', '!=', AttendancePeriod::STATUS_CANCELLED)
            ->whereDate('period_start', '<=', $end)
            ->whereDate('period_end', '>=', $start);

        if ($scopeType === AttendancePeriod::SCOPE_CENTER) {
            $exists = (clone $query)
                ->where('scope_type', AttendancePeriod::SCOPE_CENTER)
                ->exists();
        } else {
            $exists = (clone $query)
                ->where('scope_type', AttendancePeriod::SCOPE_ORGANIZATIONAL_UNITS)
                ->whereHas('scopes', fn ($scopeQuery) => $scopeQuery->whereIn('organizational_unit_id', $unitIds))
                ->exists();
        }

        if ($exists) {
            throw new InvalidArgumentException('Ya existe un periodo abierto o vigente que se cruza con ese alcance y rango.');
        }
    }
}
