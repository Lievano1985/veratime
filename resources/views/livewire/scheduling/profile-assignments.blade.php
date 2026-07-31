<?php

use App\Domains\Scheduling\Actions\AssignScheduleProfileAction;
use App\Domains\Scheduling\Actions\DeleteScheduleProfileAssignmentIfUnusedAction;
use App\Domains\Scheduling\Actions\EndScheduleProfileAssignmentAction;
use App\Domains\Scheduling\Actions\ReplaceScheduleProfileAssignmentAction;
use App\Domains\Scheduling\Actions\ResolveScheduleProfileForRelationshipAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\EmploymentRelationship;
use App\Models\ScheduleProfile;
use App\Models\ScheduleProfileAssignment;
use App\Models\Worker;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public array $assignmentForm = [];
    public array $replaceForm = [];
    public array $endForm = [];
    public array $resolveForm = [];
    public array $filters = [];
    public string $workerSearch = '';
    public string $resolveWorkerSearch = '';
    public bool $showAssignmentPanel = false;
    public bool $showReplacePanel = false;
    public bool $showEndPanel = false;
    public bool $showAdvancedFilters = false;
    public ?int $selectedWorkerId = null;
    public ?int $resolveWorkerId = null;
    public ?int $selectedAssignmentId = null;

    private const WORKER_LIMIT = 8;

    public function mount(): void
    {
        $this->assignmentForm = $this->emptyAssignmentForm();
        $this->replaceForm = $this->emptyReplaceForm();
        $this->endForm = $this->emptyEndForm();
        $this->resolveForm = ['date' => now()->toDateString()];
        $this->filters = ['scope' => 'all', 'status' => 'active', 'search' => ''];
    }

    public function updated($property): void
    {
        if (str_starts_with((string) $property, 'filters.')) {
            $this->resetPage();
        }

        if ($property === 'assignmentForm.assignment_scope') {
            $this->assignmentForm['center_id'] = '';
            $this->assignmentForm['organizational_unit_id'] = '';
            $this->assignmentForm['employment_relationship_id'] = '';
            $this->selectedWorkerId = null;
        }

        if ($property === 'assignmentForm.center_id') {
            $this->assignmentForm['organizational_unit_id'] = '';
        }
    }

    public function openAssignmentPanel(CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        Gate::authorize('viewAny', [ScheduleProfile::class, $company]);

        $this->assignmentForm = $this->emptyAssignmentForm();
        if (! Gate::allows('assign', [ScheduleProfile::class, $company, 'company', null, now()->toDateString()])) {
            $this->assignmentForm['assignment_scope'] = 'employment_relationship';
        }
        $this->selectedWorkerId = null;
        $this->showAssignmentPanel = true;
    }

    public function selectWorker(int $workerId, CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $relationship = $this->relationshipForWorker($company, $workerId, $this->assignmentForm['effective_from'] ?? now()->toDateString());

        Gate::authorize('assign', [ScheduleProfile::class, $company, 'employment_relationship', $relationship, $this->assignmentForm['effective_from'] ?? now()->toDateString()]);

        $this->selectedWorkerId = $workerId;
        $this->assignmentForm['employment_relationship_id'] = (string) $relationship->id;
        $this->workerSearch = '';
    }

    public function selectResolveWorker(int $workerId, CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $this->relationshipForWorker($company, $workerId, $this->resolveForm['date'] ?? now()->toDateString());

        $this->resolveWorkerId = $workerId;
        $this->resolveWorkerSearch = '';
    }

    public function clearSelectedWorker(): void
    {
        $this->selectedWorkerId = null;
        $this->assignmentForm['employment_relationship_id'] = '';
    }

    public function saveAssignment(CurrentCompany $currentCompany, AssignScheduleProfileAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $validated = $this->validateAssignmentForm($company);
        $profile = $company->scheduleProfiles()->where('status', 'active')->whereKey((int) $validated['schedule_profile_id'])->firstOrFail();
        $relationship = null;

        if ($validated['assignment_scope'] === 'employment_relationship') {
            $relationship = $company->employmentRelationships()->whereKey((int) $validated['employment_relationship_id'])->firstOrFail();
        }

        Gate::authorize('assign', [ScheduleProfile::class, $company, $validated['assignment_scope'], $relationship, $validated['effective_from']]);

        try {
            $action->handle($company, $profile, [
                ...$validated,
                'source' => 'manual',
            ], auth()->user());
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['assignmentForm.assignment_scope' => $exception->getMessage()]);
        }

        $this->showAssignmentPanel = false;
        $this->assignmentForm = $this->emptyAssignmentForm();
        $this->selectedWorkerId = null;
        $this->resetPage();
        Session::flash('status', 'Modelo aplicado.');
    }

    public function openReplacePanel(int $assignmentId, CurrentCompany $currentCompany): void
    {
        $assignment = $this->authorizedAssignment($assignmentId, $currentCompany);
        $company = $this->currentCompanyOrFail($currentCompany);
        $relationship = $assignment->assignment_scope === 'employment_relationship' ? $assignment->employmentRelationship : null;

        Gate::authorize('assign', [ScheduleProfile::class, $company, $assignment->assignment_scope, $relationship, now()->toDateString()]);

        $this->selectedAssignmentId = $assignment->id;
        $this->replaceForm = $this->emptyReplaceForm();
        $this->showReplacePanel = true;
    }

    public function replaceAssignment(CurrentCompany $currentCompany, ReplaceScheduleProfileAssignmentAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $assignment = $this->authorizedAssignment($this->selectedAssignmentId ?? 0, $currentCompany);
        $validated = $this->validate([
            'replaceForm.schedule_profile_id' => [
                'required',
                'integer',
                Rule::exists('schedule_profiles', 'id')->where('company_id', $company->id)->where('status', 'active'),
            ],
            'replaceForm.effective_from' => ['required', 'date'],
            'replaceForm.effective_to' => ['nullable', 'date', 'after_or_equal:replaceForm.effective_from'],
            'replaceForm.reason' => ['required', 'string', 'max:1000'],
        ])['replaceForm'];
        $relationship = $assignment->assignment_scope === 'employment_relationship' ? $assignment->employmentRelationship : null;

        Gate::authorize('assign', [ScheduleProfile::class, $company, $assignment->assignment_scope, $relationship, $validated['effective_from']]);

        $profile = $company->scheduleProfiles()->where('status', 'active')->whereKey((int) $validated['schedule_profile_id'])->firstOrFail();

        try {
            $action->handle($company, $assignment, $profile, [
                'effective_from' => $validated['effective_from'],
                'effective_to' => $validated['effective_to'] ?: null,
                'reason' => $validated['reason'],
                'source' => 'manual',
            ], auth()->user());
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['replaceForm.effective_from' => $exception->getMessage()]);
        }

        $this->showReplacePanel = false;
        $this->selectedAssignmentId = null;
        $this->replaceForm = $this->emptyReplaceForm();
        $this->resetPage();
        Session::flash('status', 'Aplicacion reemplazada sin borrar historial.');
    }

    public function openEndPanel(int $assignmentId, CurrentCompany $currentCompany): void
    {
        $assignment = $this->authorizedAssignment($assignmentId, $currentCompany);
        $company = $this->currentCompanyOrFail($currentCompany);
        $relationship = $assignment->assignment_scope === 'employment_relationship' ? $assignment->employmentRelationship : null;

        Gate::authorize('assign', [ScheduleProfile::class, $company, $assignment->assignment_scope, $relationship, now()->toDateString()]);

        $this->selectedAssignmentId = $assignment->id;
        $this->endForm = $this->emptyEndForm();
        $this->showEndPanel = true;
    }

    public function endAssignment(CurrentCompany $currentCompany, EndScheduleProfileAssignmentAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $assignment = $this->authorizedAssignment($this->selectedAssignmentId ?? 0, $currentCompany);
        $validated = $this->validate([
            'endForm.effective_to' => ['required', 'date'],
            'endForm.reason' => ['required', 'string', 'max:1000'],
        ])['endForm'];
        $relationship = $assignment->assignment_scope === 'employment_relationship' ? $assignment->employmentRelationship : null;

        Gate::authorize('assign', [ScheduleProfile::class, $company, $assignment->assignment_scope, $relationship, $validated['effective_to']]);

        try {
            $action->handle($company, $assignment, $validated['effective_to']);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['endForm.effective_to' => $exception->getMessage()]);
        }

        $this->showEndPanel = false;
        $this->selectedAssignmentId = null;
        $this->endForm = $this->emptyEndForm();
        $this->resetPage();
        Session::flash('status', 'Aplicacion finalizada. Se volvera a utilizar la configuracion heredada.');
    }

    public function delete(int $assignmentId, CurrentCompany $currentCompany, DeleteScheduleProfileAssignmentIfUnusedAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $assignment = $this->authorizedAssignment($assignmentId, $currentCompany);
        $relationship = $assignment->assignment_scope === 'employment_relationship' ? $assignment->employmentRelationship : null;

        Gate::authorize('assign', [ScheduleProfile::class, $company, $assignment->assignment_scope, $relationship, now()->toDateString()]);

        try {
            $action->handle($company, $assignment);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['assignment' => $exception->getMessage()]);
        }

        $this->resetPage();
        Session::flash('status', 'Aplicacion eliminada.');
    }

    public function closePanels(): void
    {
        $this->showAssignmentPanel = false;
        $this->showReplacePanel = false;
        $this->showEndPanel = false;
        $this->selectedAssignmentId = null;
        $this->resetValidation();
    }

    public function with(CurrentCompany $currentCompany, ResolveScheduleProfileForRelationshipAction $resolver): array
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        Gate::authorize('viewAny', [ScheduleProfile::class, $company]);

        $resolveRelationship = $this->resolveWorkerId
            ? $this->relationshipForWorker($company, $this->resolveWorkerId, $this->resolveForm['date'] ?? now()->toDateString(), false)
            : null;
        $resolved = $resolveRelationship
            ? $resolver->handle($company, $resolveRelationship, $this->resolveForm['date'] ?? now()->toDateString())
            : null;

        $canAssignCompanyScopes = Gate::allows('assign', [ScheduleProfile::class, $company, 'company', null, now()->toDateString()]);
        $profiles = $company->scheduleProfiles()->where('status', 'active')->orderBy('name')->get();

        return [
            'company' => $company,
            'canAssignCompanyScopes' => $canAssignCompanyScopes,
            'canAssignRelationshipScope' => Gate::allows('viewAny', [ScheduleProfile::class, $company]),
            'profiles' => $profiles,
            'selectedAssignmentProfile' => $profiles->firstWhere('id', (int) ($this->assignmentForm['schedule_profile_id'] ?? 0)),
            'selectedReplaceProfile' => $profiles->firstWhere('id', (int) ($this->replaceForm['schedule_profile_id'] ?? 0)),
            'centers' => $company->centers()->where('status', 'active')->orderBy('name')->get(),
            'units' => $this->unitOptions($company),
            'assignments' => $this->assignmentQuery($company)->paginate(12),
            'workerResults' => $this->workerResults($company, $this->workerSearch, $this->assignmentForm['center_id'] ?? ''),
            'resolveWorkerResults' => $this->workerResults($company, $this->resolveWorkerSearch, ''),
            'selectedWorker' => $this->selectedWorker($company, $this->selectedWorkerId),
            'resolveWorker' => $this->selectedWorker($company, $this->resolveWorkerId),
            'resolvedProfile' => $resolved,
        ];
    }

    private function validateAssignmentForm($company): array
    {
        $rules = [
            'assignmentForm.assignment_scope' => ['required', Rule::in(['company', 'center', 'organizational_unit', 'employment_relationship'])],
            'assignmentForm.schedule_profile_id' => [
                'required',
                'integer',
                Rule::exists('schedule_profiles', 'id')->where('company_id', $company->id)->where('status', 'active'),
            ],
            'assignmentForm.center_id' => ['nullable', 'integer'],
            'assignmentForm.organizational_unit_id' => ['nullable', 'integer'],
            'assignmentForm.employment_relationship_id' => ['nullable', 'integer'],
            'assignmentForm.effective_from' => ['required', 'date'],
            'assignmentForm.effective_to' => ['nullable', 'date', 'after_or_equal:assignmentForm.effective_from'],
            'assignmentForm.reason' => ['nullable', 'string', 'max:1000'],
        ];

        $validated = $this->validate($rules)['assignmentForm'];
        $scope = $validated['assignment_scope'];

        if ($scope === 'company') {
            $validated['center_id'] = null;
            $validated['organizational_unit_id'] = null;
            $validated['employment_relationship_id'] = null;
        } elseif ($scope === 'center') {
            $company->centers()->where('status', 'active')->whereKey((int) $validated['center_id'])->firstOrFail();
            $validated['organizational_unit_id'] = null;
            $validated['employment_relationship_id'] = null;
        } elseif ($scope === 'organizational_unit') {
            $unit = $company->organizationalUnits()
                ->where('status', 'active')
                ->when(filled($validated['center_id'] ?? null), fn ($query) => $query->where('center_id', (int) $validated['center_id']))
                ->whereKey((int) $validated['organizational_unit_id'])
                ->firstOrFail();
            $validated['center_id'] = null;
            $validated['organizational_unit_id'] = $unit->id;
            $validated['employment_relationship_id'] = null;
        } else {
            $relationship = $company->employmentRelationships()->whereKey((int) $validated['employment_relationship_id'])->firstOrFail();
            $validated['center_id'] = null;
            $validated['organizational_unit_id'] = null;
            $validated['employment_relationship_id'] = $relationship->id;
        }

        return $validated;
    }

    private function assignmentQuery($company)
    {
        $scope = trim((string) ($this->filters['scope'] ?? 'all'));
        $status = trim((string) ($this->filters['status'] ?? 'active'));
        $search = trim((string) ($this->filters['search'] ?? ''));

        return $company->scheduleProfileAssignments()
            ->with(['scheduleProfile', 'center', 'organizationalUnit.center', 'employmentRelationship.worker', 'employmentRelationship.center'])
            ->when($scope !== 'all', fn ($query) => $query->where('assignment_scope', $scope))
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereHas('scheduleProfile', fn ($profileQuery) => $profileQuery
                    ->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%"))
                    ->orWhereHas('employmentRelationship.worker', fn ($workerQuery) => $workerQuery
                        ->where('employee_code', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%"));
            })
            ->orderByDesc('effective_from')
            ->orderByDesc('id');
    }

    private function workerResults($company, string $search, string $centerId)
    {
        $search = trim($search);
        $centerId = trim($centerId);

        return $company->workers()
            ->where('status', 'active')
            ->with(['activeEmploymentRelationship.center'])
            ->whereHas('activeEmploymentRelationship', function ($query) use ($company, $centerId): void {
                $query->where('company_id', $company->id)
                    ->when($centerId !== '', fn ($centerQuery) => $centerQuery->where('center_id', (int) $centerId));
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(fn ($searchQuery) => $searchQuery
                    ->where('employee_code', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%"));
            })
            ->orderBy('full_name')
            ->limit(self::WORKER_LIMIT)
            ->get()
            ->filter(fn (Worker $worker): bool => $this->workerVisibleToUser($company, $worker))
            ->values();
    }

    private function workerVisibleToUser($company, Worker $worker): bool
    {
        $relationship = $worker->activeEmploymentRelationship;

        if (! $relationship) {
            return false;
        }

        return Gate::allows('assign', [ScheduleProfile::class, $company, 'company', null, now()->toDateString()])
            || Gate::allows('assign', [ScheduleProfile::class, $company, 'employment_relationship', $relationship, now()->toDateString()]);
    }

    private function unitOptions($company)
    {
        $centerId = (int) ($this->assignmentForm['center_id'] ?? 0);

        return $company->organizationalUnits()
            ->with('center')
            ->where('status', 'active')
            ->when($centerId > 0, fn ($query) => $query->where('center_id', $centerId))
            ->orderBy('name')
            ->get();
    }

    private function relationshipForWorker($company, int $workerId, string $date, bool $fail = true): ?EmploymentRelationship
    {
        $query = EmploymentRelationship::query()
            ->where('company_id', $company->id)
            ->where('worker_id', $workerId)
            ->where('status', 'active')
            ->whereDate('started_at', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query->whereNull('ended_at')->orWhereDate('ended_at', '>=', $date);
            })
            ->latest('started_at');

        return $fail ? $query->firstOrFail() : $query->first();
    }

    private function selectedWorker($company, ?int $workerId): ?Worker
    {
        if (! $workerId) {
            return null;
        }

        return $company->workers()->with('activeEmploymentRelationship.center')->where('status', 'active')->whereKey($workerId)->first();
    }

    private function authorizedAssignment(int $assignmentId, CurrentCompany $currentCompany): ScheduleProfileAssignment
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        return $company->scheduleProfileAssignments()
            ->with(['employmentRelationship.worker', 'scheduleProfile', 'center', 'organizationalUnit'])
            ->whereKey($assignmentId)
            ->firstOrFail();
    }

    private function currentCompanyOrFail(CurrentCompany $currentCompany)
    {
        $company = $currentCompany->get();
        abort_unless($company, 403);

        return $company;
    }

    private function scopeLabel(?string $scope): string
    {
        return match ($scope) {
            'company' => 'Empresa',
            'center' => 'Centro',
            'organizational_unit' => 'Unidad organizacional',
            'employment_relationship' => 'Relacion laboral',
            default => 'Sin modelo',
        };
    }

    private function profileTypeLabel(?ScheduleProfile $profile): string
    {
        if (! $profile) {
            return 'Sin modelo';
        }

        return match ($profile->profile_type) {
            'pattern' => $profile->pattern_mode === 'weekly' ? 'Horario fijo semanal' : 'Rol rotativo / ciclo',
            'calendar' => 'Programacion semanal manual',
            'flexible' => 'Flexible avanzado',
            'on_call' => 'Guardia avanzada',
            default => 'Tipo no reconocido',
        };
    }

    private function profileApplicationHint(?ScheduleProfile $profile): string
    {
        if (! $profile) {
            return 'Selecciona un modelo para ver como se interpretara la fecha.';
        }

        if ($profile->profile_type === 'pattern' && $profile->pattern_mode === 'weekly') {
            return 'Este horario se repite cada semana desde la fecha indicada. Solo aplica a trabajadores vigentes por dia.';
        }

        if ($profile->profile_type === 'pattern' && $profile->pattern_mode === 'cycle') {
            return 'La fecha indicada sera el Dia 1 del ciclo. Desde ahi el rol se repite automaticamente.';
        }

        return match ($profile->profile_type) {
            'calendar' => 'Este modelo deja dias pendientes para armar la programacion semanal por demanda o CSV.',
            'flexible' => 'Este modelo genera jornadas flexibles esperadas, sin turno fijo.',
            'on_call' => 'Este modelo genera disponibilidad de guardia; no cuenta tiempo trabajado automaticamente.',
            default => 'Modelo de horario.',
        };
    }

    private function assignmentDateLabel(?ScheduleProfile $profile): string
    {
        return $profile && $profile->profile_type === 'pattern' && $profile->pattern_mode === 'cycle'
            ? 'Inicio del ciclo (Dia 1)'
            : 'Vigente desde';
    }

    private function assignmentPeriodLabel(ScheduleProfileAssignment $assignment): string
    {
        $from = $assignment->effective_from?->toDateString();
        $to = $assignment->effective_to?->toDateString() ?? 'Abierta';
        $profile = $assignment->scheduleProfile;

        if ($profile && $profile->profile_type === 'pattern' && $profile->pattern_mode === 'cycle') {
            return 'Dia 1: '.$from.' - '.$to;
        }

        return $from.' - '.$to;
    }

    private function assignmentTarget(ScheduleProfileAssignment $assignment): string
    {
        return match ($assignment->assignment_scope) {
            'company' => 'Toda la empresa',
            'center' => $assignment->center?->name ?? 'Centro',
            'organizational_unit' => trim(($assignment->organizationalUnit?->name ?? 'Unidad').' - '.($assignment->organizationalUnit?->center?->name ?? '')),
            'employment_relationship' => trim(($assignment->employmentRelationship?->worker?->employee_code ?? '').' - '.($assignment->employmentRelationship?->worker?->full_name ?? '')),
            default => 'Sin alcance',
        };
    }

    private function emptyAssignmentForm(): array
    {
        return [
            'assignment_scope' => 'company',
            'schedule_profile_id' => '',
            'center_id' => '',
            'organizational_unit_id' => '',
            'employment_relationship_id' => '',
            'effective_from' => now()->toDateString(),
            'effective_to' => '',
            'reason' => '',
        ];
    }

    private function emptyReplaceForm(): array
    {
        return [
            'schedule_profile_id' => '',
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
    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        <div>
            <flux:heading size="xl">Aplicacion de modelos</flux:heading>
            <flux:subheading>Indica donde aplica cada modelo: empresa, centro, unidad o trabajador. El horario semanal se repite; el ciclo usa la fecha inicial como Dia 1.</flux:subheading>
        </div>

        @if ($canAssignCompanyScopes || $canAssignRelationshipScope)
            <flux:button type="button" variant="primary" wire:click="openAssignmentPanel" icon="plus">
                Aplicar modelo
            </flux:button>
        @endif
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">{{ session('status') }}</div>
    @endif

    @error('assignment')
        <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">{{ $message }}</div>
    @enderror

    <section class="rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-900/60">
        <div class="grid gap-3 lg:grid-cols-[minmax(180px,1fr)_minmax(150px,.7fr)_minmax(220px,1.1fr)_auto] lg:items-end">
            <flux:input label="Resolver trabajador" placeholder="Clave o nombre" wire:model.live.debounce.350ms="resolveWorkerSearch" />
            <flux:input type="date" label="Fecha" wire:model.live="resolveForm.date" />
            <flux:input label="Buscar aplicaciones" placeholder="Modelo, clave o trabajador" wire:model.live.debounce.350ms="filters.search" />
            <flux:button type="button" variant="ghost" wire:click="$toggle('showAdvancedFilters')">
                <span class="inline-flex items-center gap-1.5 leading-none">
                    <span class="text-base leading-none">{{ $showAdvancedFilters ? '-' : '+' }}</span>
                    <span>Filtros</span>
                </span>
            </flux:button>
        </div>

        @if ($showAdvancedFilters)
            <div class="mt-3 grid gap-3 border-t border-zinc-100 pt-3 dark:border-zinc-800 md:grid-cols-2 lg:max-w-2xl">
                <flux:select label="Alcance" wire:model.live="filters.scope">
                    <flux:select.option value="all">Todos</flux:select.option>
                    <flux:select.option value="company">Empresa</flux:select.option>
                    <flux:select.option value="center">Centro</flux:select.option>
                    <flux:select.option value="organizational_unit">Unidad</flux:select.option>
                    <flux:select.option value="employment_relationship">Relacion laboral</flux:select.option>
                </flux:select>
                <flux:select label="Estado" wire:model.live="filters.status">
                    <flux:select.option value="active">Vigentes</flux:select.option>
                    <flux:select.option value="inactive">Finalizadas</flux:select.option>
                    <flux:select.option value="replaced">Reemplazadas</flux:select.option>
                    <flux:select.option value="all">Todas</flux:select.option>
                </flux:select>
            </div>
        @endif

        @if ($resolveWorkerSearch !== '')
            <div class="mt-3 grid gap-2 md:grid-cols-2">
                @forelse ($resolveWorkerResults as $worker)
                    <button type="button" wire:click="selectResolveWorker({{ $worker->id }})" class="rounded-md border border-zinc-200 p-3 text-left text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                        <span class="block font-medium">{{ $worker->employee_code }} - {{ $worker->full_name }}</span>
                        <span class="text-xs text-zinc-500">{{ $worker->activeEmploymentRelationship?->center?->name ?? 'Sin centro' }} - {{ $worker->activeEmploymentRelationship?->position_name ?? 'Sin puesto' }}</span>
                    </button>
                @empty
                    <p class="text-sm text-zinc-500">No hay trabajadores disponibles.</p>
                @endforelse
            </div>
        @endif

        @if ($resolveWorker)
            <div class="mt-4 rounded-md border border-zinc-200 bg-zinc-50 p-4 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                <div class="grid gap-3 md:grid-cols-3">
                    <div>
                        <p class="text-xs uppercase text-zinc-500">Trabajador</p>
                        <p class="font-medium">{{ $resolveWorker->employee_code }} - {{ $resolveWorker->full_name }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-zinc-500">Modelo efectivo</p>
                        <p class="font-medium">{{ $resolvedProfile['schedule_profile']?->name ?? 'Sin modelo' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-zinc-500">Origen</p>
                        <p class="font-medium">{{ $this->scopeLabel($resolvedProfile['assignment_scope'] ?? null) }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-zinc-500">Fecha resuelta</p>
                        <p class="font-medium">{{ $resolvedProfile['date'] ?? $resolveForm['date'] }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-zinc-500">Unidad principal usada</p>
                        <p class="font-medium">{{ $resolvedProfile['organizational_unit']?->name ?? 'Sin unidad principal' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-zinc-500">Centro</p>
                        <p class="font-medium">{{ $resolvedProfile['center']?->name ?? 'Sin centro' }}</p>
                    </div>
                </div>
            </div>
        @endif
    </section>

    <div class="overflow-hidden rounded-md border border-zinc-200 dark:border-zinc-700">
        <table class="w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
            <thead class="bg-zinc-50 text-left text-xs font-medium uppercase text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                <tr>
                    <th class="px-4 py-3">Modelo</th>
                    <th class="px-4 py-3">Alcance</th>
                    <th class="px-4 py-3">Destino</th>
                    <th class="px-4 py-3">Vigencia</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 [&>tr:nth-child(odd)]:bg-white [&>tr:nth-child(even)]:bg-zinc-50/60 dark:divide-zinc-700 dark:[&>tr:nth-child(odd)]:bg-zinc-900 dark:[&>tr:nth-child(even)]:bg-zinc-800/40">
                @forelse ($assignments as $assignment)
                    <tr>
                        <td class="px-4 py-3">
                            <span class="block font-medium text-zinc-900 dark:text-zinc-100">{{ $assignment->scheduleProfile?->code }} - {{ $assignment->scheduleProfile?->name }}</span>
                            <span class="text-xs text-zinc-500">{{ $this->profileTypeLabel($assignment->scheduleProfile) }}</span>
                        </td>
                        <td class="px-4 py-3">{{ $this->scopeLabel($assignment->assignment_scope) }}</td>
                        <td class="px-4 py-3">{{ $this->assignmentTarget($assignment) }}</td>
                        <td class="px-4 py-3">{{ $this->assignmentPeriodLabel($assignment) }}</td>
                        <td class="px-4 py-3">
                            <x-ui.badge variant="{{ $assignment->status === 'active' ? 'success' : ($assignment->status === 'replaced' ? 'warning' : 'neutral') }}">
                                {{ $assignment->status === 'active' ? 'Vigente' : ($assignment->status === 'inactive' ? 'Finalizada' : 'Reemplazada') }}
                            </x-ui.badge>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                @if ($assignment->status === 'active')
                                    <flux:button size="xs" variant="ghost" wire:click="openReplacePanel({{ $assignment->id }})">Reemplazar</flux:button>
                                    <flux:button size="xs" variant="danger" wire:click="openEndPanel({{ $assignment->id }})">Finalizar</flux:button>
                                @else
                                    <span class="text-xs text-zinc-500">Historial</span>
                                @endif
                                <flux:button size="xs" variant="danger" wire:click="delete({{ $assignment->id }})" wire:confirm="Eliminar esta aplicacion solo si no genero horarios? Esta accion no se puede deshacer.">Eliminar</flux:button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-zinc-500">No hay aplicaciones de modelos con los filtros actuales.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $assignments->links() }}

    <x-side-panel wire:model="showAssignmentPanel" title="Aplicar modelo de horario" subheading="El modelo genera borradores semanales; los horarios publicados conservan su version." maxWidth="max-w-3xl">
        <form wire:submit="saveAssignment" class="space-y-5 p-6">
            <div class="grid gap-4 md:grid-cols-2">
                <flux:select label="Alcance" wire:model.live="assignmentForm.assignment_scope">
                    @if ($canAssignCompanyScopes)
                        <flux:select.option value="company">Empresa</flux:select.option>
                        <flux:select.option value="center">Centro</flux:select.option>
                        <flux:select.option value="organizational_unit">Area, departamento o equipo</flux:select.option>
                    @endif
                    <flux:select.option value="employment_relationship">Relacion laboral</flux:select.option>
                </flux:select>

                <flux:select label="Modelo activo" wire:model="assignmentForm.schedule_profile_id">
                    <flux:select.option value="">Selecciona modelo</flux:select.option>
                    @foreach ($profiles as $profile)
                        <flux:select.option value="{{ $profile->id }}">{{ $profile->code }} - {{ $profile->name }} | {{ $this->profileTypeLabel($profile) }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="rounded-md border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                {{ $this->profileApplicationHint($selectedAssignmentProfile) }}
            </div>

            @if (($assignmentForm['assignment_scope'] ?? 'company') === 'center')
                <flux:select label="Centro" wire:model="assignmentForm.center_id">
                    <flux:select.option value="">Selecciona centro</flux:select.option>
                    @foreach ($centers as $center)
                        <flux:select.option value="{{ $center->id }}">{{ $center->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            @if (($assignmentForm['assignment_scope'] ?? 'company') === 'organizational_unit')
                <div class="grid gap-4 md:grid-cols-2">
                    <flux:select label="Centro" wire:model.live="assignmentForm.center_id">
                        <flux:select.option value="">Selecciona centro</flux:select.option>
                        @foreach ($centers as $center)
                            <flux:select.option value="{{ $center->id }}">{{ $center->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select label="Unidad" wire:model="assignmentForm.organizational_unit_id">
                        <flux:select.option value="">Selecciona unidad</flux:select.option>
                        @foreach ($units as $unit)
                            <flux:select.option value="{{ $unit->id }}">{{ $unit->code }} - {{ $unit->name }} ({{ $unit->unit_type }})</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            @endif

            @if (($assignmentForm['assignment_scope'] ?? 'company') === 'employment_relationship')
                <div class="space-y-3">
                    <div class="grid gap-4 md:grid-cols-2">
                        <flux:input label="Buscar trabajador" placeholder="Clave o nombre" wire:model.live.debounce.350ms="workerSearch" />
                        <flux:select label="Filtrar por centro" wire:model.live="assignmentForm.center_id">
                            <flux:select.option value="">Todos</flux:select.option>
                            @foreach ($centers as $center)
                                <flux:select.option value="{{ $center->id }}">{{ $center->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <div class="grid gap-2">
                        @forelse ($workerResults as $worker)
                            <button type="button" wire:click="selectWorker({{ $worker->id }})" class="rounded-md border border-zinc-200 p-3 text-left text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                                <span class="block font-medium">{{ $worker->employee_code }} - {{ $worker->full_name }}</span>
                                <span class="text-xs text-zinc-500">{{ $worker->activeEmploymentRelationship?->center?->name ?? 'Sin centro' }} - {{ $worker->activeEmploymentRelationship?->position_name ?? 'Sin puesto' }}</span>
                            </button>
                        @empty
                            <p class="rounded-md border border-dashed border-zinc-200 p-4 text-sm text-zinc-500 dark:border-zinc-700">No hay trabajadores activos disponibles.</p>
                        @endforelse
                    </div>

                    @if ($selectedWorker)
                        <div class="flex items-center justify-between rounded-md bg-emerald-50 p-3 text-sm text-emerald-900 dark:bg-emerald-950 dark:text-emerald-100">
                            <span>{{ $selectedWorker->employee_code }} - {{ $selectedWorker->full_name }}</span>
                            <flux:button type="button" size="xs" variant="ghost" wire:click="clearSelectedWorker">Quitar</flux:button>
                        </div>
                    @endif
                </div>
            @endif

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input type="date" label="{{ $this->assignmentDateLabel($selectedAssignmentProfile) }}" wire:model="assignmentForm.effective_from" />
                <flux:input type="date" label="Hasta opcional" wire:model="assignmentForm.effective_to" />
            </div>
            <flux:textarea label="Motivo" wire:model="assignmentForm.reason" />

            @error('assignmentForm.assignment_scope')
                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror

            <div class="flex justify-end gap-3">
                <flux:button type="button" variant="ghost" wire:click="closePanels">Cancelar</flux:button>
                <flux:button type="submit" variant="primary">Guardar</flux:button>
            </div>
        </form>
    </x-side-panel>

    <x-side-panel wire:model="showReplacePanel" title="Reemplazar aplicacion" subheading="La aplicacion anterior queda reemplazada y se conserva en historial." maxWidth="max-w-md">
        <form wire:submit="replaceAssignment" class="space-y-5 p-6">
            <flux:select label="Nuevo modelo" wire:model="replaceForm.schedule_profile_id">
                <flux:select.option value="">Selecciona modelo</flux:select.option>
                @foreach ($profiles as $profile)
                    <flux:select.option value="{{ $profile->id }}">{{ $profile->code }} - {{ $profile->name }} | {{ $this->profileTypeLabel($profile) }}</flux:select.option>
                @endforeach
            </flux:select>
            <div class="rounded-md border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                {{ $this->profileApplicationHint($selectedReplaceProfile) }}
            </div>
            <flux:input type="date" label="{{ $this->assignmentDateLabel($selectedReplaceProfile) }}" wire:model="replaceForm.effective_from" />
            <flux:input type="date" label="Hasta opcional" wire:model="replaceForm.effective_to" />
            <flux:textarea label="Motivo" wire:model="replaceForm.reason" required />
            @error('replaceForm.effective_from')
                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
            <div class="flex justify-end gap-3">
                <flux:button type="button" variant="ghost" wire:click="closePanels">Cancelar</flux:button>
                <flux:button type="submit" variant="primary">Reemplazar</flux:button>
            </div>
        </form>
    </x-side-panel>

    <x-side-panel wire:model="showEndPanel" title="Finalizar excepcion" subheading="Al finalizar esta excepcion, se volvera a utilizar la configuracion heredada." maxWidth="max-w-md">
        <form wire:submit="endAssignment" class="space-y-5 p-6">
            <flux:input type="date" label="Finaliza el" wire:model="endForm.effective_to" />
            <flux:textarea label="Motivo" wire:model="endForm.reason" required />
            @error('endForm.effective_to')
                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
            <div class="flex justify-end gap-3">
                <flux:button type="button" variant="ghost" wire:click="closePanels">Cancelar</flux:button>
                <flux:button type="submit" variant="primary">Finalizar</flux:button>
            </div>
        </form>
    </x-side-panel>
</section>
