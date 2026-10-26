<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = [
        'kiosk_terminal_access_requests' => ['expires_at' => false, 'reviewed_at' => true, 'claimed_at' => true],
        'mobile_marking_time_references' => ['issued_at' => false, 'expires_at' => false],
        'mobile_device_binding_authorizations' => ['expires_at' => false, 'challenge_issued_at' => true, 'challenge_expires_at' => true, 'consumed_at' => true],
        'mobile_device_bindings' => ['activated_at' => false, 'revoked_at' => true],
        'mobile_offline_marking_authorizations' => ['issued_at' => false, 'expires_at' => false, 'revoked_at' => true],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($columns): void {
                foreach ($columns as $column => $nullable) {
                    $table->dateTime($column)->nullable($nullable)->change();
                }
            });
        }
    }

    public function down(): void
    {
        // Preserve datetime values: narrowing to timestamp could lose historical dates.
    }
};
