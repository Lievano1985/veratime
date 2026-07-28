<?php

use App\Domains\Organization\Actions\AssignOperationalScopeAction;
use App\Domains\Organization\Actions\EndOperationalScopeAction;
use App\Domains\Organization\Actions\ReplaceOperationalScopeAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\OperationalScopeAssignment;
use App\Models\User;
use App\Support\RoleKey;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public array $form = [];
    public array $endForm = [];
    public array $filters = [];
    public bool $showFormPanel = false;
    public bool $showEndPanel = false;
    public ?int $endingScopeId = null;

    public function mount(): void
    {
        $this->form = $this->emptyForm();
        $this->endForm = $this->emptyEndForm();
        $this->filters = ['status' => 'all', 'search' => ''];
    }

    public function updated($property): void
    {
        if (str_starts_with((string) $property, 'filters.')) {
            $this->resetPage();
        }

        if ($property === 'form.scope_kind') {
            $this->form['center_id'] = '';
            $this->form['organizational_unit_id'] = '';
        }
    }

    public function openCreatePanel(CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        Gate::authorize('create', [OperationalScopeAssignment::class, $company]);

        $this->form = $this->emptyForm();
        $this->showFormPanel = true;
    }

    public function save(
        CurrentCompany $currentCompany,
        AssignOperationalScopeAction $assignAction,
        ReplaceOperationalScopeAction $replaceAction,
    ): void {
        $company = $this->currentCompanyOrFail($currentCompany);
        Gate::authorize('create', [OperationalScopeAssignment::class, $company]);

        $validated = $this->validate([
            'form.user_id' => [
                'required',
                'integer',
                Rule::exists('company_user', 'user_id')->where('company_id', $company->id)->where('status', 'active'),
            ],
            'form.scope_kind' => ['required', Rule::in(['center', 'unit'])],
            'form.center_id' => [
                'required_if:form.scope_kind,center',
                'nullable',
                'integer',
                Rule::exists('centers', 'id')->where('company_id', $company->id)->where('status', 'active'),
            ],
            'form.organizational_unit_id' => [
                'required_if:form.scope_kind,unit',
                'nullable',
                'integer',
                Rule::exists('organizational_units', 'id')->where('company_id', $company->id)->where('status', 'active'),
            ],
            'form.responsibility_type' => ['required', Rule::in(['supervisor', 'responsible'])],
            'form.operation' => ['required', Rule::in(['assign', 'replace'])],
            'form.effective_from' => ['required', 'date'],
            'form.effective_to' => ['nullable', 'date', 'after_or_equal:form.effective_from'],
            'form.reason' => ['required', 'string', 'max:1000'],
        ])['form'];

        $user = User::query()->whereKey((int) $validated['user_id'])->firstOrFail();
        $center = $validated['scope_kind'] === 'center'
            ? $company->centers()->whereKey((int) $validated['center_id'])->firstOrFail()
            : null;
        $unit = $validated['scope_kind'] === 'unit'
            ? $company->organizationalUnits()->whereKey((int) $validated['organizational_unit_id'])->firstOrFail()
            : null;

        try {
            $data = [
                'responsibility_type' => $validated['responsibility_type'],
                'effective_from' => $validated['effective_from'],
                'effective_to' => $validated['effective_to'] ?? null,
                'source' => 'manual',
                'reason' => $validated['reason'],
                'created_by' => auth()->id(),
            ];

            $validated['operation'] === 'replace'
                ? $replaceAction->handle($company, $user, $data, $center, $unit)
                : $assignAction->handle($company, $user, $data, $center, $unit);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['form.user_id' => $exception->getMessage()]);
        }

        $this->showFormPanel = false;
        $this->form = $this->emptyForm();
        $this->resetPage();
        Session::flash('status', 'Alcance operativo guardado.');
    }

    public function openEndPanel(int $scopeId, CurrentCompany $currentCompany): void
    {
        $scope = $this->authorizedScope($scopeId, $currentCompany);

        $this->endingScopeId = $scope->id;
        $this->endForm = $this->emptyEndForm();
        $this->showEndPanel = true;
    }

    public function endScope(CurrentCompany $currentCompany, EndOperationalScopeAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $scope = $this->authorizedScope($this->endingScopeId ?? 0, $currentCompany);

        Gate::authorize('end', $scope);

        $validated = $this->validate([
            'endForm.effective_to' => ['required', 'date'],
            'endForm.reason' => ['required', 'string', 'max:1000'],
        ])['endForm'];

        try {
            $action->handle($company, $scope, $validated['effective_to'], $validated['reason']);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['endForm.effective_to' => $exception->getMessage()]);
        }

        $this->showEndPanel = false;
        $this->endingScopeId = null;
        $this->endForm = $this->emptyEndForm();
        $this->resetPage();
        Session::flash('status', 'Alcance finalizado.');
    }

    public function closePanels(): void
    {
        $this->showFormPanel = false;
        $this->showEndPanel = false;
        $this->endingScopeId = null;
        $this->resetValidation();
    }

    public function with(CurrentCompany $currentCompany): array
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        Gate::authorize('viewAny', [OperationalScopeAssignment::class, $company]);

        return [
            'currentCompany' => $company,
            'scopes' => $this->scopeQuery($company)->paginate(12),
            'supervisors' => $this->supervisorUsers($company),
            'centers' => $company->centers()->where('status', 'active')->orderBy('name')->get(),
            'units' => $company->organizationalUnits()->with('center')->where('status', 'active')->orderBy('name')->get(),
        ];
    }

    private function scopeQuery($company)
    {
        $status = trim((string) ($this->filters['status'] ?? 'all'));
        $search = trim((string) ($this->filters['search'] ?? ''));

        return $company->operationalScopeAssignments()
            ->with(['user', 'center', 'organizationalUnit.center'])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            })
            ->orderByDesc('effective_from')
            ->orderByDesc('id');
    }

    private function supervisorUsers($company)
    {
        return $company->activeUsers()
            ->where('users.status', 'active')
            ->get()
            ->filter(fn (User $user) => $user->roleKeyForCompany($company) === RoleKey::SUPERVISOR)
            ->sortBy('name')
            ->values();
    }

    private function authorizedScope(int $scopeId, CurrentCompany $currentCompany): OperationalScopeAssignment
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $scope = $company->operationalScopeAssignments()->whereKey($scopeId)->firstOrFail();

        Gate::authorize('update', $scope);

        return $scope;
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
            'user_id' => '',
            'scope_kind' => 'center',
            'center_id' => '',
            'organizational_unit_id' => '',
            'responsibility_type' => 'supervisor',
            'operation' => 'assign',
            'effective_from' => now()->toDateString(),
            'effective_to' => '',
            'reason' => '',
        ];
    }

    private function emptyEndForm(): array
    {
        return [
            'effective_to' => now()->toDateString(),
            'reason' => '',
        ];
    }
}; ?>

<section class="flex h-full w-full flex-1 flex-col gap-6 p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">Responsables y supervisores</flux:heading>
            <flux:subheading>Asigna alcance explicito por centro completo o unidad organizacional.</flux:subheading>
        </div>

        <flux:button type="button" icon="plus" variant="primary" wire:click="openCreatePanel">
            Nuevo alcance
        </flux:button>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    <div class="rounded-md border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-100">
        Owner, administrador y RH ya tienen alcance empresarial completo. Solo los usuarios con rol supervisor requieren alcances explicitos.
    </div>

    <section class="space-y-4">
        <div class="grid gap-4 rounded-md border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-900/60 md:grid-cols-2">
            <flux:input label="Buscar supervisor" placeholder="Nombre o email" wire:model.live.debounce.350ms="filters.search" />
            <flux:select label="Estado" wire:model.live="filters.status">
                <flux:select.option value="all">Todos</flux:select.option>
                <flux:select.option value="active">Vigentes</flux:select.option>
                <flux:select.option value="inactive">Finalizados</flux:select.option>
                <flux:select.option value="replaced">Reemplazados</flux:select.option>
            </flux:select>
        </div>

        <div class="overflow-hidden rounded-md border border-zinc-200 dark:border-zinc-700">
            <table class="w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 text-left text-xs font-medium uppercase text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                    <tr>
                        <th class="px-4 py-3">Usuario</th>
                        <th class="px-4 py-3">Alcance</th>
                        <th class="px-4 py-3">Responsabilidad</th>
                        <th class="px-4 py-3">Vigencia</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 [&>tr:nth-child(odd)]:bg-white [&>tr:nth-child(even)]:bg-zinc-50/60 dark:divide-zinc-700 dark:[&>tr:nth-child(odd)]:bg-zinc-900 dark:[&>tr:nth-child(even)]:bg-zinc-800/40">
                    @forelse ($scopes as $scope)
                        <tr>
                            <td class="px-4 py-3">
                                <span class="block font-medium text-zinc-900 dark:text-zinc-100">{{ $scope->user?->name }}</span>
                                <span class="text-xs text-zinc-500">{{ $scope->user?->email }}</span>
                            </td>
                            <td class="px-4 py-3">
                                @if ($scope->center)
                                    Centro {{ $scope->center->name }} - incluye todas sus unidades y trabajadores aplicables.
                                @else
                                    {{ $scope->organizationalUnit?->name }} - incluye areas y equipos descendientes.
                                    <span class="block text-xs text-zinc-500">{{ $scope->organizationalUnit?->center?->name }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $scope->responsibility_type === 'responsible' ? 'Responsable' : 'Supervisor' }}</td>
                            <td class="px-4 py-3">{{ $scope->effective_from?->toDateString() }} - {{ $scope->effective_to?->toDateString() ?? 'Abierta' }}</td>
                            <td class="px-4 py-3">{{ $scope->status === 'active' ? 'Vigente' : ($scope->status === 'inactive' ? 'Finalizado' : 'Reemplazado') }}</td>
                            <td class="px-4 py-3 text-right">
                                @if ($scope->status === 'active')
                                    <flux:button type="button" size="sm" variant="ghost" wire:click="openEndPanel({{ $scope->id }})">
                                        Finalizar
                                    </flux:button>
                                @else
                                    <span class="text-xs text-zinc-500">Historial</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-zinc-500">
                                No hay alcances operativos que coincidan con los filtros.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $scopes->links() }}
    </section>

    <x-side-panel wire:model="showFormPanel" title="Alcance operativo" subheading="El usuario debe tener rol supervisor." labelledby="operational-scope-form-title">
        <form wire:submit="save" class="flex flex-1 flex-col overflow-y-auto">
            <div class="flex-1 space-y-4 p-6">
                <flux:select label="Supervisor" wire:model="form.user_id">
                    <flux:select.option value="">Selecciona un supervisor</flux:select.option>
                    @foreach ($supervisors as $supervisor)
                        <flux:select.option value="{{ $supervisor->id }}">{{ $supervisor->name }} - {{ $supervisor->email }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select label="Operacion" wire:model="form.operation">
                    <flux:select.option value="assign">Asignar alcance</flux:select.option>
                    <flux:select.option value="replace">Reemplazar alcance vigente</flux:select.option>
                </flux:select>

                <flux:select label="Tipo de alcance" wire:model.live="form.scope_kind">
                    <flux:select.option value="center">Centro completo</flux:select.option>
                    <flux:select.option value="unit">Unidad organizacional</flux:select.option>
                </flux:select>

                @if ($form['scope_kind'] === 'center')
                    <flux:select label="Centro" wire:model="form.center_id">
                        <flux:select.option value="">Selecciona un centro</flux:select.option>
                        @foreach ($centers as $center)
                            <flux:select.option value="{{ $center->id }}">{{ $center->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @else
                    <flux:select label="Unidad" wire:model="form.organizational_unit_id">
                        <flux:select.option value="">Selecciona una unidad</flux:select.option>
                        @foreach ($units as $unit)
                            <flux:select.option value="{{ $unit->id }}">{{ $unit->name }} - {{ $unit->center?->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <p class="text-xs text-zinc-500">Una unidad incluye sus descendientes dentro del mismo centro.</p>
                @endif

                <flux:select label="Responsabilidad" wire:model="form.responsibility_type">
                    <flux:select.option value="supervisor">Supervisor</flux:select.option>
                    <flux:select.option value="responsible">Responsable</flux:select.option>
                </flux:select>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input type="date" label="Desde" wire:model="form.effective_from" />
                    <flux:input type="date" label="Hasta" wire:model="form.effective_to" />
                </div>

                <flux:textarea label="Motivo" wire:model="form.reason" required />

                @error('form.user_id')
                    <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex justify-end gap-3 border-t border-zinc-200 p-6 dark:border-zinc-700">
                <flux:button type="button" variant="ghost" wire:click="closePanels">Cancelar</flux:button>
                <flux:button type="submit" variant="primary">Guardar alcance</flux:button>
            </div>
        </form>
    </x-side-panel>

    <x-side-panel wire:model="showEndPanel" title="Finalizar alcance" subheading="Cierra la vigencia sin borrar historial." labelledby="end-scope-form-title" max-width="max-w-md">
        <form wire:submit="endScope" class="flex flex-1 flex-col overflow-y-auto">
            <div class="flex-1 space-y-4 p-6">
                <flux:input type="date" label="Finaliza el" wire:model="endForm.effective_to" />
                <flux:textarea label="Motivo" wire:model="endForm.reason" required />
                @error('endForm.effective_to')
                    <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex justify-end gap-3 border-t border-zinc-200 p-6 dark:border-zinc-700">
                <flux:button type="button" variant="ghost" wire:click="closePanels">Cancelar</flux:button>
                <flux:button type="submit" variant="primary">Finalizar</flux:button>
            </div>
        </form>
    </x-side-panel>
</section>
