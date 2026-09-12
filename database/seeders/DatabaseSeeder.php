<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleKey;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(ProductSeeder::class);
        $this->call(LegalRuleSeeder::class);
        $this->call(AlertTypeSeeder::class);
/*         $this->call(VeraTimeDemoSeeder::class);
 */        $this->call(VeraTimeOperationalVerificationSeeder::class);

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

        $role = Role::query()->where('key', RoleKey::ADMIN_EMPRESA)->first();

        $user->companies()->attach($company, [
            'role_id' => $role?->id,
            'status' => 'active',
            'is_default' => true,
        ]);

        $superAdminPassword = env('VERA_TIME_SUPER_ADMIN_PASSWORD') ?: Str::password(24);
        $superAdmin = User::query()->firstOrCreate(
            ['email' => 'superadmin@veratime.local'],
            [
                'name' => 'Super Admin Vera Time',
                'password' => Hash::make($superAdminPassword),
                'status' => 'active',
                'global_role' => RoleKey::SUPER_ADMIN,
            ],
        );

        if (! $superAdmin->global_role) {
            $superAdmin->forceFill(['global_role' => RoleKey::SUPER_ADMIN])->save();
        }

        if ($superAdmin->wasRecentlyCreated && ! env('VERA_TIME_SUPER_ADMIN_PASSWORD')) {
            $this->command?->warn('Super admin creado: superadmin@veratime.local / '.$superAdminPassword);
        }
    }
}
