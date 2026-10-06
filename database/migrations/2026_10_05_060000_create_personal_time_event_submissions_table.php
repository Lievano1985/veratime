<?php

use App\Models\Company;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_time_event_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Company::class)->constrained()->restrictOnDelete();
            $table->foreignIdFor(User::class)->constrained()->restrictOnDelete();
            $table->foreignIdFor(Worker::class)->constrained()->restrictOnDelete();
            $table->foreignIdFor(TimeEvent::class)->constrained()->restrictOnDelete();
            $table->string('client_event_id');
            $table->char('payload_hash', 64);
            $table->timestamps();

            $table->unique(['company_id', 'client_event_id'], 'ptes_company_client_event_unique');
            $table->unique('time_event_id', 'ptes_time_event_unique');
            $table->index(['company_id', 'user_id', 'worker_id'], 'ptes_company_user_worker_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_time_event_submissions');
    }
};
