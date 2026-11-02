<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_account_id')->nullable()->constrained()->nullOnDelete()->cascadeOnUpdate();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('tax_id')->nullable()->unique();
            $table->string('timezone')->default('America/Mexico_City');
            $table->string('status')->default('active')->index();
            $table->boolean('is_trial')->default(false);
            $table->dateTime('trial_ends_at')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->index(['is_trial', 'trial_ends_at'], 'companies_trial_expiration_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
