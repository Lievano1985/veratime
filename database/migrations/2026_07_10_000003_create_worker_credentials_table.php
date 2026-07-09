<?php

use App\Models\Company;
use App\Models\Worker;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worker_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Worker::class)->constrained()->cascadeOnDelete();
            $table->string('pin_hash')->nullable();
            $table->string('access_code')->nullable();
            $table->string('status')->default('active');
            $table->unsignedInteger('failed_attempts')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('last_changed_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'worker_id']);
            $table->unique(['company_id', 'access_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_credentials');
    }
};
