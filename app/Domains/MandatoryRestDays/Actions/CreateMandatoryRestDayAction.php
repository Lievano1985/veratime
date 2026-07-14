<?php

namespace App\Domains\MandatoryRestDays\Actions;

use App\Models\Company;
use App\Models\MandatoryRestDay;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateMandatoryRestDayAction
{
    public function handle(?Company $company, array $data): MandatoryRestDay
    {
        $type = $data['type'] ?? 'legal_mandatory';
        $scope = $data['scope'] ?? null;
        $status = $data['status'] ?? 'active';
        $captureSource = $data['capture_source'] ?? 'manual';
        $countryCode = $this->normalizeCountryCode($data['country_code'] ?? 'MX');
        $jurisdictionCode = $this->normalizeJurisdictionCode($data['jurisdiction_code'] ?? null);

        $this->assertValidPayload($company, $type, $scope, $countryCode, $jurisdictionCode, $status, $captureSource, $data);

        $date = CarbonImmutable::parse($data['date'])->toDateString();

        return DB::transaction(function () use ($company, $data, $type, $scope, $countryCode, $jurisdictionCode, $status, $captureSource, $date): MandatoryRestDay {
            $this->assertUniqueMandatoryRestDay($type, $scope, $countryCode, $company, $jurisdictionCode, $date, $data['name']);

            $restDay = new MandatoryRestDay([
                'name' => $data['name'],
                'date' => $date,
                'type' => $type,
                'scope' => $scope,
                'country_code' => $countryCode,
                'jurisdiction_code' => $scope === 'subnational' ? $jurisdictionCode : null,
                'source_reference' => $data['source_reference'] ?? null,
                'capture_source' => $captureSource,
                'status' => $status,
                'metadata' => $data['metadata'] ?? [],
            ]);

            $restDay->company()->associate($scope === 'company' ? $company : null);
            $restDay->save();

            return $restDay->refresh();
        });
    }

    private function assertValidPayload(?Company $company, string $type, ?string $scope, string $countryCode, ?string $jurisdictionCode, string $status, string $captureSource, array $data): void
    {
        if (blank($data['name'] ?? null)) {
            throw new InvalidArgumentException('El nombre del descanso obligatorio es requerido.');
        }

        if (blank($data['date'] ?? null)) {
            throw new InvalidArgumentException('La fecha del descanso obligatorio es requerida.');
        }

        if (! in_array($type, MandatoryRestDay::TYPES, true)) {
            throw new InvalidArgumentException('El tipo del descanso obligatorio no es valido.');
        }

        if (! in_array($scope, MandatoryRestDay::SCOPES, true)) {
            throw new InvalidArgumentException('El alcance del descanso obligatorio no es valido.');
        }

        if (! in_array($status, MandatoryRestDay::STATUSES, true)) {
            throw new InvalidArgumentException('El estado del descanso obligatorio no es valido.');
        }

        if (! in_array($captureSource, MandatoryRestDay::CAPTURE_SOURCES, true)) {
            throw new InvalidArgumentException('El origen de captura del descanso obligatorio no es valido.');
        }

        if (! $this->isValidCountryCode($countryCode)) {
            throw new InvalidArgumentException('El codigo de pais es obligatorio y debe usar formato ISO de 2 letras.');
        }

        if ($type === 'company_internal' && $scope !== 'company') {
            throw new InvalidArgumentException('Los descansos internos de empresa solo pueden tener alcance de empresa.');
        }

        if (in_array($type, ['legal_mandatory', 'electoral'], true) && ! in_array($scope, ['national', 'subnational'], true)) {
            throw new InvalidArgumentException('Los descansos legales o electorales solo pueden tener alcance nacional o subnacional.');
        }

        if ($scope === 'national') {
            if ($company || filled($jurisdictionCode)) {
                throw new InvalidArgumentException('El alcance nacional no debe tener empresa ni jurisdiccion.');
            }

            return;
        }

        if ($scope === 'subnational') {
            if ($company) {
                throw new InvalidArgumentException('El alcance subnacional no debe pertenecer a una empresa.');
            }

            if (! $this->isValidJurisdictionCode($jurisdictionCode)) {
                throw new InvalidArgumentException('La jurisdiccion es obligatoria y debe tener formato normalizado.');
            }

            return;
        }

        if ($scope === 'company' && ! $company) {
            throw new InvalidArgumentException('Los descansos obligatorios de empresa requieren una empresa activa.');
        }

        if ($scope === 'company' && filled($jurisdictionCode)) {
            throw new InvalidArgumentException('El alcance de empresa no debe tener jurisdiccion.');
        }
    }

    private function assertUniqueMandatoryRestDay(string $type, string $scope, string $countryCode, ?Company $company, ?string $jurisdictionCode, string $date, string $name): void
    {
        $exists = MandatoryRestDay::query()
            ->where('type', $type)
            ->where('scope', $scope)
            ->where('country_code', $countryCode)
            ->whereDate('date', $date)
            ->where('name', $name)
            ->tap(fn (Builder $query) => $this->applyScopeIdentity($query, $scope, $company, $jurisdictionCode))
            ->lockForUpdate()
            ->exists();

        if ($exists) {
            throw new InvalidArgumentException('Ya existe un descanso obligatorio con el mismo alcance, fecha y nombre.');
        }
    }

    private function applyScopeIdentity(Builder $query, string $scope, ?Company $company, ?string $jurisdictionCode): void
    {
        if ($scope === 'national') {
            $query->whereNull('company_id')->whereNull('jurisdiction_code');

            return;
        }

        if ($scope === 'subnational') {
            $query->whereNull('company_id')->where('jurisdiction_code', $jurisdictionCode);

            return;
        }

        $query->where('company_id', $company->id)->whereNull('jurisdiction_code');
    }

    private function normalizeCountryCode(?string $countryCode): string
    {
        return strtoupper(trim((string) $countryCode));
    }

    private function normalizeJurisdictionCode(?string $jurisdictionCode): ?string
    {
        $jurisdictionCode = trim((string) $jurisdictionCode);

        return $jurisdictionCode === '' ? null : strtoupper($jurisdictionCode);
    }

    private function isValidCountryCode(string $countryCode): bool
    {
        return preg_match('/^[A-Z]{2}$/', $countryCode) === 1;
    }

    private function isValidJurisdictionCode(?string $jurisdictionCode): bool
    {
        return is_string($jurisdictionCode) && preg_match('/^[A-Z]{2}-[A-Z0-9]{2,8}$/', $jurisdictionCode) === 1;
    }
}
