<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_requests', function (Blueprint $table) {
            $table->id();
            $table->string('contact_name');
            $table->string('company_name')->nullable();
            $table->string('email');
            $table->string('phone', 30);
            $table->unsignedInteger('team_size')->nullable();
            $table->text('message')->nullable();
            $table->string('status')->default('new');
            $table->timestamp('consented_at');
            $table->string('source')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_requests');
    }
};
