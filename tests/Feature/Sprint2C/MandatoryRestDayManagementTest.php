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
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
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

it('user sees only global and active company mandatory rest days', function (): void {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = mandatoryRestDayUserWithCompany($company);

    MandatoryRestDay::factory()->global()->create(['name' => 'Descanso global visible']);
    MandatoryRestDay::factory()->create(['company_id' => $company->id, 'name' => 'Descanso empresa visible']);
    MandatoryRestDay::factory()->create(['company_id' => $otherCompany->id, 'name' => 'Descanso empresa ajena']);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('mandatory-rest-days.index')
        ->assertSee('Descanso global visible')
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
        ->set('form.scope', 'company')
        ->set('form.center_id', '')
        ->set('form.source', 'manual')
        ->set('form.status', 'active')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('mandatory_rest_days', [
        'company_id' => $company->id,
        'center_id' => null,
        'name' => 'Aniversario empresa',
        'date' => '2026-09-16 00:00:00',
        'scope' => 'company',
        'status' => 'active',
    ]);
});

it('creates center scoped mandatory rest day from ui', function (): void {
    $company = Company::factory()->create();
    $center = Center::factory()->create(['company_id' => $company->id, 'name' => 'Centro Norte']);
    $user = mandatoryRestDayUserWithCompany($company);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('mandatory-rest-days.index')
        ->set('form.name', 'Descanso centro')
        ->set('form.date', '2026-11-02')
        ->set('form.scope', 'center')
        ->set('form.center_id', (string) $center->id)
        ->set('form.source', 'manual')
        ->set('form.status', 'active')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('mandatory_rest_days', [
        'company_id' => $company->id,
        'center_id' => $center->id,
        'name' => 'Descanso centro',
        'date' => '2026-11-02 00:00:00',
        'scope' => 'center',
        'status' => 'active',
    ]);
});

it('does not allow creating global mandatory rest day from company ui', function (): void {
    $company = Company::factory()->create();
    $user = mandatoryRestDayUserWithCompany($company);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('mandatory-rest-days.index')
        ->set('form.name', 'Intento global')
        ->set('form.date', '2026-12-25')
        ->set('form.scope', 'global')
        ->call('save')
        ->assertHasErrors(['form.scope']);

    $this->assertDatabaseMissing('mandatory_rest_days', [
        'name' => 'Intento global',
    ]);
});

it('does not allow center from another company', function (): void {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $foreignCenter = Center::factory()->create(['company_id' => $otherCompany->id]);
    $user = mandatoryRestDayUserWithCompany($company);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('mandatory-rest-days.index')
        ->set('form.name', 'Centro ajeno')
        ->set('form.date', '2026-10-01')
        ->set('form.scope', 'center')
        ->set('form.center_id', (string) $foreignCenter->id)
        ->call('save')
        ->assertHasErrors(['form.center_id']);

    $this->assertDatabaseMissing('mandatory_rest_days', [
        'name' => 'Centro ajeno',
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
        ->set('form.status', 'archived')
        ->call('save')
        ->assertHasErrors(['form.name', 'form.date', 'form.status']);
});

it('does not allow duplicate global mandatory rest day with same date and name', function (): void {
    $action = app(CreateMandatoryRestDayAction::class);

    $action->handle(null, null, [
        'name' => 'Descanso nacional',
        'date' => '2026-12-25',
        'scope' => 'global',
    ]);

    expect(fn () => $action->handle(null, null, [
        'name' => 'Descanso nacional',
        'date' => '2026-12-25',
        'scope' => 'global',
    ]))->toThrow(InvalidArgumentException::class);
});

it('does not allow duplicate company mandatory rest day with same company date and name', function (): void {
    $company = Company::factory()->create();
    $action = app(CreateMandatoryRestDayAction::class);

    $action->handle($company, null, [
        'name' => 'Descanso empresa',
        'date' => '2026-09-16',
        'scope' => 'company',
    ]);

    expect(fn () => $action->handle($company, null, [
        'name' => 'Descanso empresa',
        'date' => '2026-09-16',
        'scope' => 'company',
    ]))->toThrow(InvalidArgumentException::class);
});

it('does not allow duplicate center mandatory rest day with same center date and name', function (): void {
    $company = Company::factory()->create();
    $center = Center::factory()->create(['company_id' => $company->id]);
    $action = app(CreateMandatoryRestDayAction::class);

    $action->handle($company, $center, [
        'name' => 'Descanso centro',
        'date' => '2026-11-02',
        'scope' => 'center',
    ]);

    expect(fn () => $action->handle($company, $center, [
        'name' => 'Descanso centro',
        'date' => '2026-11-02',
        'scope' => 'center',
    ]))->toThrow(InvalidArgumentException::class);
});

it('allows same center scoped name and date in different centers', function (): void {
    $company = Company::factory()->create();
    $centerA = Center::factory()->create(['company_id' => $company->id]);
    $centerB = Center::factory()->create(['company_id' => $company->id]);
    $action = app(CreateMandatoryRestDayAction::class);

    $first = $action->handle($company, $centerA, [
        'name' => 'Descanso local',
        'date' => '2026-11-02',
        'scope' => 'center',
    ]);
    $second = $action->handle($company, $centerB, [
        'name' => 'Descanso local',
        'date' => '2026-11-02',
        'scope' => 'center',
    ]);

    expect($first->center_id)->toBe($centerA->id)
        ->and($second->center_id)->toBe($centerB->id);
});

it('allows same company scoped name and date in different companies', function (): void {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $action = app(CreateMandatoryRestDayAction::class);

    $first = $action->handle($companyA, null, [
        'name' => 'Descanso empresa',
        'date' => '2026-09-16',
        'scope' => 'company',
    ]);
    $second = $action->handle($companyB, null, [
        'name' => 'Descanso empresa',
        'date' => '2026-09-16',
        'scope' => 'company',
    ]);

    expect($first->company_id)->toBe($companyA->id)
        ->and($second->company_id)->toBe($companyB->id);
});

it('update does not allow converting a mandatory rest day into a duplicate', function (): void {
    $company = Company::factory()->create();
    $existing = MandatoryRestDay::factory()->create([
        'company_id' => $company->id,
        'center_id' => null,
        'name' => 'Descanso existente',
        'date' => '2026-09-16',
        'scope' => 'company',
    ]);
    $candidate = MandatoryRestDay::factory()->create([
        'company_id' => $company->id,
        'center_id' => null,
        'name' => 'Descanso candidato',
        'date' => '2026-09-17',
        'scope' => 'company',
    ]);

    expect(fn () => app(UpdateMandatoryRestDayAction::class)->handle($company, $candidate, null, [
        'name' => $existing->name,
        'date' => $existing->date->toDateString(),
        'scope' => 'company',
        'status' => 'active',
    ]))->toThrow(InvalidArgumentException::class);
});

it('scope global remains without company or center', function (): void {
    $restDay = app(CreateMandatoryRestDayAction::class)->handle(null, null, [
        'name' => 'Descanso global limpio',
        'date' => '2026-12-25',
        'scope' => 'global',
    ]);

    expect($restDay->scope)->toBe('global')
        ->and($restDay->company_id)->toBeNull()
        ->and($restDay->center_id)->toBeNull();
});

it('scope company remains attached to active company without center', function (): void {
    $company = Company::factory()->create();

    $restDay = app(CreateMandatoryRestDayAction::class)->handle($company, null, [
        'name' => 'Descanso company limpio',
        'date' => '2026-09-16',
        'scope' => 'company',
    ]);

    expect($restDay->scope)->toBe('company')
        ->and($restDay->company_id)->toBe($company->id)
        ->and($restDay->center_id)->toBeNull();
});

it('scope center requires center from same company in domain action', function (): void {
    $company = Company::factory()->create();
    $foreignCenter = Center::factory()->create(['company_id' => Company::factory()->create()->id]);

    expect(fn () => app(CreateMandatoryRestDayAction::class)->handle($company, null, [
        'name' => 'Sin centro',
        'date' => '2026-11-02',
        'scope' => 'center',
    ]))->toThrow(InvalidArgumentException::class);

    expect(fn () => app(CreateMandatoryRestDayAction::class)->handle($company, $foreignCenter, [
        'name' => 'Centro ajeno',
        'date' => '2026-11-02',
        'scope' => 'center',
    ]))->toThrow(InvalidArgumentException::class);
});

it('does not list inconsistent global mandatory rest day with center id', function (): void {
    $company = Company::factory()->create();
    $center = Center::factory()->create(['company_id' => $company->id]);
    $user = mandatoryRestDayUserWithCompany($company);

    DB::table('mandatory_rest_days')->insert([
        'company_id' => null,
        'center_id' => $center->id,
        'name' => 'Global inconsistente',
        'date' => '2026-12-25',
        'scope' => 'global',
        'source' => 'manual',
        'status' => 'active',
        'metadata' => json_encode([]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    MandatoryRestDay::factory()->global()->create([
        'name' => 'Global valido',
        'date' => '2026-12-25',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('mandatory-rest-days.index')
        ->assertSee('Global valido')
        ->assertDontSee('Global inconsistente');
});
it('resolves global company and center mandatory rest days for a date', function (): void {
    $company = Company::factory()->create();
    $center = Center::factory()->create(['company_id' => $company->id]);
    $otherCenter = Center::factory()->create(['company_id' => $company->id]);
    $otherCompany = Company::factory()->create();

    MandatoryRestDay::factory()->global()->create(['name' => 'Global', 'date' => '2026-12-25']);
    MandatoryRestDay::factory()->create(['company_id' => $company->id, 'center_id' => null, 'name' => 'Empresa', 'date' => '2026-12-25', 'scope' => 'company']);
    MandatoryRestDay::factory()->create(['company_id' => $company->id, 'center_id' => $center->id, 'name' => 'Centro', 'date' => '2026-12-25', 'scope' => 'center']);
    MandatoryRestDay::factory()->create(['company_id' => $company->id, 'center_id' => $otherCenter->id, 'name' => 'Otro centro', 'date' => '2026-12-25', 'scope' => 'center']);
    MandatoryRestDay::factory()->create(['company_id' => $otherCompany->id, 'name' => 'Otra empresa', 'date' => '2026-12-25', 'scope' => 'company']);
    MandatoryRestDay::factory()->inactive()->create(['company_id' => $company->id, 'name' => 'Inactivo', 'date' => '2026-12-25', 'scope' => 'company']);

    $resolved = app(ResolveMandatoryRestDaysForDateAction::class)->handle($company, $center, '2026-12-25');

    expect($resolved)->toHaveCount(3)
        ->and($resolved->pluck('name')->all())->toContain('Global', 'Empresa', 'Centro')
        ->and($resolved->pluck('name')->all())->not->toContain('Otro centro', 'Otra empresa', 'Inactivo');
});

it('resolves only global and company mandatory rest days without center', function (): void {
    $company = Company::factory()->create();
    $center = Center::factory()->create(['company_id' => $company->id]);

    MandatoryRestDay::factory()->global()->create(['name' => 'Global', 'date' => '2026-12-25']);
    MandatoryRestDay::factory()->create(['company_id' => $company->id, 'center_id' => null, 'name' => 'Empresa', 'date' => '2026-12-25', 'scope' => 'company']);
    MandatoryRestDay::factory()->create(['company_id' => $company->id, 'center_id' => $center->id, 'name' => 'Centro', 'date' => '2026-12-25', 'scope' => 'center']);

    $resolved = app(ResolveMandatoryRestDaysForDateAction::class)->handle($company, null, '2026-12-25');

    expect($resolved)->toHaveCount(2)
        ->and($resolved->pluck('name')->all())->toContain('Global', 'Empresa')
        ->and($resolved->pluck('name')->all())->not->toContain('Centro');
});

it('rejects resolving center from another company', function (): void {
    $company = Company::factory()->create();
    $foreignCenter = Center::factory()->create(['company_id' => Company::factory()->create()->id]);

    expect(fn () => app(ResolveMandatoryRestDaysForDateAction::class)->handle($company, $foreignCenter, '2026-12-25'))
        ->toThrow(InvalidArgumentException::class);
});

it('blocks hard deleting company or center with mandatory rest day history', function (): void {
    $company = Company::factory()->create();
    $center = Center::factory()->create(['company_id' => $company->id]);
    $companyRestDay = MandatoryRestDay::factory()->create(['company_id' => $company->id]);
    $centerRestDay = MandatoryRestDay::factory()->create([
        'company_id' => $company->id,
        'center_id' => $center->id,
        'scope' => 'center',
    ]);

    expect(fn () => $center->delete())->toThrow(QueryException::class);
    expect(fn () => $company->delete())->toThrow(QueryException::class);

    $this->assertDatabaseHas('mandatory_rest_days', ['id' => $companyRestDay->id]);
    $this->assertDatabaseHas('mandatory_rest_days', ['id' => $centerRestDay->id]);
});

it('sprint 2c does not create jornada calculation or operational tables', function (): void {
    expect(Schema::hasTable('mandatory_rest_days'))->toBeTrue()
        ->and(Schema::hasTable('work_days'))->toBeFalse()
        ->and(Schema::hasTable('work_day_calculations'))->toBeFalse()
        ->and(Schema::hasTable('alerts'))->toBeFalse()
        ->and(Schema::hasTable('incidents'))->toBeFalse()
        ->and(Schema::hasTable('reports'))->toBeFalse();
});

function mandatoryRestDayUserWithCompany(Company $company, string $roleKey = 'owner'): User
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
