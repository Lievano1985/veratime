<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issued_attendance_reports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('issuer_name');
            $table->string('title');
            $table->string('scope');
            $table->date('period_start');
            $table->date('period_end');
            $table->longText('snapshot');
            $table->timestamp('created_at');
            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issued_attendance_reports');
    }
};
