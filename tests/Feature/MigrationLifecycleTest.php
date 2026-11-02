<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh')->assertExitCode(0);
        $this->beforeApplicationDestroyed(function (): void {
            $this->artisan('db:wipe')->assertExitCode(0);
            RefreshDatabaseState::$migrated = false;
        });
    }

    public function test_consolidated_schema_rolls_back_and_reinstalls_with_the_same_constraints(): void
    {
        $before = $this->snapshot();
        $this->assertTrue(Schema::hasColumns('users', ['status', 'global_role']));
        $this->assertTrue(Schema::hasColumns('companies', ['customer_account_id', 'is_trial', 'trial_ends_at']));
        $this->assertTrue(Schema::hasColumns('company_settings', ['branding_image_path', 'late_arrival_tolerance_minutes', 'kiosk_enrollment_identifier']));
        $this->assertTrue(Schema::hasColumns('attendance_periods', ['closed_by', 'snapshot_canonical_json', 'snapshot_sha256']));
        $this->assertFalse(Schema::hasColumn('company_settings', 'kiosk_key_hash'));
        $this->assertFalse(Schema::hasColumn('company_settings', 'require_authorized_kiosk_devices'));
        $this->artisan('migrate:rollback', ['--force' => true])->assertExitCode(0);
        $this->assertSame([], $this->snapshot());
        $this->assertSame(0, DB::table('migrations')->count());
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->assertSame($before, $this->snapshot());
    }

    private function snapshot(): array
    {
        $schema = DB::getDriverName() === 'sqlite' ? null : DB::getDatabaseName();
        $result = [];
        foreach (Schema::getTableListing($schema, false) as $table) {
            if ($table === 'migrations') {
                continue;
            }
            $result[$table] = [Schema::getColumns($table), Schema::getIndexes($table), Schema::getForeignKeys($table)];
        }
        ksort($result);

        return $result;
    }
}
