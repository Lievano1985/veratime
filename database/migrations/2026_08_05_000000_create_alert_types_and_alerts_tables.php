<?php

use App\Models\Company;
use App\Models\User;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use App\Models\Worker;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_types', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('default_severity');
            $table->string('category');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->restrictOnDelete();
            $table->foreignId('alert_type_id')->constrained('alert_types')->restrictOnDelete();
            $table->foreignIdFor(Worker::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(WorkDay::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(WorkDayCalculation::class)->nullable()->constrained()->nullOnDelete();
            $table->string('severity');
            $table->string('status')->default('new');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('rule_code')->nullable();
            $table->dateTime('detected_at');
            $table->foreignIdFor(User::class, 'assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('due_at')->nullable();
            $table->text('resolution')->nullable();
            $table->foreignIdFor(User::class, 'resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->string('fingerprint', 64);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'fingerprint']);
            $table->index(['company_id', 'status', 'severity']);
            $table->index(['company_id', 'worker_id', 'detected_at']);
            $table->index(['company_id', 'work_day_id']);
            $table->index(['company_id', 'assigned_to', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('alert_types');
    }
};
