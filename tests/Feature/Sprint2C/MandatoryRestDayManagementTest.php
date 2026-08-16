<?php

use App\Domains\MandatoryRestDays\Actions\CreateMandatoryRestDayAction;
use App\Domains\MandatoryRestDays\Actions\InactivateMandatoryRestDayAction;
use App\Domains\MandatoryRestDays\Actions\ResolveMandatoryRestDaysForDateAction;
use App\Domains\MandatoryRestDays\Actions\UpdateMandatoryRestDayAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\MandatoryRestDay;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleKey;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Volt\Volt;

it('guest cannot access mandatory rest days route', function (): void {
    $this->get(route('mandatory-rest-days.index'))
        ->assertRedirect(route('login'));
});

it('authenticated user without active company cannot access mandatory rest days route', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('mandatory-rest-days.index'))
        ->assertForbidden();
});

it('inactive company blocks mandatory rest day operations', function (): void {
    $company = Company::factory()->create(['status' => 'inactive']);
    $user = mandatoryRestDayUserWithCompany($company);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('mandatory-rest-days.index')
        ->assertForbidden();
});

it('unauthorized role cannot manage mandatory rest days', function (): void {
    $company = Company::factory()->create();
    $user = mandatoryRestDayUserWithCompany($company, 'supervisor');

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('mandatory-rest-days.index')
        ->assertForbidden();
});

it('company admin cannot create national subnational or electoral catalog records', function (): void {
    $company = Company::factory()->create();
    $user = mandatoryRestDayUserWithCompany($company, RoleKey::ADMIN_EMPRESA);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('mandatory-rest-days.index')
        ->set('form.name', 'Catalogo no permitido')
        ->set('form.date', '2026-06-07')
        ->set('form.type', 'electoral')
        ->set('form.scope', 'subnational')
        ->set('form.jurisdiction_code', 'MX-JAL')
        ->call('save')
        ->assertHasErrors(['form.type']);

    $this->assertDatabaseMissing('mandatory_rest_days', [
        'name' => 'Catalogo no permitido',
    ]);
});

it('super admin can create national and subnational catalog records', function (): void {
    $company = Company::factory()->create();
    $user = mandatoryRestDayUserWithCompany($company, 'super_admin');

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('mandatory-rest-days.index')
        ->set('form.name', 'Descanso nacional legal')
        ->set('form.date', '2026-12-25')
        ->set('form.type', 'legal_mandatory')
        ->set('form.scope', 'national')
        ->set('form.jurisdiction_code', '')
        ->call('save')
        ->assertHasNoErrors();

    Volt::test('mandatory-rest-days.index')
        ->set('form.name', 'Descanso electoral subnacional')
        ->set('form.date', '2026-06-07')
        ->set('form.type', 'electoral')
        ->set('form.scope', 'subnational')
        ->set('form.jurisdiction_code', 'mx-jal')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('mandatory_rest_days', [
        'company_id' => null,
        'name' => 'Descanso nacional legal',
        'type' => 'legal_mandatory',
        'scope' => 'national',
        'country_code' => 'MX',
        'jurisdiction_code' => null,
    ]);
    $this->assertDatabaseHas('mandatory_rest_days', [
        'company_id' => null,
        'name' => 'Descanso electoral subnacional',
        'type' => 'electoral',
        'scope' => 'subnational',
        'country_code' => 'MX',
        'jurisdiction_code' => 'MX-JAL',
    ]);
});

it('company admin cannot edit or inactivate global catalog records', function (): void {
    $company = Company::factory()->create();
    $user = mandatoryRestDayUserWithCompany($company, RoleKey::ADMIN_EMPRESA);
    $restDay = MandatoryRestDay::factory()->stateScoped('MX-JAL')->create(['name' => 'Catalogo protegido']);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    expect(Gate::forUser($user)->allows('update', $restDay))->toBeFalse()
        ->and(Gate::forUser($user)->allows('inactivate', $restDay))->toBeFalse();

    Volt::test('mandatory-rest-days.index')
        ->call('edit', $restDay->id)
        ->assertForbidden();

    Volt::test('mandatory-rest-days.index')
        ->call('inactivate', $restDay->id)
        ->assertForbidden();
});

it('super admin can edit and inactivate global catalog records', function (): void {
    $company = Company::factory()->create();
    $user = mandatoryRestDayUserWithCompany($company, 'super_admin');
    $restDay = MandatoryRestDay::factory()->stateScoped('MX-JAL')->create(['name' => 'Catalogo editable']);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('mandatory-rest-days.index')
        ->call('edit', $restDay->id)
        ->set('form.name', 'Catalogo actualizado')
        ->call('save')
        ->assertHasNoErrors();

    $restDay->refresh();

    expect($restDay->name)->toBe('Catalogo actualizado')
        ->and($restDay->company_id)->toBeNull();

    Volt::test('mandatory-rest-days.index')
        ->call('inactivate', $restDay->id)
        ->assertHasNoErrors();

    expect($restDay->refresh()->status)->toBe('inactive');
});

it('user sees national subnational and active company mandatory rest days', function (): void {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = mandatoryRestDayUserWithCompany($company);

    MandatoryRestDay::factory()->national()->create(['name' => 'Descanso nacional visible']);
    MandatoryRestDay::factory()->stateScoped('MX-JAL')->create(['name' => 'Descanso subnacional visible']);
    MandatoryRestDay::factory()->create(['company_id' => $company->id, 'name' => 'Descanso empresa visible']);
    MandatoryRestDay::factory()->create(['company_id' => $otherCompany->id, 'name' => 'Descanso empresa ajena']);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('mandatory-rest-days.index')
        ->assertSee('Descanso nacional visible')
        ->assertSee('Descanso subnacional visible')
        ->assertSee('Descanso empresa visible')
        ->assertDontSee('Descanso empresa ajena');
});

it('creates company scoped mandatory rest day from ui', function (): void {
    $company = Company::factory()->create();
    $user = mandatoryRestDayUserWithCompany($company);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('mandatory-rest-days.index')
        ->set('form.name', 'Aniversario empresa')
        ->set('form.date', '2026-09-16')
        ->set('form.type', 'company_internal')
        ->set('form.scope', 'company')
        ->set('form.jurisdiction_code', '')
        ->set('form.source_reference', 'Politica interna demo')
        ->set('form.status', 'active')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('mandatory_rest_days', [
        'company_id' => $company->id,
        'name' => 'Aniversario empresa',
        'date' => '2026-09-16 00:00:00',
        'type' => 'company_internal',
        'scope' => 'company',
        'country_code' => 'MX',
        'jurisdiction_code' => null,
        'source_reference' => 'Politica interna demo',
        'capture_source' => 'manual',
        'status' => 'active',
    ]);
});

it('creates subnational scoped mandatory rest day from ui', function (): void {
    $company = Company::factory()->create();
    $user = mandatoryRestDayUserWithCompany($company, 'super_admin');

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('mandatory-rest-days.index')
        ->set('form.name', 'Descanso subnacional')
        ->set('form.date', '2026-11-02')
        ->set('form.type', 'electoral')
        ->set('form.scope', 'subnational')
        ->set('form.jurisdiction_code', 'mx-jal')
        ->set('form.source_reference', 'Acuerdo electoral demo')
        ->set('form.status', 'active')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('mandatory_rest_days', [
        'company_id' => null,
        'name' => 'Descanso subnacional',
        'date' => '2026-11-02 00:00:00',
        'type' => 'electoral',
        'scope' => 'subnational',
        'country_code' => 'MX',
        'jurisdiction_code' => 'MX-JAL',
        'source_reference' => 'Acuerdo electoral demo',
        'capture_source' => 'manual',
        'status' => 'active',
    ]);
});

it('requires jurisdiction code for subnational scoped mandatory rest day from ui', function (): void {
    $company = Company::factory()->create();
    $user = mandatoryRestDayUserWithCompany($company);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('mandatory-rest-days.index')
        ->set('form.name', 'Intento subnacional')
        ->set('form.date', '2026-12-25')
        ->set('form.type', 'electoral')
        ->set('form.scope', 'subnational')
        ->set('form.jurisdiction_code', '')
        ->call('save')
        ->assertHasErrors(['form.jurisdiction_code']);

    $this->assertDatabaseMissing('mandatory_rest_days', [
        'name' => 'Intento subnacional',
    ]);
});

it('does not allow jurisdiction code for non subnational scope', function (): void {
    $company = Company::factory()->create();
    $user = mandatoryRestDayUserWithCompany($company);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('mandatory-rest-days.index')
        ->set('form.name', 'Empresa con jurisdiccion')
        ->set('form.date', '2026-10-01')
        ->set('form.scope', 'company')
        ->set('form.jurisdiction_code', 'MX-JAL')
        ->call('save')
        ->assertHasErrors(['form.jurisdiction_code']);

    $this->assertDatabaseMissing('mandatory_rest_days', [
        'name' => 'Empresa con jurisdiccion',
    ]);
});

it('ignores manipulated company id when creating from ui', function (): void {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = mandatoryRestDayUserWithCompany($company);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('mandatory-rest-days.index')
        ->set('form.company_id', $otherCompany->id)
        ->set('form.name', 'Empresa activa real')
        ->set('form.date', '2026-09-17')
        ->set('form.scope', 'company')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('mandatory_rest_days', [
        'company_id' => $company->id,
        'name' => 'Empresa activa real',
    ]);
    $this->assertDatabaseMissing('mandatory_rest_days', [
        'company_id' => $otherCompany->id,
        'name' => 'Empresa activa real',
    ]);
});

it('edits own mandatory rest day', function (): void {
    $company = Company::factory()->create();
    $user = mandatoryRestDayUserWithCompany($company);
    $restDay = MandatoryRestDay::factory()->create([
        'company_id' => $company->id,
        'name' => 'Nombre inicial',
        'date' => '2026-09-16 00:00:00',
        'scope' => 'company',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('mandatory-rest-days.index')
        ->call('edit', $restDay->id)
        ->set('form.name', 'Nombre actualizado')
        ->set('form.date', '2026-09-17')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('mandatory_rest_days', [
        'id' => $restDay->id,
        'name' => 'Nombre actualizado',
        'date' => '2026-09-17 00:00:00',
    ]);
});

it('does not edit foreign mandatory rest day', function (): void {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = mandatoryRestDayUserWithCompany($company);
    $foreignRestDay = MandatoryRestDay::factory()->create([
        'company_id' => $otherCompany->id,
        'name' => 'Descanso ajeno',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    expect(fn () => Volt::test('mandatory-rest-days.index')
        ->call('edit', $foreignRestDay->id))
        ->toThrow(ModelNotFoundException::class);
});

it('inactivates own mandatory rest day without deleting it', function (): void {
    $company = Company::factory()->create();
    $user = mandatoryRestDayUserWithCompany($company);
    $restDay = MandatoryRestDay::factory()->create([
        'company_id' => $company->id,
        'status' => 'active',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('mandatory-rest-days.index')
        ->call('inactivate', $restDay->id)
        ->assertHasNoErrors();

    $this->assertDatabaseHas('mandatory_rest_days', [
        'id' => $restDay->id,
        'status' => 'inactive',
    ]);
});

it('does not inactivate foreign mandatory rest day', function (): void {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = mandatoryRestDayUserWithCompany($company);
    $foreignRestDay = MandatoryRestDay::factory()->create([
        'company_id' => $otherCompany->id,
        'status' => 'active',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    expect(fn () => Volt::test('mandatory-rest-days.index')
        ->call('inactivate', $foreignRestDay->id))
        ->toThrow(ModelNotFoundException::class);

    $this->assertDatabaseHas('mandatory_rest_days', [
        'id' => $foreignRestDay->id,
        'status' => 'active',
    ]);
});

it('validates required fields and allowed status', function (): void {
    $company = Company::factory()->create();
    $user = mandatoryRestDayUserWithCompany($company);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('mandatory-rest-days.index')
        ->set('form.name', '')
        ->set('form.date', '')
        ->set('form.type', 'unknown')
        ->set('form.status', 'archived')
        ->call('save')
        ->assertHasErrors(['form.name', 'form.date', 'form.type', 'form.status']);
});

it('does not allow duplicate national mandatory rest day with same type date and name', function (): void {
    $action = app(CreateMandatoryRestDayAction::class);

    $action->handle(null, [
        'name' => 'Descanso nacional',
        'date' => '2026-12-25',
        'type' => 'legal_mandatory',
        'scope' => 'national',
    ]);

    expect(fn () => $action->handle(null, [
        'name' => 'Descanso nacional',
        'date' => '2026-12-25',
        'type' => 'legal_mandatory',
        'scope' => 'national',
    ]))->toThrow(InvalidArgumentException::class);
});

it('does not allow duplicate subnational mandatory rest day with same type jurisdiction date and name', function (): void {
    $action = app(CreateMandatoryRestDayAction::class);

    $action->handle(null, [
        'name' => 'Eleccion subnacional',
        'date' => '2026-06-07',
        'type' => 'electoral',
        'scope' => 'subnational',
        'jurisdiction_code' => 'MX-JAL',
    ]);

    expect(fn () => $action->handle(null, [
        'name' => 'Eleccion subnacional',
        'date' => '2026-06-07',
        'type' => 'electoral',
        'scope' => 'subnational',
        'jurisdiction_code' => 'mx-jal',
    ]))->toThrow(InvalidArgumentException::class);

    $otherJurisdiction = $action->handle(null, [
        'name' => 'Eleccion subnacional',
        'date' => '2026-06-07',
        'type' => 'electoral',
        'scope' => 'subnational',
        'jurisdiction_code' => 'MX-NLE',
    ]);

    expect($otherJurisdiction->jurisdiction_code)->toBe('MX-NLE');
});

it('does not allow duplicate company mandatory rest day with same company date and name', function (): void {
    $company = Company::factory()->create();
    $action = app(CreateMandatoryRestDayAction::class);

    $action->handle($company, [
        'name' => 'Descanso empresa',
        'date' => '2026-09-16',
        'type' => 'company_internal',
        'scope' => 'company',
    ]);

    expect(fn () => $action->handle($company, [
        'name' => 'Descanso empresa',
        'date' => '2026-09-16',
        'type' => 'company_internal',
        'scope' => 'company',
    ]))->toThrow(InvalidArgumentException::class);
});

it('does not allow center scope in domain action', function (): void {
    $company = Company::factory()->create();

    expect(fn () => app(CreateMandatoryRestDayAction::class)->handle($company, [
        'name' => 'Descanso centro',
        'date' => '2026-11-02',
        'type' => 'company_internal',
        'scope' => 'center',
    ]))->toThrow(InvalidArgumentException::class);
});

it('allows same name and date for different types', function (): void {
    $action = app(CreateMandatoryRestDayAction::class);

    $first = $action->handle(null, [
        'name' => 'Descanso especial',
        'date' => '2026-11-02',
        'type' => 'legal_mandatory',
        'scope' => 'national',
    ]);
    $second = $action->handle(null, [
        'name' => 'Descanso especial',
        'date' => '2026-11-02',
        'type' => 'electoral',
        'scope' => 'national',
    ]);

    expect($first->type)->toBe('legal_mandatory')
        ->and($second->type)->toBe('electoral');
});

it('allows same company scoped name and date in different companies', function (): void {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $action = app(CreateMandatoryRestDayAction::class);

    $first = $action->handle($companyA, [
        'name' => 'Descanso empresa',
        'date' => '2026-09-16',
        'type' => 'company_internal',
        'scope' => 'company',
    ]);
    $second = $action->handle($companyB, [
        'name' => 'Descanso empresa',
        'date' => '2026-09-16',
        'type' => 'company_internal',
        'scope' => 'company',
    ]);

    expect($first->company_id)->toBe($companyA->id)
        ->and($second->company_id)->toBe($companyB->id);
});

it('update does not allow converting a mandatory rest day into a duplicate', function (): void {
    $company = Company::factory()->create();
    $existing = MandatoryRestDay::factory()->create([
        'company_id' => $company->id,
        'name' => 'Descanso existente',
        'date' => '2026-09-16',
        'scope' => 'company',
    ]);
    $candidate = MandatoryRestDay::factory()->create([
        'company_id' => $company->id,
        'name' => 'Descanso candidato',
        'date' => '2026-09-17',
        'scope' => 'company',
    ]);

    expect(fn () => app(UpdateMandatoryRestDayAction::class)->handle($company, $candidate, [
        'name' => $existing->name,
        'date' => $existing->date->toDateString(),
        'type' => $existing->type,
        'scope' => 'company',
        'status' => 'active',
    ]))->toThrow(InvalidArgumentException::class);
});

it('scope national remains without company center or jurisdiction code', function (): void {
    $restDay = app(CreateMandatoryRestDayAction::class)->handle(null, [
        'name' => 'Descanso nacional limpio',
        'date' => '2026-12-25',
        'type' => 'legal_mandatory',
        'scope' => 'national',
    ]);

    expect($restDay->scope)->toBe('national')
        ->and($restDay->country_code)->toBe('MX')
        ->and($restDay->company_id)->toBeNull()
        ->and($restDay->jurisdiction_code)->toBeNull();
});

it('scope subnational requires jurisdiction code and remains without company', function (): void {
    $restDay = app(CreateMandatoryRestDayAction::class)->handle(null, [
        'name' => 'Descanso subnacional limpio',
        'date' => '2026-06-07',
        'type' => 'electoral',
        'scope' => 'subnational',
        'jurisdiction_code' => 'mx-jal',
    ]);

    expect($restDay->scope)->toBe('subnational')
        ->and($restDay->country_code)->toBe('MX')
        ->and($restDay->company_id)->toBeNull()
        ->and($restDay->jurisdiction_code)->toBe('MX-JAL');
});

it('scope company remains attached to active company without jurisdiction code', function (): void {
    $company = Company::factory()->create();

    $restDay = app(CreateMandatoryRestDayAction::class)->handle($company, [
        'name' => 'Descanso company limpio',
        'date' => '2026-09-16',
        'type' => 'company_internal',
        'scope' => 'company',
        'source_reference' => 'Referencia operativa',
    ]);

    expect($restDay->scope)->toBe('company')
        ->and($restDay->country_code)->toBe('MX')
        ->and($restDay->company_id)->toBe($company->id)
        ->and($restDay->jurisdiction_code)->toBeNull()
        ->and($restDay->source_reference)->toBe('Referencia operativa')
        ->and($restDay->capture_source)->toBe('manual');
});

it('allows explicit capture source for non screen ingestion', function (): void {
    $company = Company::factory()->create();

    $restDay = app(CreateMandatoryRestDayAction::class)->handle($company, [
        'name' => 'Descanso seeder',
        'date' => '2026-09-16',
        'type' => 'company_internal',
        'scope' => 'company',
        'capture_source' => 'seeder',
        'source_reference' => 'Referencia demo neutral',
    ]);

    expect($restDay->capture_source)->toBe('seeder')
        ->and($restDay->source_reference)->toBe('Referencia demo neutral');
});

it('rejects invalid capture source', function (): void {
    $company = Company::factory()->create();

    expect(fn () => app(CreateMandatoryRestDayAction::class)->handle($company, [
        'name' => 'Descanso invalido',
        'date' => '2026-09-16',
        'type' => 'company_internal',
        'scope' => 'company',
        'capture_source' => 'spreadsheet',
    ]))->toThrow(InvalidArgumentException::class);
});

it('resolves national subnational and company mandatory rest days for a date', function (): void {
    $company = Company::factory()->create();
    $center = Center::factory()->create(['company_id' => $company->id, 'address' => ['country_code' => 'MX', 'jurisdiction_code' => 'MX-JAL']]);
    $otherCompany = Company::factory()->create();

    MandatoryRestDay::factory()->national()->create(['name' => 'Nacional', 'date' => '2026-12-25']);
    MandatoryRestDay::factory()->stateScoped('MX-JAL')->create(['name' => 'Estatal', 'date' => '2026-12-25']);
    MandatoryRestDay::factory()->stateScoped('MX-NLE')->create(['name' => 'Otro estado', 'date' => '2026-12-25']);
    MandatoryRestDay::factory()->create(['company_id' => $company->id, 'name' => 'Empresa', 'date' => '2026-12-25', 'scope' => 'company']);
    MandatoryRestDay::factory()->create(['company_id' => $otherCompany->id, 'name' => 'Otra empresa', 'date' => '2026-12-25', 'scope' => 'company']);
    MandatoryRestDay::factory()->inactive()->create(['company_id' => $company->id, 'name' => 'Inactivo', 'date' => '2026-12-25', 'scope' => 'company']);

    $resolved = app(ResolveMandatoryRestDaysForDateAction::class)->handle($company, $center, '2026-12-25');

    expect($resolved)->toHaveCount(3)
        ->and($resolved->pluck('name')->all())->toContain('Nacional', 'Estatal', 'Empresa')
        ->and($resolved->pluck('name')->all())->not->toContain('Otro estado', 'Otra empresa', 'Inactivo');
});

it('resolves only national and company mandatory rest days without center jurisdiction code', function (): void {
    $company = Company::factory()->create();

    MandatoryRestDay::factory()->national()->create(['name' => 'Nacional', 'date' => '2026-12-25']);
    MandatoryRestDay::factory()->stateScoped('MX-JAL')->create(['name' => 'Estatal', 'date' => '2026-12-25']);
    MandatoryRestDay::factory()->create(['company_id' => $company->id, 'name' => 'Empresa', 'date' => '2026-12-25', 'scope' => 'company']);

    $resolved = app(ResolveMandatoryRestDaysForDateAction::class)->handle($company, null, '2026-12-25');

    expect($resolved)->toHaveCount(2)
        ->and($resolved->pluck('name')->all())->toContain('Nacional', 'Empresa')
        ->and($resolved->pluck('name')->all())->not->toContain('Estatal');
});

it('rejects resolving center from another company', function (): void {
    $company = Company::factory()->create();
    $foreignCenter = Center::factory()->create(['company_id' => Company::factory()->create()->id]);

    expect(fn () => app(ResolveMandatoryRestDaysForDateAction::class)->handle($company, $foreignCenter, '2026-12-25'))
        ->toThrow(InvalidArgumentException::class);
});

it('blocks hard deleting company with mandatory rest day history', function (): void {
    $company = Company::factory()->create();
    $companyRestDay = MandatoryRestDay::factory()->create(['company_id' => $company->id]);

    expect(fn () => $company->delete())->toThrow(QueryException::class);

    $this->assertDatabaseHas('mandatory_rest_days', ['id' => $companyRestDay->id]);
});

it('sprint 2c does not create jornada calculation or operational tables', function (): void {
    expect(Schema::hasTable('mandatory_rest_days'))->toBeTrue()
        ->and(Schema::hasColumn('mandatory_rest_days', 'source'))->toBeFalse()
        ->and(Schema::hasColumn('mandatory_rest_days', 'country_code'))->toBeTrue()
        ->and(Schema::hasColumn('mandatory_rest_days', 'jurisdiction_code'))->toBeTrue()
        ->and(Schema::hasColumn('mandatory_rest_days', 'state_code'))->toBeFalse()
        ->and(Schema::hasColumn('mandatory_rest_days', 'source_reference'))->toBeTrue()
        ->and(Schema::hasColumn('mandatory_rest_days', 'capture_source'))->toBeTrue()
        ->and(Schema::hasTable('work_days'))->toBeTrue()
        ->and(Schema::hasTable('work_day_calculations'))->toBeTrue()
        ->and(Schema::hasTable('alerts'))->toBeTrue()
        ->and(Schema::hasTable('incidents'))->toBeFalse()
        ->and(Schema::hasTable('reports'))->toBeFalse();
});

function mandatoryRestDayUserWithCompany(Company $company, string $roleKey = RoleKey::ADMIN_EMPRESA): User
{
    $role = Role::factory()->create(['key' => $roleKey]);
    $user = User::factory()->create();

    $user->companies()->attach($company, [
        'role_id' => $role->id,
        'status' => 'active',
        'is_default' => true,
    ]);

    return $user;
}
