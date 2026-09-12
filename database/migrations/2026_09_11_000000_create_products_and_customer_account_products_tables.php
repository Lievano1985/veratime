<?php

use App\Support\ProductKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_addon')->default(false);
            $table->foreignId('requires_product_id')->nullable()->constrained('products')->nullOnDelete()->cascadeOnUpdate();
            $table->string('status')->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'is_addon']);
        });

        Schema::create('customer_account_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_account_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('product_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->string('status')->default('active')->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['customer_account_id', 'product_id']);
            $table->index(['product_id', 'status']);
            $table->index(['status', 'starts_at', 'ends_at']);
        });

        $now = now();

        DB::table('products')->updateOrInsert(
            ['key' => ProductKey::TIME],
            [
                'name' => 'VERA Time',
                'description' => 'Registro, control y calculo de jornadas laborales.',
                'is_addon' => false,
                'requires_product_id' => null,
                'status' => 'active',
                'metadata' => json_encode([]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $timeProductId = DB::table('products')->where('key', ProductKey::TIME)->value('id');

        DB::table('customer_accounts')
            ->select('id')
            ->orderBy('id')
            ->each(function (object $account) use ($timeProductId, $now): void {
                DB::table('customer_account_products')->updateOrInsert(
                    [
                        'customer_account_id' => $account->id,
                        'product_id' => $timeProductId,
                    ],
                    [
                        'status' => 'active',
                        'starts_at' => null,
                        'trial_ends_at' => null,
                        'ends_at' => null,
                        'metadata' => json_encode([]),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_account_products');
        Schema::dropIfExists('products');
    }
};
