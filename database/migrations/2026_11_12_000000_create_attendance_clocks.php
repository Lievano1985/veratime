<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_clocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('center_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100);
            $table->string('serial', 64)->unique();
            $table->timestamps();
            $table->index('company_id');
        });

        Schema::create('attendance_clock_workers', function (Blueprint $table) {
            $table->foreignId('attendance_clock_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->string('clock_user_id', 32);
            $table->unique(['attendance_clock_id', 'clock_user_id'], 'clock_workers_clock_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_clock_workers');
        Schema::dropIfExists('attendance_clocks');
    }
};
