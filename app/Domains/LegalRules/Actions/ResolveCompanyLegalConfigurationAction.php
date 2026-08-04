<?php

namespace App\Domains\LegalRules\Actions;

use App\Domains\LegalRules\Support\CompanyLegalParameterCatalog;
use App\Models\Company;
use App\Models\LegalRuleVersion;
use Carbon\CarbonImmutable;

class ResolveCompanyLegalConfigurationAction
{
    public function __construct(
        private readonly CompanyLegalParameterCatalog $catalog,
        private readonly ResolveLegalRuleVersionForDateAction $rules,
        private readonly ResolveLegalParameterForDateAction $parameters,
    ) {}

    /**
     * @return array{country: string, work_date: string, rules: list<array<string, mixed>>, parameters: array<string, array<string, mixed>>}
     */
    public function handle(Company $company, ?string $workDate = null): array
    {
        $date = $workDate ?: CarbonImmutable::now($company->setting?->default_timezone ?: $company->timezone)->toDateString();
        $ruleCodes = [
            'daytime_window',
            'night_minutes_mixed_threshold',
            'daily_limit_diurnal',
            'daily_limit_nocturnal',
            'daily_limit_mixed',
            'maximum_weekly_hours',
        ];

        $parameters = [];

        foreach ($this->catalog->all() as $code => $definition) {
            $parameter = $this->parameters->handle($company, $code, $date);
            $valueKey = (string) $definition['value_key'];

            $parameters[$code] = [
                'code' => $code,
                'definition' => $definition,
                'value' => $parameter?->value[$valueKey] ?? $definition['default'],
                'effective_from' => $parameter?->effective_from?->toDateString() ?? $date,
                'effective_to' => $parameter?->effective_to?->toDateString(),
                'reason' => $parameter?->reason,
                'source_reference' => $parameter?->source_reference,
                'parameter_id' => $parameter?->id,
            ];
        }

        return [
            'country' => 'MX',
            'work_date' => $date,
            'rules' => collect($ruleCodes)
                ->map(fn (string $code): ?array => $this->ruleSnapshot($code, $date))
                ->filter()
                ->values()
                ->all(),
            'parameters' => $parameters,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function ruleSnapshot(string $code, string $date): ?array
    {
        $version = $this->rules->handle($code, $date);

        if (! $version instanceof LegalRuleVersion) {
            return null;
        }

        return [
            'code' => $code,
            'name' => $version->legalRule?->name,
            'category' => $version->legalRule?->category,
            'value' => $version->value,
            'unit' => $version->unit,
            'version' => $version->version,
            'source_reference' => $version->source_reference,
            'effective_from' => $version->effective_from?->toDateString(),
            'effective_to' => $version->effective_to?->toDateString(),
        ];
    }
}
