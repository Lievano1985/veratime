<?php

namespace App\Domains\LegalRules\Actions;

use App\Domains\LegalRules\Support\CompanyLegalParameterCatalog;
use App\Models\Company;
use App\Models\LegalParameter;
use App\Models\LegalRuleVersion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateCompanyLegalParameterAction
{
    public function __construct(
        private readonly CompanyLegalParameterCatalog $catalog,
        private readonly ResolveLegalRuleVersionForDateAction $rules,
    ) {}

    public function handle(Company $company, string $code, int $value, string $effectiveFrom, string $reason, User $actor): LegalParameter
    {
        if ($company->status !== 'active') {
            throw ValidationException::withMessages([
                'legalParameterForm' => 'La configuracion legal requiere una empresa activa.',
            ]);
        }

        $definition = $this->catalog->get($code);
        $date = CarbonImmutable::parse($effectiveFrom)->startOfDay();
        $this->validateValue($definition, $value, $date);

        return DB::transaction(function () use ($company, $code, $value, $reason, $actor, $definition, $date): LegalParameter {
            $existing = LegalParameter::query()
                ->where('company_id', $company->id)
                ->where('code', $code)
                ->whereDate('effective_from', $date->toDateString())
                ->where('status', LegalParameter::STATUS_ACTIVE)
                ->first();

            if ($existing) {
                $existing->forceFill([
                    'value' => [(string) $definition['value_key'] => $value],
                    'reason' => $reason,
                    'source_reference' => 'company_legal_configuration',
                    'updated_by' => $actor->id,
                    'metadata' => [
                        'schema_version' => 1,
                        'definition' => $definition,
                    ],
                ])->save();

                return $existing->refresh();
            }

            LegalParameter::query()
                ->where('company_id', $company->id)
                ->where('code', $code)
                ->where('status', LegalParameter::STATUS_ACTIVE)
                ->where(function ($query) use ($date): void {
                    $query
                        ->whereNull('effective_to')
                        ->orWhereDate('effective_to', '>=', $date->toDateString());
                })
                ->update([
                    'effective_to' => $date->subDay()->toDateString(),
                    'updated_by' => $actor->id,
                ]);

            return LegalParameter::query()->create([
                'company_id' => $company->id,
                'code' => $code,
                'value' => [(string) $definition['value_key'] => $value],
                'effective_from' => $date->toDateString(),
                'effective_to' => null,
                'status' => LegalParameter::STATUS_ACTIVE,
                'source_reference' => 'company_legal_configuration',
                'reason' => $reason,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
                'metadata' => [
                    'schema_version' => 1,
                    'definition' => $definition,
                ],
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function validateValue(array $definition, int $value, CarbonImmutable $effectiveFrom): void
    {
        if ($value < (int) $definition['min']) {
            throw ValidationException::withMessages([
                'legalParameterForm' => "El valor minimo permitido es {$definition['min']} minutos.",
            ]);
        }

        $maximum = $this->maximumFor($definition, $effectiveFrom);

        if ($value > $maximum) {
            $message = (bool) $definition['protected_max']
                ? "Este parametro no puede superar {$maximum} minutos porque debilitara el limite legal vigente."
                : "El valor maximo permitido es {$maximum} minutos.";

            throw ValidationException::withMessages([
                'legalParameterForm' => $message,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function maximumFor(array $definition, CarbonImmutable $effectiveFrom): int
    {
        $maximum = (int) $definition['max'];
        $ruleCode = $definition['legal_rule_code'] ?? null;

        if (! is_string($ruleCode) || $ruleCode === '') {
            return $maximum;
        }

        $version = $this->rules->handle($ruleCode, $effectiveFrom);

        if (! $version instanceof LegalRuleVersion) {
            return $maximum;
        }

        return min($maximum, (int) ($version->value[(string) $definition['value_key']] ?? $maximum));
    }
}
