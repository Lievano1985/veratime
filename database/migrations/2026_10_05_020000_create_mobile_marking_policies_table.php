<?php

use App\Models\Center;
use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_marking_policies', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignIdFor(Company::class)->constrained()->restrictOnDelete();
            $table->foreignIdFor(Center::class)->nullable()->constrained()->restrictOnDelete();
            $table->string('status')->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->string('mode')->default('free');
            $table->boolean('requires_device_binding')->default(false);
            $table->boolean('requires_biometric_unlock')->default(false);
            $table->decimal('center_latitude', 10, 7)->nullable();
            $table->decimal('center_longitude', 10, 7)->nullable();
            $table->unsignedInteger('radius_meters')->nullable();
            $table->unsignedInteger('max_accuracy_meters')->nullable();
            $table->unsignedInteger('max_location_age_seconds')->nullable();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->timestamp('offline_valid_until')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'center_id', 'status'], 'mmp_company_center_status_index');
            $table->index(['company_id', 'status', 'valid_from'], 'mmp_company_status_valid_from_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_marking_policies');
    }
};
