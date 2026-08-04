<?php

namespace Database\Seeders;

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
    }

    /**
     * @param array<string, mixed> $value
     */
    private function seedRule(string $code, string $name, string $category, array $value, string $unit): void
    {
        $rule = LegalRule::query()->updateOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'description' => 'Parametro base para clasificacion legal de jornadas en Vera Time.',
                'category' => $category,
                'status' => LegalRule::STATUS_ACTIVE,
            ],
        );

        LegalRuleVersion::query()->updateOrCreate(
            ['legal_rule_id' => $rule->id, 'version' => 1],
            [
                'value' => $value,
                'unit' => $unit,
                'source_reference' => 'docs/06-Legal/LEG-0001-LFT-MEXICO.md',
                'effective_from' => '2026-01-01',
                'effective_to' => null,
                'status' => LegalRuleVersion::STATUS_ACTIVE,
                'notes' => 'Version base para MVP; las reglas futuras deben versionarse sin sobrescribir historicos.',
                'created_by' => null,
            ],
        );
    }
}
