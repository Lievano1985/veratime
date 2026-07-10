<?php

use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\Schedule;
use App\Models\Worker;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->restrictOnDelete();
            $table->foreignIdFor(Worker::class)->constrained()->restrictOnDelete();
            $table->foreignIdFor(EmploymentRelationship::class)->nullable()->constrained()->restrictOnDelete();
            $table->foreignIdFor(Schedule::class)->constrained()->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status')->default('active');
            $table->string('source')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'worker_id'], 'sched_assign_company_worker_idx');
            $table->index(['company_id', 'schedule_id'], 'sched_assign_company_schedule_idx');
            $table->index(['company_id', 'effective_from', 'effective_to'], 'sched_assign_company_effective_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_assignments');
    }
};
