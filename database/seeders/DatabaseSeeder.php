<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleKey;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(LegalRuleSeeder::class);
        $this->call(AlertTypeSeeder::class);
        $this->call(VeraTimeDemoSeeder::class);
        $this->call(VeraTimeOperationalVerificationSeeder::class);

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $company = Company::factory()->create([
            'name' => 'Vera Time Demo',
            'legal_name' => 'Vera Time Demo SA de CV',
            'tax_id' => 'VTIME260705XX1',
        ]);

        $company->setting()->create(Company::defaultSettings());

        $role = Role::query()->where('key', RoleKey::OWNER)->first();

        $user->companies()->attach($company, [
            'role_id' => $role?->id,
            'status' => 'active',
            'is_default' => true,
        ]);
    }
}
