<?php

namespace App\Livewire\Workers;

use App\Domains\Tenancy\Support\CurrentCompany;
use App\Domains\Workers\Actions\ImportWorkerWorkbookAction;
use App\Domains\Workers\Actions\ReadWorkerWorkbookAction;
use App\Models\Worker;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class ImportWorkbook extends Component
{
    use WithFileUploads;

    public bool $showPanel = false;

    public mixed $file = null;

    #[Locked]
    public array $rows = [];

    #[Locked]
    public ?int $previewCompanyId = null;

    #[Locked]
    public string $previewHash = '';

    public function openPanel(): void
    {
        $this->company();
        $this->reset('file', 'rows', 'previewCompanyId', 'previewHash');
        $this->resetValidation();
        $this->showPanel = true;
    }

    public function updatedFile(): void
    {
        $this->reset('rows', 'previewCompanyId', 'previewHash');
        $this->resetValidation();
    }

    public function preview(ReadWorkerWorkbookAction $action): void
    {
        $company = $this->company();
        $this->reset('rows', 'previewCompanyId', 'previewHash');
        $this->validate(['file' => 'required|file|extensions:xls,xlsx|max:5120']);
        $this->rows = $action->handle(auth()->user(), $company, $this->file->getRealPath());
        $this->previewCompanyId = $company->id;
        $this->previewHash = hash_file('sha256', $this->file->getRealPath());
    }

    public function apply(ImportWorkerWorkbookAction $action): void
    {
        $company = $this->company();
        if ($this->previewCompanyId !== $company->id || ! $this->rows || collect($this->rows)->contains(fn ($row) => $row['errors'] !== [])) {
            throw ValidationException::withMessages(['file' => 'Genera una vista previa sin errores para la empresa activa.']);
        }
        $this->validate(['file' => 'required|file|extensions:xls,xlsx|max:5120']);
        $count = $action->handle(auth()->user(), $company, $this->file->getRealPath(), $this->previewHash);
        $this->file->delete();
        $this->reset('file', 'rows', 'previewCompanyId', 'previewHash', 'showPanel');
        session()->flash('status', "Se importaron {$count} trabajadores con sus cuentas vinculadas. En Usuarios puedes consultar sus accesos y establecer las contraseñas para entregarlas.");
        $this->redirectRoute('workers.index');
    }

    private function company()
    {
        $company = app(CurrentCompany::class)->get();
        abort_unless($company, 403);
        Gate::authorize('viewAny', [Worker::class, $company]);

        return $company;
    }

    public function render()
    {
        return view('livewire.workers.import-workbook');
    }
}
