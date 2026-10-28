<?php

use App\Domains\Tenancy\Support\CurrentCompany;
use App\Domains\Organization\Actions\ResolveUserOperationalScopeAction;
use App\Domains\Workers\Actions\BlockWorkerCredentialAction;
use App\Domains\Workers\Actions\CreateOrUpdateWorkerCredentialAction;
use App\Domains\Workers\Actions\DeleteWorkerIfUnusedAction;
use App\Domains\Workers\Actions\ResetWorkerCredentialPinAction;
use App\Domains\Workers\Actions\SaveWorkerWithEmploymentRelationshipAction;
use App\Domains\Workers\Actions\TerminateWorkerAction;
use App\Models\EmploymentRelationship;
use App\Models\Worker;
use App\Models\WorkerCredential;
use App\Support\RoleKey;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component {
    public array $form = [];
    public array $credentialForm = [];
    public bool $showFormPanel = false;
    public ?int $editingWorkerId = null;
    public string $statusFilter = '';
    public string $search = '';

    public function mount(): void
    {
        $this->form = $this->emptyForm();
        $this->credentialForm = $this->emptyCredentialForm();
    }

    public function openCreatePanel(CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('viewAny', [Worker::class, $company]);

        $this->editingWorkerId = null;
        $this->resetWorkerForms();
        $this->resetValidation();
        $this->showFormPanel = true;
    }

    public function loadEditForm(int $workerId, CurrentCompany $currentCompany): void
    {
        $this->resetWorkerForms();
        $this->resetValidation();

        $worker = $this->authorizedWorker($workerId, $currentCompany);
        $relationship = $worker->activeEmploymentRelationship;
        $credential = $worker->credential;

        $this->editingWorkerId = $worker->id;
        $this->form = [
            'employee_code' => $worker->employee_code,
            'full_name' => $worker->full_name,
            'rfc' => $worker->rfc ?? '',
            'center_id' => $relationship?->center_id ? (string) $relationship->center_id : '',
            'started_at' => $relationship?->started_at?->format('Y-m-d') ?? now()->toDateString(),
            'status' => $worker->status,
            'relationship_change_reason' => '',
        ];
        $this->credentialForm = [
            'access_code' => $credential?->access_code ?? $worker->employee_code,
            'temporal_pin' => '',
            'status' => $credential?->status ?? 'active',
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
            'form.rfc' => ['nullable', 'string', 'max:20'],
            'form.center_id' => [
                'required',
                Rule::exists('centers', 'id')
                    ->where('company_id', $company->id)
                    ->where('status', 'active'),
            ],
            'form.started_at' => ['required', 'date'],
            'form.status' => ['required', Rule::in(['active', 'inactive', 'terminated', 'suspended'])],
            'form.relationship_change_reason' => ['nullable', 'string', 'max:500'],
        ])['form'];

        $center = $company->centers()
            ->whereKey($validated['center_id'])
            ->where('status', 'active')
            ->firstOrFail();

        $worker
            ? Gate::authorize('update', $worker)
            : Gate::authorize('createForCenter', [Worker::class, $company, $center]);

        Gate::authorize('create', [EmploymentRelationship::class, $company, $center]);

        if ($worker && $relationship = $worker->activeEmploymentRelationship()->first()) {
            Gate::authorize('update', $relationship);
        }

        try {
            $action->handle($company, $worker, $center, $validated, auth()->user());
        } catch (\InvalidArgumentException $exception) {
            $this->addError('form.started_at', $exception->getMessage());
            $this->addError('form.relationship_change_reason', $exception->getMessage());

            return;
        }

        $this->showFormPanel = false;
        $this->editingWorkerId = null;
        $this->resetWorkerForms();
        $this->resetValidation();

        Session::flash('status', $worker ? 'Trabajador actualizado.' : 'Trabajador creado.');
    }

    public function delete(int $workerId, CurrentCompany $currentCompany, DeleteWorkerIfUnusedAction $action): void
    {
        $worker = $this->authorizedWorker($workerId, $currentCompany);

        Gate::authorize('delete', $worker);

        try {
            $action->handle($worker);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['worker' => $exception->getMessage()]);
        }

        Session::flash('status', 'Trabajador eliminado.');
    }

    public function saveCredential(
        CurrentCompany $currentCompany,
        CreateOrUpdateWorkerCredentialAction $action,
    ): void {
        $worker = $this->authorizedWorker($this->editingWorkerId ?? 0, $currentCompany);
        $company = $worker->company;
        $credential = $worker->credential;

        $credential
            ? Gate::authorize('update', $credential)
            : Gate::authorize('create', [WorkerCredential::class, $company, $worker]);

        try {
            $validated = $this->validate([
                'credentialForm.access_code' => [
                    'required',
                    'string',
                    'max:50',
                    Rule::unique('worker_credentials', 'access_code')
                        ->where('company_id', $company->id)
                        ->ignore($credential?->id),
                ],
                'credentialForm.temporal_pin' => [$credential ? 'nullable' : 'required', 'string', 'min:4', 'max:50'],
                'credentialForm.status' => ['required', Rule::in(['active', 'blocked', 'reset_required'])],
            ])['credentialForm'];

            try {
                $action->handle($company, $worker, $validated);
            } catch (\InvalidArgumentException $exception) {
                $this->addError('credentialForm.temporal_pin', $exception->getMessage());

                return;
            }

            Session::flash('status', 'Credencial guardada.');
        } finally {
            $this->clearCredentialTemporalPin();
        }

    }

    public function resetCredentialPin(
        CurrentCompany $currentCompany,
        ResetWorkerCredentialPinAction $action,
    ): void {
        $worker = $this->authorizedWorker($this->editingWorkerId ?? 0, $currentCompany);
        $credential = $worker->credential;

        abort_unless($credential, 404);

        Gate::authorize('reset', $credential);

        try {
            $validated = $this->validate([
                'credentialForm.temporal_pin' => ['required', 'string', 'min:4', 'max:50'],
            ])['credentialForm'];

            try {
                $action->handle($credential, $validated['temporal_pin']);
            } catch (\InvalidArgumentException $exception) {
                $this->addError('credentialForm.temporal_pin', $exception->getMessage());

                return;
            }

            Session::flash('status', 'NIP temporal actualizado.');
        } finally {
            $this->clearCredentialTemporalPin();
        }
    }

    public function blockCredential(
        CurrentCompany $currentCompany,
        BlockWorkerCredentialAction $action,
    ): void {
        $worker = $this->authorizedWorker($this->editingWorkerId ?? 0, $currentCompany);
        $credential = $worker->credential;

        abort_unless($credential, 404);

        Gate::authorize('block', $credential);

        $action->handle($credential);
        $this->credentialForm['status'] = 'blocked';

        Session::flash('status', 'Credencial bloqueada.');
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
        $this->resetWorkerForms();
        $this->resetValidation('form');
        $this->resetValidation('credentialForm');
    }

    private function resetWorkerForms(): void
    {
        $this->form = $this->emptyForm();
        $this->credentialForm = $this->emptyCredentialForm();
        $this->clearCredentialTemporalPin();
    }

    public function with(CurrentCompany $currentCompany, ResolveUserOperationalScopeAction $resolveUserScope): array
    {
        $company = $currentCompany->get();

        abort_unless($company, 403);

        Gate::authorize('viewAny', [Worker::class, $company]);
        $scope = in_array(auth()->user()->roleKeyForCompany($company), RoleKey::scopedOperators(), true)
            ? $resolveUserScope->handle($company, auth()->user(), now()->toDateString())
            : null;

        return [
            'workers' => $company->workers()
                ->with([
                    'activeEmploymentRelationship.center',
                    'credential',
                ])
                ->when($scope !== null, function ($query) use ($scope): void {
                    $query->whereHas('activeEmploymentRelationship', function ($relationshipQuery) use ($scope): void {
                        $relationshipQuery->where(function ($scopeQuery) use ($scope): void {
                            $scopeQuery
                                ->whereIn('center_id', $scope['center_ids'])
                                ->orWhereHas('employmentUnitAssignments', fn ($unitQuery) => $unitQuery
                                    ->where('status', 'active')
                                    ->whereIn('organizational_unit_id', $scope['organizational_unit_ids']));
                        });
                    });
                })
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
                ->when($scope !== null, function ($query) use ($scope): void {
                    $query->where(function ($scopeQuery) use ($scope): void {
                        $scopeQuery
                            ->whereIn('id', $scope['center_ids'])
                            ->orWhereHas('organizationalUnits', fn ($unitQuery) => $unitQuery->whereIn('id', $scope['organizational_unit_ids']));
                    });
                })
                ->orderBy('name')
                ->get(),
            'currentCompany' => $company,
            'canManageWorkers' => Gate::allows('viewAny', [Worker::class, $company]),
            'editingWorker' => $this->editingWorkerId
                ? $company->workers()
                    ->with([
                        'credential',
                        'activeEmploymentRelationship.center',
                    ])
                    ->find($this->editingWorkerId)
                : null,
        ];
    }

    private function authorizedWorker(int $workerId, CurrentCompany $currentCompany): Worker
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        $worker = $company->workers()
            ->with([
                'credential',
                'activeEmploymentRelationship.center',
            ])
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
            'rfc' => '',
            'center_id' => '',
            'started_at' => now()->toDateString(),
            'status' => 'active',
            'relationship_change_reason' => '',
        ];
    }

    private function emptyCredentialForm(): array
    {
        return [
            'access_code' => '',
            'temporal_pin' => '',
            'status' => 'active',
        ];
    }

    private function clearCredentialTemporalPin(): void
    {
        $this->credentialForm['temporal_pin'] = '';
    }
}; ?>

<section class="w-full space-y-8 bg-surface-bg p-6 text-surface-text">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="font-display text-2xl font-bold text-brand-navy">Trabajadores</h1>
            <p class="mt-1.5 text-[13.5px] text-surface-muted">Administra trabajadores y su relación laboral inicial en la empresa activa.</p>
        </div>

        @if ($canManageWorkers)
            <button type="button" class="btn-primary" wire:click="openCreatePanel">
                <span class="text-base leading-none">+</span>
                Nuevo trabajador
            </button>
        @endif
    </div>

    @if (session('status'))
        <div class="rounded-xl border border-status-rest-line bg-status-rest-bg px-4 py-3 text-sm font-medium text-status-rest-text">
            {{ session('status') }}
        </div>
    @endif

    @error('worker')
        <div class="rounded-xl border border-status-pending-line bg-status-pending-bg px-4 py-3 text-sm font-medium text-status-pending-text">
            {{ $message }}
        </div>
    @enderror

    <section class="rounded-2xl border border-surface-line bg-surface-card p-6 shadow-[0_20px_50px_-34px_rgba(2,25,57,0.22)]">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="font-display text-lg font-bold text-brand-navy">Trabajadores de {{ $currentCompany->name }}</h2>
                <p class="mt-1 text-[13px] text-surface-muted">Solo se muestran trabajadores asociados a la empresa activa.</p>
            </div>

            <div class="grid gap-3 sm:grid-cols-[minmax(0,220px)_160px]">
                <flux:input wire:model.live.debounce.300ms="search" label="Buscar" placeholder="Código, nombre o RFC" />

                <div>
                    <label class="form-label">Estado del trabajador</label>
                    <x-ui.select wire:model.live="statusFilter" class="h-10 border-primary-border dark:border-primary-border">
                        <option value="">Todos</option>
                        <option value="active">Activo</option>
                        <option value="inactive">Inactivo</option>
                        <option value="terminated">Baja</option>
                        <option value="suspended">Suspendido</option>
                    </x-ui.select>
                </div>
            </div>
        </div>
        <div class="table-wrap mt-5">
        <table class="w-full border-collapse">
            <thead>
                <tr>
                    <th class="table-head-cell">Código</th>
                    <th class="table-head-cell">Nombre</th>
                    <th class="table-head-cell">Centro actual</th>
                    <th class="table-head-cell">Credencial</th>
                    <th class="table-head-cell">Estado</th>
                    <th class="table-head-cell text-right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($workers as $worker)
                    @php($relationship = $worker->activeEmploymentRelationship)
                    @php($credential = $worker->credential)
                    <tr class="table-row">
                        <td class="table-cell font-mono text-brand-navy">{{ $worker->employee_code }}</td>
                        <td class="table-cell font-semibold text-brand-navy">{{ $worker->full_name }}</td>
                        <td class="table-cell text-surface-muted">{{ $relationship?->center?->name ?? 'Sin centro activo' }}</td>
                        <td class="table-cell">
                            <span class="{{ $credential?->status === 'active' ? 'badge-success' : ($credential?->status === 'reset_required' ? 'badge-warn' : ($credential?->status === 'blocked' ? 'badge-danger' : 'badge-muted')) }}">
                                <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                                {{ $credential?->status === 'active' ? 'Activa' : ($credential?->status === 'reset_required' ? 'Requiere reinicio' : ($credential?->status === 'blocked' ? 'Bloqueada' : 'Sin credencial')) }}
                            </span>
                        </td>
                        <td class="table-cell">
                            <span class="{{ $worker->status === 'active' ? 'badge-success' : ($worker->status === 'terminated' ? 'badge-danger' : ($worker->status === 'suspended' ? 'badge-warn' : 'badge-muted')) }}">
                                <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                                {{ $worker->status }}
                            </span>
                        </td>
                        <td class="table-cell">
                            <div class="flex justify-end gap-1.5">
                                <button type="button" class="btn-icon" wire:click="loadEditForm({{ $worker->id }})" aria-label="Editar trabajador" title="Editar">
                                    <svg class="h-4 w-4 text-surface-muted" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </button>

                                @if ($worker->status !== 'terminated')
                                    <button type="button" class="btn-icon" wire:click="terminate({{ $worker->id }})" aria-label="Dar de baja" title="Baja">
                                        <svg class="h-4 w-4 text-status-warn-text" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M18.36 5.64 5.64 18.36M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </button>
                                @endif
                                <button type="button" class="btn-icon" wire:confirm="Eliminar este trabajador solo si no tiene horarios ni asistencias? Esta accion no se puede deshacer." wire:click="delete({{ $worker->id }})" aria-label="Eliminar trabajador" title="Eliminar">
                                    <svg class="h-4 w-4 text-status-pending-text" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6h16Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="table-cell py-8 text-center text-surface-muted">
                            Aún no hay trabajadores registrados para esta empresa.
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
            max-width="max-w-2xl"
        >
            <form wire:submit="save" class="flex min-h-0 flex-1 flex-col">
                <div class="flex-1 space-y-5 overflow-y-auto bg-slate-50/70 p-4 sm:p-6">
                    <section class="space-y-4 rounded-2xl border border-surface-line bg-white p-5">
                        <x-ui.panel-heading title="Identificación" description="Datos del trabajador dentro de la empresa." tone="blue" />
                        <flux:input wire:model="form.full_name" label="Nombre completo" required />
                        <div class="grid gap-4 sm:grid-cols-2">
                            <flux:input wire:model="form.employee_code" label="Código interno" required />
                            <flux:input wire:model="form.rfc" label="RFC" />
                        </div>
                    </section>

                    <section class="space-y-4 rounded-2xl border border-surface-line bg-white p-5">
                        <x-ui.panel-heading title="Datos laborales" description="Centro, ingreso y estado del trabajador." tone="indigo" />
                        <div>
                            <label class="form-label">Centro</label>
                            <select wire:model.live="form.center_id" class="form-select">
                                <option value="">Selecciona un centro</option>
                                @foreach ($centers as $center)
                                    <option value="{{ $center->id }}">{{ $center->code }} - {{ $center->name }}</option>
                                @endforeach
                            </select>
                            @error('form.center_id')
                                <p class="form-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <flux:input wire:model.live="form.started_at" label="Fecha de ingreso" type="date" required />

                        @if ($editingWorkerId && $editingWorker?->activeEmploymentRelationship && (
                            (string) ($form['center_id'] ?? '') !== (string) $editingWorker->activeEmploymentRelationship->center_id
                            || ($form['started_at'] ?? '') !== $editingWorker->activeEmploymentRelationship->started_at?->format('Y-m-d')
                        ))
                            <flux:textarea
                                wire:model="form.relationship_change_reason"
                                label="Motivo del cambio laboral"
                                placeholder="Obligatorio si cambias centro o fecha de ingreso."
                                rows="3"
                            />
                        @endif

                        <div>
                            <label class="form-label">Estado del trabajador</label>
                            <select wire:model="form.status" class="form-select">
                                <option value="active">Activo</option>
                                <option value="inactive">Inactivo</option>
                                <option value="suspended">Suspendido</option>
                                <option value="terminated">Baja</option>
                            </select>
                            @error('form.status')
                                <p class="form-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </section>

                    @if ($editingWorkerId)
                        @php($credential = $editingWorker?->credential)

                        <section class="space-y-4 rounded-2xl border border-surface-line bg-white p-5">
                            <x-ui.panel-heading title="Credencial kiosco" description="Acceso para registrar entradas y salidas." tone="emerald">
                                    <p class="mt-2 text-xs font-medium text-surface-muted">{{ match ($credential?->status) { 'active' => 'Activa', 'blocked' => 'Bloqueada', 'reset_required' => 'Requiere restablecer NIP', default => 'Sin credencial creada' } }}</p>
                                </x-ui.panel-heading>

                            <div class="space-y-4">
                                <flux:input wire:model="credentialForm.access_code" label="Código de acceso" required />
                                <flux:input wire:model="credentialForm.temporal_pin" label="NIP temporal" type="password" />

                                <div>
                                    <label class="form-label">Estado credencial</label>
                                    <select wire:model="credentialForm.status" class="form-select">
                                        <option value="active">Activa</option>
                                        <option value="blocked">Bloqueada</option>
                                        <option value="reset_required">Requiere reset</option>
                                    </select>
                                    @error('credentialForm.status')
                                        <p class="form-error">{{ $message }}</p>
                                    @enderror
                                </div>

                                @error('credentialForm.access_code')
                                    <p class="form-error">{{ $message }}</p>
                                @enderror
                                @error('credentialForm.temporal_pin')
                                    <p class="form-error">{{ $message }}</p>
                                @enderror

                                <div class="flex flex-wrap justify-end gap-2">
                                    <button type="button" class="btn-primary btn-sm" wire:click="saveCredential">Guardar credencial</button>
                                    @if ($credential)
                                        <button type="button" class="btn-outline btn-sm" wire:click="resetCredentialPin">Reset NIP</button>
                                        @if ($credential->status !== 'blocked')
                                            <button type="button" class="btn-danger btn-sm" wire:click="blockCredential">Bloquear</button>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </section>
                    @endif
                </div>

                <div class="flex shrink-0 justify-end gap-3 border-t border-surface-line bg-white px-5 py-4">
                    <button type="button" class="btn-ghost" wire:click="closeFormPanel">Cancelar</button>
                    <button type="submit" class="btn-primary">Guardar trabajador</button>
                </div>
            </form>
        </x-side-panel>
    @endif
</section>
