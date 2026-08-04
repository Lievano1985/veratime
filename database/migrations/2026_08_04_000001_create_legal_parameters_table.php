<?php

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->nullable()->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->string('code');
            $table->json('value');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status')->default('active');
            $table->text('source_reference')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'code', 'status'], 'legal_parameters_company_code_status_idx');
            $table->index(['code', 'effective_from', 'effective_to'], 'legal_parameters_code_effective_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_parameters');
    }
};
