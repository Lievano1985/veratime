<?php

use App\Domains\Schedules\Actions\AssignScheduleToWorkersAction;
use App\Domains\Schedules\Actions\InactivateScheduleAssignmentAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use App\Models\Worker;
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
    public string $workerFilterSearch = '';
    public ?int $selectedWorkerId = null;
    public bool $workerFilterOpen = false;
    public bool $showFormPanel = false;

    public function mount(): void
    {
        $this->form = $this->emptyForm();
        $this->filters = $this->defaultFilters();
    }

    public function updated($property): void
    {
        if (str_starts_with((string) $property, 'filters.') || in_array($property, ['workerFilterSearch', 'selectedWorkerId'], true)) {
            $this->resetPage();
        }
    }

    public function updatedWorkerFilterSearch(): void
    {
        $this->selectedWorkerId = null;
        $this->workerFilterOpen = true;
    }

    public function clearFilters(): void
    {
        $this->filters = $this->defaultFilters();
        $this->workerFilterSearch = '';
        $this->selectedWorkerId = null;
        $this->workerFilterOpen = false;
        $this->resetPage();
    }

    public function selectWorkerFilter(int $workerId, CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $worker = $this->workerFilterQuery($company)
            ->whereKey($workerId)
            ->firstOrFail();

        $this->selectedWorkerId = $worker->id;
        $this->workerFilterSearch = "{$worker->employee_code} - {$worker->full_name}";
        $this->workerFilterOpen = false;
        $this->resetPage();
    }

    public function clearWorkerFilter(): void
    {
        $this->selectedWorkerId = null;
        $this->workerFilterSearch = '';
        $this->workerFilterOpen = false;
        $this->resetPage();
    }

    public function openCreatePanel(CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('create', [ScheduleAssignment::class, $company]);

        $this->form = $this->emptyForm();
        $this->showFormPanel = true;
    }

    public function closeFormPanel(): void
    {
        $this->showFormPanel = false;
        $this->resetValidation('form');
    }

    public function save(CurrentCompany $currentCompany, AssignScheduleToWorkersAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('create', [ScheduleAssignment::class, $company]);

        $validated = $this->validate([
            'form.worker_ids' => ['required', 'array', 'min:1'],
            'form.worker_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('workers', 'id')->where('company_id', $company->id)->where('status', 'active'),
            ],
            'form.schedule_id' => [
                'required',
                'integer',
                Rule::exists('schedules', 'id')->where('company_id', $company->id),
            ],
            'form.effective_from' => ['required', 'date'],
            'form.effective_to' => ['nullable', 'date', 'after_or_equal:form.effective_from'],
        ])['form'];

        $schedule = Schedule::query()
            ->where('company_id', $company->id)
            ->findOrFail((int) $validated['schedule_id']);

        try {
            $assignments = $action->handle($company, $schedule, $validated['worker_ids'], [
                'effective_from' => $validated['effective_from'],
                'effective_to' => $validated['effective_to'] ?? null,
                'source' => 'web',
            ]);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'form.worker_ids' => $exception->getMessage(),
            ]);
        }

        $this->form = $this->emptyForm();
        $this->showFormPanel = false;
        $this->resetPage();

        Session::flash('status', $assignments->count() === 1
            ? 'Asignación de horario guardada.'
            : 'Asignaciones de horario guardadas.');
    }

    public function inactivate(
        int $assignmentId,
        CurrentCompany $currentCompany,
        InactivateScheduleAssignmentAction $action
    ): void {
        $company = $this->currentCompanyOrFail($currentCompany);
        $assignment = ScheduleAssignment::query()
            ->where('company_id', $company->id)
            ->findOrFail($assignmentId);

        Gate::authorize('inactivate', $assignment);

        $action->handle($company, $assignment);
        $this->resetPage();

        Session::flash('status', 'Asignación de horario inactivada.');
    }

    public function with(CurrentCompany $currentCompany): array
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('viewAny', [ScheduleAssignment::class, $company]);

        return [
            'assignments' => $this->assignmentQuery($company)->paginate(10),
            'schedules' => $company->schedules()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(),
            'centers' => $company->centers()
                ->orderBy('name')
                ->get(),
            'workerFilterResults' => $this->workerFilterQuery($company)
                ->limit(8)
                ->get(),
        ];
    }

    private function assignmentQuery($company)
    {
        $employeeCode = trim((string) ($this->filters['employee_code'] ?? ''));
        $centerId = trim((string) ($this->filters['center_id'] ?? ''));
        $status = (string) ($this->filters['status'] ?? 'active');
        $workerId = (int) $this->selectedWorkerId;

        return $company->scheduleAssignments()
            ->with(['worker', 'schedule', 'employmentRelationship.center'])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($employeeCode !== '', function ($query) use ($employeeCode): void {
                $query->whereHas('worker', fn ($workerQuery) => $workerQuery->where('employee_code', 'like', "%{$employeeCode}%"));
            })
            ->when($workerId > 0, fn ($query) => $query->where('worker_id', $workerId))
            ->when($centerId !== '', function ($query) use ($centerId): void {
                $query->whereHas('employmentRelationship', fn ($relationshipQuery) => $relationshipQuery->where('center_id', (int) $centerId));
            })
            ->orderByDesc('effective_from')
            ->orderByDesc('id');
    }

    private function workerFilterQuery($company)
    {
        $search = trim($this->workerFilterSearch);

        return Worker::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->with(['activeEmploymentRelationship.center'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery->where('full_name', 'like', "%{$search}%")
                        ->orWhere('employee_code', 'like', "%{$search}%");
                });
            })
            ->orderBy('full_name')
            ->orderBy('employee_code');
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
            'worker_ids' => [],
            'schedule_id' => '',
            'effective_from' => now()->toDateString(),
            'effective_to' => '',
        ];
    }

    private function defaultFilters(): array
    {
        return [
            'employee_code' => '',
            'center_id' => '',
            'status' => 'active',
        ];
    }
}; ?>

<section class="flex h-full w-full flex-1 flex-col gap-6 p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Asignaciones de horario</flux:heading>
            <flux:subheading>Administra la vigencia del horario por trabajador sin calcular jornadas.</flux:subheading>
        </div>

        <flux:button type="button" variant="primary" wire:click="openCreatePanel">
            Nueva asignación
        </flux:button>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    <x-side-panel
        wire:model="showFormPanel"
        title="Asignar o reemplazar horario"
        subheading="La vigencia se guarda para la empresa activa."
        labelledby="schedule-assignment-form-title"
        max-width="max-w-xl"
    >
        <form wire:submit="save" class="flex flex-1 flex-col overflow-y-auto">
            <div class="flex-1 space-y-4 p-6">
                <livewire:workers.multi-select wire:model="form.worker_ids" />
                @error('form.worker_ids')
                    <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror

                <flux:select label="Horario" wire:model="form.schedule_id">
                    <flux:select.option value="">Selecciona</flux:select.option>
                    @foreach ($schedules as $schedule)
                        <flux:select.option value="{{ $schedule->id }}">{{ $schedule->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input type="date" label="Desde" wire:model="form.effective_from" />
                <flux:input type="date" label="Hasta" wire:model="form.effective_to" />
            </div>

            <div class="flex justify-end gap-3 border-t border-zinc-200 p-6 dark:border-zinc-700">
                <flux:button type="button" variant="ghost" wire:click="closeFormPanel">
                    Cancelar
                </flux:button>
                <flux:button type="submit" variant="primary">
                    Guardar asignación
                </flux:button>
            </div>
        </form>
    </x-side-panel>

    <section class="space-y-4">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <flux:heading>Historial de asignaciones de horarios</flux:heading>
            <flux:button type="button" variant="ghost" wire:click="clearFilters">Limpiar filtros</flux:button>
        </div>

        <div class="grid gap-4 rounded-md border border-zinc-200 p-4 dark:border-zinc-700 md:grid-cols-2 xl:grid-cols-4">
            <flux:input
                label="Clave"
                placeholder="Código de empleado"
                wire:model.live.debounce.350ms="filters.employee_code"
            />

            <div class="md:col-span-2 xl:col-span-1">
                <x-search-clear-filter
                    label="Nombre"
                    input-id="schedule-assignment-worker-filter"
                    placeholder="Buscar trabajador"
                    clear-action="clearWorkerFilter"
                    clear-label="Limpiar trabajador"
                    :clear-disabled="! $selectedWorkerId && trim($workerFilterSearch) === ''"
                    wire:model.live.debounce.350ms="workerFilterSearch"
                    wire:focus="$set('workerFilterOpen', true)"
                >
                @if ($workerFilterOpen && trim($workerFilterSearch) !== '')
                    <div class="absolute z-40 mt-2 max-h-72 w-full overflow-y-auto rounded-md border border-zinc-200 bg-white p-2 shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                        @forelse ($workerFilterResults as $worker)
                            <button
                                type="button"
                                wire:click="selectWorkerFilter({{ $worker->id }})"
                                class="block w-full rounded-md px-3 py-2 text-left text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800"
                            >
                                <span class="block font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $worker->employee_code }} - {{ $worker->full_name }}
                                </span>
                                <span class="block text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $worker->activeEmploymentRelationship?->center?->name ?? 'Sin centro activo' }}
                                    -
                                    {{ $worker->activeEmploymentRelationship?->position_name ?? 'Sin puesto activo' }}
                                </span>
                            </button>
                        @empty
                            <p class="px-3 py-2 text-sm text-zinc-500">
                                No se encontraron trabajadores activos.
                            </p>
                        @endforelse
                    </div>
                @endif
                </x-search-clear-filter>
            </div>

            <flux:select label="Centro de trabajo" wire:model.live="filters.center_id">
                <flux:select.option value="">Todos</flux:select.option>
                @foreach ($centers as $center)
                    <flux:select.option value="{{ $center->id }}">{{ $center->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select label="Estatus" wire:model.live="filters.status">
                <flux:select.option value="active">Activas</flux:select.option>
                <flux:select.option value="inactive">Inactivas</flux:select.option>
                <flux:select.option value="replaced">Reemplazadas</flux:select.option>
                <flux:select.option value="all">Todas</flux:select.option>
            </flux:select>
        </div>

        <div class="overflow-hidden rounded-md border border-zinc-200 dark:border-zinc-700">
            <table class="w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 text-left text-xs font-medium uppercase text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                    <tr>
                        <th class="px-4 py-3">Trabajador</th>
                        <th class="px-4 py-3">Centro histórico</th>
                        <th class="px-4 py-3">Horario</th>
                        <th class="px-4 py-3">Vigencia</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($assignments as $assignment)
                        <tr>
                            <td class="px-4 py-3">
                                <span class="block font-medium text-zinc-900 dark:text-zinc-100">{{ $assignment->worker->full_name }}</span>
                                <span class="text-xs text-zinc-500">{{ $assignment->worker->employee_code }}</span>
                            </td>
                            <td class="px-4 py-3">{{ $assignment->employmentRelationship?->center?->name ?? 'Sin centro' }}</td>
                            <td class="px-4 py-3">{{ $assignment->schedule->name }}</td>
                            <td class="px-4 py-3">
                                {{ $assignment->effective_from?->toDateString() }}
                                -
                                {{ $assignment->effective_to?->toDateString() ?? 'Vigente' }}
                            </td>
                            <td class="px-4 py-3">{{ ucfirst($assignment->status) }}</td>
                            <td class="px-4 py-3 text-right">
                                @if ($assignment->status === 'active')
                                    <flux:button type="button" size="sm" variant="ghost" wire:click="inactivate({{ $assignment->id }})">
                                        Inactivar
                                    </flux:button>
                                @else
                                    <span class="text-xs text-zinc-500">Sin acción</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-zinc-500">
                                No hay asignaciones que coincidan con los filtros.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>
            {{ $assignments->links() }}
        </div>
    </section>
</section>
