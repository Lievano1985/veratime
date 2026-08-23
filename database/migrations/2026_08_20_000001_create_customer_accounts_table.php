<?php

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

        Schema::table('companies', function (Blueprint $table) {
            $table
                ->foreignId('customer_account_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        Company::query()
            ->select(['id', 'name', 'status'])
            ->orderBy('id')
            ->each(function (Company $company): void {
                $now = now();

                $customerAccountId = DB::table('customer_accounts')->insertGetId([
                    'name' => $company->name,
                    'account_type' => 'single_company',
                    'status' => 'active',
                    'metadata' => json_encode([]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $company->forceFill(['customer_account_id' => $customerAccountId])->save();
            });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_account_id');
        });

        Schema::dropIfExists('customer_accounts');
    }
};
