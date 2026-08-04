<?php

namespace Database\Factories;

use App\Models\LegalRule;
use App\Models\LegalRuleVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegalRuleVersion>
 */
class LegalRuleVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'legal_rule_id' => LegalRule::factory(),
            'version' => 1,
            'value' => ['minutes' => 480],
            'unit' => 'minutes',
            'source_reference' => 'SRC-001',
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'status' => LegalRuleVersion::STATUS_ACTIVE,
            'notes' => null,
            'created_by' => null,
        ];
    }
}
