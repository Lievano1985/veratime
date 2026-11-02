<?php

use App\Models\Company;
use App\Models\User;
use App\Support\RoleKey;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\SuperAdminSeeder;

it('seeds only catalogs without creating any companies or users', function () {
    $this->seed(DatabaseSeeder::class);
    expect(Company::query()->count())->toBe(0)
        ->and(User::query()->count())->toBe(0);
    $this->assertDatabaseCount('workers', 0);
    $this->assertDatabaseCount('company_demo_scenarios', 0);
    $this->assertDatabaseCount('roles', 6);
});

it('preserves an existing super administrator and its password when seeding again', function () {
    $admin = User::factory()->create(['email' => 'owner@example.test', 'global_role' => RoleKey::SUPER_ADMIN]);
    $hash = $admin->password;
    $this->seed(SuperAdminSeeder::class);
    expect(User::query()->count())->toBe(1)
        ->and($admin->fresh()->password)->toBe($hash);
});
