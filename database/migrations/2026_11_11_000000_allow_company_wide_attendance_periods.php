<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_periods', function (Blueprint $table): void {
            $table->unsignedBigInteger('center_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('attendance_periods')->whereNull('center_id')->exists()) {
            throw new RuntimeException('Existen periodos de toda la empresa.');
        }

        Schema::table('attendance_periods', function (Blueprint $table): void {
            $table->unsignedBigInteger('center_id')->nullable(false)->change();
        });
    }
};
