<?php

use App\Domains\MandatoryRestDays\Actions\CreateMandatoryRestDayAction;
use App\Domains\MandatoryRestDays\Actions\InactivateMandatoryRestDayAction;
use App\Domains\MandatoryRestDays\Actions\UpdateMandatoryRestDayAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\Center;
use App\Models\MandatoryRestDay;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component {
    public array $form = [];
    public array $filters = [];
    public ?int $editingRestDayId = null;

    public function mount(): void
    {
        $this->form = $this->emptyForm();
        $this->filters = [
            'date' => '',
            'scope' => '',
            'status' => '',
            'center_id' => '',
        ];
    }

    public function save(CurrentCompany $currentCompany, CreateMandatoryRestDayAction $createAction, UpdateMandatoryRestDayAction $updateAction): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('create', [MandatoryRestDay::class, $company]);

        $validated = $this->validate($this->rules($company->id))['form'];
        $center = $this->centerFromForm($company->id, $validated);

        try {
            if ($this->editingRestDayId) {
                $restDay = MandatoryRestDay::query()
                    ->where('company_id', $company->id)
                    ->findOrFail($this->editingRestDayId);

                Gate::authorize('update', $restDay);

                $updateAction->handle($company, $restDay, $center, [
                    'name' => $validated['name'],
                    'date' => $validated['date'],
                    'scope' => $validated['scope'],
                    'source' => $validated['source'] ?? 'manual',
                    'status' => $validated['status'],
                    'metadata' => [],
                ]);
            } else {
                $createAction->handle($company, $center, [
                    'name' => $validated['name'],
                    'date' => $validated['date'],
                    'scope' => $validated['scope'],
                    'source' => $validated['source'] ?? 'manual',
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
        $restDay = MandatoryRestDay::query()
            ->where('company_id', $company->id)
            ->findOrFail($restDayId);

        Gate::authorize('update', $restDay);

        $this->editingRestDayId = $restDay->id;
        $this->form = [
            'name' => $restDay->name,
            'date' => $restDay->date?->toDateString(),
            'scope' => $restDay->scope,
            'center_id' => $restDay->center_id ? (string) $restDay->center_id : '',
            'source' => $restDay->source ?? 'manual',
            'status' => $restDay->status,
        ];
    }

    public function inactivate(int $restDayId, CurrentCompany $currentCompany, InactivateMandatoryRestDayAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $restDay = MandatoryRestDay::query()
            ->where('company_id', $company->id)
            ->findOrFail($restDayId);

        Gate::authorize('inactivate', $restDay);

        $action->handle($company, $restDay);

        if ($this->editingRestDayId === $restDay->id) {
            $this->resetForm();
        }

        Session::flash('status', 'Descanso obligatorio inactivado.');
    }

    public function resetForm(): void
    {
        $this->editingRestDayId = null;
        $this->form = $this->emptyForm();
    }

    public function with(CurrentCompany $currentCompany): array
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('viewAny', [MandatoryRestDay::class, $company]);

        $restDays = MandatoryRestDay::query()
            ->with(['company', 'center'])
            ->where(function ($query) use ($company): void {
                $query->where('scope', 'global')
                    ->whereNull('company_id')
                    ->whereNull('center_id')
                    ->orWhere('company_id', $company->id);
            })
            ->when(filled($this->filters['date'] ?? null), fn ($query) => $query->whereDate('date', $this->filters['date']))
            ->when(filled($this->filters['scope'] ?? null), fn ($query) => $query->where('scope', $this->filters['scope']))
            ->when(filled($this->filters['status'] ?? null), fn ($query) => $query->where('status', $this->filters['status']))
            ->when(filled($this->filters['center_id'] ?? null), fn ($query) => $query->where('center_id', $this->filters['center_id']))
            ->orderByDesc('date')
            ->orderBy('scope')
            ->orderBy('name')
            ->get();

        return [
            'restDays' => $restDays,
            'centers' => $company->centers()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(),
        ];
    }

    private function rules(int $companyId): array
    {
        return [
            'form.name' => ['required', 'string', 'max:255'],
            'form.date' => ['required', 'date'],
            'form.scope' => ['required', Rule::in(['company', 'center'])],
            'form.center_id' => [
                'nullable',
                'integer',
                Rule::requiredIf(fn () => ($this->form['scope'] ?? null) === 'center'),
                Rule::prohibitedIf(fn () => ($this->form['scope'] ?? null) === 'company'),
                Rule::exists('centers', 'id')->where('company_id', $companyId),
            ],
            'form.source' => ['nullable', 'string', 'max:100'],
            'form.status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    private function centerFromForm(int $companyId, array $validated): ?Center
    {
        if (($validated['scope'] ?? null) !== 'center') {
            return null;
        }

        return Center::query()
            ->where('company_id', $companyId)
            ->findOrFail((int) $validated['center_id']);
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
            'name' => '',
            'date' => now()->toDateString(),
            'scope' => 'company',
            'center_id' => '',
            'source' => 'manual',
            'status' => 'active',
        ];
    }
}; ?>

<section class="flex h-full w-full flex-1 flex-col gap-6 p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Descansos obligatorios</flux:heading>
            <flux:subheading>Administra fechas de descanso por empresa o centro sin calcular jornadas.</flux:subheading>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    <section class="space-y-4">
        <flux:heading>{{ $editingRestDayId ? 'Editar descanso' : 'Crear descanso' }}</flux:heading>

        <form wire:submit="save" class="grid gap-4 lg:grid-cols-[1.2fr_0.8fr_0.8fr_1fr_0.8fr_0.8fr_auto] lg:items-end">
            <flux:input label="Nombre" wire:model="form.name" />
            <flux:input type="date" label="Fecha" wire:model="form.date" />

            <flux:select label="Alcance" wire:model.live="form.scope">
                <flux:select.option value="company">Empresa</flux:select.option>
                <flux:select.option value="center">Centro</flux:select.option>
            </flux:select>

            <flux:select label="Centro" wire:model="form.center_id" :disabled="$form['scope'] !== 'center'">
                <flux:select.option value="">Sin centro</flux:select.option>
                @foreach ($centers as $center)
                    <flux:select.option value="{{ $center->id }}">{{ $center->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input label="Fuente" wire:model="form.source" />

            <flux:select label="Estado" wire:model="form.status">
                <flux:select.option value="active">Activo</flux:select.option>
                <flux:select.option value="inactive">Inactivo</flux:select.option>
            </flux:select>

            <div class="flex gap-2">
                <flux:button type="submit" variant="primary">Guardar</flux:button>
                @if ($editingRestDayId)
                    <flux:button type="button" variant="ghost" wire:click="resetForm">Cancelar</flux:button>
                @endif
            </div>
        </form>
    </section>

    <section class="space-y-4">
        <flux:heading>Filtros</flux:heading>

        <div class="grid gap-4 lg:grid-cols-4">
            <flux:input type="date" label="Fecha" wire:model.live="filters.date" />
            <flux:select label="Alcance" wire:model.live="filters.scope">
                <flux:select.option value="">Todos</flux:select.option>
                <flux:select.option value="global">Global</flux:select.option>
                <flux:select.option value="company">Empresa</flux:select.option>
                <flux:select.option value="center">Centro</flux:select.option>
            </flux:select>
            <flux:select label="Estado" wire:model.live="filters.status">
                <flux:select.option value="">Todos</flux:select.option>
                <flux:select.option value="active">Activo</flux:select.option>
                <flux:select.option value="inactive">Inactivo</flux:select.option>
            </flux:select>
            <flux:select label="Centro" wire:model.live="filters.center_id">
                <flux:select.option value="">Todos</flux:select.option>
                @foreach ($centers as $center)
                    <flux:select.option value="{{ $center->id }}">{{ $center->name }}</flux:select.option>
                @endforeach
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
                        <th class="px-4 py-3">Alcance</th>
                        <th class="px-4 py-3">Centro</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($restDays as $restDay)
                        <tr>
                            <td class="px-4 py-3">{{ $restDay->date?->toDateString() }}</td>
                            <td class="px-4 py-3">{{ $restDay->name }}</td>
                            <td class="px-4 py-3">{{ ucfirst($restDay->scope) }}</td>
                            <td class="px-4 py-3">{{ $restDay->center?->name ?? 'Sin centro' }}</td>
                            <td class="px-4 py-3">{{ ucfirst($restDay->status) }}</td>
                            <td class="px-4 py-3 text-right">
                                @if ($restDay->company_id)
                                    <div class="flex justify-end gap-2">
                                        <flux:button type="button" size="sm" variant="ghost" wire:click="edit({{ $restDay->id }})">Editar</flux:button>
                                        @if ($restDay->status === 'active')
                                            <flux:button type="button" size="sm" variant="ghost" wire:click="inactivate({{ $restDay->id }})">Inactivar</flux:button>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-xs text-zinc-500">Global</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-zinc-500">
                                No hay descansos registrados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</section>
