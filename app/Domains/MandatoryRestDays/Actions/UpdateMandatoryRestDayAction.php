<?php

namespace App\Domains\MandatoryRestDays\Actions;

use App\Models\Center;
use App\Models\Company;
use App\Models\MandatoryRestDay;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdateMandatoryRestDayAction
{
    public function handle(Company $company, MandatoryRestDay $restDay, ?Center $center, array $data): MandatoryRestDay
    {
        $this->assertOwnedByCompany($company, $restDay);

        $scope = $data['scope'] ?? $restDay->scope;
        $status = $data['status'] ?? $restDay->status;

        $this->assertValidPayload($company, $center, $scope, $status, $data);

        $date = CarbonImmutable::parse($data['date'])->toDateString();

        return DB::transaction(function () use ($company, $restDay, $center, $data, $scope, $status, $date): MandatoryRestDay {
            $this->assertUniqueMandatoryRestDay($scope, $company, $center, $date, $data['name'], $restDay->id);

            $restDay->forceFill([
                'name' => $data['name'],
                'date' => $date,
                'scope' => $scope,
                'source' => $data['source'] ?? $restDay->source,
                'status' => $status,
                'metadata' => $data['metadata'] ?? $restDay->metadata ?? [],
            ]);

            $restDay->center()->associate($scope === 'center' ? $center : null);
            $restDay->save();

            return $restDay->refresh();
        });
    }

    private function assertOwnedByCompany(Company $company, MandatoryRestDay $restDay): void
    {
        if ($restDay->company_id !== $company->id) {
            throw new InvalidArgumentException('Mandatory rest day must belong to the active company.');
        }
    }

    private function assertValidPayload(Company $company, ?Center $center, ?string $scope, string $status, array $data): void
    {
        if (blank($data['name'] ?? null)) {
            throw new InvalidArgumentException('Mandatory rest day name is required.');
        }

        if (blank($data['date'] ?? null)) {
            throw new InvalidArgumentException('Mandatory rest day date is required.');
        }

        if (! in_array($scope, ['company', 'center'], true)) {
            throw new InvalidArgumentException('Only company and center mandatory rest days can be updated from company context.');
        }

        if (! in_array($status, ['active', 'inactive'], true)) {
            throw new InvalidArgumentException('Mandatory rest day status is invalid.');
        }

        if ($scope === 'company' && $center) {
            throw new InvalidArgumentException('Company mandatory rest days cannot belong to a center.');
        }

        if ($scope === 'center') {
            if (! $center) {
                throw new InvalidArgumentException('Center mandatory rest days require a center.');
            }

            if ($center->company_id !== $company->id) {
                throw new InvalidArgumentException('Center must belong to the active company.');
            }
        }
    }

    private function assertUniqueMandatoryRestDay(string $scope, Company $company, ?Center $center, string $date, string $name, int $ignoreId): void
    {
        $exists = MandatoryRestDay::query()
            ->whereKeyNot($ignoreId)
            ->where('scope', $scope)
            ->whereDate('date', $date)
            ->where('name', $name)
            ->tap(fn (Builder $query) => $this->applyScopeIdentity($query, $scope, $company, $center))
            ->lockForUpdate()
            ->exists();

        if ($exists) {
            throw new InvalidArgumentException('Mandatory rest day already exists for the same scope, date and name.');
        }
    }

    private function applyScopeIdentity(Builder $query, string $scope, Company $company, ?Center $center): void
    {
        if ($scope === 'company') {
            $query->where('company_id', $company->id)->whereNull('center_id');

            return;
        }

        $query->where('company_id', $company->id)->where('center_id', $center->id);
    }
}