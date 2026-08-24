<?php

use App\Domains\Tenancy\Support\CurrentCompany;
use App\Domains\Organization\Actions\ResolveUserOperationalScopeAction;
use App\Domains\Workers\Actions\BlockWorkerCredentialAction;
use App\Domains\Workers\Actions\CreateOrReplaceLaborConditionAction;
use App\Domains\Workers\Actions\CreateOrUpdateWorkerCredentialAction;
use App\Domains\Workers\Actions\DeleteWorkerIfUnusedAction;
use App\Domains\Workers\Actions\ResetWorkerCredentialPinAction;
use App\Domains\Workers\Actions\SaveWorkerWithEmploymentRelationshipAction;
use App\Domains\Workers\Actions\TerminateWorkerAction;
use App\Models\EmploymentRelationship;
use App\Models\LaborCondition;
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
    public array $conditionForm = [];
    public array $credentialForm = [];
    public bool $showFormPanel = false;
    public ?int $editingWorkerId = null;
    public string $statusFilter = '';
    public string $search = '';

    public function mount(): void
    {
        $this->form = $this->emptyForm();
        $this->conditionForm = $this->emptyConditionForm();
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
        $condition = $relationship?->activeLaborCondition;
        $credential = $worker->credential;

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
            'relationship_change_reason' => '',
        ];
        $this->conditionForm = [
            'work_modality' => $condition?->work_modality ?? 'onsite',
            'weekly_hours' => $condition?->weekly_hours ?? '',
            'rest_day_of_week' => $condition?->rest_day_of_week ?? '',
            'effective_from' => $condition?->effective_from?->format('Y-m-d') ?? now()->toDateString(),
            'effective_to' => $condition?->effective_to?->format('Y-m-d') ?? '',
            'status' => $condition?->status ?? 'active',
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

    public function saveLaborCondition(
        CurrentCompany $currentCompany,
        CreateOrReplaceLaborConditionAction $action,
    ): void {
        $worker = $this->authorizedWorker($this->editingWorkerId ?? 0, $currentCompany);
        $relationship = $worker->activeEmploymentRelationship;

        abort_unless($relationship, 422);

        Gate::authorize('create', [LaborCondition::class, $relationship->company, $relationship]);

        if ($condition = $relationship->activeLaborCondition()->first()) {
            Gate::authorize('update', $condition);
        }

        $validated = $this->validate([
            'conditionForm.work_modality' => ['required', Rule::in(['onsite', 'hybrid', 'remote', 'field'])],
            'conditionForm.weekly_hours' => ['nullable', 'numeric', 'min:0', 'max:168'],
            'conditionForm.rest_day_of_week' => ['nullable', 'integer', 'between:0,6'],
            'conditionForm.effective_from' => ['required', 'date'],
            'conditionForm.effective_to' => ['nullable', 'date', 'after_or_equal:conditionForm.effective_from'],
            'conditionForm.status' => ['required', Rule::in(['active', 'inactive', 'replaced'])],
        ])['conditionForm'];

        try {
            $action->handle($relationship->company, $worker, $relationship, $validated);
        } catch (\InvalidArgumentException $exception) {
            $this->addError('conditionForm.effective_from', $exception->getMessage());

            return;
        }

        $this->conditionForm = $this->emptyConditionForm();

        Session::flash('status', 'Condicion laboral guardada.');
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
        $this->resetValidation('conditionForm');
        $this->resetValidation('credentialForm');
    }

    private function resetWorkerForms(): void
    {
        $this->form = $this->emptyForm();
        $this->conditionForm = $this->emptyConditionForm();
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
                    'activeEmploymentRelationship.activeLaborCondition',
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
                        'activeEmploymentRelationship.activeLaborCondition',
                        'activeEmploymentRelationship.laborConditions' => fn ($query) => $query->orderByDesc('effective_from'),
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
                'activeEmploymentRelationship.activeLaborCondition',
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
            'email' => '',
            'phone' => '',
            'rfc' => '',
            'curp' => '',
            'center_id' => '',
            'position_name' => '',
            'started_at' => now()->toDateString(),
            'status' => 'active',
            'relationship_change_reason' => '',
        ];
    }

    private function emptyConditionForm(): array
    {
        return [
            'work_modality' => 'onsite',
            'weekly_hours' => '',
            'rest_day_of_week' => '',
            'effective_from' => now()->toDateString(),
            'effective_to' => '',
            'status' => 'active',
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
                    <label class="form-label">Estado</label>
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
                    <th class="table-head-cell">Puesto</th>
                    <th class="table-head-cell">Condición</th>
                    <th class="table-head-cell">Credencial</th>
                    <th class="table-head-cell">Estado</th>
                    <th class="table-head-cell text-right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($workers as $worker)
                    @php($relationship = $worker->activeEmploymentRelationship)
                    @php($condition = $relationship?->activeLaborCondition)
                    @php($credential = $worker->credential)
                    <tr class="table-row">
                        <td class="table-cell font-mono text-brand-navy">{{ $worker->employee_code }}</td>
                        <td class="table-cell font-semibold text-brand-navy">{{ $worker->full_name }}</td>
                        <td class="table-cell text-surface-muted">{{ $relationship?->center?->name ?? 'Sin centro activo' }}</td>
                        <td class="table-cell text-surface-muted">{{ $relationship?->position_name ?: 'Sin puesto' }}</td>
                        <td class="table-cell text-surface-muted">{{ $condition?->work_modality ?? 'Sin condición' }}</td>
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
                        <td colspan="8" class="table-cell py-8 text-center text-surface-muted">
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
        >
            <form wire:submit="save" class="flex flex-1 flex-col overflow-y-auto">
                <div class="flex-1 space-y-5 p-6">
                    <section class="space-y-4 rounded-2xl border border-surface-line bg-[#F7F9FC] p-4 shadow-[0_14px_35px_-28px_rgba(2,25,57,0.22)]">
                        <div>
                            <flux:heading size="sm">Datos generales</flux:heading>
                            <flux:subheading>Identificación y relación laboral base.</flux:subheading>
                        </div>

                        <flux:input wire:model="form.employee_code" label="Código interno" required />
                        <flux:input wire:model="form.full_name" label="Nombre completo" required />
                        <flux:input wire:model="form.email" label="Email" type="email" />
                        <flux:input wire:model="form.phone" label="Teléfono" />
                        <flux:input wire:model="form.rfc" label="RFC" />
                        <flux:input wire:model="form.curp" label="CURP" />

                        <div>
                            <label class="form-label">Centro</label>
                            <select wire:model="form.center_id" class="form-select">
                                <option value="">Selecciona un centro</option>
                                @foreach ($centers as $center)
                                    <option value="{{ $center->id }}">{{ $center->code }} - {{ $center->name }}</option>
                                @endforeach
                            </select>
                            @error('form.center_id')
                                <p class="form-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <flux:input wire:model="form.position_name" label="Puesto" />
                        <flux:input wire:model="form.started_at" label="Fecha de ingreso" type="date" required />

                        @if ($editingWorkerId)
                            <flux:textarea
                                wire:model="form.relationship_change_reason"
                                label="Motivo del cambio laboral"
                                placeholder="Obligatorio si cambias centro, puesto o fecha de ingreso."
                                rows="3"
                            />
                        @endif

                        <div>
                            <label class="form-label">Estado</label>
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
                        @php($relationship = $editingWorker?->activeEmploymentRelationship)
                        @php($activeCondition = $relationship?->activeLaborCondition)
                        @php($credential = $editingWorker?->credential)

                        <section class="space-y-4 rounded-2xl border border-surface-line bg-surface-card p-4 shadow-[0_14px_35px_-28px_rgba(2,25,57,0.22)]">
                            <div class="mb-4">
                                <flux:heading size="sm">Condición laboral vigente</flux:heading>
                                <flux:subheading>
                                    {{ $activeCondition ? $activeCondition->work_modality.' desde '.$activeCondition->effective_from->format('Y-m-d') : 'Sin condición activa' }}
                                </flux:subheading>
                            </div>

                            @if ($relationship)
                                <div class="space-y-4">
                                    <div>
                                        <label class="form-label">Modalidad</label>
                                        <select wire:model="conditionForm.work_modality" class="form-select">
                                            <option value="onsite">Presencial</option>
                                            <option value="hybrid">Híbrido</option>
                                            <option value="remote">Remoto</option>
                                            <option value="field">Campo</option>
                                        </select>
                                        @error('conditionForm.work_modality')
                                            <p class="form-error">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <flux:input wire:model="conditionForm.weekly_hours" label="Horas semanales" type="number" step="0.5" min="0" />

                                        <div>
                                            <label class="form-label">Día de descanso</label>
                                            <select wire:model="conditionForm.rest_day_of_week" class="form-select">
                                                <option value="">Sin definir</option>
                                                <option value="0">Domingo</option>
                                                <option value="1">Lunes</option>
                                                <option value="2">Martes</option>
                                                <option value="3">Miércoles</option>
                                                <option value="4">Jueves</option>
                                                <option value="5">Viernes</option>
                                                <option value="6">Sábado</option>
                                            </select>
                                            @error('conditionForm.rest_day_of_week')
                                                <p class="form-error">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <flux:input wire:model="conditionForm.effective_from" label="Vigente desde" type="date" required />
                                        <flux:input wire:model="conditionForm.effective_to" label="Vigente hasta" type="date" />
                                    </div>

                                    <div>
                                        <label class="form-label">Estado condición</label>
                                        <select wire:model="conditionForm.status" class="form-select">
                                            <option value="active">Activa</option>
                                            <option value="inactive">Inactiva</option>
                                            <option value="replaced">Reemplazada</option>
                                        </select>
                                        @error('conditionForm.status')
                                            <p class="form-error">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    @error('conditionForm.effective_from')
                                        <p class="form-error">{{ $message }}</p>
                                    @enderror

                                    <div class="flex justify-end">
                                        <button type="button" class="btn-primary btn-sm" wire:click="saveLaborCondition">Guardar condición</button>
                                    </div>

                                    @if ($relationship->laborConditions->isNotEmpty())
                                        <div class="rounded-xl border border-surface-line bg-white">
                                            <div class="border-b border-surface-line px-3 py-2 text-xs font-bold uppercase tracking-wide text-surface-muted">
                                                Historial
                                            </div>
                                            <div class="divide-y divide-surface-line text-sm">
                                                @foreach ($relationship->laborConditions as $condition)
                                                    <div class="px-3 py-2 text-surface-text">
                                                        {{ $condition->work_modality }} · {{ $condition->effective_from->format('Y-m-d') }} - {{ $condition->effective_to?->format('Y-m-d') ?? 'Actual' }} · {{ $condition->status }}
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @else
                                <p class="text-sm text-surface-muted">Primero guarda una relación laboral activa.</p>
                            @endif
                        </section>

                        <section class="space-y-4 rounded-2xl border border-surface-line bg-surface-card p-4 shadow-[0_14px_35px_-28px_rgba(2,25,57,0.22)]">
                            <div class="mb-4">
                                <flux:heading size="sm">Credencial kiosco</flux:heading>
                                <flux:subheading>{{ $credential ? 'Estado: '.$credential->status : 'Sin credencial creada' }}</flux:subheading>
                            </div>

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

                <div class="flex justify-end gap-3 border-t border-surface-line p-6">
                    <button type="button" class="btn-ghost" wire:click="closeFormPanel">Cancelar</button>
                    <button type="submit" class="btn-primary">Guardar trabajador</button>
                </div>
            </form>
        </x-side-panel>
    @endif
</section>
