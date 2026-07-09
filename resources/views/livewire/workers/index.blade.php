<?php

use App\Domains\Tenancy\Support\CurrentCompany;
use App\Domains\Workers\Actions\SaveWorkerWithEmploymentRelationshipAction;
use App\Domains\Workers\Actions\TerminateWorkerAction;
use App\Models\EmploymentRelationship;
use App\Models\Worker;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component {
    public array $form = [];
    public bool $showFormPanel = false;
    public ?int $editingWorkerId = null;
    public string $statusFilter = '';
    public string $search = '';

    public function mount(): void
    {
        $this->form = $this->emptyForm();
    }

    public function openCreatePanel(CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('create', [Worker::class, $company]);

        $this->editingWorkerId = null;
        $this->form = $this->emptyForm();
        $this->showFormPanel = true;
    }

    public function loadEditForm(int $workerId, CurrentCompany $currentCompany): void
    {
        $worker = $this->authorizedWorker($workerId, $currentCompany);
        $relationship = $worker->activeEmploymentRelationship;

        $this->editingWorkerId = $worker->id;
        $this->form = [
            'employee_code' => $worker->employee_code,
            'full_name' => $worker->full_name,
            'email' => $worker->email ?? '',
            'phone' => $worker->phone ?? '',
            'rfc' => $worker->rfc ?? '',
            'curp' => $worker->curp ?? '',
            'center_id' => $relationship?->center_id ? (string) $relationship->center_id : '',
            'position_name' => $relationship?->position_name ?? '',
            'started_at' => $relationship?->started_at?->format('Y-m-d') ?? now()->toDateString(),
            'status' => $worker->status,
        ];
        $this->showFormPanel = true;
    }

    public function save(
        CurrentCompany $currentCompany,
        SaveWorkerWithEmploymentRelationshipAction $action,
    ): void {
        $company = $this->currentCompanyOrFail($currentCompany);

        $worker = $this->editingWorkerId
            ? $this->authorizedWorker($this->editingWorkerId, $currentCompany)
            : null;

        $worker
            ? Gate::authorize('update', $worker)
            : Gate::authorize('create', [Worker::class, $company]);

        $validated = $this->validate([
            'form.employee_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('workers', 'employee_code')
                    ->where('company_id', $company->id)
                    ->ignore($worker?->id),
            ],
            'form.full_name' => ['required', 'string', 'max:255'],
            'form.email' => ['nullable', 'email', 'max:255'],
            'form.phone' => ['nullable', 'string', 'max:50'],
            'form.rfc' => ['nullable', 'string', 'max:20'],
            'form.curp' => ['nullable', 'string', 'max:30'],
            'form.center_id' => [
                'required',
                Rule::exists('centers', 'id')
                    ->where('company_id', $company->id)
                    ->where('status', 'active'),
            ],
            'form.position_name' => ['nullable', 'string', 'max:255'],
            'form.started_at' => ['required', 'date'],
            'form.status' => ['required', Rule::in(['active', 'inactive', 'terminated', 'suspended'])],
        ])['form'];

        $center = $company->centers()
            ->whereKey($validated['center_id'])
            ->where('status', 'active')
            ->firstOrFail();

        Gate::authorize('create', [EmploymentRelationship::class, $company, $center]);

        if ($worker && $relationship = $worker->activeEmploymentRelationship()->first()) {
            Gate::authorize('update', $relationship);
        }

        try {
            $action->handle($company, $worker, $center, $validated);
        } catch (\InvalidArgumentException $exception) {
            $this->addError('form.started_at', $exception->getMessage());

            return;
        }

        $this->showFormPanel = false;
        $this->editingWorkerId = null;
        $this->form = $this->emptyForm();

        Session::flash('status', $worker ? 'Trabajador actualizado.' : 'Trabajador creado.');
    }

    public function terminate(
        int $workerId,
        CurrentCompany $currentCompany,
        TerminateWorkerAction $action,
    ): void {
        $worker = $this->authorizedWorker($workerId, $currentCompany);

        Gate::authorize('terminate', $worker);

        $action->handle($worker);

        Session::flash('status', 'Trabajador dado de baja.');
    }

    public function closeFormPanel(): void
    {
        $this->showFormPanel = false;
        $this->editingWorkerId = null;
        $this->resetValidation('form');
    }

    public function with(CurrentCompany $currentCompany): array
    {
        $company = $currentCompany->get();

        abort_unless($company, 403);

        Gate::authorize('viewAny', [Worker::class, $company]);

        return [
            'workers' => $company->workers()
                ->with('activeEmploymentRelationship.center')
                ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
                ->when($this->search !== '', function ($query): void {
                    $search = '%'.$this->search.'%';

                    $query->where(function ($query) use ($search): void {
                        $query
                            ->where('employee_code', 'like', $search)
                            ->orWhere('full_name', 'like', $search)
                            ->orWhere('rfc', 'like', $search);
                    });
                })
                ->orderBy('full_name')
                ->get(),
            'centers' => $company->centers()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(),
            'currentCompany' => $company,
            'canManageWorkers' => Gate::allows('create', [Worker::class, $company]),
        ];
    }

    private function authorizedWorker(int $workerId, CurrentCompany $currentCompany): Worker
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        $worker = $company->workers()
            ->with('activeEmploymentRelationship.center')
            ->whereKey($workerId)
            ->firstOrFail();

        Gate::authorize('update', $worker);

        return $worker;
    }

    private function currentCompanyOrFail(CurrentCompany $currentCompany)
    {
        $company = $currentCompany->get();

        abort_unless($company, 403);

        return $company;
    }

    private function emptyForm(): array
    {
        return [
            'employee_code' => '',
            'full_name' => '',
            'email' => '',
            'phone' => '',
            'rfc' => '',
            'curp' => '',
            'center_id' => '',
            'position_name' => '',
            'started_at' => now()->toDateString(),
            'status' => 'active',
        ];
    }
}; ?>

<section class="w-full space-y-8 p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">Trabajadores</flux:heading>
            <flux:subheading>Administra trabajadores y su relacion laboral inicial en la empresa activa.</flux:subheading>
        </div>

        @if ($canManageWorkers)
            <flux:button type="button" variant="primary" wire:click="openCreatePanel">
                Nuevo trabajador
            </flux:button>
        @endif
    </div>

    @if (session('status'))
        <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
            {{ session('status') }}
        </div>
    @endif

    <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <flux:heading>Trabajadores de {{ $currentCompany->name }}</flux:heading>
                <flux:subheading>Solo se muestran trabajadores asociados a la empresa activa.</flux:subheading>
            </div>

            <div class="grid gap-3 sm:grid-cols-[minmax(0,220px)_160px]">
                <flux:input wire:model.live.debounce.300ms="search" label="Buscar" placeholder="Codigo, nombre o RFC" />

                <div>
                    <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Estado</label>
                    <select wire:model.live="statusFilter" class="w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">
                        <option value="">Todos</option>
                        <option value="active">Activo</option>
                        <option value="inactive">Inactivo</option>
                        <option value="terminated">Baja</option>
                        <option value="suspended">Suspendido</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-md border border-zinc-200 dark:border-zinc-700">
            <table class="w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 text-left text-xs font-medium uppercase text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                    <tr>
                        <th class="px-4 py-3">Codigo</th>
                        <th class="px-4 py-3">Nombre</th>
                        <th class="px-4 py-3">Centro actual</th>
                        <th class="px-4 py-3">Puesto</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($workers as $worker)
                        @php($relationship = $worker->activeEmploymentRelationship)
                        <tr>
                            <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">{{ $worker->employee_code }}</td>
                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ $worker->full_name }}</td>
                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ $relationship?->center?->name ?? 'Sin centro activo' }}</td>
                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ $relationship?->position_name ?: 'Sin puesto' }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-zinc-100 px-2 py-1 text-xs font-medium uppercase text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                    {{ $worker->status }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <flux:button type="button" size="sm" wire:click="loadEditForm({{ $worker->id }})">
                                        Editar
                                    </flux:button>

                                    @if ($worker->status !== 'terminated')
                                        <flux:button type="button" size="sm" variant="danger" wire:click="terminate({{ $worker->id }})">
                                            Baja
                                        </flux:button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-sm text-zinc-500 dark:text-zinc-400">
                                Aun no hay trabajadores registrados para esta empresa.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($canManageWorkers)
        <x-side-panel
            wire:model="showFormPanel"
            :title="$editingWorkerId ? 'Editar trabajador' : 'Nuevo trabajador'"
            subheading="Los datos aplican solo a la empresa activa."
            labelledby="worker-form-title"
        >
            <form wire:submit="save" class="flex flex-1 flex-col overflow-y-auto">
                <div class="flex-1 space-y-4 p-6">
                    <flux:input wire:model="form.employee_code" label="Codigo interno" required />
                    <flux:input wire:model="form.full_name" label="Nombre completo" required />
                    <flux:input wire:model="form.email" label="Email" type="email" />
                    <flux:input wire:model="form.phone" label="Telefono" />
                    <flux:input wire:model="form.rfc" label="RFC" />
                    <flux:input wire:model="form.curp" label="CURP" />

                    <div>
                        <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Centro</label>
                        <select wire:model="form.center_id" class="w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">
                            <option value="">Selecciona un centro</option>
                            @foreach ($centers as $center)
                                <option value="{{ $center->id }}">{{ $center->code }} - {{ $center->name }}</option>
                            @endforeach
                        </select>
                        @error('form.center_id')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <flux:input wire:model="form.position_name" label="Puesto" />
                    <flux:input wire:model="form.started_at" label="Fecha de ingreso" type="date" required />

                    <div>
                        <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Estado</label>
                        <select wire:model="form.status" class="w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">
                            <option value="active">Activo</option>
                            <option value="inactive">Inactivo</option>
                            <option value="suspended">Suspendido</option>
                            <option value="terminated">Baja</option>
                        </select>
                        @error('form.status')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t border-zinc-200 p-6 dark:border-zinc-700">
                    <flux:button type="button" variant="ghost" wire:click="closeFormPanel">
                        Cancelar
                    </flux:button>
                    <flux:button type="submit" variant="primary">
                        Guardar trabajador
                    </flux:button>
                </div>
            </form>
        </x-side-panel>
    @endif
</section>
