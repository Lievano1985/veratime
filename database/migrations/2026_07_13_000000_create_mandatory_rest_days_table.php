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
        Schema::create('mandatory_rest_days', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->nullable()->constrained()->restrictOnDelete();
            $table->foreignIdFor(Center::class)->nullable()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->date('date');
            $table->string('scope');
            $table->string('source')->nullable();
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('date');
            $table->index(['scope', 'date']);
            $table->index(['company_id', 'date']);
            $table->index(['company_id', 'center_id', 'date'], 'mrd_company_center_date_idx');
            $table->index(['scope', 'company_id', 'center_id', 'date'], 'mrd_scope_company_center_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mandatory_rest_days');
    }
};