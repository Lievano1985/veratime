<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_parameters', function (Blueprint $table) {
            $table->text('reason')->nullable()->after('source_reference');
            $table->foreignIdFor(User::class, 'created_by')->nullable()->after('reason')->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(User::class, 'updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->json('metadata')->nullable()->after('updated_by');
        });
    }

    public function down(): void
    {
        Schema::table('legal_parameters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['reason', 'metadata']);
        });
    }
};
