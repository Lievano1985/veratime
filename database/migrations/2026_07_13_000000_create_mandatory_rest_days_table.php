<?php

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mandatory_rest_days', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->nullable()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->date('date');
            $table->string('type');
            $table->string('scope');
            $table->string('country_code', 2)->default('MX');
            $table->string('jurisdiction_code', 16)->nullable();
            $table->text('source_reference')->nullable();
            $table->string('capture_source')->default('manual');
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('date');
            $table->index(['type', 'date']);
            $table->index(['scope', 'date']);
            $table->index(['country_code', 'date']);
            $table->index(['scope', 'country_code', 'jurisdiction_code', 'date'], 'mrd_scope_jurisdiction_date_idx');
            $table->index(['company_id', 'date']);
            $table->index(['capture_source', 'date']);
            $table->index(['type', 'scope', 'country_code', 'company_id', 'jurisdiction_code', 'date'], 'mrd_type_scope_identity_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mandatory_rest_days');
    }
};
