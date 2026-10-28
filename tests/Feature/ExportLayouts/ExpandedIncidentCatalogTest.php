<?php

use App\Domains\ExportLayouts\Actions\BuildLayoutMatrixAction;
use App\Domains\ExportLayouts\Actions\SaveExportLayoutAction;
use App\Domains\ExportLayouts\Support\IncidentCatalog;
use App\Domains\ExportLayouts\Support\LayoutDefinition;
use App\Domains\ExportLayouts\Support\LayoutExample;
use App\Models\AttendanceIncident;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleKey;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;

function definitionForIncident(string $key, string $unit = 'days', string $code = 'TEST'): array
{
    $definition = LayoutDefinition::defaults(false);
    $definition['name'] = 'Concepto aislado';
    foreach ($definition['rules'] as &$rule) {
        if ($rule['key'] === $key) {
            $rule['enabled'] = true;
            $rule['code'] = $code;
            $rule['unit'] = $unit;
        }
    }
    unset($rule);

    return $definition;
}

it('includes every recorded Vera Time attendance incident without enabling it by default', function (): void {
    foreach (AttendanceIncident::types() as $type) {
        expect(IncidentCatalog::all())->toHaveKey($type)
            ->and(IncidentCatalog::all()[$type]['available'])->toBeTrue()
            ->and(IncidentCatalog::all()[$type]['kind'])->toBe('incident');
        $definition = definitionForIncident($type);
        $row = array_replace(LayoutExample::rows()[0], ['tipo_incidencia' => $type, 'estatus_incidencia' => 'approved']);
        $matrix = app(BuildLayoutMatrixAction::class)->handle($definition, [$row], '2026-10-12', '2026-10-18');
        expect($matrix['rows'][0][1])->toBe('1TEST')->and($matrix['errors'])->toBe([]);
        $row['estatus_incidencia'] = 'cancelled';
        $matrix = app(BuildLayoutMatrixAction::class)->handle($definition, [$row], '2026-10-12', '2026-10-18');
        expect($matrix['incident_cells'])->toBe(0);
    }
    expect(collect(IncidentCatalog::rules())->where('enabled', true)->pluck('key')->all())
        ->toBe(['unjustified_absence', 'paid_permission', 'unpaid_permission', 'late_arrival', 'overtime_double', 'overtime_triple']);
});

it('exports Sunday and Sunday premium separately in the chosen units without calculating money', function (): void {
    $rows = LayoutExample::rows();
    foreach (['days' => '1DOM', 'hours' => '8DOM', 'minutes' => '480DOM'] as $unit => $expected) {
        $matrix = app(BuildLayoutMatrixAction::class)->handle(definitionForIncident('sunday_work', $unit, 'DOM'), $rows, '2026-10-12', '2026-10-18');
        expect($matrix['rows'][0][7])->toBe($expected)->and($matrix['errors'])->toBe([]);
    }
    $matrix = app(BuildLayoutMatrixAction::class)->handle(definitionForIncident('sunday_premium', 'days', 'PD'), $rows, '2026-10-12', '2026-10-18');
    expect($matrix['rows'][0][7])->toBe('1PD');
    expect(fn () => LayoutDefinition::validate(definitionForIncident('sunday_premium', 'hours')))->toThrow(ValidationException::class);
});

it('exports all available frozen rest holiday pause and time concepts independently', function (): void {
    $facts = [
        'scheduled_rest_work' => ['days', '2026-10-18', '1TEST'],
        'sunday_rest_work' => ['days', '2026-10-18', '1TEST'],
        'mandatory_rest_work' => ['days', '2026-10-17', '1TEST'],
        'scheduled_rest' => ['days', '2026-10-18', '1TEST'],
        'ordinary_hours' => ['hours', '2026-10-13', '8TEST'],
        'overtime_total' => ['hours', '2026-10-13', '2.5TEST'],
        'night_hours' => ['hours', '2026-10-13', '2TEST'],
        'holiday_normal_hours' => ['hours', '2026-10-17', '8TEST'],
        'holiday_additional_hours' => ['hours', '2026-10-17', '8TEST'],
        'paid_break' => ['minutes', '2026-10-13', '30TEST'],
        'unpaid_break' => ['hours', '2026-10-13', '1TEST'],
        'early_departure' => ['minutes', '2026-10-13', '15TEST'],
        'absence_time' => ['hours', '2026-10-12', '8TEST'],
        'incident_day' => ['days', '2026-10-12', '1TEST'],
        'paid_incident' => ['days', '2026-10-15', '1TEST'],
        'unpaid_incident' => ['days', '2026-10-12', '1TEST'],
    ];
    foreach ($facts as $key => [$unit, $date, $expected]) {
        $source = array_values(array_filter(LayoutExample::rows(), fn ($row) => $row['fecha'] === $date));
        $matrix = app(BuildLayoutMatrixAction::class)->handle(definitionForIncident($key, $unit), $source, '2026-10-12', '2026-10-18');
        expect($matrix['errors'])->toBe([])->and(implode('|', array_merge(...$matrix['rows'])))->toContain($expected);
    }
});

it('does not interpret missing frozen indicators as a false value or recalculate old closures', function (): void {
    $row = LayoutExample::rows()[0];
    unset($row['domingo_trabajado']);
    $matrix = app(BuildLayoutMatrixAction::class)->handle(definitionForIncident('sunday_work'), [$row], '2026-10-12', '2026-10-18');
    expect(implode(' ', $matrix['errors']))->toContain('no conserva un indicador');
    $row['domingo_trabajado'] = 'si';
    unset($row['horas_domingo_trabajado']);
    $matrix = app(BuildLayoutMatrixAction::class)->handle(definitionForIncident('sunday_work', 'hours'), [$row], '2026-10-12', '2026-10-18');
    expect(implode(' ', $matrix['errors']))->toContain('no conserva sus horas');
});

it('adds the expanded catalog to old layouts without changing selected codes order or persistence', function (): void {
    $company = Company::factory()->create();
    $user = User::factory()->create(['status' => 'active']);
    $role = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
    $user->companies()->attach($company, ['role_id' => $role->id, 'status' => 'active', 'is_default' => true]);
    $definition = LayoutDefinition::defaults();
    $definition['rules'] = array_slice($definition['rules'], 0, 19);
    $definition['rules'][0]['code'] = 'FTA';
    $layout = app(SaveExportLayoutAction::class)->handle($user, $company, $definition);
    // Simulate a layout stored before the expanded catalog, without migrating its record.
    $layout->update(['configuration' => $definition]);
    $before = $layout->fresh()->getRawOriginal();
    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);
    $screen = Volt::test('export-layouts.index')->call('openLayout', $layout->id)
        ->assertSet('form.rules.0.code', 'FTA')->assertSet('form.rules.0.enabled', true);
    $form = $screen->get('form');
    expect(count($form['rules']))->toBe(count(IncidentCatalog::all()))
        ->and(collect($form['rules'])->firstWhere('key', 'sunday_work')['enabled'])->toBeFalse()
        ->and($layout->fresh()->getRawOriginal())->toBe($before);
    $screen->set('activeTab', 'incidents')->set('incidentSearch', 'dominical')->assertSee('Prima dominical aplicable')
        ->assertDontSee('Pausa computable / pagada');
    $screen->set('incidentSearch', '')->call('saveLayout')->assertHasNoErrors();
    expect($layout->fresh()->configuration['rules'][0]['code'])->toBe('FTA');
});

it('continues to report a conflict when Sunday and Sunday premium occupy the same cell', function (): void {
    $definition = definitionForIncident('sunday_work', 'days', 'DOM');
    foreach ($definition['rules'] as &$rule) {
        if ($rule['key'] === 'sunday_premium') {
            $rule['enabled'] = true;
            $rule['code'] = 'PD';
        }
    }
    unset($rule);
    $matrix = app(BuildLayoutMatrixAction::class)->handle($definition, LayoutExample::rows(), '2026-10-12', '2026-10-18');
    expect(implode(' ', $matrix['errors']))->toContain('1DOM, 1PD');
});
