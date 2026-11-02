<?php

namespace Tests\Feature;

use App\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_database_seeder_is_repeatable_without_creating_demo_companies_or_users(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, Company::query()->count());
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('roles', 6);
        $this->assertDatabaseCount('products', 3);
    }
}
