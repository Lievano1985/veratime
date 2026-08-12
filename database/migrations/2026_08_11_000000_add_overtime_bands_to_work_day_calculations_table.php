<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_day_calculations', function (Blueprint $table) {
            $table->unsignedInteger('overtime_double_minutes')->default(0)->after('overtime_minutes');
            $table->unsignedInteger('overtime_triple_minutes')->default(0)->after('overtime_double_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('work_day_calculations', function (Blueprint $table) {
            $table->dropColumn(['overtime_double_minutes', 'overtime_triple_minutes']);
        });
    }
};
