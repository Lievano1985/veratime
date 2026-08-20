<?php

use App\Support\RoleKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('global_role')->nullable()->after('status')->index();
        });

        if (! Schema::hasTable('roles') || ! Schema::hasTable('company_user')) {
            return;
        }

        $superAdminRoleId = DB::table('roles')
            ->where('key', RoleKey::SUPER_ADMIN)
            ->value('id');

        if (! $superAdminRoleId) {
            return;
        }

        $superAdminUserIds = DB::table('company_user')
            ->where('role_id', $superAdminRoleId)
            ->pluck('user_id');

        if ($superAdminUserIds->isEmpty()) {
            return;
        }

        DB::table('users')
            ->whereIn('id', $superAdminUserIds)
            ->update(['global_role' => RoleKey::SUPER_ADMIN]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['global_role']);
            $table->dropColumn('global_role');
        });
    }
};
