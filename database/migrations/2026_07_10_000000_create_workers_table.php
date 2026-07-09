<?php

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workers', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->cascadeOnDelete();
            $table->string('employee_code');
            $table->string('full_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('curp')->nullable();
            $table->string('rfc')->nullable();
            $table->string('status')->default('active');
            $table->string('source')->default('web');
            $table->string('external_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'employee_code']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'external_id']);
            $table->index(['company_id', 'rfc']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workers');
    }
};
