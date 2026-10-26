<?php

namespace Tests\Feature;

use App\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_database_seeder_creates_only_the_vera_time_demo_company(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, Company::query()->count());
        $this->assertDatabaseHas('companies', [
            'tax_id' => 'VTIME260705XX1',
            'name' => 'Vera Time Demo',
        ]);
        $this->assertDatabaseMissing('companies', [
            'tax_id' => 'VTD260712XX1',
        ]);
    }
}
