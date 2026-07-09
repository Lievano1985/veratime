<?php

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centers', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('timezone');
            $table->string('status')->default('active');
            $table->json('address')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centers');
    }
};
