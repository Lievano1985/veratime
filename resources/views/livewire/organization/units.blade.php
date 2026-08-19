<?php

use App\Domains\Organization\Actions\CreateOrganizationalUnitAction;
use App\Domains\Organization\Actions\DeleteOrganizationalUnitIfUnusedAction;
use App\Domains\Organization\Actions\InactivateOrganizationalUnitAction;
use App\Domains\Organization\Actions\ResolveUserOperationalScopeAction;
use App\Domains\Organization\Actions\UpdateOrganizationalUnitAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\OrganizationalUnit;
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
    public array $filters = [];
    public bool $showFormPanel = false;
    public ?int $editingUnitId = null;

    public function mount(): void
    {
        $this->form = $this->emptyForm();
        $this->filters = ['center_id' => '', 'status' => 'active', 'search' => ''];
    }

    public function updated($property): void
    {
        if (str_starts_with((string) $property, 'filters.')) {
            $this->resetPage();
        }

        if (in_array($property, ['form.center_id', 'form.type'], true)) {
            $this->form['parent_id'] = '';
        }
    }

    public function openCreatePanel(CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('viewAny', [OrganizationalUnit::class, $company]);

        $this->editingUnitId = null;
        $this->form = $this->emptyForm();
        $this->showFormPanel = true;
    }

    public function loadEditForm(int $unitId, CurrentCompany $currentCompany): void
    {
        $unit = $this->authorizedUnit($unitId, $currentCompany);

        $this->editingUnitId = $unit->id;
        $this->form = [
            'center_id' => (string) $unit->center_id,
            'type' => $unit->type,
            'parent_id' => $unit->parent_id ? (string) $unit->parent_id : '',
            'code' => $unit->code,
            'name' => $unit->name,
            'status' => $unit->status,
        ];
        $this->showFormPanel = true;
    }

    public function save(
        CurrentCompany $currentCompany,
        CreateOrganizationalUnitAction $createAction,
        UpdateOrganizationalUnitAction $updateAction,
    ): void {
        $company = $this->currentCompanyOrFail($currentCompany);
        $unit = $this->editingUnitId ? $this->authorizedUnit($this->editingUnitId, $currentCompany) : null;

        $validated = $this->validate([
            'form.center_id' => [
                'required',
                'integer',
                Rule::exists('centers', 'id')->where('company_id', $company->id)->where('status', 'active'),
            ],
            'form.type' => ['required', Rule::in(['department', 'area', 'team'])],
            'form.parent_id' => [
                'nullable',
                'integer',
                Rule::exists('organizational_units', 'id')->where('company_id', $company->id)->where('status', 'active'),
            ],
            'form.code' => ['required', 'string', 'max:50'],
            'form.name' => ['required', 'string', 'max:255'],
            'form.status' => ['required', Rule::in(['active', 'inactive'])],
        ])['form'];

        $center = $company->centers()->whereKey((int) $validated['center_id'])->where('status', 'active')->firstOrFail();
        $parent = filled($validated['parent_id'] ?? null)
            ? $company->organizationalUnits()->whereKey((int) $validated['parent_id'])->firstOrFail()
            : null;

        $unit
            ? Gate::authorize('update', $unit)
            : Gate::authorize('createInScope', [OrganizationalUnit::class, $company, $center, $parent]);

        try {
            $unit
                ? $updateAction->handle($company, $unit, $validated, $parent)
                : $createAction->handle($company, $center, $validated, $parent);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['form.parent_id' => $exception->getMessage()]);
        }

        $this->showFormPanel = false;
        $this->editingUnitId = null;
        $this->form = $this->emptyForm();
        $this->resetPage();

        Session::flash('status', $unit ? 'Unidad actualizada.' : 'Unidad creada.');
    }

    public function inactivate(int $unitId, CurrentCompany $currentCompany, InactivateOrganizationalUnitAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $unit = $this->authorizedUnit($unitId, $currentCompany);

        Gate::authorize('inactivate', $unit);

        try {
            $action->handle($company, $unit);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['unit' => $exception->getMessage()]);
        }

        Session::flash('status', 'Unidad inactivada.');
        $this->resetPage();
    }

    public function delete(int $unitId, CurrentCompany $currentCompany, DeleteOrganizationalUnitIfUnusedAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $unit = $this->authorizedUnit($unitId, $currentCompany);

        Gate::authorize('delete', $unit);

        try {
            $action->handle($company, $unit);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['unit' => $exception->getMessage()]);
        }

        Session::flash('status', 'Unidad eliminada.');
        $this->resetPage();
    }

    public function closeFormPanel(): void
    {
        $this->showFormPanel = false;
        $this->editingUnitId = null;
        $this->resetValidation('form');
    }

    public function with(CurrentCompany $currentCompany, ResolveUserOperationalScopeAction $resolveUserScope): array
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('viewAny', [OrganizationalUnit::class, $company]);
        $scope = in_array(auth()->user()->roleKeyForCompany($company), RoleKey::scopeAssignableRoles(), true)
            ? $resolveUserScope->handle($company, auth()->user(), now()->toDateString())
            : null;

        return [
            'currentCompany' => $company,
            'centers' => $this->centerQuery($company, $scope)->get(),
            'units' => $this->unitQuery($company, $scope)->paginate(12),
            'parentOptions' => $this->parentOptions($company),
            'canManageUnits' => Gate::allows('create', [OrganizationalUnit::class, $company])
                || (in_array(auth()->user()->roleKeyForCompany($company), RoleKey::scopedOperators(), true)
                    && (($scope['center_ids'] ?? []) !== [] || ($scope['organizational_unit_ids'] ?? []) !== [])),
            'visibleOrganizationalUnitIds' => $scope['organizational_unit_ids'] ?? null,
        ];
    }

    private function unitQuery($company, ?array $scope = null)
    {
        $search = trim((string) ($this->filters['search'] ?? ''));
        $centerId = trim((string) ($this->filters['center_id'] ?? ''));
        $status = trim((string) ($this->filters['status'] ?? 'active'));

        return $company->organizationalUnits()
            ->with(['center', 'parent'])
            ->when($scope !== null, function ($query) use ($scope): void {
                $query->where(function ($scopeQuery) use ($scope): void {
                    $scopeQuery
                        ->whereIn('center_id', $scope['center_ids'])
                        ->orWhereIn('id', $scope['organizational_unit_ids']);
                });
            })
            ->when($centerId !== '', fn ($query) => $query->where('center_id', (int) $centerId))
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderBy('center_id')
            ->orderByRaw("case type when 'department' then 1 when 'area' then 2 else 3 end")
            ->orderBy('name');
    }

    private function centerQuery($company, ?array $scope = null)
    {
        return $company->centers()
            ->where('status', 'active')
            ->when($scope !== null, function ($query) use ($scope): void {
                $query->where(function ($scopeQuery) use ($scope): void {
                    $scopeQuery
                        ->whereIn('id', $scope['center_ids'])
                        ->orWhereHas('organizationalUnits', fn ($unitQuery) => $unitQuery->whereIn('id', $scope['organizational_unit_ids']));
                });
            })
            ->orderBy('name');
    }

    private function parentOptions($company)
    {
        $centerId = (int) ($this->form['center_id'] ?? 0);
        $type = (string) ($this->form['type'] ?? '');

        if ($centerId <= 0 || $type === 'department') {
            return collect();
        }

        return $company->organizationalUnits()
            ->where('center_id', $centerId)
            ->where('status', 'active')
            ->whereIn('type', $type === 'area' ? ['department'] : ['area'])
            ->when(in_array(auth()->user()->roleKeyForCompany($company), RoleKey::scopedOperators(), true), function ($query) use ($company): void {
                $scope = app(ResolveUserOperationalScopeAction::class)->handle($company, auth()->user(), now()->toDateString());
                $query->where(function ($scopeQuery) use ($scope): void {
                    $scopeQuery
                        ->whereIn('center_id', $scope['center_ids'])
                        ->orWhereIn('id', $scope['organizational_unit_ids']);
                });
            })
            ->when($this->editingUnitId, fn ($query) => $query->whereKeyNot($this->editingUnitId))
            ->orderBy('name')
            ->get();
    }

    private function authorizedUnit(int $unitId, CurrentCompany $currentCompany): OrganizationalUnit
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $unit = $company->organizationalUnits()->with(['company', 'center', 'parent'])->whereKey($unitId)->firstOrFail();

        Gate::authorize('update', $unit);

        return $unit;
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
            'center_id' => '',
            'type' => 'department',
            'parent_id' => '',
            'code' => '',
            'name' => '',
            'status' => 'active',
        ];
    }
}; ?>

<section class="flex h-full w-full flex-1 flex-col gap-6 p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">Areas y departamentos</flux:heading>
            <flux:subheading>Administra departamentos, areas y equipos por centro.</flux:subheading>
        </div>

        @if ($canManageUnits)
            <flux:button type="button" icon="plus" variant="primary" wire:click="openCreatePanel">
                Nueva unidad
            </flux:button>
        @endif
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    @error('unit')
        <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
            {{ $message }}
        </div>
    @enderror

    <section class="space-y-4">
        <div class="grid gap-4 rounded-md border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-900/60 md:grid-cols-3">
            <flux:input label="Buscar" placeholder="Codigo o nombre" wire:model.live.debounce.350ms="filters.search" />

            <flux:select label="Centro" wire:model.live="filters.center_id">
                <flux:select.option value="">Todos</flux:select.option>
                @foreach ($centers as $center)
                    <flux:select.option value="{{ $center->id }}">{{ $center->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select label="Estado" wire:model.live="filters.status">
                <flux:select.option value="active">Activas</flux:select.option>
                <flux:select.option value="inactive">Inactivas</flux:select.option>
                <flux:select.option value="all">Todas</flux:select.option>
            </flux:select>
        </div>

        <div class="overflow-hidden rounded-md border border-zinc-200 dark:border-zinc-700">
            <table class="w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 text-left text-xs font-medium uppercase text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                    <tr>
                        <th class="px-4 py-3">Unidad</th>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3">Centro</th>
                        <th class="px-4 py-3">Padre</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 [&>tr:nth-child(odd)]:bg-white [&>tr:nth-child(even)]:bg-zinc-50/60 dark:divide-zinc-700 dark:[&>tr:nth-child(odd)]:bg-zinc-900 dark:[&>tr:nth-child(even)]:bg-zinc-800/40">
                    @forelse ($units as $unit)
                        <tr>
                            <td class="px-4 py-3">
                                <span class="block font-medium text-zinc-900 dark:text-zinc-100">{{ $unit->code }} - {{ $unit->name }}</span>
                                <span class="text-xs text-zinc-500">{{ $unit->type === 'department' ? 'Departamento' : ($unit->type === 'area' ? 'Area' : 'Equipo') }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <x-ui.badge variant="info">
                                    {{ $unit->type === 'department' ? 'Departamento' : ($unit->type === 'area' ? 'Area' : 'Equipo') }}
                                </x-ui.badge>
                            </td>
                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ $unit->center?->name }}</td>
                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">
                                @if (! $unit->parent_id)
                                    Centro directo
                                @elseif ($visibleOrganizationalUnitIds === null || in_array($unit->parent_id, $visibleOrganizationalUnitIds, true))
                                    {{ $unit->parent?->name }}
                                @else
                                    Fuera del alcance
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <x-ui.badge variant="{{ $unit->status === 'active' ? 'success' : 'neutral' }}">
                                    {{ $unit->status === 'active' ? 'Activa' : 'Inactiva' }}
                                </x-ui.badge>
                            </td>
                            <td class="px-4 py-3">
                                @if ($canManageUnits)
                                    <div class="flex justify-end gap-2">
                                        <flux:button type="button" size="sm" wire:click="loadEditForm({{ $unit->id }})">
                                            Editar
                                        </flux:button>
                                        @if ($unit->status === 'active')
                                            <flux:button type="button" size="sm" variant="danger" wire:confirm="Esta accion inactivara la unidad si no tiene hijos, asignaciones o alcances vigentes." wire:click="inactivate({{ $unit->id }})">
                                                Inactivar
                                            </flux:button>
                                        @endif
                                        <flux:button type="button" size="sm" variant="danger" wire:confirm="Eliminar esta unidad solo si no tiene uso? Esta accion no se puede deshacer." wire:click="delete({{ $unit->id }})">
                                            Eliminar
                                        </flux:button>
                                    </div>
                                @else
                                    <span class="text-xs text-zinc-500">Solo consulta</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-zinc-500">
                                No hay unidades que coincidan con los filtros.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $units->links() }}
    </section>

    @if ($canManageUnits)
        <x-side-panel
            wire:model="showFormPanel"
            :title="$editingUnitId ? 'Editar unidad' : 'Nueva unidad'"
            subheading="Las unidades pertenecen a la empresa activa y a un centro."
            labelledby="organizational-unit-form-title"
        >
            <form wire:submit="save" class="flex flex-1 flex-col overflow-y-auto">
                <div class="flex-1 space-y-4 p-6">
                    <flux:select label="Centro" wire:model.live="form.center_id">
                        <flux:select.option value="">Selecciona un centro</flux:select.option>
                        @foreach ($centers as $center)
                            <flux:select.option value="{{ $center->id }}">{{ $center->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select label="Tipo" wire:model.live="form.type">
                        <flux:select.option value="department">Departamento</flux:select.option>
                        <flux:select.option value="area">Area</flux:select.option>
                        <flux:select.option value="team">Equipo</flux:select.option>
                    </flux:select>

                    @if ($form['type'] !== 'department')
                        <flux:select label="Unidad padre" wire:model="form.parent_id">
                            <flux:select.option value="">{{ $form['type'] === 'area' ? 'Centro directo o departamento' : 'Selecciona un area' }}</flux:select.option>
                            @foreach ($parentOptions as $parent)
                                <flux:select.option value="{{ $parent->id }}">{{ $parent->code }} - {{ $parent->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @endif

                    <flux:input label="Codigo" wire:model="form.code" required />
                    <flux:input label="Nombre" wire:model="form.name" required />

                    @if ($editingUnitId)
                        <flux:select label="Estado" wire:model="form.status">
                            <flux:select.option value="active">Activa</flux:select.option>
                            <flux:select.option value="inactive">Inactiva</flux:select.option>
                        </flux:select>
                    @endif

                    @error('form.parent_id')
                        <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex justify-end gap-3 border-t border-zinc-200 p-6 dark:border-zinc-700">
                    <flux:button type="button" variant="ghost" wire:click="closeFormPanel">
                        Cancelar
                    </flux:button>
                    <flux:button type="submit" variant="primary">
                        Guardar unidad
                    </flux:button>
                </div>
            </form>
        </x-side-panel>
    @endif
</section>
