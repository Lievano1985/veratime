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
        Schema::create('user_worker_links', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->restrictOnDelete();
            $table->foreignIdFor(User::class)->constrained()->restrictOnDelete();
            $table->foreignIdFor(Worker::class)->constrained()->restrictOnDelete();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['company_id', 'user_id'], 'uwl_company_user_unique');
            $table->unique(['company_id', 'worker_id'], 'uwl_company_worker_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_worker_links');
    }
};
