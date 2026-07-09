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
            $table->boolean('allow_worker_corrections')->default(false);
            $table->boolean('require_pin_for_kiosk')->default(true);
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
