<?php

namespace App\Livewire\Scheduling;

use App\Domains\Scheduling\Actions\ApplyDailyScheduleCsvImportAction;
use App\Domains\Scheduling\Actions\CreateDailyScheduleCsvImportAction;
use App\Domains\Scheduling\Actions\StoreDailyScheduleCsvUploadAction;
use App\Domains\Scheduling\Actions\ValidateDailyScheduleCsvImportAction;
use App\Domains\Scheduling\Exceptions\DailyScheduleCsvImportStateException;
use App\Domains\Scheduling\Exceptions\DailyScheduleCsvStalePreviewException;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\ImportBatch;
use App\Models\ScheduleBatch;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Throwable;

class DailyScheduleCsvImport extends Component
{
    use WithFileUploads;
    use WithPagination;

    public int $scheduleBatchId;

    public string $workerSearch = '';

    public string $organizationalUnitId = '';

    public bool $showPanel = false;

    public ?int $activeImportId = null;

    public mixed $file = null;

    public string $existingAssignmentPolicy = 'replace_existing';

    public bool $confirmApply = false;

    public function mount(int $scheduleBatchId, string $workerSearch = '', string $organizationalUnitId = ''): void
    {
        $this->scheduleBatchId = $scheduleBatchId;
        $this->workerSearch = $workerSearch;
        $this->organizationalUnitId = $organizationalUnitId;
    }

    public function openPanel(): void
    {
        $batch = $this->batch();
        Gate::authorize('update', $batch);
        $this->showPanel = true;
    }

    public function closePanel(): void
    {
        $this->showPanel = false;
    }

    public function uploadAndValidate(
        StoreDailyScheduleCsvUploadAction $storeUpload,
        CreateDailyScheduleCsvImportAction $createImport,
        ValidateDailyScheduleCsvImportAction $validateImport,
    ): void {
        $company = $this->company();
        $batch = $this->batch();
        Gate::authorize('update', $batch);

        $this->validate([
            'file' => ['required', 'file', 'max:10240'],
            'existingAssignmentPolicy' => ['required', 'in:preserve_existing,replace_existing'],
        ], [
            'file.required' => 'Selecciona un archivo CSV.',
        ]);

        if (! $this->file instanceof TemporaryUploadedFile || mb_strtolower($this->file->getClientOriginalExtension()) !== 'csv') {
            throw ValidationException::withMessages(['file' => 'El archivo debe tener extension .csv.']);
        }

        $stored = null;

        try {
            $stored = $storeUpload->handle($company, $batch, $this->file);
            $result = $createImport->handle(auth()->user(), $company, $batch, [
                'storage_disk' => $stored['disk'],
                'storage_path' => $stored['path'],
                'original_filename' => $stored['original_filename'],
                'existing_assignment_policy' => $this->existingAssignmentPolicy,
            ]);

            $validation = $validateImport->handle(auth()->user(), $result->importBatch);
            $this->activeImportId = $validation->importBatch->id;
            $this->file = null;
            $this->confirmApply = false;
            $this->resetPage('csvRowsPage');
            session()->flash('csvImportMessage', 'Archivo validado. Revisa la vista previa antes de aplicar.');
        } catch (Throwable $exception) {
            if ($stored !== null && ! isset($result)) {
                $storeUpload->deleteStoredFile($stored['disk'], $stored['path']);
            }

            throw $exception;
        }
    }

    public function validateImport(ValidateDailyScheduleCsvImportAction $validateImport): void
    {
        $import = $this->activeImport();
        Gate::authorize('update', $import->scheduleBatch);

        $validation = $validateImport->handle(auth()->user(), $import);
        $this->activeImportId = $validation->importBatch->id;
        $this->confirmApply = false;
        session()->flash('csvImportMessage', 'La validacion se actualizo correctamente.');
    }

    public function applyImport(ApplyDailyScheduleCsvImportAction $applyImport): void
    {
        $import = $this->activeImport();
        Gate::authorize('update', $import->scheduleBatch);

        if (! $this->confirmApply) {
            throw ValidationException::withMessages(['confirmApply' => 'Confirma que revisaste la vista previa antes de aplicar.']);
        }

        try {
            $result = $applyImport->handle(auth()->user(), $import, $import->validation_sha256);
            $this->activeImportId = $result->importBatch->id;
            $this->confirmApply = false;
            session()->flash('csvImportMessage', "Importacion aplicada: {$result->appliedRows} filas aplicadas y {$result->skippedRows} omitidas.");
            $this->dispatch('daily-schedule-import-applied');
        } catch (DailyScheduleCsvStalePreviewException $exception) {
            $this->confirmApply = false;
            throw ValidationException::withMessages(['csvImport' => $exception->getMessage()]);
        } catch (DailyScheduleCsvImportStateException $exception) {
            $this->confirmApply = false;
            throw ValidationException::withMessages(['csvImport' => $exception->getMessage()]);
        }
    }

    public function render(): View
    {
        $company = $this->company();
        $batch = $this->batch();
        Gate::authorize('view', $batch);

        $activeImport = $this->activeImportId ? $this->importForCurrentBatch($this->activeImportId) : null;
        $rows = $activeImport
            ? $activeImport->rows()->with(['employmentRelationship.worker', 'existingDailyScheduleAssignment'])->paginate(10, ['*'], 'csvRowsPage')
            : null;

        return view('livewire.scheduling.daily-schedule-csv-import', [
            'batch' => $batch,
            'activeImport' => $activeImport,
            'rows' => $rows,
            'summary' => $activeImport ? $this->summaryFor($activeImport) : [],
            'canUpdate' => Gate::allows('update', $batch),
            'templateUrl' => route('scheduling.daily.csv.template', array_filter([
                'schedule_batch_id' => $batch->id,
                'worker_search' => $this->workerSearch,
                'organizational_unit_id' => $this->organizationalUnitId,
            ], fn ($value): bool => filled($value))),
        ]);
    }

    public function statusLabel(?string $status): string
    {
        return match ($status) {
            'uploaded' => 'Cargada',
            'validating' => 'Validando',
            'validated' => 'Validada',
            'invalid' => 'Con errores',
            'applying' => 'Aplicando',
            'applied' => 'Aplicada',
            'cancelled' => 'Cancelada',
            default => 'Registrada',
        };
    }

    public function dayTypeLabel(?string $dayType): string
    {
        return match ($dayType) {
            'shift' => 'Turno',
            'rest' => 'Descanso',
            'flexible' => 'Flexible',
            'on_call' => 'Guardia',
            'unassigned' => 'Pendiente',
            default => 'Sin resolver',
        };
    }

    public function rowActionLabel($row): string
    {
        if ($row->status === 'invalid') {
            return 'No aplicable';
        }

        if ($row->status === 'applied') {
            return 'Aplicada';
        }

        if ($row->status === 'skipped') {
            return 'Omitida';
        }

        $warnings = implode(' ', $row->warnings ?? []);
        if (str_contains($warnings, 'preservada')) {
            return 'Conservar existente';
        }

        if (str_contains($warnings, 'no cambia')) {
            return 'Sin cambio';
        }

        return $row->existing_daily_schedule_assignment_id ? 'Reemplazar' : 'Crear';
    }

    private function company()
    {
        $company = app(CurrentCompany::class)->get();
        abort_unless($company, 403);

        return $company;
    }

    private function batch(): ScheduleBatch
    {
        $company = $this->company();

        $batch = ScheduleBatch::query()
            ->where('company_id', $company->id)
            ->with(['company', 'center'])
            ->find($this->scheduleBatchId);

        abort_unless($batch, 403);

        return $batch;
    }

    private function activeImport(): ImportBatch
    {
        if (! $this->activeImportId) {
            throw new DailyScheduleCsvImportStateException('Selecciona una importacion.');
        }

        return $this->importForCurrentBatch($this->activeImportId);
    }

    private function importForCurrentBatch(int $importBatchId): ImportBatch
    {
        $company = $this->company();

        return ImportBatch::query()
            ->where('company_id', $company->id)
            ->where('import_type', 'daily_schedule')
            ->where('target_type', 'schedule_batch')
            ->where('target_id', $this->scheduleBatchId)
            ->with(['scheduleBatch.company', 'creator', 'validator', 'applier', 'canceller'])
            ->findOrFail($importBatchId);
    }

    /**
     * @return array<string, int>
     */
    private function summaryFor(ImportBatch $import): array
    {
        $rows = $import->rows()->get(['status', 'normalized_data', 'warnings', 'existing_daily_schedule_assignment_id']);
        $summary = [
            'create' => 0,
            'replace' => 0,
            'preserve' => 0,
            'no_change' => 0,
            'shift' => 0,
            'rest' => 0,
            'flexible' => 0,
            'on_call' => 0,
            'unassigned' => 0,
        ];

        foreach ($rows as $row) {
            $normalized = $row->normalized_data ?? [];
            $dayType = $normalized['assignment']['day_type'] ?? null;
            if ($dayType && array_key_exists($dayType, $summary)) {
                $summary[$dayType]++;
            }

            if (! in_array($row->status, ['valid', 'warning', 'applied', 'skipped'], true)) {
                continue;
            }

            $warnings = implode(' ', $row->warnings ?? []);
            if (str_contains($warnings, 'preservada')) {
                $summary['preserve']++;
            } elseif (str_contains($warnings, 'no cambia')) {
                $summary['no_change']++;
            } elseif ($row->existing_daily_schedule_assignment_id) {
                $summary['replace']++;
            } else {
                $summary['create']++;
            }
        }

        return $summary;
    }
}
