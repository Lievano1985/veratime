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

    public function deleteRestDay(int $restDayId, CurrentCompany $currentCompany, DeleteMandatoryRestDayIfUnusedAction $action): void
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

    public function typeLabel(string $type): string
    {
        return match ($type) {
            'legal_mandatory' => 'Legal obligatorio',
            'electoral' => 'Electoral',
            'company_internal' => 'Interno de empresa',
            default => $type,
        };
    }

    public function scopeLabel(string $scope): string
    {
        return match ($scope) {
            'national' => 'Nacional',
            'subnational' => 'Entidad federativa',
            'company' => 'Empresa',
            default => $scope,
        };
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'Activo',
            'inactive' => 'Inactivo',
            default => $status,
        };
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

<section class="flex h-full w-full flex-1 flex-col gap-6 bg-surface-bg p-6 text-surface-text">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="font-display text-2xl font-bold text-brand-navy">Descansos obligatorios</h1>
            <p class="mt-1.5 text-[13.5px] text-surface-muted">Administra fechas de descanso por tipo y alcance sin calcular jornadas.</p>
        </div>

        <button type="button" class="btn-primary" wire:click="openCreatePanel">
            <span class="text-base leading-none">+</span>
            Crear descanso
        </button>
    </div>

    @if (session('status'))
        <div class="rounded-xl border border-status-rest-line bg-status-rest-bg px-4 py-3 text-sm font-medium text-status-rest-text">
            {{ session('status') }}
        </div>
    @endif

    @error('restDay')
        <div class="rounded-xl border border-status-pending-line bg-status-pending-bg px-4 py-3 text-sm font-medium text-status-pending-text">
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
                    <p class="form-hint">
                        Ejemplo: LFT artículo 74, acuerdo electoral o política interna
                    </p>
                </div>

                <flux:select label="Estado" wire:model="form.status">
                    <flux:select.option value="active">Activo</flux:select.option>
                    <flux:select.option value="inactive">Inactivo</flux:select.option>
                </flux:select>
            </div>

            <div class="flex justify-end gap-3 border-t border-surface-line p-6">
                <button type="button" class="btn-ghost" wire:click="resetForm">Cancelar</button>
                <button type="submit" class="btn-primary">Guardar descanso</button>
            </div>
        </form>
    </x-side-panel>

    <section class="rounded-2xl border border-surface-line bg-surface-card p-6 shadow-[0_20px_50px_-34px_rgba(2,25,57,0.22)]">
        <div class="flex flex-col gap-1">
            <h2 class="font-display text-lg font-bold text-brand-navy">Listado de descansos</h2>
            <p class="text-[13px] text-surface-muted">Filtra por fecha, tipo, alcance o estado operativo.</p>
        </div>

        <div class="mt-5 grid gap-4 md:grid-cols-4 xl:grid-cols-5">
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

        <div class="table-wrap mt-6">
            <table class="w-full min-w-[980px] border-collapse">
                <thead>
                    <tr>
                        <th class="table-head-cell">Fecha</th>
                        <th class="table-head-cell">Nombre</th>
                        <th class="table-head-cell">Tipo</th>
                        <th class="table-head-cell">Alcance</th>
                        <th class="table-head-cell">Estado/Empresa</th>
                        <th class="table-head-cell">Fundamento o referencia</th>
                        <th class="table-head-cell">Estado</th>
                        <th class="table-head-cell text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($restDays as $restDay)
                        <tr class="table-row">
                            <td class="table-cell font-mono text-brand-navy">{{ $restDay->date?->toDateString() }}</td>
                            <td class="table-cell font-semibold text-brand-navy">{{ $restDay->name }}</td>
                            <td class="table-cell text-surface-muted">{{ $this->typeLabel($restDay->type) }}</td>
                            <td class="table-cell text-surface-muted">{{ $this->scopeLabel($restDay->scope) }}</td>
                            <td class="table-cell text-surface-muted">
                                {{ $restDay->scope === 'subnational' ? $restDay->jurisdiction_code : ($restDay->company?->name ?? 'Sin empresa') }}
                            </td>
                            <td class="table-cell text-surface-muted">{{ $restDay->source_reference ?: 'Sin referencia' }}</td>
                            <td class="table-cell">
                                <span class="{{ $restDay->status === 'active' ? 'badge-success' : 'badge-muted' }}">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                                    {{ $this->statusLabel($restDay->status) }}
                                </span>
                            </td>
                            <td class="table-cell">
                                @if (Gate::allows('update', $restDay))
                                    <div class="flex justify-end gap-1.5">
                                        <button type="button" class="btn-icon" wire:click="edit({{ $restDay->id }})" aria-label="Editar descanso" title="Editar">
                                            <svg class="h-4 w-4 text-surface-muted" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        </button>
                                        @if ($restDay->status === 'active')
                                            <button type="button" class="btn-icon" wire:click="inactivate({{ $restDay->id }})" aria-label="Inactivar descanso" title="Inactivar">
                                                <svg class="h-4 w-4 text-status-warn-text" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M18.36 5.64 5.64 18.36M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            </button>
                                        @endif
                                        <button type="button" class="btn-icon" wire:confirm="Eliminar este descanso solo si fue capturado por error? Esta acción no se puede deshacer." wire:click="deleteRestDay({{ $restDay->id }})" aria-label="Eliminar descanso" title="Eliminar">
                                            <svg class="h-4 w-4 text-status-pending-text" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6h16Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        </button>
                                    </div>
                                @else
                                    <span class="text-xs text-surface-muted">Sin permisos</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="table-cell py-8 text-center text-surface-muted">
                                No hay descansos registrados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</section>
