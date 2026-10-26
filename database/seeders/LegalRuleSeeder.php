<?php

namespace Database\Seeders;

use App\Domains\LegalRules\Actions\EnsureLegalRuleCatalogAction;
use App\Models\LegalRule;
use App\Models\LegalRuleVersion;
use Illuminate\Database\Seeder;

class LegalRuleSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedRule(
            code: 'daytime_window',
            name: 'Ventana de jornada diurna',
            category: 'classification',
            value: ['start' => '06:00', 'end' => '20:00'],
            unit: 'time_window',
        );

        $this->seedRule(
            code: 'night_minutes_mixed_threshold',
            name: 'Umbral nocturno para jornada mixta',
            category: 'classification',
            value: ['minutes' => 210],
            unit: 'minutes',
        );

        $this->seedRule(
            code: 'daily_limit_diurnal',
            name: 'Limite diario de jornada diurna',
            category: 'daily_limit',
            value: ['minutes' => 480],
            unit: 'minutes',
        );

        $this->seedRule(
            code: 'daily_limit_nocturnal',
            name: 'Limite diario de jornada nocturna',
            category: 'daily_limit',
            value: ['minutes' => 420],
            unit: 'minutes',
        );

        $this->seedRule(
            code: 'daily_limit_mixed',
            name: 'Limite diario de jornada mixta',
            category: 'daily_limit',
            value: ['minutes' => 450],
            unit: 'minutes',
        );

        app(EnsureLegalRuleCatalogAction::class)->ensureProgressiveWeeklyLimits();
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  list<array{version: int, value: array<string, mixed>, effective_from: string, effective_to: string|null}>|null  $versions
     */
    private function seedRule(string $code, string $name, string $category, array $value, string $unit, ?array $versions = null): void
    {
        $rule = LegalRule::query()->firstOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'description' => 'Parametro base para clasificacion legal de jornadas en Vera Time.',
                'category' => $category,
                'status' => LegalRule::STATUS_ACTIVE,
            ],
        );

        foreach ($versions ?? [[
            'version' => 1,
            'value' => $value,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
        ]] as $version) {
            LegalRuleVersion::query()->firstOrCreate(
                ['legal_rule_id' => $rule->id, 'version' => $version['version']],
                [
                    'value' => $version['value'],
                    'unit' => $unit,
                    'source_reference' => 'https://www.dof.gob.mx/abrirPDF.php?anio=2026&archivo=01052026-VES.pdf',
                    'effective_from' => $version['effective_from'],
                    'effective_to' => $version['effective_to'],
                    'status' => LegalRuleVersion::STATUS_ACTIVE,
                    'notes' => 'Regla legal versionada por vigencia; no sobrescribe calculos historicos.',
                    'created_by' => null,
                ],
            );
        }
    }
}
