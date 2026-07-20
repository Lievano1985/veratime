<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_batches', function (Blueprint $table) {
            $table->text('correction_reason')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_batches', function (Blueprint $table) {
            $table->dropColumn('correction_reason');
        });
    }
};
