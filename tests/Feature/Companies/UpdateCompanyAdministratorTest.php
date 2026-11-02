<?php

use App\Domains\Companies\Actions\UpdateCompanyAdministratorAction;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;

function companyAdminFixture(): array
{
    $company = Company::factory()->create();
    $role = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
    $admin = User::factory()->create(['password' => 'Original-password-123']);
    $admin->companies()->attach($company, ['role_id' => $role->id, 'status' => 'active', 'is_default' => true]);
    $super = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN, 'status' => 'active']);

    return [$company, $admin, $super];
}

it('lets the super admin change the existing administrator email without changing its password or membership', function () {
    [$company, $admin, $super] = companyAdminFixture();
    $this->actingAs($super)->withSession(['current_company_id' => $company->id]);
    Volt::test('companies.index')->call('loadEditForm', $company->id)
        ->assertSee('Acceso del administrador')
        ->set('administratorForm.name', 'Nombre actualizado')
        ->set('administratorForm.email', 'new@example.test')
        ->call('saveAdministrator')->assertHasNoErrors()
        ->assertSet('administratorForm.password', '');
    expect($admin->fresh()->email)->toBe('new@example.test')
        ->and(Hash::check('Original-password-123', $admin->fresh()->password))->toBeTrue()
        ->and($admin->fresh()->roleKeyForCompany($company))->toBe(RoleKey::ADMIN_EMPRESA);
});

it('resets passwords and revokes tokens when credentials change', function () {
    [$company, $admin, $super] = companyAdminFixture();
    \Illuminate\Support\Facades\DB::table('personal_access_tokens')->insert([
        'company_id' => $company->id, 'tokenable_type' => User::class, 'tokenable_id' => $admin->id,
        'name' => 'old-session', 'token' => hash('sha256', 'old-secret'), 'abilities' => '["*"]',
    ]);
    app(UpdateCompanyAdministratorAction::class)->handle($super, $company, $admin->id, ['name' => $admin->name, 'email' => $admin->email, 'password' => 'New-password-123']);
    expect(Hash::check('New-password-123', $admin->fresh()->password))->toBeTrue()
        ->and($admin->tokens()->count())->toBe(0);
});

it('rejects duplicate emails without changing the account', function () {
    [$company, $admin, $super] = companyAdminFixture();
    $other = User::factory()->create();
    expect(fn () => app(UpdateCompanyAdministratorAction::class)->handle($super, $company, $admin->id, ['name' => 'Changed', 'email' => $other->email]))->toThrow(ValidationException::class);
    expect($admin->fresh()->name)->toBe($admin->name);
});

it('blocks company administrators from this operation and rejects foreign targets', function () {
    [$company, $admin, $super] = companyAdminFixture();
    $data = ['name' => 'Changed', 'email' => 'new@example.test'];
    expect(fn () => app(UpdateCompanyAdministratorAction::class)->handle($admin, $company, $admin->id, $data))->toThrow(AuthorizationException::class);
    $foreign = User::factory()->create();
    expect(fn () => app(UpdateCompanyAdministratorAction::class)->handle($super, $company, $foreign->id, $data))->toThrow(ModelNotFoundException::class);
});
