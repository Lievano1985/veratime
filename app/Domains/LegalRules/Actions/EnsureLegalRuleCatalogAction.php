<?php

namespace App\Domains\LegalRules\Actions;

use App\Models\LegalRule;
use App\Models\LegalRuleVersion;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class EnsureLegalRuleCatalogAction
{
    public function ensureProgressiveWeeklyLimits(): void
    {
        DB::transaction(function (): void {
            foreach ([
                'maximum_weekly_hours' => ['Limite semanal ordinario', 'weekly_limit', [2880, 2760, 2640, 2520, 2400]],
                'weekly_overtime_double_limit' => ['Limite semanal de tiempo extra con pago doble', 'overtime_limit', [540, 540, 600, 660, 720]],
            ] as $code => [$name, $category, $limits]) {
                $versions = [];
                foreach ($limits as $index => $minutes) {
                    $year = 2026 + $index;
                    $versions[] = [
                        'version' => $index + 1,
                        'value' => ['minutes' => $minutes],
                        'unit' => 'minutes',
                        'effective_from' => "{$year}-01-01",
                        'effective_to' => $year === 2030 ? null : "{$year}-12-31",
                        'status' => LegalRuleVersion::STATUS_ACTIVE,
                        'source_reference' => 'https://www.dof.gob.mx/abrirPDF.php?anio=2026&archivo=01052026-VES.pdf',
                        'notes' => 'Catalogo progresivo DOF 01-05-2026; las versiones existentes se conservan.',
                        'created_by' => null,
                    ];
                }

                $this->handle($code, [
                    'name' => $name,
                    'category' => $category,
                    'description' => 'Parametro legal versionado por fecha de trabajo.',
                    'status' => LegalRule::STATUS_ACTIVE,
                ], $versions);
            }
        });
    }

    /**
     * Add catalog entries without rewriting existing legal evidence or approval states.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<array<string, mixed>>  $versions
     */
    public function handle(string $code, array $attributes, array $versions): void
    {
        DB::transaction(function () use ($code, $attributes, $versions): void {
            $rule = LegalRule::query()->firstOrCreate(['code' => $code], $attributes);
            $rule = LegalRule::query()->lockForUpdate()->findOrFail($rule->id);

            foreach ($versions as $attributes) {
                $existing = $rule->versions()->orderByDesc('version')->get();
                $sameStart = $existing->filter(fn (LegalRuleVersion $version): bool => $version->effective_from->toDateString() === $attributes['effective_from']);

                if ($sameStart->isNotEmpty()) {
                    $matches = $sameStart->every(fn (LegalRuleVersion $version): bool => $version->value == $attributes['value']
                        && $version->unit === $attributes['unit']
                        && ($version->effective_to?->toDateString() === $attributes['effective_to']
                            || $version->effective_to === null));

                    if (! $matches) {
                        throw new RuntimeException("Conflicto en el catalogo legal {$code} para {$attributes['effective_from']}; se conservaron las versiones existentes. Requiere revision explicita.");
                    }

                    // Keep identifiers, sources, validity and approval states intact.
                    continue;
                }

                $conflict = $existing->contains(function (LegalRuleVersion $version) use ($attributes, $versions): bool {
                    $from = $version->effective_from->toDateString();
                    $to = $version->effective_to?->toDateString();
                    $overlaps = ($attributes['effective_to'] === null || $from <= $attributes['effective_to'])
                        && ($to === null || $to >= $attributes['effective_from']);
                    $knownLegacyVersion = collect($versions)->contains(fn (array $entry): bool => $entry['effective_from'] === $from
                        && $entry['value'] == $version->value
                        && $entry['unit'] === $version->unit
                        && ($to === null || $to === $entry['effective_to']));

                    return $overlaps && ! $knownLegacyVersion;
                });

                if ($conflict) {
                    throw new RuntimeException("Conflicto de vigencia en el catalogo legal {$code}; se conservaron las versiones existentes. Requiere revision explicita.");
                }

                $number = max((int) $attributes['version'], (int) $existing->max('version') + 1);
                $rule->versions()->create(array_replace($attributes, ['version' => $number]));
            }
        });
    }
}
