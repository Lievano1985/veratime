<?php

use App\Models\Company;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_device_binding_authorizations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignIdFor(Company::class)->constrained()->restrictOnDelete();
            $table->foreignIdFor(User::class)->constrained()->restrictOnDelete();
            $table->foreignIdFor(Worker::class)->constrained()->restrictOnDelete();
            $table->foreignIdFor(User::class, 'created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('authorization_secret_hash', 64)->unique();
            $table->string('status')->default('pending');
            $table->timestamp('expires_at');
            $table->string('challenge_hash', 64)->nullable();
            $table->timestamp('challenge_issued_at')->nullable();
            $table->timestamp('challenge_expires_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'user_id', 'worker_id', 'status'], 'mdba_company_identity_status_index');
            $table->index(['company_id', 'expires_at'], 'mdba_company_expires_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_device_binding_authorizations');
    }
};
