<?php

use App\Domains\Scheduling\Actions\AssignScheduleProfileAction;
use App\Domains\Scheduling\Actions\DeleteScheduleProfileAssignmentIfUnusedAction;
use App\Domains\Scheduling\Actions\EndScheduleProfileAssignmentAction;
use App\Domains\Scheduling\Actions\ReplaceScheduleProfileAssignmentAction;
use App\Domains\Scheduling\Actions\ResolveScheduleProfileForRelationshipAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\EmploymentRelationship;
use App\Models\Center;
use App\Models\OrganizationalUnit;
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

        $this->authorizeAssignmentScope($company, $validated, $validated['effective_from']);

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

        $this->authorizeExistingAssignmentScope($company, $assignment, now()->toDateString());

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

        $this->authorizeExistingAssignmentScope($company, $assignment, $validated['effective_from']);

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
        Session::flash('status', 'Aplicación reemplazada sin borrar historial.');
    }

    public function openEndPanel(int $assignmentId, CurrentCompany $currentCompany): void
    {
        $assignment = $this->authorizedAssignment($assignmentId, $currentCompany);
        $company = $this->currentCompanyOrFail($currentCompany);
        $relationship = $assignment->assignment_scope === 'employment_relationship' ? $assignment->employmentRelationship : null;

        $this->authorizeExistingAssignmentScope($company, $assignment, now()->toDateString());

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

        $this->authorizeExistingAssignmentScope($company, $assignment, $validated['effective_to']);

        try {
            $action->handle($company, $assignment, $validated['effective_to']);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['endForm.effective_to' => $exception->getMessage()]);
        }

        $this->showEndPanel = false;
        $this->selectedAssignmentId = null;
        $this->endForm = $this->emptyEndForm();
        $this->resetPage();
        Session::flash('status', 'Aplicación finalizada. Se volverá a utilizar la configuración heredada.');
    }

    public function delete(int $assignmentId, CurrentCompany $currentCompany, DeleteScheduleProfileAssignmentIfUnusedAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $assignment = $this->authorizedAssignment($assignmentId, $currentCompany);
        $relationship = $assignment->assignment_scope === 'employment_relationship' ? $assignment->employmentRelationship : null;

        $this->authorizeExistingAssignmentScope($company, $assignment, now()->toDateString());

        try {
            $action->handle($company, $assignment);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['assignment' => $exception->getMessage()]);
        }

        $this->resetPage();
        Session::flash('status', 'Aplicación eliminada.');
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

    private function authorizeAssignmentScope($company, array $data, string $date): void
    {
        if ($data['assignment_scope'] === 'center') {
            $center = $company->centers()->whereKey((int) $data['center_id'])->firstOrFail();
            Gate::authorize('assignToCenter', [ScheduleProfile::class, $company, $center, $date]);

            return;
        }

        if ($data['assignment_scope'] === 'organizational_unit') {
            $unit = $company->organizationalUnits()->whereKey((int) $data['organizational_unit_id'])->firstOrFail();
            Gate::authorize('assignToUnit', [ScheduleProfile::class, $company, $unit, $date]);

            return;
        }

        $relationship = $data['assignment_scope'] === 'employment_relationship'
            ? $company->employmentRelationships()->whereKey((int) $data['employment_relationship_id'])->firstOrFail()
            : null;

        Gate::authorize('assign', [ScheduleProfile::class, $company, $data['assignment_scope'], $relationship, $date]);
    }

    private function authorizeExistingAssignmentScope($company, $assignment, string $date): void
    {
        if ($assignment->assignment_scope === 'center') {
            Gate::authorize('assignToCenter', [ScheduleProfile::class, $company, $assignment->center, $date]);

            return;
        }

        if ($assignment->assignment_scope === 'organizational_unit') {
            Gate::authorize('assignToUnit', [ScheduleProfile::class, $company, $assignment->organizationalUnit, $date]);

            return;
        }

        $relationship = $assignment->assignment_scope === 'employment_relationship' ? $assignment->employmentRelationship : null;
        Gate::authorize('assign', [ScheduleProfile::class, $company, $assignment->assignment_scope, $relationship, $date]);
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
            'employment_relationship' => 'Relación laboral',
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
            return 'Selecciona un modelo para ver cómo se interpretará la fecha.';
        }

        if ($profile->profile_type === 'pattern' && $profile->pattern_mode === 'weekly') {
            return 'Este horario se repite cada semana desde la fecha indicada. Solo aplica a trabajadores vigentes por día.';
        }

        if ($profile->profile_type === 'pattern' && $profile->pattern_mode === 'cycle') {
            return 'La fecha indicada será el Día 1 del ciclo. Desde ahí el rol se repite automáticamente.';
        }

        return match ($profile->profile_type) {
            'calendar' => 'Este modelo deja días pendientes para armar la programación semanal por demanda o CSV.',
            'flexible' => 'Este modelo genera jornadas flexibles esperadas, sin turno fijo.',
            'on_call' => 'Este modelo genera disponibilidad de guardia; no cuenta tiempo trabajado automáticamente.',
            default => 'Modelo de horario.',
        };
    }

    private function assignmentDateLabel(?ScheduleProfile $profile): string
    {
        return $profile && $profile->profile_type === 'pattern' && $profile->pattern_mode === 'cycle'
            ? 'Inicio del ciclo (Día 1)'
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

<section class="flex h-full w-full flex-1 flex-col gap-6 bg-surface-bg p-6 text-surface-text">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="font-display text-2xl font-bold text-brand-navy">Aplicación de modelos</h1>
            <p class="mt-1.5 text-[13.5px] text-surface-muted">Indica dónde aplica cada modelo: empresa, centro, unidad o trabajador. El horario semanal se repite; el ciclo usa la fecha inicial como Día 1.</p>
        </div>

        @if ($canAssignCompanyScopes || $canAssignRelationshipScope)
            <button type="button" class="btn-primary" wire:click="openAssignmentPanel">
                <span class="text-base leading-none">+</span>
                Aplicar modelo
            </button>
        @endif
    </div>

    @if (session('status'))
        <div class="rounded-xl border border-status-rest-line bg-status-rest-bg px-4 py-3 text-sm font-medium text-status-rest-text">{{ session('status') }}</div>
    @endif

    @error('assignment')
        <div class="rounded-xl border border-status-pending-line bg-status-pending-bg px-4 py-3 text-sm font-medium text-status-pending-text">{{ $message }}</div>
    @enderror

    <section class="rounded-2xl border border-surface-line bg-surface-card p-6 shadow-[0_20px_50px_-34px_rgba(2,25,57,0.22)]">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-[minmax(180px,1fr)_minmax(150px,.7fr)_minmax(220px,1.1fr)_auto] xl:items-end">
            <flux:input label="Resolver trabajador" placeholder="Clave o nombre" wire:model.live.debounce.350ms="resolveWorkerSearch" />
            <flux:input type="date" label="Fecha" wire:model.live="resolveForm.date" />
            <flux:input label="Buscar aplicaciones" placeholder="Modelo, clave o trabajador" wire:model.live.debounce.350ms="filters.search" />
            <button type="button" wire:click="$toggle('showAdvancedFilters')" class="btn-outline h-[42px] self-end justify-center">
                <span class="inline-flex items-center gap-2 leading-none">
                    <span class="text-lg leading-none">{{ $showAdvancedFilters ? '-' : '+' }}</span>
                    <span>Filtros</span>
                </span>
            </button>
        </div>

        @if ($showAdvancedFilters)
            <div class="mt-4 grid gap-3 border-t border-surface-line pt-4 md:grid-cols-2 lg:max-w-2xl">
                <flux:select label="Alcance" wire:model.live="filters.scope">
                    <flux:select.option value="all">Todos</flux:select.option>
                    <flux:select.option value="company">Empresa</flux:select.option>
                    <flux:select.option value="center">Centro</flux:select.option>
                    <flux:select.option value="organizational_unit">Unidad</flux:select.option>
                    <flux:select.option value="employment_relationship">Relación laboral</flux:select.option>
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
                    <button type="button" wire:click="selectResolveWorker({{ $worker->id }})" class="rounded-xl border border-surface-line bg-white p-3 text-left text-sm transition hover:border-brand-blue hover:bg-[#F5F9FF]">
                        <span class="block font-medium">{{ $worker->employee_code }} - {{ $worker->full_name }}</span>
                        <span class="text-xs text-surface-muted">{{ $worker->activeEmploymentRelationship?->center?->name ?? 'Sin centro' }} - {{ $worker->activeEmploymentRelationship?->position_name ?? 'Sin puesto' }}</span>
                    </button>
                @empty
                    <p class="text-sm text-surface-muted">No hay trabajadores disponibles.</p>
                @endforelse
            </div>
        @endif

        @if ($resolveWorker)
            <div class="mt-4 rounded-2xl border border-surface-line bg-[#F7F9FC] p-4 text-sm">
                <div class="grid gap-3 md:grid-cols-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-surface-muted">Trabajador</p>
                        <p class="font-semibold text-brand-navy">{{ $resolveWorker->employee_code }} - {{ $resolveWorker->full_name }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-surface-muted">Modelo efectivo</p>
                        <p class="font-semibold text-brand-navy">{{ $resolvedProfile['schedule_profile']?->name ?? 'Sin modelo' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-surface-muted">Origen</p>
                        <p class="font-semibold text-brand-navy">{{ $this->scopeLabel($resolvedProfile['assignment_scope'] ?? null) }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-surface-muted">Fecha resuelta</p>
                        <p class="font-semibold text-brand-navy">{{ $resolvedProfile['date'] ?? $resolveForm['date'] }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-surface-muted">Unidad principal usada</p>
                        <p class="font-semibold text-brand-navy">{{ $resolvedProfile['organizational_unit']?->name ?? 'Sin unidad principal' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-surface-muted">Centro</p>
                        <p class="font-semibold text-brand-navy">{{ $resolvedProfile['center']?->name ?? 'Sin centro' }}</p>
                    </div>
                </div>
            </div>
        @endif
    </section>

    <div class="table-wrap">
        <table class="w-full min-w-[920px] border-collapse">
            <thead>
                <tr class="table-row">
                    <th class="table-head-cell">Modelo</th>
                    <th class="table-head-cell">Alcance</th>
                    <th class="table-head-cell">Destino</th>
                    <th class="table-head-cell">Vigencia</th>
                    <th class="table-head-cell">Estado</th>
                    <th class="table-head-cell text-right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($assignments as $assignment)
                    <tr class="table-row">
                        <td class="table-cell">
                            <span class="block font-semibold text-brand-navy">{{ $assignment->scheduleProfile?->code }} - {{ $assignment->scheduleProfile?->name }}</span>
                            <span class="text-xs text-surface-muted">{{ $this->profileTypeLabel($assignment->scheduleProfile) }}</span>
                        </td>
                        <td class="table-cell">{{ $this->scopeLabel($assignment->assignment_scope) }}</td>
                        <td class="table-cell">{{ $this->assignmentTarget($assignment) }}</td>
                        <td class="table-cell">{{ $this->assignmentPeriodLabel($assignment) }}</td>
                        <td class="table-cell">
                            <span class="{{ $assignment->status === 'active' ? 'badge-success' : ($assignment->status === 'replaced' ? 'badge-warn' : 'badge-muted') }}"><span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ $assignment->status === 'active' ? 'Vigente' : ($assignment->status === 'inactive' ? 'Finalizada' : 'Reemplazada') }}</span>
                        </td>
                        <td class="table-cell">
                            <div class="flex justify-end gap-2">
                                @if ($assignment->status === 'active')
                                    <button type="button" class="btn-icon" wire:click="openReplacePanel({{ $assignment->id }})" aria-label="Reemplazar aplicación" title="Reemplazar"><svg class="h-4 w-4 text-surface-muted" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M16 3h5v5M21 3l-7 7M8 21H3v-5M3 21l7-7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                                    <button type="button" class="btn-icon" wire:click="openEndPanel({{ $assignment->id }})" aria-label="Finalizar aplicación" title="Finalizar"><svg class="h-4 w-4 text-status-warn-text" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14M12 5v14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                                @else
                                    <span class="text-xs text-surface-muted">Historial</span>
                                @endif
                                <button type="button" class="btn-icon" wire:click="delete({{ $assignment->id }})" wire:confirm="Eliminar esta aplicación solo si no generó horarios? Esta acción no se puede deshacer." aria-label="Eliminar aplicación" title="Eliminar"><svg class="h-4 w-4 text-status-pending-text" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6h16Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="table-row">
                        <td colspan="6" class="table-cell py-8 text-center text-surface-muted">No hay aplicaciones de modelos con los filtros actuales.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $assignments->links() }}

    <x-side-panel wire:model="showAssignmentPanel" title="Aplicar modelo de horario" subheading="El modelo genera borradores semanales; los horarios publicados conservan su versión." maxWidth="max-w-3xl">
        <form wire:submit="saveAssignment" class="space-y-5 p-6">
            <div class="grid gap-4 md:grid-cols-2">
                <flux:select label="Alcance" wire:model.live="assignmentForm.assignment_scope">
                    @if ($canAssignCompanyScopes)
                        <flux:select.option value="company">Empresa</flux:select.option>
                        <flux:select.option value="center">Centro</flux:select.option>
                        <flux:select.option value="organizational_unit">Área, departamento o equipo</flux:select.option>
                    @endif
                    <flux:select.option value="employment_relationship">Relación laboral</flux:select.option>
                </flux:select>

                <flux:select label="Modelo activo" wire:model="assignmentForm.schedule_profile_id">
                    <flux:select.option value="">Selecciona modelo</flux:select.option>
                    @foreach ($profiles as $profile)
                        <flux:select.option value="{{ $profile->id }}">{{ $profile->code }} - {{ $profile->name }} | {{ $this->profileTypeLabel($profile) }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="rounded-xl border border-brand-blue/20 bg-brand-blue/5 px-4 py-3 text-sm text-brand-navy">
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
                            <button type="button" wire:click="selectWorker({{ $worker->id }})" class="rounded-xl border border-surface-line bg-white p-3 text-left text-sm transition hover:border-brand-blue hover:bg-[#F5F9FF]">
                                <span class="block font-medium">{{ $worker->employee_code }} - {{ $worker->full_name }}</span>
                                <span class="text-xs text-surface-muted">{{ $worker->activeEmploymentRelationship?->center?->name ?? 'Sin centro' }} - {{ $worker->activeEmploymentRelationship?->position_name ?? 'Sin puesto' }}</span>
                            </button>
                        @empty
                            <p class="rounded-xl border border-dashed border-surface-line p-4 text-sm text-surface-muted">No hay trabajadores activos disponibles.</p>
                        @endforelse
                    </div>

                    @if ($selectedWorker)
                        <div class="flex items-center justify-between rounded-xl border border-status-rest-line bg-status-rest-bg p-3 text-sm text-status-rest-text">
                            <span>{{ $selectedWorker->employee_code }} - {{ $selectedWorker->full_name }}</span>
                            <button type="button" class="btn-ghost btn-sm" wire:click="clearSelectedWorker">Quitar</button>
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
                <p class="form-error">{{ $message }}</p>
            @enderror

            <div class="flex justify-end gap-3">
                <button type="button" class="btn-ghost" wire:click="closePanels">Cancelar</button>
                <button type="submit" class="btn-primary">Guardar</button>
            </div>
        </form>
    </x-side-panel>

    <x-side-panel wire:model="showReplacePanel" title="Reemplazar aplicación" subheading="La aplicación anterior queda reemplazada y se conserva en historial." maxWidth="max-w-md">
        <form wire:submit="replaceAssignment" class="space-y-5 p-6">
            <flux:select label="Nuevo modelo" wire:model="replaceForm.schedule_profile_id">
                <flux:select.option value="">Selecciona modelo</flux:select.option>
                @foreach ($profiles as $profile)
                    <flux:select.option value="{{ $profile->id }}">{{ $profile->code }} - {{ $profile->name }} | {{ $this->profileTypeLabel($profile) }}</flux:select.option>
                @endforeach
            </flux:select>
            <div class="rounded-xl border border-brand-blue/20 bg-brand-blue/5 px-4 py-3 text-sm text-brand-navy">
                {{ $this->profileApplicationHint($selectedReplaceProfile) }}
            </div>
            <flux:input type="date" label="{{ $this->assignmentDateLabel($selectedReplaceProfile) }}" wire:model="replaceForm.effective_from" />
            <flux:input type="date" label="Hasta opcional" wire:model="replaceForm.effective_to" />
            <flux:textarea label="Motivo" wire:model="replaceForm.reason" required />
            @error('replaceForm.effective_from')
                <p class="form-error">{{ $message }}</p>
            @enderror
            <div class="flex justify-end gap-3">
                <button type="button" class="btn-ghost" wire:click="closePanels">Cancelar</button>
                <button type="submit" class="btn-primary">Reemplazar</button>
            </div>
        </form>
    </x-side-panel>

    <x-side-panel wire:model="showEndPanel" title="Finalizar excepción" subheading="Al finalizar esta excepción, se volverá a utilizar la configuración heredada." maxWidth="max-w-md">
        <form wire:submit="endAssignment" class="space-y-5 p-6">
            <flux:input type="date" label="Finaliza el" wire:model="endForm.effective_to" />
            <flux:textarea label="Motivo" wire:model="endForm.reason" required />
            @error('endForm.effective_to')
                <p class="form-error">{{ $message }}</p>
            @enderror
            <div class="flex justify-end gap-3">
                <button type="button" class="btn-ghost" wire:click="closePanels">Cancelar</button>
                <button type="submit" class="btn-danger">Finalizar</button>
            </div>
        </form>
    </x-side-panel>
</section>
