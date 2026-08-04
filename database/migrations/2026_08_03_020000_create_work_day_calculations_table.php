<?php

use App\Models\Company;
use App\Models\User;
use App\Models\WorkDay;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_day_calculations', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(WorkDay::class)->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->unsignedInteger('version');
            $table->string('status')->default('active');
            $table->timestamp('calculated_at');
            $table->string('generated_by_type')->default('system');
            $table->foreignIdFor(User::class, 'generated_by_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->text('reason')->nullable();
            $table->unsignedInteger('total_work_minutes')->default(0);
            $table->unsignedInteger('ordinary_minutes')->default(0);
            $table->unsignedInteger('night_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->unsignedInteger('break_minutes')->default(0);
            $table->unsignedInteger('paid_break_minutes')->default(0);
            $table->unsignedInteger('sunday_minutes')->default(0);
            $table->unsignedInteger('mandatory_rest_minutes')->default(0);
            $table->string('classification')->default('pending');
            $table->json('rules_snapshot')->nullable();
            $table->json('inputs_snapshot')->nullable();
            $table->json('result_snapshot')->nullable();
            $table->json('explanation')->nullable();
            $table->timestamps();

            $table->unique(['work_day_id', 'version'], 'work_day_calculations_work_day_version_unique');
            $table->index(['company_id', 'status'], 'work_day_calculations_company_status_idx');
            $table->index(['company_id', 'calculated_at'], 'work_day_calculations_company_calculated_idx');
        });

        Schema::table('work_days', function (Blueprint $table) {
            $table->foreign('active_calculation_id')
                ->references('id')
                ->on('work_day_calculations')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('work_days', function (Blueprint $table) {
            $table->dropForeign(['active_calculation_id']);
        });

        Schema::dropIfExists('work_day_calculations');
    }
};
