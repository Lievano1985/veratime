<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('account_type')->default('single_company');
            $table->string('status')->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['account_type', 'status']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('customer_accounts');
    }
};
