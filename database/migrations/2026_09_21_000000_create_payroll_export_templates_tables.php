<?php

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_export_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->boolean('is_system')->default(false);
            $table->boolean('is_default')->default(false);
            $table->char('delimiter', 1)->default(',');
            $table->timestamps();

            $table->unique(['company_id', 'name']);
            $table->index(['company_id', 'status', 'is_default']);
        });

        Schema::create('payroll_export_template_columns', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('payroll_export_template_id');
            $table->foreign('payroll_export_template_id', 'petc_template_fk')
                ->references('id')
                ->on('payroll_export_templates')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('source_key', 100);
            $table->string('header');
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->unique(['payroll_export_template_id', 'source_key'], 'petc_template_source_unique');
            $table->unique(['payroll_export_template_id', 'position'], 'petc_template_position_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_export_template_columns');
        Schema::dropIfExists('payroll_export_templates');
    }
};
