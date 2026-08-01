<?php

use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\EmploymentUnitAssignment;
use App\Models\Worker;
use Livewire\Attributes\Modelable;
use Livewire\Volt\Component;

new class extends Component {
    #[Modelable]
    public array $selectedWorkerIds = [];

    public string $search = '';
    public string $centerId = '';
    public bool $open = false;
    public string $mode = 'multiple';
    public string $heading = 'Trabajadores';
    public string $subheading ="";
    public int $resultLimit = 8;
    public bool $showPrimaryAssignmentStatus = false;
    public string $assignmentDate = '';

    private const MAX_RESULT_LIMIT = 150;

    public function updatedSearch(): void
    {
        $this->open = true;
    }

    public function updatedCenterId(): void
    {
        $this->open = true;
    }

    public function updatedSelectedWorkerIds(): void
    {
        $selected = collect($this->selectedWorkerIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        $this->selectedWorkerIds = $this->mode === 'single'
            ? $selected->take(-1)->values()->all()
            : $selected->all();
    }

    public function toggleWorker(int $workerId, CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $worker = $this->workerQuery($company)
            ->whereKey($workerId)
            ->firstOrFail();

        if ($this->mode === 'single') {
            $this->selectedWorkerIds = [$worker->id];
            $this->open = false;

            return;
        }

        $selected = collect($this->selectedWorkerIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values();

        $this->selectedWorkerIds = $selected->contains($worker->id)
            ? $selected->reject(fn (int $id) => $id === $worker->id)->values()->all()
            : $selected->push($worker->id)->unique()->values()->all();
    }

    public function removeWorker(int $workerId): void
    {
        $this->selectedWorkerIds = collect($this->selectedWorkerIds)
            ->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => $id === $workerId)
            ->values()
            ->all();
    }

    public function clearSelection(): void
    {
        $this->selectedWorkerIds = [];
    }

    public function with(CurrentCompany $currentCompany): array
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        $workerResults = $this->workerQuery($company)
            ->limit($this->normalizedResultLimit())
            ->get();

        return [
            'centers' => $company->centers()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(),
            'workerResults' => $workerResults,
            'selectedWorkers' => $this->selectedWorkers($company),
            'primaryAssignedWorkerIds' => $this->primaryAssignedWorkerIds($company, $workerResults),
        ];
    }

    private function normalizedResultLimit(): int
    {
        return max(1, min($this->resultLimit, self::MAX_RESULT_LIMIT));
    }

    private function workerQuery($company)
    {
        $search = trim($this->search);
        $centerId = trim($this->centerId);

        return $company->workers()
            ->where('status', 'active')
            ->with(['activeEmploymentRelationship.center'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery->where('full_name', 'like', "%{$search}%")
                        ->orWhere('employee_code', 'like', "%{$search}%");
                });
            })
            ->when($centerId !== '', function ($query) use ($centerId, $company): void {
                $query->whereHas('activeEmploymentRelationship', function ($relationshipQuery) use ($centerId, $company): void {
                    $relationshipQuery->where('company_id', $company->id)
                        ->where('center_id', (int) $centerId);
                });
            })
            ->orderBy('full_name')
            ->orderBy('employee_code');
    }

    private function selectedWorkers($company)
    {
        $ids = collect($this->selectedWorkerIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Worker::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->whereIn('id', $ids)
            ->with(['activeEmploymentRelationship.center'])
            ->orderBy('full_name')
            ->get();
    }
    private function primaryAssignedWorkerIds($company, $workers)
    {
        if (! $this->showPrimaryAssignmentStatus || $workers->isEmpty()) {
            return collect();
        }

        return EmploymentUnitAssignment::query()
            ->where('company_id', $company->id)
            ->where('assignment_type', 'primary')
            ->where('status', 'active')
            ->whereHas('employmentRelationship', function ($query) use ($workers, $company): void {
                $query->where('company_id', $company->id)
                    ->whereIn('worker_id', $workers->pluck('id'));
            })
            ->with('employmentRelationship:id,worker_id')
            ->get()
            ->pluck('employmentRelationship.worker_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    private function currentCompanyOrFail(CurrentCompany $currentCompany)
    {
        $company = $currentCompany->get();

        abort_unless($company, 403);

        return $company;
    }
}; ?>

<div class="space-y-3">
    <div class="flex items-center justify-between gap-3">
        <div>
            <flux:heading>{{ $heading }}</flux:heading>
            <flux:subheading>{{ $subheading }}</flux:subheading>
        </div>

        <x-ui.badge>
            {{ $selectedWorkers->count() }} {{ $selectedWorkers->count() === 1 ? 'seleccionado' : 'seleccionados' }}
        </x-ui.badge>
    </div>

    @if ($selectedWorkers->isNotEmpty())
        <div class="max-h-24 overflow-y-auto pr-1">
            <div class="flex flex-wrap gap-2">
            @foreach ($selectedWorkers as $worker)
                <x-ui.badge variant="success" class="gap-2 px-3 py-1">
                    {{ $worker->employee_code }} - {{ $worker->full_name }}
                    <button type="button" wire:click="removeWorker({{ $worker->id }})" class="text-emerald-700 hover:text-emerald-950 dark:text-emerald-200">
                        Quitar
                    </button>
                </x-ui.badge>
            @endforeach
            </div>
        </div>
    @endif

    <div class="relative">
        <div class="flex h-10 w-full rounded-lg shadow-xs -space-x-px" role="group">
            <button
                type="button"
                wire:click="$toggle('open')"
                class="h-10 min-w-0 flex-1 rounded-s-lg border border-zinc-200 border-b-zinc-300/80 bg-white px-3 py-2 text-left text-sm font-medium leading-[1.375rem] text-zinc-700 hover:bg-zinc-50 focus:z-10 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary dark:border-white/10 dark:bg-white/10 dark:text-zinc-300 dark:hover:bg-white/15"
            >
                {{ $mode === 'single' ? 'Buscar trabajador' : 'Buscar y seleccionar trabajadores' }}
            </button>

            <button
                type="button"
                wire:click="$toggle('open')"
                aria-label="{{ $open ? 'Cerrar selector de trabajadores' : 'Abrir selector de trabajadores' }}"
                title="{{ $open ? 'Cerrar' : 'Abrir' }}"
                class="h-10 shrink-0 rounded-e-lg border border-zinc-200 border-b-zinc-300/80 bg-white px-3 text-sm font-medium leading-[1.375rem] text-zinc-600 hover:bg-accent hover:text-white focus:z-10 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary dark:border-white/10 dark:bg-white/10 dark:text-zinc-300 dark:hover:bg-accent dark:hover:text-white"
            >
                @if ($open)
                    <svg class="h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5" />
                    </svg>
                @else
                    <svg class="h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                    </svg>
                @endif
            </button>
        </div>

        @if ($open)
            <div class="absolute z-40 mt-2 w-full rounded-md border border-zinc-200 bg-white p-4 shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                <div class="grid gap-3 sm:grid-cols-2">
                    <flux:input
                        wire:model.live.debounce.350ms="search"
                        label="Buscar"
                        placeholder="Nombre o código"
                        autocomplete="off"
                    />

                    <flux:select label="Centro" wire:model.live="centerId">
                        <flux:select.option value="">Todos los centros</flux:select.option>
                        @foreach ($centers as $center)
                            <flux:select.option value="{{ $center->id }}">{{ $center->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <div class="mt-4 max-h-72 space-y-2 overflow-y-auto">
                    @forelse ($workerResults as $worker)
                        @php($hasPrimaryAssignment = $primaryAssignedWorkerIds->contains((int) $worker->id))
                        <label @class([
                            'flex cursor-pointer items-start gap-3 rounded-md border p-3 text-sm transition',
                            'border-emerald-300 bg-emerald-50 text-emerald-950 hover:border-emerald-400 hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-100' => $hasPrimaryAssignment,
                            'border-zinc-200 hover:border-zinc-400 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:border-zinc-500 dark:hover:bg-zinc-800' => ! $hasPrimaryAssignment,
                        ])>
                            <input
                                value="{{ $worker->id }}"
                                wire:model.live="selectedWorkerIds"
                                type="checkbox"
                                class="mt-1 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500 dark:border-zinc-600"
                            >
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-2 font-medium">
                                    <span @class([
                                        'text-emerald-950 dark:text-emerald-100' => $hasPrimaryAssignment,
                                        'text-zinc-900 dark:text-zinc-100' => ! $hasPrimaryAssignment,
                                    ])>
                                        {{ $worker->employee_code }} - {{ $worker->full_name }}
                                    </span>
                                    @if ($hasPrimaryAssignment)
                                        <x-ui.badge variant="success" class="text-[11px]">
                                            Ya asignado
                                        </x-ui.badge>
                                    @else
                                        <x-ui.badge variant="warning" class="text-[11px]">
                                            Sin unidad principal
                                        </x-ui.badge>
                                    @endif
                                </span>
                                <span @class([
                                    'block text-xs',
                                    'text-emerald-700 dark:text-emerald-200' => $hasPrimaryAssignment,
                                    'text-zinc-500 dark:text-zinc-400' => ! $hasPrimaryAssignment,
                                ])>
                                    {{ $worker->activeEmploymentRelationship?->center?->name ?? 'Sin centro activo' }} - {{ $worker->activeEmploymentRelationship?->position_name ?? 'Sin puesto activo' }}
                                </span>
                            </span>
                        </label>
                    @empty
                        <p class="rounded-md border border-dashed border-zinc-200 p-4 text-sm text-zinc-500 dark:border-zinc-700">
                            No se encontraron trabajadores activos.
                        </p>
                    @endforelse
                </div>

                @if ($selectedWorkers->isNotEmpty())
                    <div class="mt-4 flex justify-end">
                        <flux:button type="button" size="sm" variant="ghost" wire:click="clearSelection">
                            Limpiar selección
                        </flux:button>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
