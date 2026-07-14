<?php

use App\Models\Center;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Volt\Volt;

it('guest cannot access centers route', function (): void {
    $this->get(route('centers.index'))
        ->assertRedirect(route('login'));
});

it('authenticated user without active company cannot access centers route', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('centers.index'))
        ->assertForbidden();
});

it('user sees only centers from active company', function (): void {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = centerUserWithCompany($company);
    $visibleCenter = Center::factory()->create([
        'company_id' => $company->id,
        'name' => 'Centro visible',
    ]);
    $foreignCenter = Center::factory()->create([
        'company_id' => $otherCompany->id,
        'name' => 'Centro ajeno',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('centers.index')
        ->assertSee($visibleCenter->name)
        ->assertDontSee($foreignCenter->name);
});

it('user cannot see centers from another company', function (): void {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = centerUserWithCompany($company);
    $foreignCenter = Center::factory()->create([
        'company_id' => $otherCompany->id,
        'name' => 'Centro ajeno',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('centers.index')
        ->assertDontSee($foreignCenter->name);
});

it('user can create center in active company', function (): void {
    $company = Company::factory()->create(['timezone' => 'America/Mexico_City']);
    $user = centerUserWithCompany($company);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('centers.index')
        ->set('form.code', 'MTY-01')
        ->set('form.name', 'Monterrey')
        ->set('form.timezone', 'America/Monterrey')
        ->set('form.status', 'active')
        ->call('save');

    $this->assertDatabaseHas('centers', [
        'company_id' => $company->id,
        'code' => 'MTY-01',
        'name' => 'Monterrey',
        'timezone' => 'America/Monterrey',
        'status' => 'active',
    ]);
});

it('user cannot create duplicate center code within same company', function (): void {
    $company = Company::factory()->create();
    $user = centerUserWithCompany($company);
    Center::factory()->create([
        'company_id' => $company->id,
        'code' => 'CDMX-01',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('centers.index')
        ->set('form.code', 'CDMX-01')
        ->set('form.name', 'Centro duplicado')
        ->set('form.timezone', 'America/Mexico_City')
        ->set('form.status', 'active')
        ->call('save')
        ->assertHasErrors(['form.code']);
});

it('center code can repeat in another company', function (): void {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = centerUserWithCompany($company);
    Center::factory()->create([
        'company_id' => $otherCompany->id,
        'code' => 'SHARED',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('centers.index')
        ->set('form.code', 'SHARED')
        ->set('form.name', 'Centro propio')
        ->set('form.timezone', 'America/Mexico_City')
        ->set('form.status', 'active')
        ->call('save');

    $this->assertDatabaseHas('centers', [
        'company_id' => $company->id,
        'code' => 'SHARED',
    ]);
});

it('user can update center from active company', function (): void {
    $company = Company::factory()->create();
    $user = centerUserWithCompany($company);
    $center = Center::factory()->create([
        'company_id' => $company->id,
        'code' => 'OLD',
        'name' => 'Anterior',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('centers.index')
        ->call('loadEditForm', $center->id)
        ->set('form.code', 'NEW')
        ->set('form.name', 'Actualizado')
        ->set('form.timezone', 'America/Tijuana')
        ->set('form.status', 'active')
        ->call('save');

    $this->assertDatabaseHas('centers', [
        'id' => $center->id,
        'company_id' => $company->id,
        'code' => 'NEW',
        'name' => 'Actualizado',
        'timezone' => 'America/Tijuana',
    ]);
});

it('user cannot update center from another company', function (): void {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = centerUserWithCompany($company);
    $foreignCenter = Center::factory()->create([
        'company_id' => $otherCompany->id,
        'code' => 'FOREIGN',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    $this->assertFalse($user->can('update', $foreignCenter));
    $this->expectException(ModelNotFoundException::class);

    Volt::test('centers.index')
        ->call('loadEditForm', $foreignCenter->id);
});

it('user can inactivate center', function (): void {
    $company = Company::factory()->create();
    $user = centerUserWithCompany($company);
    $center = Center::factory()->create([
        'company_id' => $company->id,
        'status' => 'active',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('centers.index')
        ->call('inactivate', $center->id);

    $this->assertDatabaseHas('centers', [
        'id' => $center->id,
        'status' => 'inactive',
    ]);
});

it('center with inactive company is not operable', function (): void {
    $company = Company::factory()->create(['status' => 'inactive']);
    $user = centerUserWithCompany($company);
    $center = Center::factory()->create(['company_id' => $company->id]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    $this->assertFalse($user->can('update', $center));

    Volt::test('centers.index')
        ->assertForbidden();
});

it('unauthorized role cannot create center', function (): void {
    $company = Company::factory()->create();
    $user = centerUserWithCompany($company, 'supervisor');

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    $this->assertFalse($user->can('create', [Center::class, $company]));

    Volt::test('centers.index')
        ->assertForbidden();

    $this->assertDatabaseCount('centers', 0);
});

it('unauthorized role cannot edit center', function (): void {
    $company = Company::factory()->create();
    $user = centerUserWithCompany($company, 'supervisor');
    $center = Center::factory()->create([
        'company_id' => $company->id,
        'name' => 'Centro protegido',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    $this->assertFalse($user->can('update', $center));

    Volt::test('centers.index')
        ->assertForbidden();

    $this->assertDatabaseHas('centers', [
        'id' => $center->id,
        'name' => 'Centro protegido',
    ]);
});

it('unauthorized role cannot inactivate center', function (): void {
    $company = Company::factory()->create();
    $user = centerUserWithCompany($company, 'supervisor');
    $center = Center::factory()->create([
        'company_id' => $company->id,
        'status' => 'active',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    $this->assertFalse($user->can('inactivate', $center));

    Volt::test('centers.index')
        ->assertForbidden();

    $this->assertDatabaseHas('centers', [
        'id' => $center->id,
        'status' => 'active',
    ]);
});

it('manipulated company id does not create center in another company', function (): void {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = centerUserWithCompany($company);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('centers.index')
        ->set('form.company_id', $otherCompany->id)
        ->set('form.code', 'SAFE-01')
        ->set('form.name', 'Centro seguro')
        ->set('form.timezone', 'America/Mexico_City')
        ->set('form.status', 'active')
        ->call('save');

    $this->assertDatabaseHas('centers', [
        'company_id' => $company->id,
        'code' => 'SAFE-01',
    ]);

    $this->assertDatabaseMissing('centers', [
        'company_id' => $otherCompany->id,
        'code' => 'SAFE-01',
    ]);
});

it('manipulated company id does not change existing center company', function (): void {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = centerUserWithCompany($company);
    $center = Center::factory()->create([
        'company_id' => $company->id,
        'code' => 'OWN-01',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('centers.index')
        ->call('loadEditForm', $center->id)
        ->set('form.company_id', $otherCompany->id)
        ->set('form.code', 'OWN-02')
        ->set('form.name', 'Centro actualizado')
        ->set('form.timezone', 'America/Mexico_City')
        ->set('form.status', 'active')
        ->call('save');

    $this->assertDatabaseHas('centers', [
        'id' => $center->id,
        'company_id' => $company->id,
        'code' => 'OWN-02',
    ]);

    $this->assertDatabaseMissing('centers', [
        'id' => $center->id,
        'company_id' => $otherCompany->id,
    ]);
});

it('center form requires code name and timezone', function (): void {
    $company = Company::factory()->create();
    $user = centerUserWithCompany($company);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('centers.index')
        ->set('form.code', '')
        ->set('form.name', '')
        ->set('form.timezone', '')
        ->set('form.status', 'active')
        ->call('save')
        ->assertHasErrors([
            'form.code' => 'required',
            'form.name' => 'required',
            'form.timezone' => 'required',
        ]);
});

it('center form blocks invalid status', function (): void {
    $company = Company::factory()->create();
    $user = centerUserWithCompany($company);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('centers.index')
        ->set('form.code', 'VAL-01')
        ->set('form.name', 'Centro validado')
        ->set('form.timezone', 'America/Mexico_City')
        ->set('form.status', 'archived')
        ->call('save')
        ->assertHasErrors(['form.status']);
});

it('center form stores structured optional address as json', function (): void {
    $company = Company::factory()->create();
    $user = centerUserWithCompany($company);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('centers.index')
        ->set('form.code', 'ADDR-01')
        ->set('form.name', 'Centro Direccion')
        ->set('form.timezone', 'America/Mexico_City')
        ->set('form.status', 'active')
        ->set('form.address.street', 'Av. Constitucion')
        ->set('form.address.exterior_number', '100')
        ->set('form.address.interior_number', '2B')
        ->set('form.address.neighborhood', 'Centro')
        ->set('form.address.postal_code', '64000')
        ->set('form.address.municipality', 'Monterrey')
        ->set('form.address.city', 'Monterrey')
        ->set('form.address.state', 'Nuevo Leon')
        ->set('form.address.country', 'Mexico')
        ->set('form.address.country_code', 'MX')
        ->set('form.address.jurisdiction_code', 'MX-NLE')
        ->call('save')
        ->assertHasNoErrors();

    $center = Center::query()->where('company_id', $company->id)->where('code', 'ADDR-01')->firstOrFail();

    expect($center->address)->toMatchArray([
        'street' => 'Av. Constitucion',
        'exterior_number' => '100',
        'interior_number' => '2B',
        'neighborhood' => 'Centro',
        'postal_code' => '64000',
        'municipality' => 'Monterrey',
        'city' => 'Monterrey',
        'state' => 'Nuevo Leon',
        'country' => 'Mexico',
        'country_code' => 'MX',
        'jurisdiction_code' => 'MX-NLE',
    ]);
});

it('center form loads existing address json into address fields', function (): void {
    $company = Company::factory()->create();
    $user = centerUserWithCompany($company);
    $center = Center::factory()->create([
        'company_id' => $company->id,
        'address' => [
            'street' => 'Calle Hidalgo',
            'exterior_number' => '55',
            'postal_code' => '44100',
            'city' => 'Guadalajara',
            'state' => 'Jalisco',
            'country' => 'Mexico',
            'country_code' => 'MX',
            'jurisdiction_code' => 'MX-JAL',
        ],
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('centers.index')
        ->call('loadEditForm', $center->id)
        ->assertSet('form.address.street', 'Calle Hidalgo')
        ->assertSet('form.address.exterior_number', '55')
        ->assertSet('form.address.interior_number', '')
        ->assertSet('form.address.postal_code', '44100')
        ->assertSet('form.address.city', 'Guadalajara')
        ->assertSet('form.address.state', 'Jalisco')
        ->assertSet('form.address.country', 'Mexico')
        ->assertSet('form.address.country_code', 'MX')
        ->assertSet('form.address.jurisdiction_code', 'MX-JAL');
});

it('centers migration is mysql mariadb compatible', function (): void {
    $this->assertNotSame('pgsql', DB::connection()->getDriverName());
    $this->assertTrue(Schema::hasTable('centers'));
    $this->assertTrue(Schema::hasColumns('centers', [
        'id',
        'company_id',
        'code',
        'name',
        'timezone',
        'status',
        'address',
        'metadata',
        'created_at',
        'updated_at',
    ]));

    $schema = Schema::getConnection()->getSchemaBuilder();

    if (method_exists($schema, 'getIndexes')) {
        $indexes = collect($schema->getIndexes('centers'))
            ->pluck('name')
            ->unique()
            ->values();
    } else {
        $indexes = collect(DB::select('SHOW INDEX FROM centers'))
            ->pluck('Key_name')
            ->unique()
            ->values();
    }

    $this->assertTrue($indexes->contains('centers_company_id_code_unique'));
    $this->assertTrue($indexes->contains('centers_company_id_status_index'));
});

function centerUserWithCompany(Company $company, string $roleKey = 'owner'): User
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
