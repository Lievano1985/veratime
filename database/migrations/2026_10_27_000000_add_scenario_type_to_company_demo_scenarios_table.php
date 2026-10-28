<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_demo_scenarios', function (Blueprint $table): void {
            $table->string('scenario_type')->default('operational');
        });
    }

    public function down(): void
    {
        Schema::table('company_demo_scenarios', function (Blueprint $table): void {
            $table->dropColumn('scenario_type');
        });
    }
};
