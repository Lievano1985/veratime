<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_rules', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['category', 'status'], 'legal_rules_category_status_idx');
        });

        Schema::create('legal_rule_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_rule_id')->constrained('legal_rules')->restrictOnDelete()->cascadeOnUpdate();
            $table->unsignedInteger('version');
            $table->json('value');
            $table->string('unit');
            $table->text('source_reference')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->foreignIdFor(User::class, 'created_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamps();

            $table->unique(['legal_rule_id', 'version'], 'legal_rule_versions_rule_version_unique');
            $table->index(['legal_rule_id', 'effective_from', 'effective_to'], 'legal_rule_versions_rule_effective_idx');
            $table->index(['legal_rule_id', 'status', 'effective_from'], 'legal_rule_versions_rule_status_from_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_rule_versions');
        Schema::dropIfExists('legal_rules');
    }
};
