<?php

use App\Models\OrganizationalUnit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_marking_policies', function (Blueprint $table) {
            $table->foreignIdFor(OrganizationalUnit::class)
                ->nullable()
                ->after('center_id')
                ->constrained()
                ->restrictOnDelete();
            $table->index(['company_id', 'organizational_unit_id', 'status'], 'mmp_company_unit_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('mobile_marking_policies', function (Blueprint $table) {
            $table->dropIndex('mmp_company_unit_status_index');
            $table->dropConstrainedForeignId('organizational_unit_id');
        });
    }
};
