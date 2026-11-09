<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const REFERENCES = [
        'mobile_marking_event_evidences' => 'mmee_policy_fk',
        'mobile_offline_marking_authorizations' => 'moma_policy_fk',
        'mobile_offline_marking_captures' => 'momc_policy_fk',
    ];

    public function up(): void
    {
        foreach (self::REFERENCES as $table => $constraint) {
            $this->dropPolicyForeignKey($table, $constraint);
            Schema::table($table, fn (Blueprint $schema) => $schema->unsignedBigInteger('mobile_marking_policy_id')->nullable()->change());
            Schema::table($table, fn (Blueprint $schema) => $schema->foreign('mobile_marking_policy_id', $constraint)->references('id')->on('mobile_marking_policies')->nullOnDelete());
        }

        Schema::table('mobile_offline_marking_captures', fn (Blueprint $schema) => $schema->json('review_policy_snapshot')->nullable());
    }

    public function down(): void
    {
        foreach (['mobile_marking_event_evidences', 'mobile_offline_marking_authorizations'] as $table) {
            if (DB::table($table)->whereNull('mobile_marking_policy_id')->exists()) {
                throw new RuntimeException('No se puede revertir la migración después de eliminar políticas con historial.');
            }
        }

        foreach (self::REFERENCES as $table => $constraint) {
            $this->dropPolicyForeignKey($table, $constraint);
            if ($table !== 'mobile_offline_marking_captures') {
                Schema::table($table, fn (Blueprint $schema) => $schema->unsignedBigInteger('mobile_marking_policy_id')->nullable(false)->change());
            }
            Schema::table($table, fn (Blueprint $schema) => $schema->foreign('mobile_marking_policy_id', $constraint)->references('id')->on('mobile_marking_policies')->restrictOnDelete());
        }

        Schema::table('mobile_offline_marking_captures', fn (Blueprint $schema) => $schema->dropColumn('review_policy_snapshot'));
    }

    private function dropPolicyForeignKey(string $table, string $constraint): void
    {
        $key = DB::connection()->getDriverName() === 'sqlite' ? ['mobile_marking_policy_id'] : $constraint;
        Schema::table($table, fn (Blueprint $schema) => $schema->dropForeign($key));
    }
};
