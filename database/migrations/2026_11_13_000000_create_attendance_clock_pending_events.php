<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_clock_pending_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_clock_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 69)->unique();
            $table->string('clock_user_id', 32);
            $table->date('occurred_local_date');
            $table->time('occurred_local_time');
            $table->string('timezone', 100);
            $table->string('event_type', 20);
            $table->string('reason', 64);
            $table->timestamp('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_clock_pending_events');
    }
};
