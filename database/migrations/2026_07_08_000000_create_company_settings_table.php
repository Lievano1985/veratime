<?php

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->unique()->constrained()->cascadeOnDelete();
            $table->string('payroll_period_type')->default('biweekly');
            $table->string('default_timezone')->default('America/Mexico_City');
            $table->unsignedTinyInteger('default_closure_day')->nullable();
            $table->time('work_days_auto_refresh_time')->nullable();
            $table->unsignedSmallInteger('late_arrival_tolerance_minutes')->default(0);
            $table->unsignedSmallInteger('early_departure_tolerance_minutes')->default(0);
            $table->dateTime('work_days_last_refreshed_at')->nullable();
            $table->string('work_days_last_refresh_status')->nullable();
            $table->json('work_days_last_refresh_summary')->nullable();
            $table->boolean('allow_worker_corrections')->default(false);
            $table->boolean('require_pin_for_kiosk')->default(true);
            $table->string('kiosk_enrollment_identifier', 32)->nullable()->unique();
            $table->string('kiosk_enrollment_key_hash')->nullable();
            $table->string('branding_image_path')->nullable();
            $table->boolean('require_pin_for_confirmation')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};
