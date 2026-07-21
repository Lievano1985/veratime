<?php

namespace Database\Seeders;

use App\Domains\Scheduling\Actions\ApplyDailyScheduleCsvImportAction;
use App\Domains\Scheduling\Actions\CreateDailyScheduleCsvImportAction;
use App\Domains\Scheduling\Actions\ValidateDailyScheduleCsvImportAction;
use App\Models\Company;
use App\Models\ScheduleBatch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class VeraTimeDailyScheduleCsvScenarioSeeder extends Seeder
{
    private const PERIOD_START = '2026-08-03';
    private const PERIOD_END = '2026-08-16';

    public function run(): void
    {
        if ($this->missingStoreDraft()) {
            $this->call(VeraTimeDailyScheduleScenarioSeeder::class);
        }

        $company = Company::query()->where('tax_id', 'VTSP-STORE')->firstOrFail();
        $actor = User::query()->where('email', 'rh.store.demo@veratime.local')->firstOrFail();
        $batch = ScheduleBatch::query()
            ->where('company_id', $company->id)
            ->whereDate('period_start', self::PERIOD_START)
            ->whereDate('period_end', self::PERIOD_END)
            ->where('version', 1)
            ->where('status', 'draft')
            ->firstOrFail();

        $this->validAppliedImport($actor, $company, $batch);
        $this->invalidPreviewImport($actor, $company, $batch);
    }

    private function missingStoreDraft(): bool
    {
        $company = Company::query()->where('tax_id', 'VTSP-STORE')->first();
        if (! $company) {
            return true;
        }

        return ! ScheduleBatch::query()
            ->where('company_id', $company->id)
            ->whereDate('period_start', self::PERIOD_START)
            ->whereDate('period_end', self::PERIOD_END)
            ->where('version', 1)
            ->where('status', 'draft')
            ->exists();
    }

    private function validAppliedImport(User $actor, Company $company, ScheduleBatch $batch): void
    {
        $path = 'imports/daily-schedule/demo-store-valid.csv';
        Storage::disk('local')->put($path, $this->csv([
            ['STR-001', '2026-08-03', 'turno', 'OPEN-08-16', '', '', '', '', '', '', '', '', '', '', 'Demo CSV: apertura.'],
            ['STR-002', '2026-08-03', 'turno', 'MID-11-19', '', '', '', '', '', '', '', '', '', '', 'Demo CSV: intermedio.'],
            ['STR-003', '2026-08-03', 'descanso', '', '', '', '', '', '', '', '', '', '', '', 'Demo CSV: descanso.'],
            ['STR-004', '2026-08-03', 'flexible', '', '420', '09:00', '18:00', '0', '0', '', '', '', '', '', 'Demo CSV: flexible.'],
            ['STR-001', '2026-08-04', 'guardia', '', '', '', '', '', '', '08:00', '20:00', '0', '0', '240', 'Demo CSV: guardia.'],
        ]));

        $result = app(CreateDailyScheduleCsvImportAction::class)->handle($actor, $company, $batch, [
            'storage_disk' => 'local',
            'storage_path' => $path,
            'original_filename' => 'demo-store-valid.csv',
            'existing_assignment_policy' => 'replace_existing',
            'reason' => 'Escenario demo local: importacion CSV valida de programacion diaria.',
            'idempotency_key' => 'demo-store-csv-valid-v1',
        ]);

        if ($result->importBatch->status === 'applied') {
            return;
        }

        $validated = app(ValidateDailyScheduleCsvImportAction::class)->handle($actor, $result->importBatch)->importBatch;
        if ($validated->status === 'validated') {
            app(ApplyDailyScheduleCsvImportAction::class)->handle($actor, $validated);
        }
    }

    private function invalidPreviewImport(User $actor, Company $company, ScheduleBatch $batch): void
    {
        $path = 'imports/daily-schedule/demo-store-invalid.csv';
        Storage::disk('local')->put($path, $this->csv([
            ['NO-EXISTE', '2026-08-03', 'turno', 'OPEN-08-16', '', '', '', '', '', '', '', '', '', '', 'Demo CSV invalido.'],
            ['STR-001', '2026-08-05', 'turno', 'NO-TURNO', '', '', '', '', '', '', '', '', '', '', 'Demo CSV invalido.'],
        ]));

        $result = app(CreateDailyScheduleCsvImportAction::class)->handle($actor, $company, $batch, [
            'storage_disk' => 'local',
            'storage_path' => $path,
            'original_filename' => 'demo-store-invalid.csv',
            'existing_assignment_policy' => 'replace_existing',
            'reason' => 'Escenario demo local: importacion CSV con errores para preview.',
            'idempotency_key' => 'demo-store-csv-invalid-v1',
        ]);

        if (in_array($result->importBatch->status, ['uploaded', 'validated', 'invalid'], true)) {
            app(ValidateDailyScheduleCsvImportAction::class)->handle($actor, $result->importBatch);
        }
    }

    /**
     * @param list<list<string>> $rows
     */
    private function csv(array $rows): string
    {
        $headers = [
            'clave_empleado',
            'fecha',
            'tipo_dia',
            'codigo_turno',
            'minutos_requeridos',
            'inicio_ventana',
            'fin_ventana',
            'offset_inicio_ventana',
            'offset_fin_ventana',
            'inicio_disponibilidad',
            'fin_disponibilidad',
            'offset_inicio_disponibilidad',
            'offset_fin_disponibilidad',
            'maximo_minutos_trabajo',
            'motivo',
        ];

        $lines = [implode(',', $headers)];
        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(fn (string $value): string => str_contains($value, ',') ? '"'.str_replace('"', '""', $value).'"' : $value, $row));
        }

        return implode("\n", $lines)."\n";
    }
}
