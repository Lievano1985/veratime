<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('active')->index();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['company_id', 'user_id']);
            $table->index(['user_id', 'status']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_user');
    }
};
