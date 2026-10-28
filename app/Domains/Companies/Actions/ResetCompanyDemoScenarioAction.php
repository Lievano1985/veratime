<?php

namespace App\Domains\Companies\Actions;

use App\Models\Company;
use App\Models\CompanyDemoScenario;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ResetCompanyDemoScenarioAction
{
    private const PRESERVED_TABLES = [
        'company_settings', 'company_user', 'company_demo_scenarios', 'company_demo_reset_logs',
        'export_layouts', 'payroll_export_templates', 'legal_rules', 'legal_parameters', 'mandatory_rest_days',
    ];

    public function __construct(private readonly RequestCompanyDemoScenarioAction $requestDemo) {}

    public function handle(Company $company, User $actor, string $confirmation, string $replacement = 'empty'): ?CompanyDemoScenario
    {
        if (! $actor->isSuperAdmin()) {
            throw new AuthorizationException('Solo el superadministrador puede borrar o reemplazar el demo.');
        }
        if (! in_array($replacement, ['empty', CompanyDemoScenario::TYPE_OPERATIONAL, CompanyDemoScenario::TYPE_CLOSED_WEEK], true)) {
            $this->reject('Selecciona una opción válida para el demo.');
        }

        return DB::transaction(function () use ($company, $actor, $confirmation, $replacement): ?CompanyDemoScenario {
            $company = Company::query()->whereKey($company->id)->lockForUpdate()->firstOrFail();
            if ($confirmation !== $company->name) {
                throw ValidationException::withMessages(['demoResetConfirmation' => 'Escribe exactamente el nombre de la empresa.']);
            }
            $scenario = CompanyDemoScenario::query()->where('company_id', $company->id)->lockForUpdate()->first();
            if (! $scenario || ! in_array($scenario->status, [CompanyDemoScenario::STATUS_COMPLETED, CompanyDemoScenario::STATUS_FAILED], true)) {
                $this->reject('El demo debe haber terminado antes de borrarlo o reemplazarlo.');
            }
            if ($replacement !== 'empty' && $company->status !== 'active') {
                $this->reject('Activa la empresa antes de generar otro demo.');
            }
            $tables = array_values(array_filter(Schema::getTableListing(null, false), fn ($table) => ! in_array($table, self::PRESERVED_TABLES, true) && Schema::hasColumn($table, 'company_id')));
            $snapshot = ['schema_version' => 1, 'company_id' => $company->id, 'scenario' => $scenario->getRawOriginal(), 'tables' => []];
            foreach ($tables as $table) {
                $snapshot['tables'][$table] = DB::table($table)->where('company_id', $company->id)->orderBy('id')->lockForUpdate()->get()->map(fn ($row) => (array) $row)->all();
            }
            $snapshot['settings'] = DB::table('company_settings')->where('company_id', $company->id)->first();
            $json = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            DB::table('company_demo_reset_logs')->insert([
                'company_id' => $company->id, 'actor_id' => $actor->id, 'replacement_type' => $replacement,
                'snapshot_json' => $json, 'snapshot_sha256' => hash('sha256', $json), 'created_at' => now(),
            ]);
            $this->deleteOperationalTables($company, $tables);
            DB::table('company_settings')->where('company_id', $company->id)->update([
                'work_days_last_refreshed_at' => null, 'work_days_last_refresh_status' => null, 'work_days_last_refresh_summary' => null,
            ]);
            $scenario->delete();
            $company->unsetRelation('demoScenario');
            if ($replacement === 'empty') {
                return null;
            }
            $newScenario = $this->requestDemo->handle($company, $actor, $replacement);
            if ($newScenario->status !== CompanyDemoScenario::STATUS_COMPLETED) {
                $this->reject('No se pudo generar el nuevo demo. Se conservó el anterior. '.$newScenario->error_message);
            }

            return $newScenario;
        });
    }

    /** @param list<string> $tables */
    private function deleteOperationalTables(Company $company, array $tables): void
    {
        $dependencies = [];
        foreach ($tables as $table) {
            $dependencies[$table] = [];
            $columns = collect(Schema::getColumns($table))->keyBy('name');
            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                $parent = $foreignKey['foreign_table'];
                if (! in_array($parent, $tables, true)) {
                    continue;
                }
                if (collect($foreignKey['columns'])->every(fn ($column) => $columns[$column]['nullable'])) {
                    DB::table($table)->where('company_id', $company->id)->update(array_fill_keys($foreignKey['columns'], null));
                } else {
                    $dependencies[$table][] = $parent;
                }
            }
        }
        // Remove children before their parents without disabling foreign-key checks.
        while ($dependencies !== []) {
            $progress = false;
            foreach (array_keys($dependencies) as $table) {
                $referenced = collect($dependencies)->contains(fn ($parents) => in_array($table, $parents, true));
                if ($referenced) {
                    continue;
                }
                DB::table($table)->where('company_id', $company->id)->delete();
                unset($dependencies[$table]);
                $progress = true;
            }
            if (! $progress) {
                $this->reject('No se pudo completar la limpieza por dependencias entre registros. Se conservaron los datos anteriores.');
            }
        }
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['demoReset' => $message]);
    }
}
