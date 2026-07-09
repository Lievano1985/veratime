<?php

use App\Models\Center;
use App\Models\Company;
use App\Models\Worker;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employment_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Worker::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Center::class)->constrained()->cascadeOnDelete();
            $table->string('position_name')->nullable();
            $table->date('started_at');
            $table->date('ended_at')->nullable();
            $table->string('status')->default('active');
            $table->string('source')->default('web');
            $table->string('external_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'worker_id', 'status']);
            $table->index(['company_id', 'center_id', 'status']);
            $table->index(['company_id', 'started_at', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employment_relationships');
    }
};
