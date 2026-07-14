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
        Schema::create('organizational_units', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(Center::class)->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('parent_id')->nullable()->constrained('organizational_units')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('code');
            $table->string('name');
            $table->string('type');
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'center_id', 'code']);
            $table->index(['company_id', 'center_id', 'type']);
            $table->index(['company_id', 'center_id', 'status']);
            $table->index(['company_id', 'parent_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizational_units');
    }
};