<?php

use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_incidents', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(Worker::class)->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(EmploymentRelationship::class)->nullable()->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('incident_type');
            $table->string('payment_status')->default('not_applicable');
            $table->string('status')->default('approved');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignIdFor(User::class, 'created_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(User::class, 'cancelled_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'worker_id', 'start_date', 'end_date'], 'attendance_incidents_company_worker_range_idx');
            $table->index(['company_id', 'status'], 'attendance_incidents_company_status_idx');
            $table->index(['company_id', 'incident_type'], 'attendance_incidents_company_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_incidents');
    }
};
