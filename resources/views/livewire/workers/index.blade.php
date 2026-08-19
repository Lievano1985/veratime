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

<section class="w-full space-y-8 p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">Trabajadores</flux:heading>
            <flux:subheading>Administra trabajadores y su relacion laboral inicial en la empresa activa.</flux:subheading>
        </div>

        @if ($canManageWorkers)
            <flux:button type="button" icon="plus" variant="primary" wire:click="openCreatePanel">
                Nuevo trabajador
            </flux:button>
        @endif
    </div>

    @if (session('status'))
        <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
            {{ session('status') }}
        </div>
    @endif

    @error('worker')
        <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
            {{ $message }}
        </div>
    @enderror

    <section class="rounded-lg border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-700 dark:bg-zinc-900/60">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <flux:heading>Trabajadores de {{ $currentCompany->name }}</flux:heading>
                <flux:subheading>Solo se muestran trabajadores asociados a la empresa activa.</flux:subheading>
            </div>

            <div class="grid gap-3 sm:grid-cols-[minmax(0,220px)_160px]">
                <flux:input wire:model.live.debounce.300ms="search" label="Buscar" placeholder="Codigo, nombre o RFC" />

                <div>
                    <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Estado</label>
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
    </section>

    <div class="overflow-hidden rounded-md border border-zinc-200 dark:border-zinc-700">
        <table class="w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
            <thead class="bg-zinc-50 text-left text-xs font-medium uppercase text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                <tr>
                    <th class="px-4 py-3">Codigo</th>
                    <th class="px-4 py-3">Nombre</th>
                    <th class="px-4 py-3">Centro actual</th>
                    <th class="px-4 py-3">Puesto</th>
                    <th class="px-4 py-3">Condicion</th>
                    <th class="px-4 py-3">Credencial</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 [&>tr:nth-child(odd)]:bg-white [&>tr:nth-child(even)]:bg-zinc-50/60 dark:divide-zinc-700 dark:[&>tr:nth-child(odd)]:bg-zinc-900 dark:[&>tr:nth-child(even)]:bg-zinc-800/40">
                @forelse ($workers as $worker)
                    @php($relationship = $worker->activeEmploymentRelationship)
                    @php($condition = $relationship?->activeLaborCondition)
                    @php($credential = $worker->credential)
                    <tr>
                        <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">{{ $worker->employee_code }}</td>
                        <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ $worker->full_name }}</td>
                        <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ $relationship?->center?->name ?? 'Sin centro activo' }}</td>
                        <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ $relationship?->position_name ?: 'Sin puesto' }}</td>
                        <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ $condition?->work_modality ?? 'Sin condicion' }}</td>
                        <td class="px-4 py-3">
                            <x-ui.badge variant="{{ $credential?->status === 'active' ? 'success' : ($credential?->status === 'reset_required' ? 'warning' : ($credential?->status === 'blocked' ? 'danger' : 'neutral')) }}">
                                {{ $credential?->status === 'active' ? 'Activa' : ($credential?->status === 'reset_required' ? 'Requiere reinicio' : ($credential?->status === 'blocked' ? 'Bloqueada' : 'Sin credencial')) }}
                            </x-ui.badge>
                        </td>
                        <td class="px-4 py-3">
                            <x-ui.badge variant="{{ $worker->status === 'active' ? 'success' : ($worker->status === 'terminated' ? 'danger' : ($worker->status === 'suspended' ? 'warning' : 'neutral')) }}">
                                {{ $worker->status }}
                            </x-ui.badge>
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
                                <flux:button type="button" size="sm" variant="danger" wire:confirm="Eliminar este trabajador solo si no tiene horarios ni asistencias? Esta accion no se puede deshacer." wire:click="delete({{ $worker->id }})">
                                    Eliminar
                                </flux:button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-sm text-zinc-500 dark:text-zinc-400">
                            Aun no hay trabajadores registrados para esta empresa.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($canManageWorkers)
        <x-side-panel
            wire:model="showFormPanel"
            :title="$editingWorkerId ? 'Editar trabajador' : 'Nuevo trabajador'"
            subheading="Los datos aplican solo a la empresa activa."
            labelledby="worker-form-title"
        >
            <form wire:submit="save" class="flex flex-1 flex-col overflow-y-auto">
                <div class="flex-1 space-y-5 p-6">
                    <section class="space-y-4 rounded-lg border border-zinc-200 bg-zinc-50/70 p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-900/60">
                        <div>
                            <flux:heading size="sm">Datos generales</flux:heading>
                            <flux:subheading>Identificación y relación laboral base.</flux:subheading>
                        </div>

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

                        @if ($editingWorkerId)
                            <flux:textarea
                                wire:model="form.relationship_change_reason"
                                label="Motivo del cambio laboral"
                                placeholder="Obligatorio si cambias centro, puesto o fecha de ingreso."
                                rows="3"
                            />
                        @endif

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
                    </section>

                    @if ($editingWorkerId)
                        @php($relationship = $editingWorker?->activeEmploymentRelationship)
                        @php($activeCondition = $relationship?->activeLaborCondition)
                        @php($credential = $editingWorker?->credential)

                        <section class="space-y-4 rounded-lg border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
                            <div class="mb-4">
                                <flux:heading size="sm">Condicion laboral vigente</flux:heading>
                                <flux:subheading>
                                    {{ $activeCondition ? $activeCondition->work_modality.' desde '.$activeCondition->effective_from->format('Y-m-d') : 'Sin condicion activa' }}
                                </flux:subheading>
                            </div>

                            @if ($relationship)
                                <div class="space-y-4">
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Modalidad</label>
                                        <select wire:model="conditionForm.work_modality" class="w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">
                                            <option value="onsite">Presencial</option>
                                            <option value="hybrid">Hibrido</option>
                                            <option value="remote">Remoto</option>
                                            <option value="field">Campo</option>
                                        </select>
                                        @error('conditionForm.work_modality')
                                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <flux:input wire:model="conditionForm.weekly_hours" label="Horas semanales" type="number" step="0.5" min="0" />

                                        <div>
                                            <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Dia de descanso</label>
                                            <select wire:model="conditionForm.rest_day_of_week" class="w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">
                                                <option value="">Sin definir</option>
                                                <option value="0">Domingo</option>
                                                <option value="1">Lunes</option>
                                                <option value="2">Martes</option>
                                                <option value="3">Miercoles</option>
                                                <option value="4">Jueves</option>
                                                <option value="5">Viernes</option>
                                                <option value="6">Sabado</option>
                                            </select>
                                            @error('conditionForm.rest_day_of_week')
                                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <flux:input wire:model="conditionForm.effective_from" label="Vigente desde" type="date" required />
                                        <flux:input wire:model="conditionForm.effective_to" label="Vigente hasta" type="date" />
                                    </div>

                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Estado condicion</label>
                                        <select wire:model="conditionForm.status" class="w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">
                                            <option value="active">Activa</option>
                                            <option value="inactive">Inactiva</option>
                                            <option value="replaced">Reemplazada</option>
                                        </select>
                                        @error('conditionForm.status')
                                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    @error('conditionForm.effective_from')
                                        <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror

                                    <div class="flex justify-end">
                                        <flux:button type="button" size="sm" wire:click="saveLaborCondition">
                                            Guardar condicion
                                        </flux:button>
                                    </div>

                                    @if ($relationship->laborConditions->isNotEmpty())
                                        <div class="rounded-md border border-zinc-200 dark:border-zinc-700">
                                            <div class="border-b border-zinc-200 px-3 py-2 text-xs font-medium uppercase text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                                                Historial
                                            </div>
                                            <div class="divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                                                @foreach ($relationship->laborConditions as $condition)
                                                    <div class="px-3 py-2 text-zinc-700 dark:text-zinc-300">
                                                        {{ $condition->work_modality }} · {{ $condition->effective_from->format('Y-m-d') }} - {{ $condition->effective_to?->format('Y-m-d') ?? 'Actual' }} · {{ $condition->status }}
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @else
                                <p class="text-sm text-zinc-500 dark:text-zinc-400">Primero guarda una relacion laboral activa.</p>
                            @endif
                        </section>

                        <section class="space-y-4 rounded-lg border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
                            <div class="mb-4">
                                <flux:heading size="sm">Credencial kiosco</flux:heading>
                                <flux:subheading>{{ $credential ? 'Estado: '.$credential->status : 'Sin credencial creada' }}</flux:subheading>
                            </div>

                            <div class="space-y-4">
                                <flux:input wire:model="credentialForm.access_code" label="Codigo de acceso" required />
                                <flux:input wire:model="credentialForm.temporal_pin" label="NIP temporal" type="password" />

                                <div>
                                    <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Estado credencial</label>
                                    <select wire:model="credentialForm.status" class="w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">
                                        <option value="active">Activa</option>
                                        <option value="blocked">Bloqueada</option>
                                        <option value="reset_required">Requiere reset</option>
                                    </select>
                                    @error('credentialForm.status')
                                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>

                                @error('credentialForm.access_code')
                                    <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                                @error('credentialForm.temporal_pin')
                                    <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror

                                <div class="flex flex-wrap justify-end gap-2">
                                    <flux:button type="button" size="sm" wire:click="saveCredential">
                                        Guardar credencial
                                    </flux:button>
                                    @if ($credential)
                                        <flux:button type="button" size="sm" wire:click="resetCredentialPin">
                                            Reset NIP
                                        </flux:button>
                                        @if ($credential->status !== 'blocked')
                                            <flux:button type="button" size="sm" variant="danger" wire:click="blockCredential">
                                                Bloquear
                                            </flux:button>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </section>
                    @endif
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
