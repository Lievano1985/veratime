<?php

use App\Models\User;
use App\Support\RoleKey;
use Illuminate\Support\Facades\Hash;

it('creates a super administrator from interactive input without printing the password', function () {
    $this->artisan('veratime:create-super-admin')
        ->expectsQuestion('Nombre', 'Administrador plataforma')
        ->expectsQuestion('Correo electrónico', 'owner@example.test')
        ->expectsQuestion('Contraseña', 'Strong-private-123')
        ->expectsQuestion('Confirma la contraseña', 'Strong-private-123')
        ->doesntExpectOutputToContain('Strong-private-123')
        ->expectsOutputToContain('Superadministrador creado: owner@example.test')
        ->assertSuccessful();
    $user = User::query()->sole();
    expect($user->global_role)->toBe(RoleKey::SUPER_ADMIN)
        ->and(Hash::check('Strong-private-123', $user->password))->toBeTrue()
        ->and($user->password)->not->toBe('Strong-private-123');
    $this->assertDatabaseCount('companies', 0);
});

it('rejects mismatched or weak passwords without creating an account', function ($password, $confirmation) {
    $this->artisan('veratime:create-super-admin')
        ->expectsQuestion('Nombre', 'Administrador')
        ->expectsQuestion('Correo electrónico', 'owner@example.test')
        ->expectsQuestion('Contraseña', $password)
        ->expectsQuestion('Confirma la contraseña', $confirmation)
        ->assertFailed();
    $this->assertDatabaseCount('users', 0);
})->with([['Strong-private-123', 'Different-secret-123'], ['short', 'short']]);

it('does not replace or promote an existing account with the same email', function () {
    $existing = User::factory()->create(['email' => 'owner@example.test']);
    $hash = $existing->password;
    $this->artisan('veratime:create-super-admin')
        ->expectsQuestion('Nombre', 'Changed')
        ->expectsQuestion('Correo electrónico', 'OWNER@example.test')
        ->expectsQuestion('Contraseña', 'Strong-private-123')
        ->expectsQuestion('Confirma la contraseña', 'Strong-private-123')
        ->assertFailed();
    expect($existing->fresh()->password)->toBe($hash)
        ->and($existing->fresh()->global_role)->toBeNull();
});

it('requires an interactive terminal', function () {
    $this->artisan('veratime:create-super-admin', ['--no-interaction' => true])->assertFailed();
    $this->assertDatabaseCount('users', 0);
});
