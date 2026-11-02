<?php

namespace Tests\Feature;

use App\Models\KioskTerminalAccessRequest;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MarkingDateMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // DDL commits implicitly on MySQL; these tests need a disposable schema,
        // not a transaction or a rollback of historical data migrations.
        $this->artisan('migrate:fresh');
        $this->beforeApplicationDestroyed(function (): void {
            $this->artisan('db:wipe');
            RefreshDatabaseState::$migrated = false;
        });
    }

    public function test_consolidated_schema_keeps_request_dates_and_nullable_values(): void
    {
        $request = KioskTerminalAccessRequest::factory()->create(['expires_at' => '2026-10-19 14:30:00', 'reviewed_at' => null]);
        $before = DB::table('kiosk_terminal_access_requests')->where('id', $request->id)->first();
        $request->refresh();
        $after = DB::table('kiosk_terminal_access_requests')->where('id', $request->id)->first();

        $this->assertEquals($before, $after);
        $this->assertSame('datetime', Schema::getColumnType('kiosk_terminal_access_requests', 'expires_at'));
        $this->assertSame('datetime', Schema::getColumnType('kiosk_terminal_access_requests', 'reviewed_at'));

        DB::table('kiosk_terminal_access_requests')->where('id', $request->id)->update(['expires_at' => '2040-01-01 12:00:00']);
        $this->assertSame('2040-01-01 12:00:00', DB::table('kiosk_terminal_access_requests')->where('id', $request->id)->value('expires_at'));
        $this->assertNull(DB::table('kiosk_terminal_access_requests')->where('id', $request->id)->value('reviewed_at'));
    }

    public function test_fresh_installation_creates_all_marking_dates_as_datetime(): void
    {
        foreach ([
            'mobile_marking_policies' => ['valid_from', 'valid_until', 'offline_valid_until'],
            'kiosk_terminal_access_requests' => ['expires_at', 'reviewed_at', 'claimed_at'],
            'mobile_marking_time_references' => ['issued_at', 'expires_at'],
            'mobile_device_binding_authorizations' => ['expires_at', 'challenge_issued_at', 'challenge_expires_at', 'consumed_at'],
            'mobile_device_bindings' => ['activated_at', 'revoked_at'],
            'mobile_offline_marking_authorizations' => ['issued_at', 'expires_at', 'revoked_at'],
        ] as $table => $columns) {
            foreach ($columns as $column) {
                $this->assertSame('datetime', Schema::getColumnType($table, $column), "{$table}.{$column}");
            }
        }
    }
}
