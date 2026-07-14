<?php

namespace App\Domains\MandatoryRestDays\Actions;

use App\Models\Company;
use App\Models\MandatoryRestDay;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdateMandatoryRestDayAction
{
    public function handle(Company $company, MandatoryRestDay $restDay, array $data): MandatoryRestDay
    {
        $this->assertEditableFromCompanyContext($company, $restDay);

        $type = $data['type'] ?? $restDay->type;
        $scope = $data['scope'] ?? $restDay->scope;
        $status = $data['status'] ?? $restDay->status;
        $captureSource = $data['capture_source'] ?? $restDay->capture_source ?? 'manual';
        $stateCode = $this->normalizeStateCode($data['state_code'] ?? $restDay->state_code);

        $targetCompany = $scope === 'company' ? $company : null;

        $this->assertValidPayload($targetCompany, $type, $scope, $stateCode, $status, $captureSource, $data);

        $date = CarbonImmutable::parse($data['date'])->toDateString();

        return DB::transaction(function () use ($targetCompany, $restDay, $data, $type, $scope, $stateCode, $status, $captureSource, $date): MandatoryRestDay {
            $this->assertUniqueMandatoryRestDay($type, $scope, $targetCompany, $stateCode, $date, $data['name'], $restDay->id);

            $restDay->forceFill([
                'name' => $data['name'],
                'date' => $date,
                'type' => $type,
                'scope' => $scope,
                'state_code' => $scope === 'state' ? $stateCode : null,
                'source_reference' => $data['source_reference'] ?? $restDay->source_reference,
                'capture_source' => $captureSource,
                'status' => $status,
                'metadata' => $data['metadata'] ?? $restDay->metadata ?? [],
            ]);

            $restDay->company()->associate($scope === 'company' ? $targetCompany : null);
            $restDay->save();

            return $restDay->refresh();
        });
    }

    private function assertEditableFromCompanyContext(Company $company, MandatoryRestDay $restDay): void
    {
        if ($restDay->company_id !== null && $restDay->company_id !== $company->id) {
            throw new InvalidArgumentException('El descanso obligatorio debe pertenecer a la empresa activa.');
        }
    }

    private function assertValidPayload(?Company $company, string $type, ?string $scope, ?string $stateCode, string $status, string $captureSource, array $data): void
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

        if ($type === 'company_internal' && $scope !== 'company') {
            throw new InvalidArgumentException('Los descansos internos de empresa solo pueden tener alcance de empresa.');
        }

        if (in_array($type, ['legal_mandatory', 'electoral'], true) && ! in_array($scope, ['national', 'state'], true)) {
            throw new InvalidArgumentException('Los descansos legales o electorales solo pueden tener alcance nacional o estatal.');
        }

        if ($scope === 'national') {
            if ($company || filled($stateCode)) {
                throw new InvalidArgumentException('El alcance nacional no debe tener empresa ni codigo de estado.');
            }

            return;
        }

        if ($scope === 'state') {
            if ($company) {
                throw new InvalidArgumentException('El alcance estatal no debe pertenecer a una empresa.');
            }

            if (! $this->isValidStateCode($stateCode)) {
                throw new InvalidArgumentException('El codigo de estado es obligatorio y debe tener formato normalizado.');
            }

            return;
        }

        if ($scope === 'company' && ! $company) {
            throw new InvalidArgumentException('Los descansos obligatorios de empresa requieren una empresa activa.');
        }

        if ($scope === 'company' && filled($stateCode)) {
            throw new InvalidArgumentException('El alcance de empresa no debe tener codigo de estado.');
        }
    }

    private function assertUniqueMandatoryRestDay(string $type, string $scope, ?Company $company, ?string $stateCode, string $date, string $name, int $ignoreId): void
    {
        $exists = MandatoryRestDay::query()
            ->whereKeyNot($ignoreId)
            ->where('type', $type)
            ->where('scope', $scope)
            ->whereDate('date', $date)
            ->where('name', $name)
            ->tap(fn (Builder $query) => $this->applyScopeIdentity($query, $scope, $company, $stateCode))
            ->lockForUpdate()
            ->exists();

        if ($exists) {
            throw new InvalidArgumentException('Ya existe un descanso obligatorio con el mismo alcance, fecha y nombre.');
        }
    }

    private function applyScopeIdentity(Builder $query, string $scope, ?Company $company, ?string $stateCode): void
    {
        if ($scope === 'national') {
            $query->whereNull('company_id');

            return;
        }

        if ($scope === 'state') {
            $query->whereNull('company_id')->where('state_code', $stateCode);

            return;
        }

        $query->where('company_id', $company->id);
    }

    private function normalizeStateCode(?string $stateCode): ?string
    {
        $stateCode = trim((string) $stateCode);

        return $stateCode === '' ? null : strtoupper($stateCode);
    }

    private function isValidStateCode(?string $stateCode): bool
    {
        return is_string($stateCode) && preg_match('/^[A-Z]{2}-[A-Z0-9]{2,5}$/', $stateCode) === 1;
    }
}
