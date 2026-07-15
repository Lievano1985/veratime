<?php

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('profile_type');
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'profile_type']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_profiles');
    }
};
