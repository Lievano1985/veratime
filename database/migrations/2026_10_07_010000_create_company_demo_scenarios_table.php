<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_demo_scenarios', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('requested_by_user_id')->nullable();
            $table->string('status', 30)->default('pending');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->json('summary')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique('company_id', 'cds_company_unique');
            $table->index('status', 'cds_status_index');
            $table->foreign('company_id', 'cds_company_fk')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('requested_by_user_id', 'cds_requested_by_fk')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_demo_scenarios');
    }
};
