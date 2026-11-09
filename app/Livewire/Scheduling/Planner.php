<?php

namespace App\Livewire\Scheduling;

use App\Domains\Organization\Actions\ResolveEmploymentUnitsForDateAction;
use App\Domains\Scheduling\Actions\AddMissingWorkersToDraftScheduleAction;
use App\Domains\Scheduling\Actions\ApplyPlannerScheduleAction;
use App\Domains\Scheduling\Actions\BulkReplaceDraftDailyScheduleAssignmentsAction;
use App\Domains\Scheduling\Actions\ClonePublishedScheduleWeeksAction;
use App\Domains\Scheduling\Actions\CopyPlannerRowAction;
use App\Domains\Scheduling\Actions\CreateCorrectiveScheduleBatchAction;
use App\Domains\Scheduling\Actions\CreateScheduleBatchAction;
use App\Domains\Scheduling\Actions\DeleteDraftScheduleBatchAction;
use App\Domains\Scheduling\Actions\GenerateDraftScheduleBatchFromProfilesAction;
use App\Domains\Scheduling\Actions\PrepareNextScheduleWeekAction;
use App\Domains\Scheduling\Actions\PublishCorrectiveScheduleBatchAction;
use App\Domains\Scheduling\Actions\PublishScheduleBatchAction;
use App\Domains\Scheduling\Actions\ResetDraftScheduleBatchAction;
use App\Domains\Scheduling\Actions\ValidateCorrectiveScheduleBatchForPublicationAction;
use App\Domains\Scheduling\Actions\ValidateScheduleBatchForPublicationAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\Center;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\ScheduleBatch;
use App\Models\ShiftTemplate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Planner extends Component
{
    use WithPagination;

    #[Url]
    public string $centerId = '';

    #[Url]
    public string $week = '';

    public int $weekSearchYear = 2026;

    public int $weekSearchNumber = 1;

    #[Locked]
    public ?int $batchId = null;

    public string $search = '';

    public string $unitId = '';

    public array $people = [];

    public array $cells = [];

    public string $brush = 'rest';

    public bool $showAssignment = false;

    public bool $showReview = false;

    public bool $showResetConfirmation = false;

    #[Locked]
    public array $review = [];

    public string $notice = '';

    public string $mode = 'manual';

    public string $name = '';

    public string $profileId = '';

    public string $effectiveFrom = '';

    public string $effectiveTo = '';

    public bool $replaceDays = false;

    public bool $replaceHabitual = false;

    public array $rules = [];

    public array $cycle = [];

    public array $workdays = [1, 2, 3, 4, 5];

    public string $fillTurn = '';

    public string $cycleTurn = 'rest';

    public int $cycleDays = 2;

    public int $futureWeeks = 1;

    public string $copyTarget = '';

    public int $copyWeekSearchYear = 2026;

    public int $copyWeekSearchNumber = 1;

    public int $cloneWeeks = 1;

    public string $cloneMode = 'draft';

    public string $copySource = '';

    public bool $replaceCopiedDays = false;

    public string $correctionReason = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', [ScheduleBatch::class, $this->company()]);
        $this->week = $this->normalizeWeek($this->week ?: now()->toDateString());
        $centers = $this->centers();
        $this->centerId = $this->centerId ?: (string) ($centers->first()?->id ?? '');
        $this->resolveWeek();
        $this->rules = array_fill(1, 7, 'rest');
        $this->cycle = [];
    }

    private function company(): Company
    {
        $company = app(CurrentCompany::class)->get();
        abort_unless($company, 403);

        return $company;
    }

    private function centers()
    {
        $company = $this->company();

        return $company->centers()->where('status', 'active')->orderBy('name')->get()
            ->filter(fn ($center) => Gate::allows('createForCenter', [ScheduleBatch::class, $company, $center]));
    }

    private function center(): Center
    {
        $center = $this->company()->centers()->findOrFail($this->centerId);
        Gate::authorize('createForCenter', [ScheduleBatch::class, $this->company(), $center]);

        return $center;
    }

    private function batch(): ScheduleBatch
    {
        $batch = $this->company()->scheduleBatches()->with('center')->findOrFail($this->batchId);
        $center = $this->center();
        abort_unless($batch->center_id === $center->id && $batch->period_start->toDateString() === $this->week, 403);
        Gate::authorize('view', $batch);

        return $batch;
    }

    private function normalizeWeek(string $value): string
    {
        validator(['week' => $value], ['week' => 'required|date_format:Y-m-d'])->validate();

        return CarbonImmutable::parse($value)->startOfWeek()->toDateString();
    }

    public function updated($property): void
    {
        if ($property === 'copyTarget') {
            $this->copyTarget = $this->normalizeWeek($this->copyTarget);
            $this->syncCopyWeekSearch();
        }
        if (in_array($property, ['centerId', 'week'], true)) {
            $this->week = $this->normalizeWeek($this->week);
            $this->unitId = '';
            $this->resolveWeek();
        }
        if (in_array($property, ['search', 'unitId'], true)) {
            $this->resetPage();
        }
        if (in_array($property, ['mode', 'profileId'], true)) {
            $this->replaceHabitual = false;
        }
        $this->review = [];
        $this->showReview = false;
    }

    private function resolveWeek(): void
    {
        $date = CarbonImmutable::parse($this->week);
        $this->weekSearchYear = $date->isoWeekYear();
        $this->weekSearchNumber = $date->isoWeek();
        $this->batchId = null;
        if ($this->centerId !== '') {
            $this->center();
            $this->batchId = $this->company()->scheduleBatches()->where('center_id', $this->centerId)
                ->whereDate('period_start', $this->week)->whereIn('status', ['draft', 'published'])
                ->orderByRaw("CASE WHEN status = 'draft' THEN 0 ELSE 1 END")->orderByDesc('id')->value('id');
        }
        $this->people = $this->cells = $this->review = [];
        $this->copySource = '';
        $this->replaceCopiedDays = false;
        $this->showAssignment = $this->showReview = false;
        $this->showResetConfirmation = false;
        $this->notice = '';
        $this->effectiveFrom = $this->week;
        $this->copyTarget = CarbonImmutable::parse($this->week)->addWeek()->toDateString();
        $this->syncCopyWeekSearch();
        $this->resetPage();
        $this->resetValidation();
        $this->dispatch('planner-week-changed');
    }

    public function navigateWeek(int $direction): void
    {
        abort_unless(in_array($direction, [-1, 1], true), 422);
        $this->week = CarbonImmutable::parse($this->week)->addWeeks($direction)->toDateString();
        $this->resolveWeek();
    }

    public function jumpToWeek(): void
    {
        $this->validate(['weekSearchYear' => 'required|integer|between:1900,9998', 'weekSearchNumber' => 'required|integer|between:1,53']);
        $date = CarbonImmutable::create($this->weekSearchYear, 1, 4)->setISODate($this->weekSearchYear, $this->weekSearchNumber, 1);
        if ($date->isoWeekYear() !== $this->weekSearchYear) {
            throw ValidationException::withMessages(['weekSearchNumber' => 'Ese año no tiene la semana seleccionada.']);
        }
        $this->week = $date->toDateString();
        $this->resolveWeek();
    }

    private function syncCopyWeekSearch(): void
    {
        $date = CarbonImmutable::parse($this->copyTarget);
        $this->copyWeekSearchYear = $date->isoWeekYear();
        $this->copyWeekSearchNumber = $date->isoWeek();
    }

    public function navigateCopyWeek(int $direction): void
    {
        abort_unless(in_array($direction, [-1, 1], true), 422);
        $this->copyTarget = CarbonImmutable::parse($this->normalizeWeek($this->copyTarget))->addWeeks($direction)->toDateString();
        $this->syncCopyWeekSearch();
    }

    public function chooseCopyWeek(): void
    {
        $this->validate(['copyWeekSearchYear' => 'required|integer|between:1900,9998', 'copyWeekSearchNumber' => 'required|integer|between:1,53']);
        $date = CarbonImmutable::create($this->copyWeekSearchYear, 1, 4)->setISODate($this->copyWeekSearchYear, $this->copyWeekSearchNumber, 1);
        if ($date->isoWeekYear() !== $this->copyWeekSearchYear) {
            throw ValidationException::withMessages(['copyWeekSearchNumber' => 'Ese año no tiene la semana seleccionada.']);
        }
        $this->copyTarget = $date->toDateString();
        $this->dispatch('planner-copy-week-changed');
    }

    public function openWeek(bool $generate = false): void
    {
        $this->run(function () use ($generate) {
            $company = $this->company();
            $center = $this->center();
            DB::transaction(function () use ($company, $center, $generate) {
                Center::query()->whereKey($center->id)->lockForUpdate()->firstOrFail();
                $this->resolveWeek();
                if (! $this->batchId) {
                    $this->batchId = app(CreateScheduleBatchAction::class)->handle($company, $center, ['period_start' => $this->week], auth()->user())->id;
                }
                if ($generate) {
                    $batch = $this->batch();
                    Gate::authorize('generate', $batch);
                    app(GenerateDraftScheduleBatchFromProfilesAction::class)->handle(auth()->user(), $company, $batch);
                }
            });
            $this->notice = $generate ? 'Semana preparada con los horarios habituales. Revisa los pendientes.' : 'Semana lista para asignar horarios.';
        });
    }

    private function relationships()
    {
        $this->center();

        return EmploymentRelationship::query()->where('company_id', $this->company()->id)->where('center_id', $this->centerId)
            ->whereDate('started_at', '<=', CarbonImmutable::parse($this->week)->addDays(6)->toDateString())
            ->where(fn ($q) => $q->whereNull('ended_at')->orWhereDate('ended_at', '>=', $this->week))
            ->whereHas('worker', fn ($q) => $q->where('company_id', $this->company()->id)
                ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q->where('full_name', 'like', '%'.$this->search.'%')->orWhere('employee_code', 'like', '%'.$this->search.'%'))))
            ->when($this->unitId !== '', fn ($q) => $q->whereHas('employmentUnitAssignments', fn ($q) => $q
                ->where('company_id', $this->company()->id)->where('organizational_unit_id', $this->unitId)
                ->where('status', 'active')->whereDate('effective_from', '<=', CarbonImmutable::parse($this->week)->addDays(6)->toDateString())
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $this->week))));
    }

    public function refreshRoster(AddMissingWorkersToDraftScheduleAction $action): void
    {
        $this->run(function () use ($action): void {
            $added = $action->handle(auth()->user(), $this->company(), $this->batch());
            $this->notice = match ($added) {
                0 => 'La plantilla ya está actualizada para esta semana y centro.',
                1 => 'Se agregó 1 trabajador al borrador con días pendientes de asignar.',
                default => "Se agregaron {$added} trabajadores al borrador con días pendientes de asignar.",
            };
            if ($added > 0) {
                $this->search = $this->unitId = '';
                $this->clearSelection();
                $this->resetPage();
            }
        });
    }

    public function selectVisible(): void
    {
        $batch = $this->batch();
        Gate::authorize('update', $batch);
        $existing = $batch->previous_batch_id ? $batch->dailyAssignments()->get()->keyBy(fn ($day) => $day->employment_relationship_id.'|'.$day->work_date->toDateString()) : collect();
        $keys = [];
        foreach ($this->relationships()->orderBy('worker_id')->paginate(20) as $relationship) {
            for ($i = 0; $i < 7; $i++) {
                $date = CarbonImmutable::parse($this->week)->addDays($i);
                $key = $relationship->id.'|'.$date->toDateString();
                if ($relationship->isEffectiveOn($date) || $existing->has($key)) {
                    $keys[] = $key;
                }
            }
        }
        $this->cells = array_values(array_unique([...$this->cells, ...$keys]));
        $this->people = array_values(array_unique(array_map(fn ($cell) => explode('|', $cell)[0], $this->cells)));
        $this->review = [];
        $this->showReview = false;
    }

    public function clearSelection(): void
    {
        $this->people = $this->cells = [];
    }

    public function toggleCell(int $relationshipId, string $date): void
    {
        $batch = $this->batch();
        Gate::authorize('update', $batch);
        $key = $relationshipId.'|'.$date;
        $this->cells = in_array($key, $this->cells, true) ? array_values(array_diff($this->cells, [$key])) : [...$this->cells, $key];
    }

    public function selectDay(string $date): void
    {
        $batch = $this->batch();
        Gate::authorize('update', $batch);
        validator(['date' => $date], ['date' => 'required|date_format:Y-m-d'])->validate();
        if ($date < $batch->period_start->toDateString() || $date > $batch->period_end->toDateString()) {
            throw ValidationException::withMessages(['planner' => 'Selecciona un día de la semana abierta.']);
        }
        $existingIds = $batch->previous_batch_id
            ? $batch->dailyAssignments()->whereDate('work_date', $date)->pluck('employment_relationship_id')->all()
            : [];
        $keys = $this->relationships()->get()->filter(fn ($relationship) => $relationship->isEffectiveOn($date) || in_array($relationship->id, $existingIds))
            ->map(fn ($relationship) => $relationship->id.'|'.$date)->values()->all();
        $allSelected = $keys !== [] && array_diff($keys, $this->cells) === [];
        $selection = $allSelected ? array_values(array_diff($this->cells, $keys)) : array_values(array_unique([...$this->cells, ...$keys]));
        if (count($selection) > 1400) {
            throw ValidationException::withMessages(['planner' => 'Selecciona hasta 1,400 celdas por operación. Filtra por equipo para dividir el grupo.']);
        }
        $this->cells = $selection;
        $this->people = array_values(array_unique(array_map(fn ($cell) => explode('|', $cell)[0], $selection)));
        $this->review = [];
        $this->showReview = false;
    }

    public function applyCells(): void
    {
        $this->validate(['cells' => 'required|array|min:1|max:1400', 'brush' => ['required', 'regex:/^(rest|[1-9][0-9]*)$/']]);
        $this->run(function () {
            $batch = $this->batch();
            Gate::authorize('update', $batch);
            DB::transaction(function () use ($batch) {
                foreach ($this->cells as $cell) {
                    $parts = explode('|', $cell);
                    validator(['id' => $parts[0] ?? '', 'date' => $parts[1] ?? ''], ['id' => 'required|integer', 'date' => 'required|date_format:Y-m-d'])->validate();
                    $relationship = EmploymentRelationship::query()->where('company_id', $this->company()->id)->where('center_id', $batch->center_id)->findOrFail($parts[0]);
                    $unit = app(ResolveEmploymentUnitsForDateAction::class)->handle($this->company(), $relationship, $parts[1])['primary'];
                    app(BulkReplaceDraftDailyScheduleAssignmentsAction::class)->handle($this->company(), $batch, [(int) $parts[0]], [$parts[1]], [
                        'day_type' => $this->brush === 'rest' ? 'rest' : 'shift',
                        'shift_template_id' => $this->brush === 'rest' ? null : (int) $this->brush, 'source_type' => 'manual', 'organizational_unit_id' => $unit?->id,
                    ]);
                }
            });
            $this->notice = count($this->cells).' días actualizados en borrador.';
            $this->cells = [];
            $this->people = [];
        });
    }

    public function openAssignment(): void
    {
        Gate::authorize('update', $this->batch());
        $this->validate(['people' => 'required|array|min:1']);
        $this->replaceDays = $this->replaceHabitual = false;
        $this->showAssignment = true;
    }

    public function fillDays(): void
    {
        $this->validate(['fillTurn' => ['required', 'regex:/^(rest|[1-9][0-9]*)$/'], 'workdays' => 'required|array|min:1', 'workdays.*' => 'integer|between:1,7']);
        foreach ($this->workdays as $day) {
            $this->rules[$day] = $this->fillTurn;
        }
    }

    public function addCycleBlock(): void
    {
        $this->validate(['cycleTurn' => ['required', 'regex:/^(rest|[1-9][0-9]*)$/'], 'cycleDays' => 'required|integer|min:1|max:366']);
        if (count($this->cycle) + $this->cycleDays > 366) {
            throw ValidationException::withMessages(['planner' => 'El ciclo admite hasta 366 días.']);
        }
        $this->cycle = [...$this->cycle, ...array_fill(0, $this->cycleDays, $this->cycleTurn)];
    }

    public function removeCycleDay(int $index): void
    {
        if (count($this->cycle) > 2) {
            unset($this->cycle[$index]);
            $this->cycle = array_values($this->cycle);
        }
    }

    public function applyAssignment(): void
    {
        $this->run(function () {
            $rules = $this->mode === 'cycle'
                ? collect($this->cycle)->map(fn ($value, $i) => ['day' => $i + 1, 'value' => $value])->all()
                : collect($this->rules)->map(fn ($value, $day) => ['day' => (int) $day, 'value' => $value])->values()->all();
            $result = app(ApplyPlannerScheduleAction::class)->handle(auth()->user(), $this->company(), $this->batch(), [
                'relationships' => $this->people, 'mode' => $this->mode, 'rules' => $rules,
                'name' => $this->name, 'profile_id' => $this->profileId ?: null,
                'effective_from' => $this->effectiveFrom, 'effective_to' => $this->effectiveTo ?: null,
                'replace_days' => $this->replaceDays, 'replace_habitual' => $this->replaceHabitual,
            ]);
            $this->notice = $result['changed'].' días preparados; '.$result['preserved'].' días existentes conservados.'.($result['profile_id'] ? ' Horario habitual guardado para próximas semanas.' : '');
            $this->showAssignment = false;
        });
    }

    public function openCreateShift(): void
    {
        Gate::authorize('create', [ShiftTemplate::class, $this->company()]);
        $this->dispatch('planner-create-shift')->to('scheduling.shifts');
    }

    #[On('planner-shift-saved')]
    public function shiftSaved(int $templateId): void
    {
        $turn = $this->company()->shiftTemplates()->findOrFail($templateId);
        Gate::authorize('view', $turn);
        if ($turn->status !== 'active') {
            $this->notice = 'Turno guardado como inactivo en el catálogo. Actívalo para poder asignarlo.';

            return;
        }
        $this->brush = $this->fillTurn = (string) $turn->id;
        $this->notice = 'Turno guardado en el catálogo y disponible para asignar.';
    }

    public function cloneWeek(): void
    {
        $this->validate(['copyTarget' => 'required|date_format:Y-m-d', 'cloneWeeks' => 'required|integer|between:1,12', 'cloneMode' => 'required|in:draft,publish']);
        $this->run(function () {
            $count = $this->cloneWeeks;
            $publish = $this->cloneMode === 'publish';
            $result = app(ClonePublishedScheduleWeeksAction::class)->handle(auth()->user(), $this->company(), $this->batch(), $this->copyTarget, $count, $publish);
            $this->week = $result['last_batch']->period_start->toDateString();
            $this->resolveWeek();
            $this->notice = $publish ? $count.' semanas clonadas y publicadas.' : $count.' semanas clonadas en borrador. Revisa los horarios antes de publicar.';
        });
    }

    public function copyRow(): void
    {
        $this->validate(['copySource' => 'required|integer', 'people' => 'required|array|min:1']);
        $this->run(function () {
            $result = app(CopyPlannerRowAction::class)->handle(auth()->user(), $this->company(), $this->batch(), (int) $this->copySource, $this->people, $this->replaceCopiedDays);
            $this->notice = $result['changed'].' días copiados; '.$result['preserved'].' días existentes conservados.';
        });
    }

    public function prepareFutureWeeks(): void
    {
        $this->validate(['futureWeeks' => 'required|integer|between:1,4']);
        $this->run(function () {
            Gate::authorize('create', [ScheduleBatch::class, $this->company()]);
            $result = DB::transaction(fn () => app(PrepareNextScheduleWeekAction::class)
                ->prepareWeeks(auth()->user(), $this->company(), $this->batch(), $this->futureWeeks));
            $this->week = $result['final_batch']->period_start->toDateString();
            $this->resolveWeek();
            $this->notice = $result['created_count'].' semanas preparadas en borrador; '.$result['existing_count'].' ya existían. Se abrió la última para revisión.';
        });
    }

    public function createCorrection(): void
    {
        $this->validate(['correctionReason' => 'required|string|max:2000']);
        $this->run(function () {
            $result = app(CreateCorrectiveScheduleBatchAction::class)->handle(auth()->user(), $this->company(), $this->batch(), $this->correctionReason);
            $this->correctionReason = '';
            $this->resolveWeek();
            $this->notice = 'Corrección en borrador. La publicación anterior sigue vigente hasta publicar los cambios.';
        });
    }

    public function deleteDraft(): void
    {
        $this->run(function () {
            $batch = $this->batch();
            Gate::authorize('deleteDraft', $batch);
            app(DeleteDraftScheduleBatchAction::class)->handle(auth()->user(), $this->company(), $batch);
            $this->resolveWeek();
            $this->notice = 'Borrador eliminado. Los turnos y horarios habituales se conservan.';
        });
    }

    public function openResetConfirmation(): void
    {
        Gate::authorize('update', $this->batch());
        $this->resetValidation();
        $this->showResetConfirmation = true;
    }

    public function resetDraftSchedules(): void
    {
        $this->run(function () {
            $count = app(ResetDraftScheduleBatchAction::class)->handle(auth()->user(), $this->company(), $this->batch());
            $this->people = $this->cells = [];
            $this->copySource = '';
            $this->showAssignment = false;
            $this->showResetConfirmation = false;
            $this->notice = 'Horarios restablecidos: '.$count.' días quedaron pendientes. Se conserva el borrador y los horarios habituales.';
        });
    }

    public function reviewWeek(): void
    {
        $this->run(function () {
            $batch = $this->batch();
            Gate::authorize($batch->previous_batch_id ? 'publishCorrection' : 'publish', $batch);
            $action = $batch->previous_batch_id ? ValidateCorrectiveScheduleBatchForPublicationAction::class : ValidateScheduleBatchForPublicationAction::class;
            $this->review = app($action)->handle(auth()->user(), $this->company(), $batch)->toArray();
            $this->showReview = true;
        }, false);
    }

    public function publish(): void
    {
        if (! $this->showReview || ! ($this->review['valid'] ?? false)) {
            throw ValidationException::withMessages(['planner' => 'Revisa la semana antes de confirmar su publicación.']);
        }
        $this->run(function () {
            $batch = $this->batch();
            Gate::authorize($batch->previous_batch_id ? 'publishCorrection' : 'publish', $batch);
            $action = $batch->previous_batch_id ? PublishCorrectiveScheduleBatchAction::class : PublishScheduleBatchAction::class;
            app($action)->handle(auth()->user(), $this->company(), $batch);
            $this->notice = 'Semana publicada. El horario y su evidencia quedaron conservados.';
            $this->showReview = false;
            $this->people = $this->cells = [];
        });
    }

    #[On('daily-schedule-import-applied')]
    public function csvApplied(): void
    {
        $this->review = [];
        $this->showReview = false;
        $this->notice = 'Archivo aplicado al borrador.';
    }

    private function run(callable $operation, bool $invalidate = true): void
    {
        $this->resetValidation();
        if ($invalidate) {
            $this->review = [];
            $this->showReview = false;
        }
        try {
            $operation();
        } catch (InvalidArgumentException $error) {
            throw ValidationException::withMessages(['planner' => $error->getMessage()]);
        }
    }

    public function render()
    {
        Gate::authorize('viewAny', [ScheduleBatch::class, $this->company()]);
        $batch = $this->batchId ? $this->batch() : null;
        $relationships = $this->centerId ? $this->relationships()->with('worker')->orderBy('worker_id')->paginate(20) : null;
        $assignments = $batch && $relationships ? $batch->dailyAssignments()->whereIn('employment_relationship_id', $relationships->pluck('id'))
            ->with(['shiftTemplate', 'segments', 'organizationalUnit'])->get()->keyBy(fn ($day) => $day->employment_relationship_id.'|'.$day->work_date->toDateString()) : collect();

        $searchYear = max(1900, min(9998, $this->weekSearchYear));
        $yearStart = CarbonImmutable::create($searchYear, 1, 4);
        $copySearchYear = max(1900, min(9998, $this->copyWeekSearchYear));
        $copyYearStart = CarbonImmutable::create($copySearchYear, 1, 4);

        $columnKeys = [];
        $typeCounts = $batch ? $batch->dailyAssignments()->selectRaw('day_type, COUNT(*) as total')->groupBy('day_type')->pluck('total', 'day_type')->all() : [];
        $calendarStats = $typeCounts + [
            'workers' => $batch ? $batch->dailyAssignments()->distinct()->count('employment_relationship_id') : 0,
            'days' => array_sum($typeCounts),
        ];
        if ($batch && Gate::allows('update', $batch)) {
            $filteredPeople = $this->relationships()->get();
            $historical = $batch->previous_batch_id ? $batch->dailyAssignments()->get()->keyBy(fn ($day) => $day->employment_relationship_id.'|'.$day->work_date->toDateString()) : collect();
            for ($i = 0; $i < 7; $i++) {
                $date = CarbonImmutable::parse($this->week)->addDays($i)->toDateString();
                $columnKeys[$date] = $filteredPeople->filter(fn ($person) => $person->isEffectiveOn($date) || $historical->has($person->id.'|'.$date))
                    ->map(fn ($person) => $person->id.'|'.$date)->values()->all();
            }
        }

        return view('livewire.scheduling.planner', [
            'copyStart' => CarbonImmutable::parse($this->copyTarget),
            'copySearchWeeks' => collect(range(1, $copyYearStart->isoWeeksInYear()))->map(fn ($number) => $copyYearStart->setISODate($copySearchYear, $number, 1)),
            'columnKeys' => $columnKeys,
            'calendarStats' => $calendarStats,
            'searchWeeks' => collect(range(1, $yearStart->isoWeeksInYear()))->map(fn ($number) => $yearStart->setISODate($searchYear, $number, 1)),
            'centers' => $this->centers(), 'batch' => $batch, 'relationships' => $relationships, 'assignments' => $assignments,
            'turns' => $this->company()->shiftTemplates()->where('status', 'active')->with('segments')->orderBy('name')->get(),
            'profiles' => $this->company()->scheduleProfiles()->where('status', 'active')->orderBy('name')->get(),
            'units' => $this->company()->organizationalUnits()->where('center_id', $this->centerId)->where('status', 'active')->orderBy('name')->get(),
            'dates' => collect(range(0, 6))->map(fn ($i) => CarbonImmutable::parse($this->week)->addDays($i)),
            'editable' => $batch && Gate::allows('update', $batch),
        ]);
    }
}
