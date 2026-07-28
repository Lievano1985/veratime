<?php

use App\Domains\MandatoryRestDays\Actions\CreateMandatoryRestDayAction;
use App\Domains\MandatoryRestDays\Actions\DeleteMandatoryRestDayIfUnusedAction;
use App\Domains\MandatoryRestDays\Actions\InactivateMandatoryRestDayAction;
use App\Domains\MandatoryRestDays\Actions\UpdateMandatoryRestDayAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\MandatoryRestDay;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component {
    public array $form = [];
    public bool $showFormPanel = false;
    public array $filters = [];
    public ?int $editingRestDayId = null;

    public function mount(): void
    {
        $this->form = $this->emptyForm();
        $this->filters = [
            'date' => '',
            'type' => '',
            'scope' => '',
            'status' => '',
            'jurisdiction_code' => '',
        ];
    }

    public function openCreatePanel(CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('create', [MandatoryRestDay::class, $company]);

        $this->editingRestDayId = null;
        $this->form = $this->emptyForm();
        $this->showFormPanel = true;
    }

    public function save(CurrentCompany $currentCompany, CreateMandatoryRestDayAction $createAction, UpdateMandatoryRestDayAction $updateAction): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('create', [MandatoryRestDay::class, $company]);

        $validated = $this->validate($this->rules($company->id))['form'];
        $this->assertAllowedPayloadForUser($company, $validated);
        $targetCompany = $validated['scope'] === 'company' ? $company : null;

        try {
            if ($this->editingRestDayId) {
                $restDay = $this->editableRestDay($company, $this->editingRestDayId);

                Gate::authorize('update', $restDay);

                $updateAction->handle($company, $restDay, [
                    'name' => $validated['name'],
                    'date' => $validated['date'],
                    'type' => $validated['type'],
                    'scope' => $validated['scope'],
                    'country_code' => 'MX',
                    'jurisdiction_code' => $validated['jurisdiction_code'] ?? null,
                    'source_reference' => $validated['source_reference'] ?? null,
                    'status' => $validated['status'],
                    'metadata' => [],
                ]);
            } else {
                $createAction->handle($targetCompany, [
                    'name' => $validated['name'],
                    'date' => $validated['date'],
                    'type' => $validated['type'],
                    'scope' => $validated['scope'],
                    'country_code' => 'MX',
                    'jurisdiction_code' => $validated['jurisdiction_code'] ?? null,
                    'source_reference' => $validated['source_reference'] ?? null,
                    'capture_source' => 'manual',
                    'status' => $validated['status'],
                    'metadata' => [],
                ]);
            }
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'form.scope' => $exception->getMessage(),
            ]);
        }

        $this->resetForm();

        Session::flash('status', 'Descanso obligatorio guardado.');
    }

    public function edit(int $restDayId, CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $restDay = $this->editableRestDay($company, $restDayId);

        Gate::authorize('update', $restDay);

        $this->editingRestDayId = $restDay->id;
        $this->showFormPanel = true;
        $this->form = [
            'name' => $restDay->name,
            'date' => $restDay->date?->toDateString(),
            'type' => $restDay->type,
            'scope' => $restDay->scope,
            'jurisdiction_code' => $restDay->jurisdiction_code ?? '',
            'source_reference' => $restDay->source_reference ?? '',
            'status' => $restDay->status,
        ];
    }

    public function inactivate(int $restDayId, CurrentCompany $currentCompany, InactivateMandatoryRestDayAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $restDay = $this->editableRestDay($company, $restDayId);

        Gate::authorize('inactivate', $restDay);

        $action->handle($company, $restDay);

        if ($this->editingRestDayId === $restDay->id) {
            $this->resetForm();
        }

        Session::flash('status', 'Descanso obligatorio inactivado.');
    }

    public function delete(int $restDayId, CurrentCompany $currentCompany, DeleteMandatoryRestDayIfUnusedAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $restDay = $this->editableRestDay($company, $restDayId);

        Gate::authorize('delete', $restDay);

        try {
            $action->handle($company, $restDay);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['restDay' => $exception->getMessage()]);
        }

        if ($this->editingRestDayId === $restDay->id) {
            $this->resetForm();
        }

        Session::flash('status', 'Descanso obligatorio eliminado.');
    }

    public function resetForm(): void
    {
        $this->editingRestDayId = null;
        $this->form = $this->emptyForm();
        $this->showFormPanel = false;
        $this->resetValidation('form');
    }

    public function with(CurrentCompany $currentCompany): array
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('viewAny', [MandatoryRestDay::class, $company]);

        $restDays = MandatoryRestDay::query()
            ->with(['company'])
            ->where(function ($query) use ($company): void {
                $query->whereIn('scope', ['national', 'subnational'])
                    ->whereNull('company_id')
                    ->orWhere('company_id', $company->id);
            })
            ->when(filled($this->filters['date'] ?? null), fn ($query) => $query->whereDate('date', $this->filters['date']))
            ->when(filled($this->filters['type'] ?? null), fn ($query) => $query->where('type', $this->filters['type']))
            ->when(filled($this->filters['scope'] ?? null), fn ($query) => $query->where('scope', $this->filters['scope']))
            ->where('country_code', 'MX')
            ->when(filled($this->filters['jurisdiction_code'] ?? null), fn ($query) => $query->where('jurisdiction_code', strtoupper(trim((string) $this->filters['jurisdiction_code']))))
            ->when(filled($this->filters['status'] ?? null), fn ($query) => $query->where('status', $this->filters['status']))
            ->orderByDesc('date')
            ->orderBy('type')
            ->orderBy('scope')
            ->orderBy('name')
            ->get();

        return [
            'restDays' => $restDays,
        ];
    }

    private function rules(int $companyId): array
    {
        return [
            'form.name' => ['required', 'string', 'max:255'],
            'form.date' => ['required', 'date'],
            'form.type' => ['required', Rule::in(MandatoryRestDay::TYPES)],
            'form.scope' => ['required', Rule::in(MandatoryRestDay::SCOPES)],
            'form.jurisdiction_code' => [
                'nullable',
                'string',
                'max:16',
                'regex:/^[A-Za-z]{2}-[A-Za-z0-9]{2,8}$/',
                Rule::requiredIf(fn () => ($this->form['scope'] ?? null) === 'subnational'),
                Rule::prohibitedIf(fn () => ($this->form['scope'] ?? null) !== 'subnational'),
            ],
            'form.source_reference' => ['nullable', 'string', 'max:500'],
            'form.status' => ['required', Rule::in(MandatoryRestDay::STATUSES)],
        ];
    }

    private function currentCompanyOrFail(CurrentCompany $currentCompany)
    {
        $company = $currentCompany->get();

        abort_unless($company, 403);

        return $company;
    }

    private function editableRestDay($company, int $restDayId): MandatoryRestDay
    {
        return MandatoryRestDay::query()
            ->where(function ($query) use ($company): void {
                $query->where('company_id', $company->id)
                    ->orWhere(function ($query): void {
                        $query->whereNull('company_id')
                            ->whereIn('scope', ['national', 'subnational']);
                    });
            })
            ->findOrFail($restDayId);
    }

    private function assertAllowedPayloadForUser($company, array $validated): void
    {
        $isCompanyInternal = $validated['type'] === 'company_internal' && $validated['scope'] === 'company';
        $isGlobalCatalog = in_array($validated['scope'], ['national', 'subnational'], true)
            || $validated['type'] === 'electoral';

        if ($isCompanyInternal) {
            return;
        }

        if ($isGlobalCatalog && $this->isSuperAdmin($company)) {
            return;
        }

        throw ValidationException::withMessages([
            'form.type' => 'Solo super_admin puede administrar descansos nacionales, estatales o electorales.',
        ]);
    }

    private function isSuperAdmin($company): bool
    {
        return auth()->user()?->roleKeyForCompany($company) === 'super_admin';
    }

    private function emptyForm(): array
    {
        return [
            'name' => '',
            'date' => now()->toDateString(),
            'type' => 'company_internal',
            'scope' => 'company',
            'jurisdiction_code' => '',
            'source_reference' => '',
            'status' => 'active',
        ];
    }
}; ?>

<section class="flex h-full w-full flex-1 flex-col gap-6 p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Descansos obligatorios</flux:heading>
            <flux:subheading>Administra fechas de descanso por tipo y alcance sin calcular jornadas.</flux:subheading>
        </div>

        <flux:button type="button" icon="plus" variant="primary" wire:click="openCreatePanel">
            Crear descanso
        </flux:button>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    @error('restDay')
        <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
            {{ $message }}
        </div>
    @enderror

    <x-side-panel
        wire:model="showFormPanel"
        :title="$editingRestDayId ? 'Editar descanso' : 'Crear descanso'"
        subheading="La fecha se aplica solo al alcance seleccionado."
        labelledby="mandatory-rest-day-form-title"
        max-width="max-w-xl"
    >
        <form wire:submit="save" class="flex flex-1 flex-col overflow-y-auto">
            <div class="flex-1 space-y-4 p-6">
                <flux:input label="Nombre" wire:model="form.name" />
                <flux:input type="date" label="Fecha" wire:model="form.date" />

                <flux:select label="Tipo" wire:model="form.type">
                    <flux:select.option value="legal_mandatory">Legal obligatorio</flux:select.option>
                    <flux:select.option value="electoral">Electoral</flux:select.option>
                    <flux:select.option value="company_internal">Interno de empresa</flux:select.option>
                </flux:select>

                <flux:select label="Alcance" wire:model.live="form.scope">
                    <flux:select.option value="national">Nacional</flux:select.option>
                    <flux:select.option value="subnational">Entidad federativa</flux:select.option>
                    <flux:select.option value="company">Empresa</flux:select.option>
                </flux:select>

                <flux:input
                    label="Entidad federativa"
                    placeholder="Ej. MX-TAB"
                    wire:model="form.jurisdiction_code"
                    :disabled="$form['scope'] !== 'subnational'"
                />

                <div class="space-y-1">
                    <flux:textarea label="Fundamento o referencia" wire:model="form.source_reference" rows="3" />
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                        Ejemplo: LFT artículo 74, acuerdo electoral o política interna
                    </p>
                </div>

                <flux:select label="Estado" wire:model="form.status">
                    <flux:select.option value="active">Activo</flux:select.option>
                    <flux:select.option value="inactive">Inactivo</flux:select.option>
                </flux:select>
            </div>

            <div class="flex justify-end gap-3 border-t border-zinc-200 p-6 dark:border-zinc-700">
                <flux:button type="button" variant="ghost" wire:click="resetForm">
                    Cancelar
                </flux:button>
                <flux:button type="submit" variant="primary">
                    Guardar descanso
                </flux:button>
            </div>
        </form>
    </x-side-panel>

    <section class="space-y-4">
        <flux:heading>Filtros</flux:heading>

        <div class="grid gap-4 lg:grid-cols-5">
            <flux:input type="date" label="Fecha" wire:model.live="filters.date" />
            <flux:select label="Tipo" wire:model.live="filters.type">
                <flux:select.option value="">Todos</flux:select.option>
                <flux:select.option value="legal_mandatory">Legal obligatorio</flux:select.option>
                <flux:select.option value="electoral">Electoral</flux:select.option>
                <flux:select.option value="company_internal">Interno de empresa</flux:select.option>
            </flux:select>
            <flux:select label="Alcance" wire:model.live="filters.scope">
                <flux:select.option value="">Todos</flux:select.option>
                <flux:select.option value="national">Nacional</flux:select.option>
                <flux:select.option value="subnational">Entidad federativa</flux:select.option>
                <flux:select.option value="company">Empresa</flux:select.option>
            </flux:select>
            <flux:input label="Entidad federativa" placeholder="MX-TAB" wire:model.live="filters.jurisdiction_code" />
            <flux:select label="Estado" wire:model.live="filters.status">
                <flux:select.option value="">Todos</flux:select.option>
                <flux:select.option value="active">Activo</flux:select.option>
                <flux:select.option value="inactive">Inactivo</flux:select.option>
            </flux:select>
        </div>
    </section>

    <section class="space-y-4">
        <flux:heading>Listado</flux:heading>

        <div class="overflow-hidden rounded-md border border-zinc-200 dark:border-zinc-700">
            <table class="w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 text-left text-xs font-medium uppercase text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                    <tr>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3">Nombre</th>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3">Alcance</th>
                        <th class="px-4 py-3">Estado/Empresa</th>
                        <th class="px-4 py-3">Fundamento o referencia</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 [&>tr:nth-child(odd)]:bg-white [&>tr:nth-child(even)]:bg-zinc-50/60 dark:divide-zinc-700 dark:[&>tr:nth-child(odd)]:bg-zinc-900 dark:[&>tr:nth-child(even)]:bg-zinc-800/40">
                    @forelse ($restDays as $restDay)
                        <tr>
                            <td class="px-4 py-3">{{ $restDay->date?->toDateString() }}</td>
                            <td class="px-4 py-3">{{ $restDay->name }}</td>
                            <td class="px-4 py-3">{{ str($restDay->type)->replace('_', ' ')->title() }}</td>
                            <td class="px-4 py-3">{{ str($restDay->scope)->replace('_', ' ')->title() }}</td>
                            <td class="px-4 py-3">
                                {{ $restDay->scope === 'subnational' ? $restDay->jurisdiction_code : ($restDay->company?->name ?? 'Sin empresa') }}
                            </td>
                            <td class="px-4 py-3">{{ $restDay->source_reference ?: 'Sin referencia' }}</td>
                            <td class="px-4 py-3">{{ ucfirst($restDay->status) }}</td>
                            <td class="px-4 py-3 text-right">
                                @if (Gate::allows('update', $restDay))
                                    <div class="flex justify-end gap-2">
                                        <flux:button type="button" size="sm" variant="ghost" wire:click="edit({{ $restDay->id }})">Editar</flux:button>
                                        @if ($restDay->status === 'active')
                                            <flux:button type="button" size="sm" variant="ghost" wire:click="inactivate({{ $restDay->id }})">Inactivar</flux:button>
                                        @endif
                                        <flux:button type="button" size="sm" variant="danger" wire:confirm="Eliminar este descanso solo si fue capturado por error? Esta accion no se puede deshacer." wire:click="delete({{ $restDay->id }})">Eliminar</flux:button>
                                    </div>
                                @else
                                    <span class="text-xs text-zinc-500">Sin permisos</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-zinc-500">
                                No hay descansos registrados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</section>
