<?php

namespace Database\Seeders;

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
        $this->call(LegalRuleSeeder::class);
        $this->call(AlertTypeSeeder::class);

        $this->ensureSuperAdmin();

        if (app()->environment(['local', 'testing'])) {
            $this->call(VeraTimeOperationalVerificationSeeder::class);
        }
    }

    private function ensureSuperAdmin(): void
    {
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
