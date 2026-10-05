<?php

use App\Models\Company;
use App\Models\MobileMarkingPolicy;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_marking_time_references', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignIdFor(Company::class)->constrained()->restrictOnDelete();
            $table->foreignIdFor(User::class)->constrained()->restrictOnDelete();
            $table->foreignIdFor(Worker::class)->constrained()->restrictOnDelete();
            $table->foreignIdFor(MobileMarkingPolicy::class)->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('policy_version')->nullable();
            $table->timestamp('issued_at');
            $table->timestamp('expires_at');
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->timestamps();

            $table->index(['company_id', 'worker_id', 'expires_at'], 'mmtr_company_worker_expires_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_marking_time_references');
    }
};
