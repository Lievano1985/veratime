<?php

use App\Domains\Organization\Actions\ResolveUserOperationalScopeAction;
use App\Domains\Scheduling\Actions\BulkReplaceDraftDailyScheduleAssignmentsAction;
use App\Domains\Scheduling\Actions\BuildDailyScheduleSegmentsFromShiftTemplateAction;
use App\Domains\Scheduling\Actions\ClonePublishedScheduleWeekAndPublishAction;
use App\Domains\Scheduling\Actions\ClonePublishedScheduleWeekToDraftAction;
use App\Domains\Scheduling\Actions\CompareScheduleBatchVersionsAction;
use App\Domains\Scheduling\Actions\CreateCorrectiveScheduleBatchAction;
use App\Domains\Scheduling\Actions\CreateScheduleBatchAction;
use App\Domains\Scheduling\Actions\DeleteCancelledScheduleBatchAction;
use App\Domains\Scheduling\Actions\DeleteDraftScheduleBatchAction;
use App\Domains\Scheduling\Actions\GenerateDraftScheduleBatchFromProfilesAction;
use App\Domains\Scheduling\Actions\PublishCorrectiveScheduleBatchAction;
use App\Domains\Scheduling\Actions\PublishScheduleBatchAction;
use App\Domains\Scheduling\Actions\PrepareNextScheduleWeekAction;
use App\Domains\Scheduling\Actions\ReplaceDraftDailyScheduleAssignmentAction;
use App\Domains\Scheduling\Actions\ResolveScheduleBatchVersionChainAction;
use App\Domains\Scheduling\Actions\ResolveScheduleBatchExpectedRelationshipDatesAction;
use App\Domains\Scheduling\Actions\ValidateCorrectiveScheduleBatchForPublicationAction;
use App\Domains\Scheduling\Actions\ValidateScheduleBatchForPublicationAction;
use App\Domains\Scheduling\Actions\VerifyPublishedScheduleBatchSnapshotAction;
use App\Domains\Scheduling\Data\ScheduleBatchPublicationValidationResult;
use App\Domains\Scheduling\Exceptions\ScheduleBatchPublicationValidationException;
use App\Domains\Scheduling\Exceptions\ScheduleCorrectionAlreadyExistsException;
use App\Domains\Scheduling\Exceptions\ScheduleCorrectionHasNoChangesException;
use App\Domains\Scheduling\Exceptions\ScheduleCorrectionPublicationConflictException;
use App\Domains\Scheduling\Support\ShiftTemplateTimeline;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\DailyScheduleAssignment;
use App\Models\EmploymentRelationship;
use App\Models\OrganizationalUnit;
use App\Models\ScheduleBatch;
use App\Models\ShiftTemplate;
use App\Support\RoleKey;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public array $filters = [];
    public array $batchForm = [];
    public array $correctionForm = [];
    public array $dayForm = [];
    public array $bulkForm = [];
    public array $bulkWorkerIds = [];
    public array $cloneWeekForm = [];
    public array $prepareWeeksForm = [];
    public array $validationPanel = [];
    public array $integrityPanel = [];
    public array $comparisonPanel = [];
    public array $versionHistoryPanel = [];
    public ?int $selectedBatchId = null;
    public ?int $editingAssignmentId = null;
    public ?int $editingRelationshipId = null;
    public ?string $editingWorkDate = null;
    public ?string $weekStart = null;
    public bool $showCreatePanel = false;
    public bool $showCorrectionPanel = false;
    public bool $showDayPanel = false;
    public bool $showBulkPanel = false;
    public bool $showCloneWeekPanel = false;
    public bool $showPrepareWeeksPanel = false;
    public bool $showAdvancedFilters = false;
    public bool $confirmBulk = false;
    public bool $confirmPublish = false;

    public function mount(): void
    {
        $this->filters = [
            'center_id' => '',
            'period_start' => '',
            'period_end' => '',
            'status' => 'active_work',
            'period_scope' => 'current_future',
            'worker_search' => '',
            'organizational_unit_id' => '',
            'day_type' => 'all',
            'pending_only' => false,
        ];
        $this->batchForm = $this->emptyBatchForm();
        $this->correctionForm = ['correction_reason' => ''];
        $this->dayForm = $this->emptyDayForm();
        $this->bulkForm = $this->emptyBulkForm();
        $this->bulkWorkerIds = [];
        $this->cloneWeekForm = $this->emptyCloneWeekForm();
        $this->prepareWeeksForm = $this->emptyPrepareWeeksForm();
        $this->validationPanel = [];
        $this->integrityPanel = [];
        $this->comparisonPanel = [];
        $this->versionHistoryPanel = [];
    }

    public function updated($property): void
    {
        if (str_starts_with((string) $property, 'filters.')) {
            $this->resetPage();
            $this->resetPage('calendarPage');
        }

        if ($property === 'batchForm.period_start' && filled($this->batchForm['period_start'] ?? null)) {
            [$this->batchForm['period_start'], $this->batchForm['period_end']] = $this->naturalWeekForDate($this->batchForm['period_start']);
        }

        if ($property === 'selectedBatchId') {
            $this->validationPanel = [];
            $this->integrityPanel = [];
            $this->comparisonPanel = [];
            $this->resetPage('calendarPage');
        }

        if ($property === 'bulkWorkerIds') {
            $this->syncBulkRelationshipsFromWorkers();
        }

        if ($property === 'cloneWeekForm.target_date' && filled($this->cloneWeekForm['target_date'] ?? null)) {
            [$this->cloneWeekForm['target_date'], $this->cloneWeekForm['target_end']] = $this->naturalWeekForDate($this->cloneWeekForm['target_date']);
        }
    }

    #[On('daily-schedule-import-applied')]
    public function refreshAfterCsvImport(): void
    {
        $this->validationPanel = [];
        $this->integrityPanel = [];
        $this->comparisonPanel = [];
    }

    public function openCreatePanel(CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        Gate::authorize('create', [ScheduleBatch::class, $company]);

        $this->batchForm = $this->emptyBatchForm();
        $this->showCreatePanel = true;
        $this->resetValidation();
    }

    public function openCorrectionPanel(CurrentCompany $currentCompany): void
    {
        $batch = $this->selectedBatch($currentCompany, false);
        Gate::authorize('createCorrection', $batch);
        $this->correctionForm = ['correction_reason' => ''];
        $this->showCorrectionPanel = true;
        $this->resetValidation();
    }

    public function createCorrection(CurrentCompany $currentCompany, CreateCorrectiveScheduleBatchAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $batch = $this->selectedBatch($currentCompany, false);
        $validated = $this->validate([
            'correctionForm.correction_reason' => ['required', 'string', 'min:5', 'max:2000'],
        ])['correctionForm'];

        try {
            $result = $action->handle(auth()->user(), $company, $batch, $validated['correction_reason']);
        } catch (ScheduleCorrectionAlreadyExistsException $exception) {
            if ($exception->existingBatchId) {
                $this->selectedBatchId = $exception->existingBatchId;
                $this->weekStart = null;
                $this->resetPage('calendarPage');
            }
            throw ValidationException::withMessages(['correctionForm.correction_reason' => $exception->getMessage()]);
        } catch (\InvalidArgumentException|\Illuminate\Auth\Access\AuthorizationException $exception) {
            throw ValidationException::withMessages(['correctionForm.correction_reason' => $exception->getMessage()]);
        }

        $this->selectedBatchId = $result->correctiveBatch->id;
        $this->weekStart = $this->calendarWeekStart($result->correctiveBatch->period_start->toDateString());
        $this->resetPage('calendarPage');
        $this->showCorrectionPanel = false;
        Session::flash('status', "Correccion creada: {$result->assignmentsCloned} dias clonados.");
    }

    public function createEmptyBatch(CurrentCompany $currentCompany, CreateScheduleBatchAction $action): void
    {
        $batches = $this->createBatches($currentCompany, $action);
        $batch = $batches[array_key_last($batches)];
        $this->selectedBatchId = $batch->id;
        $this->weekStart = $this->calendarWeekStart($batch->period_start->toDateString());
        $this->resetPage('calendarPage');
        $this->showCreatePanel = false;
        Session::flash('status', count($batches) === 1 ? 'Semana creada en borrador.' : count($batches).' semanas creadas en borrador.');
    }

    public function createAndGenerate(
        CurrentCompany $currentCompany,
        CreateScheduleBatchAction $createAction,
        GenerateDraftScheduleBatchFromProfilesAction $generateAction,
    ): void {
        $company = $this->currentCompanyOrFail($currentCompany);
        $batches = $this->createBatches($currentCompany, $createAction);
        $lastResult = null;

        foreach ($batches as $batch) {
            try {
                $lastResult = $generateAction->handle(auth()->user(), $company, $batch, GenerateDraftScheduleBatchFromProfilesAction::MODE_MISSING_ONLY);
            } catch (\InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['batchForm.center_id' => $exception->getMessage()]);
            }
        }

        $batch = $batches[array_key_last($batches)];
        $this->selectedBatchId = $batch->id;
        $this->weekStart = $this->calendarWeekStart($batch->period_start->toDateString());
        $this->resetPage('calendarPage');
        $this->showCreatePanel = false;
        Session::flash('status', count($batches) === 1
            ? $this->generationMessage($lastResult)
            : count($batches).' semanas creadas y generadas desde modelos. Se abrio la ultima para revision.');
    }

    public function selectBatch(int $batchId, CurrentCompany $currentCompany): void
    {
        $batch = $this->authorizedBatch($batchId, $currentCompany, false);
        $this->selectedBatchId = $batch->id;
        $this->weekStart = $this->calendarWeekStart($batch->period_start->toDateString());
        $this->resetPage('calendarPage');
        $this->validationPanel = [];
        $this->integrityPanel = [];
        $this->comparisonPanel = [];
        $this->versionHistoryPanel = [];
    }

    public function closeCalendar(): void
    {
        $this->selectedBatchId = null;
        $this->weekStart = null;
        $this->resetPage('calendarPage');
        $this->validationPanel = [];
        $this->integrityPanel = [];
        $this->comparisonPanel = [];
        $this->versionHistoryPanel = [];
    }

    public function editBatchInfo(CurrentCompany $currentCompany, \App\Domains\Scheduling\Actions\UpdateDraftScheduleBatchAction $action): void
    {
        $batch = $this->selectedBatch($currentCompany, true);
        $validated = $this->validate([
            'batchForm.period_start' => ['required', 'date'],
            'batchForm.notes' => ['nullable', 'string', 'max:2000'],
        ])['batchForm'];
        [$validated['period_start'], $validated['period_end']] = $this->naturalWeekForDate($validated['period_start']);

        try {
            $action->handle($batch, [
                'period_start' => $validated['period_start'],
                'period_end' => $validated['period_end'],
                'notes' => $validated['notes'] ?: null,
            ]);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['batchForm.period_start' => $exception->getMessage()]);
        }

        Session::flash('status', 'Informacion basica actualizada.');
    }

    public function generateMissing(CurrentCompany $currentCompany, GenerateDraftScheduleBatchFromProfilesAction $action): void
    {
        $this->generateFromProfiles($currentCompany, $action, GenerateDraftScheduleBatchFromProfilesAction::MODE_MISSING_ONLY);
    }

    public function refreshGenerated(CurrentCompany $currentCompany, GenerateDraftScheduleBatchFromProfilesAction $action): void
    {
        $this->generateFromProfiles($currentCompany, $action, GenerateDraftScheduleBatchFromProfilesAction::MODE_REFRESH_PROFILE_GENERATED);
    }

    public function openPrepareWeeksPanel(CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $batch = $this->selectedBatch($currentCompany, false);
        Gate::authorize('create', [ScheduleBatch::class, $company]);

        if ($batch->previous_batch_id !== null || $batch->status === 'cancelled') {
            throw ValidationException::withMessages(['generation' => 'Este lote no permite preparar semanas futuras.']);
        }

        $this->prepareWeeksForm = $this->emptyPrepareWeeksForm();
        $this->showPrepareWeeksPanel = true;
        $this->resetValidation();
    }

    public function openCloneWeekPanel(CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $batch = $this->selectedBatch($currentCompany, false);
        Gate::authorize('create', [ScheduleBatch::class, $company]);

        if ($batch->status !== 'published' || $batch->previous_batch_id !== null) {
            throw ValidationException::withMessages(['cloneWeekForm.target_date' => 'Solo se puede clonar una semana publicada vigente.']);
        }

        $nextStart = CarbonImmutable::parse($batch->period_end)->addDay()->startOfWeek(CarbonInterface::MONDAY)->toDateString();
        [$targetStart, $targetEnd] = $this->naturalWeekForDate($nextStart);

        $this->cloneWeekForm = [
            'target_date' => $targetStart,
            'target_end' => $targetEnd,
        ];
        $this->showCloneWeekPanel = true;
        $this->resetValidation();
    }

    public function clonePublishedWeek(CurrentCompany $currentCompany, ClonePublishedScheduleWeekToDraftAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $batch = $this->selectedBatch($currentCompany, false);
        Gate::authorize('create', [ScheduleBatch::class, $company]);

        $validated = $this->validate([
            'cloneWeekForm.target_date' => ['required', 'date'],
        ])['cloneWeekForm'];

        try {
            $result = $action->handle(auth()->user(), $company, $batch, $validated['target_date']);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['cloneWeekForm.target_date' => $exception->getMessage()]);
        }

        $cloned = $result['batch'];
        $this->selectedBatchId = $cloned->id;
        $this->weekStart = $this->calendarWeekStart($cloned->period_start->toDateString());
        $this->showCloneWeekPanel = false;
        $this->validationPanel = [];
        $this->integrityPanel = [];
        $this->comparisonPanel = [];
        $this->versionHistoryPanel = [];
        $this->resetPage('calendarPage');

        Session::flash('status', "Semana clonada en borrador: {$result['assignments']} dias copiados".($result['skipped'] > 0 ? ", {$result['skipped']} omitidos por vigencia." : '.'));
    }

    public function clonePublishedWeekAndPublish(CurrentCompany $currentCompany, ClonePublishedScheduleWeekAndPublishAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $batch = $this->selectedBatch($currentCompany, false);
        Gate::authorize('create', [ScheduleBatch::class, $company]);

        $validated = $this->validate([
            'cloneWeekForm.target_date' => ['required', 'date'],
        ])['cloneWeekForm'];

        try {
            $published = $action->handle(auth()->user(), $company, $batch, $validated['target_date']);
        } catch (ScheduleBatchPublicationValidationException $exception) {
            $this->validationPanel = $exception->result->toArray();
            throw ValidationException::withMessages(['cloneWeekForm.target_date' => 'No se pudo publicar la semana clonada: '.$exception->getMessage()]);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['cloneWeekForm.target_date' => $exception->getMessage()]);
        }

        $this->selectedBatchId = $published->scheduleBatch->id;
        $this->weekStart = $this->calendarWeekStart($published->scheduleBatch->period_start->toDateString());
        $this->showCloneWeekPanel = false;
        $this->validationPanel = [];
        $this->integrityPanel = [];
        $this->comparisonPanel = [];
        $this->versionHistoryPanel = [];
        $this->resetPage('calendarPage');

        Session::flash('status', "Semana clonada y publicada. SHA-256: {$published->snapshotSha256}");
    }

    public function prepareNextWeek(CurrentCompany $currentCompany, PrepareNextScheduleWeekAction $action): void
    {
        $this->prepareWeeks($currentCompany, $action, 1);
    }

    public function prepareFutureWeeks(CurrentCompany $currentCompany, PrepareNextScheduleWeekAction $action): void
    {
        $validated = $this->validate([
            'prepareWeeksForm.weeks' => ['required', 'integer', 'min:1', 'max:4'],
        ])['prepareWeeksForm'];

        $this->prepareWeeks($currentCompany, $action, (int) $validated['weeks']);
        $this->showPrepareWeeksPanel = false;
    }

    private function prepareWeeks(CurrentCompany $currentCompany, PrepareNextScheduleWeekAction $action, int $weeks): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $batch = $this->selectedBatch($currentCompany, false);
        Gate::authorize('create', [ScheduleBatch::class, $company]);

        try {
            $result = $action->prepareWeeks(auth()->user(), $company, $batch, $weeks);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['generation' => $exception->getMessage()]);
        }

        $finalBatch = $result['final_batch'];
        $this->selectedBatchId = $finalBatch->id;
        $this->weekStart = $this->calendarWeekStart($finalBatch->period_start->toDateString());
        $this->validationPanel = [];
        $this->integrityPanel = [];
        $this->comparisonPanel = [];
        $this->versionHistoryPanel = [];
        $this->resetPage('calendarPage');

        if ($result['created_count'] === 0) {
            Session::flash('status', $weeks === 1
                ? 'La semana siguiente ya existia; se abrio para revision.'
                : "Las {$weeks} semanas solicitadas ya existian; se abrio la ultima para revision.");

            return;
        }

        Session::flash('status', $weeks === 1
            ? 'Semana siguiente preparada. '.$this->generationMessage($result['results'][0]['generation_result'])
            : "Semanas preparadas: {$result['created_count']} creadas, {$result['existing_count']} ya existian. Se abrio la ultima semana para revision.");
    }

    public function previousWeek(CurrentCompany $currentCompany): void
    {
        $batch = $this->selectedBatch($currentCompany, false);
        $previous = $this->adjacentBatch($currentCompany, $batch, 'previous');

        if (! $previous) {
            Session::flash('status', 'No existe una semana anterior para este centro.');

            return;
        }

        $this->openBatchForCalendar($previous);
        Session::flash('status', 'Semana anterior abierta.');
    }

    public function nextWeek(CurrentCompany $currentCompany): void
    {
        $batch = $this->selectedBatch($currentCompany, false);
        $next = $this->adjacentBatch($currentCompany, $batch, 'next');

        if (! $next) {
            Session::flash('status', 'No existe la semana siguiente. Puedes usar Preparar semanas.');

            return;
        }

        $this->openBatchForCalendar($next);
        Session::flash('status', 'Semana siguiente abierta.');
    }

    public function openDayEditor(int $relationshipId, string $workDate, CurrentCompany $currentCompany): void
    {
        $batch = $this->selectedBatch($currentCompany, true);
        Gate::authorize('update', $batch);

        $relationship = $this->relationshipForBatch($batch, $relationshipId, $workDate);
        $assignment = DailyScheduleAssignment::query()
            ->where('schedule_batch_id', $batch->id)
            ->where('employment_relationship_id', $relationship->id)
            ->whereDate('work_date', $workDate)
            ->first();

        $this->editingAssignmentId = $assignment?->id;
        $this->editingRelationshipId = $relationship->id;
        $this->editingWorkDate = $workDate;
        $this->dayForm = $this->formFromAssignment($assignment);
        $this->showDayPanel = true;
        $this->resetValidation();
    }

    public function saveDay(
        CurrentCompany $currentCompany,
        ReplaceDraftDailyScheduleAssignmentAction $replaceAction,
        BuildDailyScheduleSegmentsFromShiftTemplateAction $segmentsBuilder,
    ): void {
        $company = $this->currentCompanyOrFail($currentCompany);
        $batch = $this->selectedBatch($currentCompany, true);
        Gate::authorize('update', $batch);
        $relationship = $this->relationshipForBatch($batch, (int) $this->editingRelationshipId, (string) $this->editingWorkDate);
        $payload = $this->validatedDayPayload($company, $batch);
        $segments = $this->segmentsForDayPayload($company, $batch, $payload, $segmentsBuilder);

        try {
            $replaceAction->handle($company, $batch, $relationship, $payload, $segments);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['dayForm.day_type' => $exception->getMessage()]);
        }

        $this->showDayPanel = false;
        $this->editingAssignmentId = null;
        $this->editingRelationshipId = null;
        $this->editingWorkDate = null;
        $this->dayForm = $this->emptyDayForm();
        $this->validationPanel = [];
        Session::flash('status', 'Dia actualizado.');
    }

    public function openBulkPanel(CurrentCompany $currentCompany): void
    {
        $batch = $this->selectedBatch($currentCompany, true);
        Gate::authorize('update', $batch);
        $this->bulkForm = $this->emptyBulkForm($batch);
        $this->bulkWorkerIds = [];
        $this->confirmBulk = false;
        $this->showBulkPanel = true;
    }

    public function syncBulkRelationshipsFromWorkers(): void
    {
        $workerIds = collect($this->bulkWorkerIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $this->bulkWorkerIds = $workerIds;

        if (! $this->selectedBatchId || $workerIds === []) {
            $this->bulkForm['employment_relationship_ids'] = [];

            return;
        }

        $batch = ScheduleBatch::query()->whereKey($this->selectedBatchId)->first();
        if (! $batch) {
            $this->bulkForm['employment_relationship_ids'] = [];

            return;
        }

        $this->bulkForm['employment_relationship_ids'] = EmploymentRelationship::query()
            ->where('company_id', $batch->company_id)
            ->where('center_id', $batch->center_id)
            ->whereIn('worker_id', $workerIds)
            ->whereIn('id', $batch->dailyAssignments()->select('employment_relationship_id'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function applyBulk(CurrentCompany $currentCompany, BulkReplaceDraftDailyScheduleAssignmentsAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $batch = $this->selectedBatch($currentCompany, true);
        Gate::authorize('update', $batch);

        $validated = $this->validateBulkForm($company, $batch);
        if (! $this->confirmBulk) {
            throw ValidationException::withMessages(['confirmBulk' => 'Confirma que deseas aplicar el cambio masivo.']);
        }

        $dates = $this->datesBetween($validated['date_from'], $validated['date_to'], $batch);
        $payload = $this->payloadFromEditorData($validated, $batch, previousSourceType: 'bulk');

        try {
            $result = $action->handle($company, $batch, array_map('intval', $validated['employment_relationship_ids']), $dates, $payload);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['bulkForm.day_type' => $exception->getMessage()]);
        }

        $this->showBulkPanel = false;
        $this->confirmBulk = false;
        $this->validationPanel = [];
        Session::flash('status', "Cambio masivo aplicado a {$result['changed']} dias.");
    }

    public function reviewBatch(CurrentCompany $currentCompany, ValidateScheduleBatchForPublicationAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $batch = $this->selectedBatch($currentCompany, false);
        Gate::authorize('view', $batch);

        if ($batch->previous_batch_id !== null) {
            $result = app(ValidateCorrectiveScheduleBatchForPublicationAction::class)->handle(auth()->user(), $company, $batch);
            $this->validationPanel = $this->normalValidationPanel($result->toArray());
            $this->confirmPublish = false;

            return;
        }

        $result = $action->handle(auth()->user(), $company, $batch);
        $this->validationPanel = $result->toArray();
        $this->confirmPublish = false;
    }

    public function publishBatch(
        CurrentCompany $currentCompany,
        PublishScheduleBatchAction $action,
        ValidateScheduleBatchForPublicationAction $validator,
        PublishCorrectiveScheduleBatchAction $correctivePublisher,
        ValidateCorrectiveScheduleBatchForPublicationAction $correctiveValidator,
    ): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $batch = $this->selectedBatch($currentCompany, true);
        Gate::authorize($batch->previous_batch_id ? 'publishCorrection' : 'publish', $batch);

        if ($batch->previous_batch_id !== null) {
            $result = $correctiveValidator->handle(auth()->user(), $company, $batch);
            $this->validationPanel = $this->normalValidationPanel($result->toArray());

            if (! $result->valid()) {
                throw ValidationException::withMessages(['publication' => 'La correccion todavia tiene bloqueos para publicar.']);
            }

            if (! $this->confirmPublish) {
                throw ValidationException::withMessages(['confirmPublish' => 'Confirma la publicacion de la correccion para continuar.']);
            }

            try {
                $published = $correctivePublisher->handle(auth()->user(), $company, $batch);
            } catch (ScheduleCorrectionHasNoChangesException|ScheduleCorrectionPublicationConflictException $exception) {
                throw ValidationException::withMessages(['publication' => $exception->getMessage()]);
            }

            $this->confirmPublish = false;
            $this->validationPanel = [];
            $this->comparisonPanel = [];
            Session::flash('status', "Correccion publicada. Version anterior sustituida. SHA-256: {$published->snapshotSha256}");

            return;
        }

        $result = $validator->handle(auth()->user(), $company, $batch);
        $this->validationPanel = $result->toArray();

        if (! $result->valid()) {
            throw ValidationException::withMessages(['publication' => 'El lote todavia tiene bloqueos para publicar.']);
        }

        if (! $this->confirmPublish) {
            throw ValidationException::withMessages(['confirmPublish' => 'Confirma la publicacion para continuar.']);
        }

        try {
            $published = $action->handle(auth()->user(), $company, $batch);
        } catch (ScheduleBatchPublicationValidationException $exception) {
            $this->validationPanel = $exception->result->toArray();
            throw ValidationException::withMessages(['publication' => $exception->getMessage()]);
        }

        $this->confirmPublish = false;
        $this->validationPanel = [];
        Session::flash('status', "Programacion publicada. SHA-256: {$published->snapshotSha256}");
    }

    public function deleteDraftBatch(CurrentCompany $currentCompany, DeleteDraftScheduleBatchAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $batch = $this->selectedBatch($currentCompany, true);
        Gate::authorize('deleteDraft', $batch);

        try {
            $action->handle(auth()->user(), $company, $batch);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['deleteDraftBatch' => $exception->getMessage()]);
        }

        $this->selectedBatchId = null;
        $this->resetPage('calendarPage');
        $this->validationPanel = [];
        $this->comparisonPanel = [];
        $this->integrityPanel = [];
        $this->versionHistoryPanel = [];
        $this->confirmPublish = false;

        Session::flash('status', 'Borrador eliminado definitivamente.');
    }

    public function deleteCancelledBatch(CurrentCompany $currentCompany, DeleteCancelledScheduleBatchAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $batch = $this->selectedBatch($currentCompany, false);
        Gate::authorize('deleteCancelled', $batch);

        try {
            $action->handle(auth()->user(), $company, $batch);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['deleteCancelledBatch' => $exception->getMessage()]);
        }

        $this->selectedBatchId = null;
        $this->resetPage('calendarPage');
        $this->validationPanel = [];
        $this->comparisonPanel = [];
        $this->integrityPanel = [];
        $this->versionHistoryPanel = [];
        $this->confirmPublish = false;

        Session::flash('status', 'Lote cancelado eliminado definitivamente.');
    }

    public function compareWithPrevious(CurrentCompany $currentCompany, CompareScheduleBatchVersionsAction $action): void
    {
        $batch = $this->selectedBatch($currentCompany, false);
        Gate::authorize('compareVersions', $batch);

        if (! $batch->previousBatch) {
            throw ValidationException::withMessages(['comparison' => 'Esta version no tiene version anterior para comparar.']);
        }

        $this->comparisonPanel = $action->handle($batch->previousBatch, $batch)->toArray();
    }

    public function loadVersionHistory(CurrentCompany $currentCompany, ResolveScheduleBatchVersionChainAction $action): void
    {
        $batch = $this->selectedBatch($currentCompany, false);
        Gate::authorize('viewVersionHistory', $batch);
        $result = $action->handle($batch);
        $this->versionHistoryPanel = [
            'valid' => $result->valid(),
            'errors' => $result->errors,
            'versions' => $result->versions->map(fn (ScheduleBatch $version): array => [
                'id' => $version->id,
                'version' => $version->version,
                'status' => $version->status,
                'published_at' => $version->published_at?->format('Y-m-d H:i'),
                'published_by' => $version->publisher?->name,
                'hash' => $version->snapshot_sha256,
                'correction_reason' => $version->correction_reason,
                'previous_batch_id' => $version->previous_batch_id,
                'superseded_by' => $version->superseded_by,
            ])->all(),
        ];
    }

    public function hideVersionHistory(): void
    {
        $this->versionHistoryPanel = [];
    }

    public function hideValidationPanel(): void
    {
        $this->validationPanel = [];
    }

    public function hideComparisonPanel(): void
    {
        $this->comparisonPanel = [];
    }

    public function hideIntegrityPanel(): void
    {
        $this->integrityPanel = [];
    }

    public function verifyIntegrity(CurrentCompany $currentCompany, VerifyPublishedScheduleBatchSnapshotAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $batch = $this->selectedBatch($currentCompany, false);
        Gate::authorize('view', $batch);
        $result = $action->handle($company, $batch);

        $this->integrityPanel = [
            'valid' => $result->valid,
            'expected_hash' => $result->expectedHash,
            'actual_hash' => $result->actualHash,
            'schema_version' => $result->schemaVersion,
            'json_valid' => $result->jsonValid,
            'errors' => $result->errors,
        ];
    }

    public function with(CurrentCompany $currentCompany): array
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        Gate::authorize('viewAny', [ScheduleBatch::class, $company]);

        $selectedBatch = $this->selectedBatchId
            ? $company->scheduleBatches()->with(['center', 'publisher', 'previousBatch', 'supersededByBatch', 'dailyAssignments.segments', 'dailyAssignments.employmentRelationship.worker', 'dailyAssignments.organizationalUnit', 'dailyAssignments.shiftTemplate'])->whereKey($this->selectedBatchId)->first()
            : null;

        if ($selectedBatch && ! Gate::allows('view', $selectedBatch)) {
            $selectedBatch = null;
            $this->selectedBatchId = null;
        }

        return [
            'company' => $company,
            'centers' => $company->centers()->where('status', 'active')->orderBy('name')->get(),
            'units' => $company->organizationalUnits()->with('center')->where('status', 'active')->orderBy('name')->get(),
            'shiftTemplates' => $company->shiftTemplates()->with('segments')->where('status', 'active')->orderBy('name')->get(),
            'batches' => $this->batchQuery($company)->paginate(8),
            'selectedBatch' => $selectedBatch,
            'selectedSummary' => $selectedBatch ? $this->batchSummary($selectedBatch) : null,
            'weekDates' => $selectedBatch ? $this->weekDates($selectedBatch) : [],
            'calendarRows' => $selectedBatch ? $this->calendarRows($company, $selectedBatch) : [],
            'canCreateBatch' => Gate::allows('create', [ScheduleBatch::class, $company]),
            'canEditSelectedBatch' => $selectedBatch ? Gate::allows('update', $selectedBatch) : false,
            'canPublishSelectedBatch' => $selectedBatch ? Gate::allows('publish', $selectedBatch) : false,
            'canCreateCorrection' => $selectedBatch ? Gate::allows('createCorrection', $selectedBatch) : false,
            'canPublishCorrection' => $selectedBatch ? Gate::allows('publishCorrection', $selectedBatch) : false,
            'canDeleteDraftSelectedBatch' => $selectedBatch ? Gate::allows('deleteDraft', $selectedBatch) : false,
            'canDeleteCancelledSelectedBatch' => $selectedBatch ? Gate::allows('deleteCancelled', $selectedBatch) : false,
            'canClonePublishedWeek' => $selectedBatch
                ? Gate::allows('create', [ScheduleBatch::class, $company])
                    && $selectedBatch->status === 'published'
                    && $selectedBatch->previous_batch_id === null
                : false,
            'canPrepareNextWeek' => $selectedBatch
                ? Gate::allows('create', [ScheduleBatch::class, $company])
                    && $selectedBatch->previous_batch_id === null
                    && $selectedBatch->status !== 'cancelled'
                : false,
            'previewTemplate' => $this->previewTemplate($company),
            'bulkPreview' => $selectedBatch ? $this->bulkPreview($selectedBatch) : null,
        ];
    }

    /**
     * @return list<ScheduleBatch>
     */
    private function createBatches(CurrentCompany $currentCompany, CreateScheduleBatchAction $action): array
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        Gate::authorize('create', [ScheduleBatch::class, $company]);

        $validated = $this->validate([
            'batchForm.center_id' => ['required', 'integer', Rule::exists('centers', 'id')->where('company_id', $company->id)->where('status', 'active')],
            'batchForm.period_start' => ['required', 'date'],
            'batchForm.weeks' => ['required', 'integer', 'min:1', 'max:4'],
            'batchForm.notes' => ['nullable', 'string', 'max:2000'],
        ])['batchForm'];
        [$validated['period_start'], $validated['period_end']] = $this->naturalWeekForDate($validated['period_start']);
        $center = $company->centers()->where('status', 'active')->whereKey((int) $validated['center_id'])->firstOrFail();
        $weeks = (int) $validated['weeks'];
        $batches = [];
        $periodStarts = [];

        for ($index = 0; $index < $weeks; $index++) {
            $periodStarts[] = CarbonImmutable::parse($validated['period_start'])->addWeeks($index)->toDateString();
        }

        $existingDraft = ScheduleBatch::query()
            ->where('company_id', $company->id)
            ->where('center_id', $center->id)
            ->whereNull('previous_batch_id')
            ->where('status', 'draft')
            ->whereIn('period_start', $periodStarts)
            ->first();

        if ($existingDraft) {
            throw ValidationException::withMessages([
                'batchForm.period_start' => 'Ya existe un borrador abierto en una de las semanas solicitadas.',
            ]);
        }

        foreach ($periodStarts as $periodStart) {
            try {
                $batches[] = $action->handle($company, $center, [
                    'period_start' => $periodStart,
                    'creation_source' => 'manual',
                    'notes' => $validated['notes'] ?: null,
                ], auth()->user());
            } catch (\InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['batchForm.period_start' => $exception->getMessage()]);
            }
        }

        return $batches;
    }

    private function generateFromProfiles(CurrentCompany $currentCompany, GenerateDraftScheduleBatchFromProfilesAction $action, string $mode): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $batch = $this->selectedBatch($currentCompany, true);
        Gate::authorize('generate', $batch);

        try {
            $result = $action->handle(auth()->user(), $company, $batch, $mode);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['generation' => $exception->getMessage()]);
        }

        $this->validationPanel = [];
        Session::flash('status', $this->generationMessage($result));
    }

    private function batchQuery($company)
    {
        $status = trim((string) ($this->filters['status'] ?? 'draft'));
        $centerId = trim((string) ($this->filters['center_id'] ?? ''));
        $workerSearch = trim((string) ($this->filters['worker_search'] ?? ''));
        $unitId = trim((string) ($this->filters['organizational_unit_id'] ?? ''));
        $dayType = trim((string) ($this->filters['day_type'] ?? 'all'));
        $periodStart = trim((string) ($this->filters['period_start'] ?? ''));
        $periodEnd = trim((string) ($this->filters['period_end'] ?? ''));
        $periodScope = trim((string) ($this->filters['period_scope'] ?? 'current_future'));
        $pendingOnly = (bool) ($this->filters['pending_only'] ?? false);
        $centerIds = $this->visibleCenterIds($company);
        $today = CarbonImmutable::today()->toDateString();

        return $company->scheduleBatches()
            ->with(['center', 'publisher'])
            ->withCount([
                'dailyAssignments as total_days',
                'dailyAssignments as pending_days' => fn ($query) => $query->where('day_type', 'unassigned'),
            ])
            ->when($centerIds !== null, fn ($query) => $query->whereIn('center_id', $centerIds))
            ->when($status === 'active_work', fn ($query) => $query->whereIn('status', ['draft', 'published']))
            ->when(! in_array($status, ['all', 'active_work'], true), fn ($query) => $query->where('status', $status))
            ->when($centerId !== '', fn ($query) => $query->where('center_id', (int) $centerId))
            ->when($periodStart !== '', fn ($query) => $query->whereDate('period_end', '>=', $periodStart))
            ->when($periodEnd !== '', fn ($query) => $query->whereDate('period_start', '<=', $periodEnd))
            ->when($periodStart === '' && $periodEnd === '' && $periodScope === 'current_future', fn ($query) => $query->whereDate('period_end', '>=', $today))
            ->when($periodStart === '' && $periodEnd === '' && $periodScope === 'past', fn ($query) => $query->whereDate('period_end', '<', $today))
            ->when($workerSearch !== '', fn ($query) => $query->whereHas('dailyAssignments.employmentRelationship.worker', fn ($workerQuery) => $workerQuery
                ->where('employee_code', 'like', "%{$workerSearch}%")
                ->orWhere('full_name', 'like', "%{$workerSearch}%")))
            ->when($unitId !== '', fn ($query) => $query->whereHas('dailyAssignments', fn ($assignmentQuery) => $assignmentQuery->where('organizational_unit_id', (int) $unitId)))
            ->when($dayType !== 'all', fn ($query) => $query->whereHas('dailyAssignments', fn ($assignmentQuery) => $assignmentQuery->where('day_type', $dayType)))
            ->when($pendingOnly, fn ($query) => $query->whereHas('dailyAssignments', fn ($assignmentQuery) => $assignmentQuery->where('day_type', 'unassigned')))
            ->orderByRaw('case when period_end >= ? then 0 else 1 end', [$today])
            ->orderByRaw('case when period_end >= ? then period_start end asc', [$today])
            ->orderByRaw('case when period_end < ? then period_start end desc', [$today])
            ->orderByDesc('id');
    }

    private function visibleCenterIds($company): ?array
    {
        if (in_array(auth()->user()->roleKeyForCompany($company), RoleKey::companyManagers(), true)) {
            return null;
        }

        if (auth()->user()->roleKeyForCompany($company) !== RoleKey::SUPERVISOR) {
            return [];
        }

        $scope = app(ResolveUserOperationalScopeAction::class)->handle($company, auth()->user(), now()->toDateString());

        return $this->visibleSupervisorCenterIds($company, $scope);
    }

    private function selectedBatch(CurrentCompany $currentCompany, bool $forUpdate): ScheduleBatch
    {
        $batch = $this->authorizedBatch((int) $this->selectedBatchId, $currentCompany, $forUpdate);
        $this->weekStart = $this->calendarWeekStart($batch->period_start->toDateString());

        return $batch;
    }

    private function adjacentBatch(CurrentCompany $currentCompany, ScheduleBatch $batch, string $direction): ?ScheduleBatch
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $centerIds = $this->visibleCenterIds($company);

        $query = $company->scheduleBatches()
            ->with(['center', 'previousBatch', 'supersededByBatch', 'dailyAssignments.segments', 'dailyAssignments.employmentRelationship.worker', 'dailyAssignments.organizationalUnit', 'dailyAssignments.shiftTemplate'])
            ->where('center_id', $batch->center_id)
            ->whereNull('previous_batch_id')
            ->whereIn('status', ['draft', 'published', 'superseded']);

        if ($centerIds !== null) {
            $query->whereIn('center_id', $centerIds);
        }

        if ($direction === 'previous') {
            $query
                ->whereDate('period_end', '<', $batch->period_start->toDateString())
                ->orderByDesc('period_end')
                ->orderByDesc('id');
        } else {
            $query
                ->whereDate('period_start', '>', $batch->period_end->toDateString())
                ->orderBy('period_start')
                ->orderBy('id');
        }

        $adjacent = $query->first();

        return $adjacent && Gate::allows('view', $adjacent) ? $adjacent : null;
    }

    private function openBatchForCalendar(ScheduleBatch $batch): void
    {
        $this->selectedBatchId = $batch->id;
        $this->weekStart = $this->calendarWeekStart($batch->period_start->toDateString());
        $this->validationPanel = [];
        $this->integrityPanel = [];
        $this->comparisonPanel = [];
        $this->versionHistoryPanel = [];
        $this->showPrepareWeeksPanel = false;
        $this->showDayPanel = false;
        $this->showBulkPanel = false;
        $this->resetPage('calendarPage');
    }

    private function authorizedBatch(int $batchId, CurrentCompany $currentCompany, bool $forUpdate): ScheduleBatch
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $batch = $company->scheduleBatches()
            ->with(['center', 'previousBatch', 'supersededByBatch', 'dailyAssignments.segments', 'dailyAssignments.employmentRelationship.worker', 'dailyAssignments.organizationalUnit', 'dailyAssignments.shiftTemplate'])
            ->whereKey($batchId)
            ->firstOrFail();

        Gate::authorize($forUpdate ? 'update' : 'view', $batch);

        return $batch;
    }

    private function calendarRows($company, ScheduleBatch $batch): LengthAwarePaginator
    {
        $expected = $this->calendarRelationshipDates($company, $batch);
        $dates = collect($this->weekDates($batch))->map(fn ($date) => $date['date'])->all();
        $assignments = $batch->dailyAssignments
            ->filter(fn (DailyScheduleAssignment $assignment) => in_array($assignment->work_date->toDateString(), $dates, true))
            ->keyBy(fn (DailyScheduleAssignment $assignment) => $assignment->employment_relationship_id.'|'.$assignment->work_date->toDateString());

        $rows = [];
        foreach ($expected as $item) {
            /** @var EmploymentRelationship $relationship */
            $relationship = $item['relationship'];
            if (! $this->relationshipPassesCalendarFilters($company, $batch, $relationship)) {
                continue;
            }

            $cells = [];
            foreach ($dates as $date) {
                if (! in_array($date, $item['dates'], true)) {
                    $cells[] = ['date' => $date, 'assignment' => null, 'outside_vigence' => true, 'relationship_effective' => false, 'historical_only' => false];
                    continue;
                }

                $assignment = $assignments[$relationship->id.'|'.$date] ?? null;
                $relationshipEffective = $relationship->isEffectiveOn($date);
                $cells[] = [
                    'date' => $date,
                    'assignment' => $assignment,
                    'outside_vigence' => false,
                    'relationship_effective' => $relationshipEffective,
                    'historical_only' => ! $relationshipEffective && $assignment !== null,
                ];
            }

            $unitName = collect($cells)
                ->map(fn (array $cell) => $cell['assignment']?->organizationalUnit?->name)
                ->filter()
                ->first();

            $rows[] = [
                'relationship' => $relationship,
                'organizational_unit_name' => $unitName,
                'cells' => $cells,
                'historical_only_dates' => collect($cells)->where('historical_only', true)->count(),
            ];
        }

        $perPage = 8;
        $total = count($rows);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, (int) $this->getPage('calendarPage')), $lastPage);

        return new LengthAwarePaginator(
            collect($rows)->forPage($page, $perPage)->values(),
            $total,
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => 'calendarPage',
            ],
        );
    }

    private function calendarRelationshipDates($company, ScheduleBatch $batch)
    {
        if (in_array($batch->status, ['published', 'superseded'], true) || $batch->previous_batch_id !== null) {
            return $batch->dailyAssignments
                ->loadMissing(['employmentRelationship.worker', 'employmentRelationship.center'])
                ->groupBy('employment_relationship_id')
                ->map(function ($assignments): array {
                    /** @var DailyScheduleAssignment $first */
                    $first = $assignments->first();

                    return [
                        'relationship' => $first->employmentRelationship,
                        'dates' => $assignments
                            ->map(fn (DailyScheduleAssignment $assignment): string => $assignment->work_date->toDateString())
                            ->unique()
                            ->sort()
                            ->values()
                            ->all(),
                    ];
                })
                ->filter(fn (array $item): bool => $item['relationship'] !== null && $item['dates'] !== [])
                ->sortBy(fn (array $item): string => sprintf('%010d-%010d', $item['relationship']->worker_id, $item['relationship']->id))
                ->values();
        }

        return app(ResolveScheduleBatchExpectedRelationshipDatesAction::class)->handle($company, $batch);
    }

    private function relationshipPassesCalendarFilters($company, ScheduleBatch $batch, EmploymentRelationship $relationship): bool
    {
        $search = trim((string) ($this->filters['worker_search'] ?? ''));
        if ($search !== ''
            && ! str_contains(strtolower((string) $relationship->worker?->employee_code), strtolower($search))
            && ! str_contains(strtolower((string) $relationship->worker?->full_name), strtolower($search))) {
            return false;
        }

        $unitId = trim((string) ($this->filters['organizational_unit_id'] ?? ''));
        if ($unitId !== ''
            && ! $batch->dailyAssignments
                ->where('employment_relationship_id', $relationship->id)
                ->contains(fn (DailyScheduleAssignment $assignment) => (int) $assignment->organizational_unit_id === (int) $unitId)) {
            return false;
        }

        if (auth()->user()->roleKeyForCompany($company) !== RoleKey::SUPERVISOR) {
            return true;
        }

        $scope = app(ResolveUserOperationalScopeAction::class)->handle($company, auth()->user(), now()->toDateString());
        if (in_array((int) $relationship->center_id, $scope['center_ids'], true)) {
            return true;
        }

        if ($scope['organizational_unit_ids'] === []) {
            return false;
        }

        return $batch->dailyAssignments
            ->where('employment_relationship_id', $relationship->id)
            ->contains(fn (DailyScheduleAssignment $assignment) => in_array((int) $assignment->organizational_unit_id, $scope['organizational_unit_ids'], true));
    }

    private function visibleSupervisorCenterIds($company, array $scope): array
    {
        $unitCenterIds = $scope['organizational_unit_ids'] === []
            ? []
            : OrganizationalUnit::query()
                ->where('company_id', $company->id)
                ->whereIn('id', $scope['organizational_unit_ids'])
                ->pluck('center_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->all();

        return array_values(array_unique([...$scope['center_ids'], ...$unitCenterIds]));
    }

    private function weekDates(ScheduleBatch $batch): array
    {
        $start = CarbonImmutable::parse($this->calendarWeekStart($batch->period_start->toDateString()));

        return collect(range(0, 6))->map(function (int $offset) use ($start, $batch): array {
            $date = $start->addDays($offset);

            return [
                'date' => $date->toDateString(),
                'label' => ucfirst($date->locale('es_MX')->isoFormat('ddd DD/MM')),
                'outside_period' => $date->lt($batch->period_start) || $date->gt($batch->period_end),
            ];
        })->values()->all();
    }

    private function calendarWeekStart(string $date): string
    {
        return CarbonImmutable::parse($date)->startOfWeek(CarbonInterface::MONDAY)->toDateString();
    }

    private function batchSummary(ScheduleBatch $batch): array
    {
        $assignments = $batch->dailyAssignments;

        return [
            'workers' => $assignments->pluck('employment_relationship_id')->unique()->count(),
            'days' => $assignments->count(),
            'shift' => $assignments->where('day_type', 'shift')->count(),
            'rest' => $assignments->where('day_type', 'rest')->count(),
            'flexible' => $assignments->where('day_type', 'flexible')->count(),
            'on_call' => $assignments->where('day_type', 'on_call')->count(),
            'unassigned' => $assignments->where('day_type', 'unassigned')->count(),
        ];
    }

    private function batchWeekLabel(ScheduleBatch $batch): string
    {
        $date = CarbonImmutable::parse($batch->period_start);

        return 'Semana '.$date->isoWeek().' / '.$date->isoWeekYear();
    }

    private function batchBlockStatus(ScheduleBatch $batch): array
    {
        if ($batch->status === 'published') {
            return ['label' => 'Sin bloqueos', 'variant' => 'success'];
        }

        if ($batch->status !== 'draft') {
            return ['label' => $this->statusLabel($batch->status), 'variant' => 'neutral'];
        }

        if ((int) $batch->total_days === 0 || (int) $batch->pending_days > 0) {
            return ['label' => 'Con bloqueos', 'variant' => 'danger'];
        }

        return ['label' => 'Sin bloqueos', 'variant' => 'success'];
    }

    private function validatedDayPayload($company, ScheduleBatch $batch): array
    {
        $validated = $this->validateEditorData('dayForm')['dayForm'];

        return $this->payloadFromEditorData($validated, $batch, previousSourceType: $this->previousSourceType());
    }

    private function validateEditorData(string $root): array
    {
        $rules = [
            "{$root}.day_type" => ['required', Rule::in(['shift', 'rest', 'flexible', 'on_call', 'unassigned'])],
            "{$root}.shift_template_id" => ['nullable', 'integer'],
            "{$root}.required_minutes" => ['nullable', 'integer', 'min:1', 'max:1440'],
            "{$root}.uses_window" => ['boolean'],
            "{$root}.window_start_local_time" => ['nullable', 'date_format:H:i'],
            "{$root}.window_end_local_time" => ['nullable', 'date_format:H:i'],
            "{$root}.window_start_day_offset" => ['nullable', Rule::in(['0', '1', 0, 1])],
            "{$root}.window_end_day_offset" => ['nullable', Rule::in(['0', '1', 0, 1])],
            "{$root}.availability_start_local_time" => ['nullable', 'date_format:H:i'],
            "{$root}.availability_end_local_time" => ['nullable', 'date_format:H:i'],
            "{$root}.availability_start_day_offset" => ['nullable', Rule::in(['0', '1', 0, 1])],
            "{$root}.availability_end_day_offset" => ['nullable', Rule::in(['0', '1', 0, 1])],
            "{$root}.max_work_minutes" => ['nullable', 'integer', 'min:1', 'max:1440'],
            "{$root}.reason" => ['required', 'string', 'max:1000'],
            "{$root}.pending_reason" => ['nullable', 'string', 'max:100'],
        ];

        return $this->validate($rules);
    }

    private function validateBulkForm($company, ScheduleBatch $batch): array
    {
        $validated = $this->validate([
            'bulkForm.employment_relationship_ids' => ['required', 'array', 'min:1'],
            'bulkForm.employment_relationship_ids.*' => ['integer'],
            'bulkForm.date_from' => ['required', 'date'],
            'bulkForm.date_to' => ['required', 'date', 'after_or_equal:bulkForm.date_from'],
            ...$this->prefixRules($this->editorRules(), 'bulkForm'),
        ])['bulkForm'];

        $this->datesBetween($validated['date_from'], $validated['date_to'], $batch);

        return $validated;
    }

    private function editorRules(): array
    {
        return [
            'day_type' => ['required', Rule::in(['shift', 'rest', 'flexible', 'on_call', 'unassigned'])],
            'shift_template_id' => ['nullable', 'integer'],
            'required_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'uses_window' => ['boolean'],
            'window_start_local_time' => ['nullable', 'date_format:H:i'],
            'window_end_local_time' => ['nullable', 'date_format:H:i'],
            'window_start_day_offset' => ['nullable', Rule::in(['0', '1', 0, 1])],
            'window_end_day_offset' => ['nullable', Rule::in(['0', '1', 0, 1])],
            'availability_start_local_time' => ['nullable', 'date_format:H:i'],
            'availability_end_local_time' => ['nullable', 'date_format:H:i'],
            'availability_start_day_offset' => ['nullable', Rule::in(['0', '1', 0, 1])],
            'availability_end_day_offset' => ['nullable', Rule::in(['0', '1', 0, 1])],
            'max_work_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'reason' => ['required', 'string', 'max:1000'],
            'pending_reason' => ['nullable', 'string', 'max:100'],
        ];
    }

    private function prefixRules(array $rules, string $prefix): array
    {
        $prefixed = [];
        foreach ($rules as $field => $rule) {
            $prefixed["{$prefix}.{$field}"] = $rule;
        }

        return $prefixed;
    }

    private function payloadFromEditorData(array $data, ScheduleBatch $batch, string $previousSourceType): array
    {
        $dayType = $data['day_type'];
        $payload = [
            'work_date' => $this->editingWorkDate,
            'day_type' => $dayType,
            'timezone' => $batch->center->timezone ?: $batch->company->timezone,
            'source_type' => 'manual',
            'source_reference' => [
                'schema_version' => 1,
                'editor' => 'daily_schedule_ui',
                'correction' => $batch->previous_batch_id !== null,
                'reason' => trim((string) $data['reason']),
                'previous_source_type' => $previousSourceType,
                'pending_reason' => $dayType === 'unassigned' ? ($data['pending_reason'] ?: 'manual_definition_required') : null,
            ],
            'metadata' => [],
        ];

        if ($dayType === 'shift') {
            $payload['shift_template_id'] = (int) ($data['shift_template_id'] ?? 0);
        } elseif ($dayType === 'flexible') {
            $payload['required_minutes'] = (int) ($data['required_minutes'] ?? 0);
            if ((bool) ($data['uses_window'] ?? false)) {
                $payload['window_start_local_time'] = $data['window_start_local_time'] ?: null;
                $payload['window_end_local_time'] = $data['window_end_local_time'] ?: null;
                $payload['window_start_day_offset'] = (int) ($data['window_start_day_offset'] ?? 0);
                $payload['window_end_day_offset'] = (int) ($data['window_end_day_offset'] ?? 0);
            }
        } elseif ($dayType === 'on_call') {
            $payload['availability_start_local_time'] = $data['availability_start_local_time'] ?: null;
            $payload['availability_end_local_time'] = $data['availability_end_local_time'] ?: null;
            $payload['availability_start_day_offset'] = (int) ($data['availability_start_day_offset'] ?? 0);
            $payload['availability_end_day_offset'] = (int) ($data['availability_end_day_offset'] ?? 0);
            $payload['max_work_minutes'] = (int) ($data['max_work_minutes'] ?? 0);
        }

        return $payload;
    }

    private function segmentsForDayPayload($company, ScheduleBatch $batch, array $payload, BuildDailyScheduleSegmentsFromShiftTemplateAction $segmentsBuilder): array
    {
        if (($payload['day_type'] ?? null) !== 'shift') {
            return [];
        }

        $template = $company->shiftTemplates()
            ->where('status', 'active')
            ->whereKey((int) ($payload['shift_template_id'] ?? 0))
            ->firstOrFail();

        return $segmentsBuilder->handle($template, (string) $this->editingWorkDate, $batch->center->timezone ?: $company->timezone);
    }

    private function relationshipForBatch(ScheduleBatch $batch, int $relationshipId, string $workDate): EmploymentRelationship
    {
        $query = EmploymentRelationship::query()
            ->where('company_id', $batch->company_id)
            ->where('center_id', $batch->center_id)
            ->whereKey($relationshipId);

        if ($batch->previous_batch_id === null) {
            $query
                ->where('status', 'active')
                ->whereDate('started_at', '<=', $workDate)
                ->where(function ($query) use ($workDate): void {
                    $query->whereNull('ended_at')->orWhereDate('ended_at', '>=', $workDate);
                });
        } else {
            $query->whereHas('dailyScheduleAssignments', function ($query) use ($batch, $workDate): void {
                $query
                    ->where('schedule_batch_id', $batch->id)
                    ->whereDate('work_date', $workDate);
            });
        }

        return $query->firstOrFail();
    }

    private function datesBetween(string $from, string $to, ScheduleBatch $batch): array
    {
        if ($from < $batch->period_start->toDateString() || $to > $batch->period_end->toDateString()) {
            throw ValidationException::withMessages(['bulkForm.date_from' => 'El rango debe estar dentro del periodo del lote.']);
        }

        return collect(CarbonPeriod::create($from, $to))->map(fn ($date) => $date->toDateString())->all();
    }

    private function bulkPreview(ScheduleBatch $batch): array
    {
        $ids = array_map('intval', $this->bulkForm['employment_relationship_ids'] ?? []);
        $dates = [];
        if (($this->bulkForm['date_from'] ?? '') !== '' && ($this->bulkForm['date_to'] ?? '') !== '') {
            try {
                $dates = $this->datesBetween($this->bulkForm['date_from'], $this->bulkForm['date_to'], $batch);
            } catch (\Throwable) {
                $dates = [];
            }
        }

        $existing = $batch->loadMissing('dailyAssignments')->dailyAssignments
            ->filter(fn (DailyScheduleAssignment $assignment): bool => in_array((int) $assignment->employment_relationship_id, $ids, true)
                && in_array($assignment->work_date->toDateString(), $dates, true));
        $relationships = EmploymentRelationship::query()
            ->where('company_id', $batch->company_id)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
        $historicalOnly = 0;
        foreach ($ids as $id) {
            $relationship = $relationships[(int) $id] ?? null;
            if (! $relationship) {
                continue;
            }

            foreach ($dates as $date) {
                if (! $relationship->isEffectiveOn($date)
                    && $existing->contains(fn (DailyScheduleAssignment $assignment): bool => (int) $assignment->employment_relationship_id === (int) $id && $assignment->work_date->toDateString() === $date)) {
                    $historicalOnly++;
                }
            }
        }

        return [
            'workers' => count($ids),
            'dates' => count($dates),
            'total' => count($ids) * count($dates),
            'manual' => $existing->where('source_type', 'manual')->count(),
            'generated' => $existing->where('source_type', 'profile')->count() + $existing->where('source_type', 'system')->count(),
            'historical_only' => $historicalOnly,
        ];
    }

    private function previewTemplate($company): ?array
    {
        $templateId = (int) ($this->dayForm['shift_template_id'] ?? 0);
        if ($templateId <= 0) {
            $templateId = (int) ($this->bulkForm['shift_template_id'] ?? 0);
        }

        $template = $templateId > 0 ? $company->shiftTemplates()->with('segments')->where('status', 'active')->whereKey($templateId)->first() : null;
        if (! $template) {
            return null;
        }

        return [
            'template' => $template,
            'lines' => $template->segments->map(fn ($segment) => $this->segmentLine($segment->toArray()))->all(),
            'metrics' => ShiftTemplateTimeline::fromSegments($template->segments)->metrics(),
        ];
    }

    private function formFromAssignment(?DailyScheduleAssignment $assignment): array
    {
        $form = $this->emptyDayForm();
        if (! $assignment) {
            return $form;
        }

        $form['day_type'] = $assignment->day_type;
        $form['shift_template_id'] = $assignment->shift_template_id ? (string) $assignment->shift_template_id : '';
        $form['required_minutes'] = $assignment->required_minutes ? (string) $assignment->required_minutes : '';
        $form['uses_window'] = filled($assignment->window_start_local_time) && filled($assignment->window_end_local_time);
        $form['window_start_local_time'] = $this->timeInput($assignment->window_start_local_time);
        $form['window_end_local_time'] = $this->timeInput($assignment->window_end_local_time);
        $form['window_start_day_offset'] = (string) $assignment->window_start_day_offset;
        $form['window_end_day_offset'] = (string) $assignment->window_end_day_offset;
        $form['availability_start_local_time'] = $this->timeInput($assignment->availability_start_local_time);
        $form['availability_end_local_time'] = $this->timeInput($assignment->availability_end_local_time);
        $form['availability_start_day_offset'] = (string) $assignment->availability_start_day_offset;
        $form['availability_end_day_offset'] = (string) $assignment->availability_end_day_offset;
        $form['max_work_minutes'] = $assignment->max_work_minutes ? (string) $assignment->max_work_minutes : '';
        $form['reason'] = '';
        $form['pending_reason'] = $assignment->source_reference['pending_reason'] ?? '';

        return $form;
    }

    private function previousSourceType(): string
    {
        if (! $this->editingAssignmentId) {
            return 'none';
        }

        return DailyScheduleAssignment::query()->whereKey($this->editingAssignmentId)->value('source_type') ?? 'none';
    }

    private function emptyBatchForm(): array
    {
        return [
            'center_id' => '',
            'period_start' => $this->naturalWeekForDate(now()->toDateString())[0],
            'period_end' => $this->naturalWeekForDate(now()->toDateString())[1],
            'weeks' => '1',
            'notes' => '',
        ];
    }

    private function naturalWeekForDate(string $date): array
    {
        $start = CarbonImmutable::parse($date)->startOfWeek(CarbonInterface::MONDAY);

        return [
            $start->toDateString(),
            $start->addDays(6)->toDateString(),
        ];
    }

    private function emptyDayForm(): array
    {
        return [
            'day_type' => 'shift',
            'shift_template_id' => '',
            'required_minutes' => '480',
            'uses_window' => false,
            'window_start_local_time' => '07:00',
            'window_end_local_time' => '20:00',
            'window_start_day_offset' => '0',
            'window_end_day_offset' => '0',
            'availability_start_local_time' => '06:00',
            'availability_end_local_time' => '22:00',
            'availability_start_day_offset' => '0',
            'availability_end_day_offset' => '0',
            'max_work_minutes' => '480',
            'reason' => '',
            'pending_reason' => 'manual_definition_required',
        ];
    }

    private function emptyBulkForm(?ScheduleBatch $batch = null): array
    {
        return $this->emptyDayForm() + [
            'employment_relationship_ids' => [],
            'date_from' => $batch?->period_start?->toDateString() ?? now()->toDateString(),
            'date_to' => $batch?->period_start?->toDateString() ?? now()->toDateString(),
        ];
    }

    private function emptyPrepareWeeksForm(): array
    {
        return [
            'weeks' => '2',
        ];
    }

    private function emptyCloneWeekForm(): array
    {
        $week = $this->naturalWeekForDate(now()->toDateString());

        return [
            'target_date' => $week[0],
            'target_end' => $week[1],
        ];
    }

    private function generationMessage($result): string
    {
        return "Generacion lista: {$result->relationshipsConsidered} relaciones, {$result->assignmentsCreated} dias creados, {$result->assignmentsRefreshed} actualizados, {$result->assignmentsPreserved} preservados.";
    }

    private function normalValidationPanel(array $result): array
    {
        return [
            'valid' => $result['valid'],
            'errors' => $result['errors'],
            'warnings' => $result['warnings'] ?? [],
            'relationships_expected' => $result['relationships_expected'] ?? 0,
            'dates_expected' => $result['dates_expected'] ?? $result['assignments_expected'] ?? 0,
            'assignments_expected' => $result['assignments_expected'] ?? 0,
            'assignments_found' => $result['assignments_found'] ?? 0,
            'assignments_missing' => $result['assignments_missing'] ?? 0,
            'assignments_added' => $result['assignments_added'] ?? 0,
            'assignments_unassigned' => $result['assignments_unassigned'] ?? 0,
            'assignments_shift' => $result['assignments_shift'] ?? ($result['counts_by_day_type']['shift'] ?? 0),
            'assignments_rest' => $result['assignments_rest'] ?? ($result['counts_by_day_type']['rest'] ?? 0),
            'assignments_flexible' => $result['assignments_flexible'] ?? ($result['counts_by_day_type']['flexible'] ?? 0),
            'assignments_on_call' => $result['assignments_on_call'] ?? ($result['counts_by_day_type']['on_call'] ?? 0),
            'conflicting_assignments' => $result['conflicting_assignments'] ?? $result['conflicting_batches'] ?? 0,
            'changed_days' => $result['changed_days'] ?? null,
            'unchanged_days' => $result['unchanged_days'] ?? null,
            'snapshot_ready' => $result['snapshot_ready'] ?? false,
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'Borrador',
            'published' => 'Publicado',
            'superseded' => 'Sustituido',
            'cancelled' => 'Cancelado',
            default => 'Estado no reconocido',
        };
    }

    private function dayTypeLabel(?string $type): string
    {
        return match ($type) {
            'shift' => 'Turno',
            'rest' => 'Descanso',
            'flexible' => 'Flexible',
            'on_call' => 'Guardia',
            'unassigned' => 'Pendiente',
            default => 'Sin programacion',
        };
    }

    private function calendarCellClasses(?DailyScheduleAssignment $assignment, bool $historicalOnly = false): string
    {
        if ($historicalOnly) {
            return 'border-zinc-200 bg-zinc-50 text-zinc-700 hover:border-zinc-400 hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900/70 dark:text-zinc-200 dark:hover:border-zinc-600';
        }

        return match ($assignment?->day_type) {
            'shift' => 'border-sky-200 bg-sky-50 text-sky-950 hover:border-sky-400 hover:bg-sky-100 dark:border-sky-800 dark:bg-sky-950/50 dark:text-sky-100 dark:hover:border-sky-600',
            'rest' => 'border-emerald-200 bg-emerald-50 text-emerald-950 hover:border-emerald-400 hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-100 dark:hover:border-emerald-600',
            'flexible' => 'border-violet-200 bg-violet-50 text-violet-950 hover:border-violet-400 hover:bg-violet-100 dark:border-violet-800 dark:bg-violet-950/50 dark:text-violet-100 dark:hover:border-violet-600',
            'on_call' => 'border-amber-200 bg-amber-50 text-amber-950 hover:border-amber-400 hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-950/50 dark:text-amber-100 dark:hover:border-amber-600',
            'unassigned', null => 'border-rose-200 bg-rose-50 text-rose-950 hover:border-rose-400 hover:bg-rose-100 dark:border-rose-800 dark:bg-rose-950/50 dark:text-rose-100 dark:hover:border-rose-600',
            default => 'border-zinc-200 bg-zinc-50 text-zinc-900 hover:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100',
        };
    }

    private function calendarCellTextClasses(?DailyScheduleAssignment $assignment, bool $historicalOnly = false): string
    {
        if ($historicalOnly) {
            return 'text-zinc-600 dark:text-zinc-300';
        }

        return match ($assignment?->day_type) {
            'shift' => 'text-sky-700 dark:text-sky-200',
            'rest' => 'text-emerald-700 dark:text-emerald-200',
            'flexible' => 'text-violet-700 dark:text-violet-200',
            'on_call' => 'text-amber-700 dark:text-amber-200',
            'unassigned', null => 'text-rose-700 dark:text-rose-200',
            default => 'text-zinc-500 dark:text-zinc-400',
        };
    }
    private function sourceLabel(?string $source): string
    {
        return match ($source) {
            'profile' => 'Perfil',
            'manual' => 'Manual',
            'csv' => 'Archivo',
            'api' => 'Integracion',
            'system' => 'Sistema',
            default => 'Sin origen',
        };
    }

    private function assignmentSummary(?DailyScheduleAssignment $assignment): string
    {
        if (! $assignment) {
            return 'Pendiente de captura';
        }

        return match ($assignment->day_type) {
            'shift' => trim(($assignment->shiftTemplate?->code ?? 'Turno').' '.$this->mainShiftRange($assignment)),
            'rest' => 'Descanso',
            'flexible' => 'Flexible '.$this->formatMinutes((int) $assignment->required_minutes).$this->windowText($assignment),
            'on_call' => 'Guardia '.$this->availabilityText($assignment),
            'unassigned' => 'Pendiente - '.$this->pendingReason($assignment),
            default => 'Sin programacion',
        };
    }

    private function mainShiftRange(DailyScheduleAssignment $assignment): string
    {
        $segment = $assignment->segments->firstWhere('segment_type', 'work');
        if (! $segment) {
            return '';
        }

        return substr((string) $segment->start_local_time, 0, 5).'-'.substr((string) $segment->end_local_time, 0, 5).((int) $segment->end_day_offset === 1 ? ' +1 dia' : '');
    }

    private function windowText(DailyScheduleAssignment $assignment): string
    {
        if (! $assignment->window_start_local_time || ! $assignment->window_end_local_time) {
            return '';
        }

        return ' dentro de '.substr((string) $assignment->window_start_local_time, 0, 5).'-'.substr((string) $assignment->window_end_local_time, 0, 5);
    }

    private function availabilityText(DailyScheduleAssignment $assignment): string
    {
        return substr((string) $assignment->availability_start_local_time, 0, 5).'-'.substr((string) $assignment->availability_end_local_time, 0, 5).' max '.$this->formatMinutes((int) $assignment->max_work_minutes);
    }

    private function pendingReason(DailyScheduleAssignment $assignment): string
    {
        return match ($assignment->source_reference['reason'] ?? $assignment->source_reference['pending_reason'] ?? null) {
            'calendar_requires_daily_definition' => 'Requiere definicion por calendario',
            'no_effective_schedule_profile' => 'No tiene perfil aplicable',
            'manual_definition_required' => 'Requiere definicion manual',
            default => 'Pendiente de captura',
        };
    }

    private function segmentLine(array $segment): string
    {
        $label = $segment['segment_type'] === 'work' ? 'Trabajo' : ((bool) ($segment['is_paid'] ?? false) ? 'Descanso pagado' : 'Descanso no pagado');
        if (($segment['timing_mode'] ?? 'fixed') === 'duration') {
            return "{$segment['duration_minutes']} min - {$label}";
        }

        return substr((string) $segment['start_local_time'], 0, 5).'-'.substr((string) $segment['end_local_time'], 0, 5).(((int) ($segment['end_day_offset'] ?? 0)) === 1 ? ' (+1 dia)' : '')." {$label}";
    }

    private function formatMinutes(?int $minutes): string
    {
        $minutes = (int) $minutes;
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return $hours > 0 ? trim("{$hours} h ".($rest > 0 ? "{$rest} min" : '')) : "{$rest} min";
    }

    private function timeInput(mixed $time): string
    {
        return $time ? substr((string) $time, 0, 5) : '';
    }

    private function currentCompanyOrFail(CurrentCompany $currentCompany)
    {
        $company = $currentCompany->get();
        abort_unless($company, 403);

        return $company;
    }
}; ?>

<section class="flex h-full w-full flex-1 flex-col gap-6 p-6">
    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        <div>
            <flux:heading size="xl">Programacion semanal</flux:heading>
            <flux:subheading>Arma o ajusta la semana lunes-domingo. Los modelos generan la base; aqui se capturan excepciones, CSV y cambios por demanda.</flux:subheading>
        </div>

        @if ($canCreateBatch)
            <flux:button type="button" icon="plus" wire:click="openCreatePanel" variant="primary">Nueva semana</flux:button>
        @endif
    </div>

    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-100">{{ session('status') }}</div>
    @endif

    <section class="rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-900/60">
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5 xl:items-end">
            <div class="min-w-0">
                <flux:select label="Centro de trabajo" wire:model.live="filters.center_id">
                    <flux:select.option value="">Todos</flux:select.option>
                    @foreach ($centers as $center)
                        <flux:select.option value="{{ $center->id }}">{{ $center->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <div class="min-w-0">
                <flux:select label="Estado" wire:model.live="filters.status">
                    <flux:select.option value="active_work">Borradores y publicados</flux:select.option>
                    <flux:select.option value="draft">Borrador</flux:select.option>
                    <flux:select.option value="published">Publicado</flux:select.option>
                    <flux:select.option value="superseded">Sustituido</flux:select.option>
                    <flux:select.option value="cancelled">Cancelado</flux:select.option>
                    <flux:select.option value="all">Todos</flux:select.option>
                </flux:select>
            </div>
            <div class="min-w-0">
                <flux:select label="Periodo" wire:model.live="filters.period_scope">
                    <flux:select.option value="current_future">Actuales y futuras</flux:select.option>
                    <flux:select.option value="past">Historicas</flux:select.option>
                    <flux:select.option value="all">Todas</flux:select.option>
                </flux:select>
            </div>
            <div class="min-w-0">
                <flux:input label="Buscar trabajador" placeholder="Clave o nombre" wire:model.live.debounce.350ms="filters.worker_search" />
            </div>
            <flux:button class="w-full justify-center" type="button" variant="ghost" wire:click="$toggle('showAdvancedFilters')">
                <span class="inline-flex items-center gap-1.5 leading-none">
                    <span class="text-base leading-none">{{ $showAdvancedFilters ? '-' : '+' }}</span>
                    <span>Filtros</span>
                </span>
            </flux:button>
        </div>

        @if ($showAdvancedFilters)
            <div class="mt-3 grid gap-3 border-t border-zinc-100 pt-3 dark:border-zinc-800 lg:grid-cols-5 lg:items-end">
                <flux:input type="date" label="Desde" wire:model.live="filters.period_start" />
                <flux:input type="date" label="Hasta" wire:model.live="filters.period_end" />
                <flux:select label="Unidad organizacional" wire:model.live="filters.organizational_unit_id">
                    <flux:select.option value="">Todas</flux:select.option>
                    @foreach ($units as $unit)
                        <flux:select.option value="{{ $unit->id }}">{{ $unit->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select label="Tipo de dia" wire:model.live="filters.day_type">
                    <flux:select.option value="all">Todos</flux:select.option>
                    <flux:select.option value="shift">Turno</flux:select.option>
                    <flux:select.option value="rest">Descanso</flux:select.option>
                    <flux:select.option value="flexible">Flexible</flux:select.option>
                    <flux:select.option value="on_call">Guardia</flux:select.option>
                    <flux:select.option value="unassigned">Pendiente</flux:select.option>
                </flux:select>
                <label class="flex min-h-10 items-center gap-2 rounded-md border border-zinc-200 px-3 text-sm dark:border-zinc-700">
                    <input type="checkbox" wire:model.live="filters.pending_only" class="rounded border-zinc-300">
                    <span>Solo pendientes</span>
                </label>
            </div>
        @endif
    </section>

    <section class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex items-center justify-between border-b border-zinc-100 px-3 py-2 dark:border-zinc-800">
            <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">Semanas</p>
            <p class="text-xs text-zinc-500">{{ $batches->total() }} encontrados</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[820px] divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                <thead class="bg-zinc-50 text-left text-[11px] font-medium uppercase text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                    <tr>
                        <th class="px-3 py-2">Centro</th>
                        <th class="px-3 py-2">Periodo</th>
                        <th class="px-3 py-2">Semana</th>
                        <th class="px-3 py-2">Estado</th>
                        <th class="px-3 py-2">Version</th>
                        <th class="px-3 py-2">Bloqueos</th>
                        <th class="px-3 py-2">Publicacion</th>
                        <th class="px-3 py-2 text-right">Accion</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 [&>tr:nth-child(odd)]:bg-white [&>tr:nth-child(even)]:bg-zinc-50/60 dark:divide-zinc-800 dark:[&>tr:nth-child(odd)]:bg-zinc-900 dark:[&>tr:nth-child(even)]:bg-zinc-800/40">
                    @forelse ($batches as $batch)
                        @php($blockStatus = $this->batchBlockStatus($batch))
                        <tr class="{{ $selectedBatchId === $batch->id ? '!bg-sky-50 dark:!bg-sky-950/30' : 'hover:bg-zinc-100 dark:hover:bg-zinc-800/70' }}">
                            <td class="px-3 py-2 font-medium">{{ $batch->center?->name }}</td>
                            <td class="px-3 py-2 whitespace-nowrap">{{ $batch->period_start->toDateString() }} - {{ $batch->period_end->toDateString() }}</td>
                            <td class="px-3 py-2 whitespace-nowrap">{{ $this->batchWeekLabel($batch) }}</td>
                            <td class="px-3 py-2">
                                <x-ui.badge variant="{{ $batch->status === 'draft' ? 'warning' : ($batch->status === 'published' ? 'success' : 'neutral') }}">
                                    {{ $this->statusLabel($batch->status) }}
                                </x-ui.badge>
                            </td>
                            <td class="px-3 py-2 text-zinc-600 dark:text-zinc-300">{{ $batch->version ? 'Version '.$batch->version : 'Sin version' }}</td>
                            <td class="px-3 py-2">
                                <x-ui.badge variant="{{ $blockStatus['variant'] }}">
                                    {{ $blockStatus['label'] }}
                                </x-ui.badge>
                            </td>
                            <td class="px-3 py-2 text-zinc-600 dark:text-zinc-300">
                                @if ($batch->published_at)
                                    {{ $batch->published_at->format('d/m/Y H:i') }}
                                @else
                                    Sin publicar
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right">
                                @if ($batch->status === 'draft')
                                    <button type="button" wire:click="selectBatch({{ $batch->id }})" class="inline-flex items-center rounded-md border border-sky-200 bg-sky-50 px-3 py-1.5 text-xs font-medium text-sky-700 transition hover:border-sky-300 hover:bg-sky-100 hover:text-sky-900 focus:outline-none focus:ring-2 focus:ring-sky-200 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-200 dark:hover:bg-sky-900/70">
                                        Abrir
                                    </button>
                                @else
                                    <button type="button" wire:click="selectBatch({{ $batch->id }})" class="inline-flex items-center rounded-md border border-zinc-200 bg-zinc-50 px-3 py-1.5 text-xs font-medium text-zinc-700 transition hover:border-zinc-300 hover:bg-zinc-100 hover:text-zinc-950 focus:outline-none focus:ring-2 focus:ring-zinc-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700">
                                        Consultar
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-3 py-5 text-center text-zinc-500">No hay semanas con los filtros actuales.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{ $batches->links() }}

    @if ($selectedBatch)
        <section
            wire:key="daily-calendar-{{ $selectedBatch->id }}"
            x-data="{ visible: false }"
            x-init="requestAnimationFrame(() => visible = true)"
            x-show="visible"
            x-transition:enter="transition-opacity ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-in duration-250"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="space-y-3 rounded-lg border border-zinc-200 bg-surface-muted p-4 dark:border-zinc-700 dark:bg-zinc-950/40"
        >
            <div class="min-w-0">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <flux:heading>{{ $selectedBatch->center?->name }} - {{ $selectedBatch->period_start->toDateString() }} a {{ $selectedBatch->period_end->toDateString() }}</flux:heading>
                    </div>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md border border-zinc-300 bg-zinc-100 px-4 py-2 text-sm font-medium leading-5 text-zinc-700 shadow-sm transition hover:bg-zinc-200 hover:text-zinc-950 focus:outline-none focus:ring-4 focus:ring-zinc-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700 dark:hover:text-white dark:focus:ring-zinc-700/60"
                        x-on:click="visible = false; setTimeout(() => $wire.closeCalendar(), 260)"
                    >
                        Cerrar
                    </button>
                </div>
                <flux:subheading>
                    @if ($selectedBatch->previous_batch_id)
                        Correccion de programacion - {{ $selectedBatch->version ? 'Version '.$selectedBatch->version : 'Borrador correctivo' }} - {{ $this->statusLabel($selectedBatch->status) }}
                    @else
                        {{ $selectedBatch->version ? 'Version '.$selectedBatch->version : 'Borrador sin version publicada' }} - {{ $this->statusLabel($selectedBatch->status) }}
                    @endif
                </flux:subheading>
            </div>

            <div class="flex justify-end">
                <div class="flex flex-wrap items-center justify-end gap-2">
                    @if ($canEditSelectedBatch && ! $selectedBatch->previous_batch_id)
                        <flux:button size="xs" variant="ghost" wire:click="generateMissing">Generar</flux:button>
                        <flux:button size="xs" variant="ghost" wire:click="refreshGenerated" wire:confirm="Actualiza los dias generados desde perfiles. Los cambios manuales y cargas externas se conservaran.">Actualizar</flux:button>
                    @endif
                    @if ($canPrepareNextWeek)
                        <flux:button size="xs" variant="ghost" wire:click="openPrepareWeeksPanel">Preparar semanas</flux:button>
                    @endif
                    @if ($canClonePublishedWeek)
                        <flux:button size="xs" variant="ghost" wire:click="openCloneWeekPanel">Clonar semana</flux:button>
                    @endif
                    @if ($canEditSelectedBatch)
                        <flux:button size="xs" variant="ghost" wire:click="openBulkPanel">Masivo</flux:button>
                    @endif
                    <flux:button size="xs" variant="ghost" wire:click="reviewBatch">Revisar</flux:button>
                    @if ($selectedBatch->previous_batch_id)
                        <flux:button size="xs" variant="ghost" wire:click="compareWithPrevious">Comparar con version anterior</flux:button>
                    @endif
                    @if (in_array($selectedBatch->status, ['published', 'superseded'], true))
                        <flux:button size="xs" variant="ghost" wire:click="loadVersionHistory">Historial</flux:button>
                    @endif
                    @if ($canEditSelectedBatch)
                        <livewire:scheduling.daily-schedule-csv-import
                            :schedule-batch-id="$selectedBatch->id"
                            :worker-search="$filters['worker_search'] ?? ''"
                            :organizational-unit-id="$filters['organizational_unit_id'] ?? ''"
                            :key="'daily-csv-import-action-'.$selectedBatch->id.'-'.($filters['worker_search'] ?? '').'-'.($filters['organizational_unit_id'] ?? '')"
                        />
                    @endif
                    @if ($selectedBatch->status === 'published')
                        <flux:button size="xs" variant="ghost" wire:click="verifyIntegrity">Integridad</flux:button>
                    @endif
                    @if ($canCreateCorrection)
                        <flux:button size="xs" variant="primary" wire:click="openCorrectionPanel">Correccion</flux:button>
                    @endif
                    @if ($canDeleteDraftSelectedBatch)
                        <flux:button size="xs" variant="danger" wire:confirm="Borrar definitivamente este borrador? Esta accion no se puede deshacer." wire:click="deleteDraftBatch">Borrar</flux:button>
                    @endif
                    @if ($canDeleteCancelledSelectedBatch)
                        <flux:button size="xs" variant="danger" wire:confirm="Eliminar definitivamente este lote cancelado? Esta accion no se puede deshacer." wire:click="deleteCancelledBatch">Eliminar definitivo</flux:button>
                    @endif
                </div>
            </div>
            @error('deleteDraftBatch')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

            @if ($selectedBatch->status === 'cancelled')
                <div class="rounded-md border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                    <p class="font-medium">Lote cancelado</p>
                    <p>Motivo: {{ $selectedBatch->cancellation_reason ?: 'Sin motivo registrado' }}</p>
                    <p>Cancelado: {{ $selectedBatch->cancelled_at?->format('Y-m-d H:i') }} por {{ $selectedBatch->canceller?->name ?? 'Usuario' }}</p>
                </div>
                @error('deleteCancelledBatch')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            @endif

            @if ($selectedBatch->status === 'superseded')
                <div class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100">
                    Esta version fue sustituida. Se conserva como evidencia historica.
                    @if ($selectedBatch->supersededByBatch)
                        Sustituida por version {{ $selectedBatch->supersededByBatch->version }}.
                    @endif
                </div>
            @endif

            <div class="flex flex-wrap gap-x-4 gap-y-1 rounded-md bg-zinc-50 px-3 py-2 text-sm dark:bg-zinc-800">
                <span><strong>{{ $selectedSummary['workers'] }}</strong> trabajadores</span>
                <span><strong>{{ $selectedSummary['days'] }}</strong> dias</span>
                <span><strong>{{ $selectedSummary['shift'] }}</strong> turnos</span>
                <span><strong>{{ $selectedSummary['rest'] }}</strong> descansos</span>
                <span><strong>{{ $selectedSummary['flexible'] }}</strong> flexibles</span>
                <span><strong>{{ $selectedSummary['on_call'] }}</strong> guardias</span>
                <span class="{{ $selectedSummary['unassigned'] > 0 ? 'font-medium text-amber-700 dark:text-amber-300' : '' }}"><strong>{{ $selectedSummary['unassigned'] }}</strong> pendientes</span>
            </div>

            @if ($selectedSummary['unassigned'] > 0)
                <p class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100">Existen {{ $selectedSummary['unassigned'] }} dias pendientes de definicion.</p>
            @endif

            @if ($validationPanel !== [])
                <div class="grid gap-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700 md:grid-cols-3">
                    <div>
                        <div class="flex items-center justify-between gap-3">
                            <p class="font-medium">{{ $validationPanel['valid'] ? 'Listo para publicar' : 'Bloqueos para publicar' }}</p>
                            <flux:button size="xs" variant="ghost" wire:click="hideValidationPanel">Ocultar</flux:button>
                        </div>
                        @forelse ($validationPanel['errors'] as $error)
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $error }}</p>
                        @empty
                            <p class="mt-1 text-sm text-emerald-700 dark:text-emerald-300">No se detectaron bloqueos.</p>
                        @endforelse
                    </div>
                    <div class="text-sm">
                        <p>Relaciones esperadas: {{ $validationPanel['relationships_expected'] }}</p>
                        <p>Dias esperados: {{ $validationPanel['dates_expected'] }}</p>
                        <p>Encontrados: {{ $validationPanel['assignments_found'] }}</p>
                        <p>Faltantes: {{ $validationPanel['assignments_missing'] }}</p>
                        @if (($validationPanel['assignments_added'] ?? 0) > 0)
                            <p>Adicionales: {{ $validationPanel['assignments_added'] }}</p>
                        @endif
                    </div>
                    <div class="text-sm">
                        <p>Turnos: {{ $validationPanel['assignments_shift'] }}</p>
                        <p>Descansos: {{ $validationPanel['assignments_rest'] }}</p>
                        <p>Flexibles: {{ $validationPanel['assignments_flexible'] }}</p>
                        <p>Guardias: {{ $validationPanel['assignments_on_call'] }}</p>
                        <p>Conflictos: {{ $validationPanel['conflicting_assignments'] }}</p>
                        @if (($validationPanel['changed_days'] ?? null) !== null)
                            <p>Modificados: {{ $validationPanel['changed_days'] }}</p>
                            <p>Sin cambio: {{ $validationPanel['unchanged_days'] }}</p>
                        @endif
                    </div>
                    @if ($validationPanel['valid'] && ($canPublishSelectedBatch || $canPublishCorrection))
                        <div class="md:col-span-3 rounded-md border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-100">
                            <p class="font-medium">{{ $selectedBatch->previous_batch_id ? 'Publicar correccion' : 'Publicar programacion' }}</p>
                            <p>
                                @if ($selectedBatch->previous_batch_id)
                                    Al publicar, la version anterior quedara marcada como Sustituida. Ambas versiones y sus evidencias se conservaran.
                                @else
                                    Despues de publicar, la programacion quedara versionada e inmutable. Las correcciones posteriores se realizaran mediante una nueva version.
                                @endif
                            </p>
                            <label class="mt-3 flex items-center gap-2">
                                <input type="checkbox" wire:model="confirmPublish" class="rounded border-zinc-300">
                                <span>Confirmo publicar esta programacion.</span>
                            </label>
                            @error('confirmPublish')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                            @error('publication')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                            <flux:button class="mt-3" size="sm" variant="primary" wire:click="publishBatch">Publicar</flux:button>
                        </div>
                    @endif
                </div>
            @endif

            @if ($comparisonPanel !== [])
                <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="flex items-center justify-between gap-3">
                        <p class="font-medium">Comparacion con version anterior</p>
                        <flux:button size="xs" variant="ghost" wire:click="hideComparisonPanel">Ocultar</flux:button>
                    </div>
                    <div class="mt-3 grid gap-3 text-sm md:grid-cols-4">
                        <p>Dias totales: {{ $comparisonPanel['total_days'] }}</p>
                        <p>Sin cambio: {{ $comparisonPanel['unchanged_days'] }}</p>
                        <p>Modificados: {{ $comparisonPanel['changed_days'] }}</p>
                        <p>Trabajadores con cambios: {{ count($comparisonPanel['changed_relationships']) }}</p>
                    </div>
                    <div class="mt-4 divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                        @forelse (array_slice($comparisonPanel['differences'], 0, 20) as $difference)
                            <div class="py-3">
                                <p class="font-medium">{{ $difference['employee_code'] }} - {{ $difference['worker_name'] }} | {{ $difference['work_date'] }}</p>
                                <p>Antes: {{ $difference['before_summary'] ?? 'Sin programacion' }}</p>
                                <p>Despues: {{ $difference['after_summary'] ?? 'Sin programacion' }}</p>
                            </div>
                        @empty
                            <p class="py-3 text-zinc-500">No hay diferencias funcionales.</p>
                        @endforelse
                    </div>
                </div>
            @endif

            @if ($versionHistoryPanel !== [])
                <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="flex items-center justify-between gap-3">
                        <p class="font-medium">Historial de versiones</p>
                        <flux:button size="xs" variant="ghost" wire:click="hideVersionHistory">Ocultar</flux:button>
                    </div>
                    @foreach ($versionHistoryPanel['errors'] as $error)
                        <p class="mt-1 text-sm text-red-600">{{ $error }}</p>
                    @endforeach
                    <div class="mt-3 grid gap-3 md:grid-cols-2">
                        @foreach ($versionHistoryPanel['versions'] as $version)
                            <button type="button" wire:click="selectBatch({{ $version['id'] }})" class="rounded-md border border-zinc-200 p-3 text-left text-sm hover:border-sky-400 dark:border-zinc-700">
                                <span class="block font-medium">{{ $version['version'] ? 'Version '.$version['version'] : 'Borrador correctivo' }} - {{ $this->statusLabel($version['status']) }}</span>
                                <span class="block text-xs text-zinc-500">Publicada: {{ $version['published_at'] ?? 'Sin publicar' }}</span>
                                @if ($version['correction_reason'])
                                    <span class="block text-xs text-zinc-500">Motivo: {{ $version['correction_reason'] }}</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($integrityPanel !== [])
                <div class="rounded-lg border {{ $integrityPanel['valid'] ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-red-200 bg-red-50 text-red-900' }} p-4 text-sm dark:bg-zinc-900">
                    <div class="flex items-center justify-between gap-3">
                        <p class="font-medium">{{ $integrityPanel['valid'] ? 'Integridad verificada' : 'No fue posible verificar la integridad' }}</p>
                        <flux:button size="xs" variant="ghost" wire:click="hideIntegrityPanel">Ocultar</flux:button>
                    </div>
                    <p class="break-all">Hash actual: {{ $integrityPanel['actual_hash'] }}</p>
                    @foreach ($integrityPanel['errors'] as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <div class="rounded-lg bg-surface-muted p-3 dark:bg-zinc-950/40">
            <div class="mb-3 flex items-center justify-between gap-3">
                <flux:button size="xs" variant="ghost" icon="chevron-left" wire:click="previousWeek">Semana anterior</flux:button>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $selectedBatch->period_start->toDateString() }} a {{ $selectedBatch->period_end->toDateString() }}</p>
                <flux:button size="xs" variant="ghost" icon-trailing="chevron-right" wire:click="nextWeek">Semana siguiente</flux:button>
            </div>
            <div class="hidden lg:block">
                <table class="w-full table-fixed divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <colgroup>
                        <col class="w-[18rem]">
                        @foreach ($weekDates as $date)
                            <col class="w-[calc((100%-18rem)/7)]">
                        @endforeach
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="sticky left-0 bg-white px-2 py-2 text-left dark:bg-zinc-900">Trabajador</th>
                            @foreach ($weekDates as $date)
                                <th @class([
                                    'px-2 py-2 text-left text-xs font-medium',
                                    'text-zinc-400' => $date['outside_period'] ?? false,
                                    'text-zinc-600 dark:text-zinc-300' => ! ($date['outside_period'] ?? false),
                                ])>{{ $date['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 [&>tr:nth-child(odd)]:bg-white [&>tr:nth-child(even)]:bg-zinc-50/60 dark:divide-zinc-800 dark:[&>tr:nth-child(odd)]:bg-zinc-900 dark:[&>tr:nth-child(even)]:bg-zinc-800/40">
                        @forelse ($calendarRows as $row)
                            <tr>
                                <td class="sticky left-0 bg-inherit px-2 py-3 align-top">
                                    <span class="block truncate font-medium">{{ $row['relationship']->worker?->employee_code }} - {{ $row['relationship']->worker?->full_name }}</span>
                                    <span class="block truncate text-xs text-zinc-500">{{ $row['relationship']->position_name }} | {{ $row['relationship']->center?->name }}</span>
                                    <span class="block truncate text-xs text-zinc-500">{{ $row['organizational_unit_name'] ?: 'Sin unidad' }}</span>
                                </td>
                                @foreach ($row['cells'] as $cell)
                                    <td class="px-1.5 py-2 align-top">
                                        @if ($cell['outside_vigence'])
                                            <div class="min-h-20 rounded-md border border-dashed border-zinc-200 bg-zinc-50 p-2 text-xs text-zinc-400 dark:border-zinc-700 dark:bg-zinc-900/60">
                                                Fuera de vigencia
                                            </div>
                                        @else
                                            <button type="button" wire:click="openDayEditor({{ $row['relationship']->id }}, '{{ $cell['date'] }}')" @disabled(! $canEditSelectedBatch) class="min-h-20 w-full rounded-md border p-2 text-left transition disabled:cursor-default disabled:opacity-90 {{ $this->calendarCellClasses($cell['assignment'], $cell['historical_only']) }}">
                                                <span class="flex flex-wrap items-center gap-1">
                                                    <span class="truncate font-semibold">{{ $this->dayTypeLabel($cell['assignment']?->day_type) }}</span>
                                                    @if ($cell['historical_only'])
                                                        <x-ui.badge>Baja historica</x-ui.badge>
                                                    @endif
                                                </span>
                                                <span class="mt-1 block line-clamp-2 text-xs {{ $this->calendarCellTextClasses($cell['assignment'], $cell['historical_only']) }}">{{ $this->assignmentSummary($cell['assignment']) }}</span>
                                                <span class="mt-1 block truncate text-[11px] opacity-70">{{ $this->sourceLabel($cell['assignment']?->source_type) }}</span>
                                            </button>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($weekDates) + 1 }}" class="px-4 py-8 text-center text-zinc-500">No hay trabajadores o dias para mostrar.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="grid gap-3 lg:hidden">
                @foreach ($calendarRows as $row)
                    @foreach ($row['cells'] as $cell)
                        @if (! $cell['outside_vigence'])
                            <button type="button" wire:click="openDayEditor({{ $row['relationship']->id }}, '{{ $cell['date'] }}')" @disabled(! $canEditSelectedBatch) class="rounded-md border p-4 text-left transition {{ $this->calendarCellClasses($cell['assignment'], $cell['historical_only']) }}">
                                <span class="block text-xs opacity-70">{{ $cell['date'] }}</span>
                                <span class="flex flex-wrap items-center gap-2 font-medium">
                                    <span>{{ $row['relationship']->worker?->employee_code }} - {{ $row['relationship']->worker?->full_name }}</span>
                                    @if ($cell['historical_only'])
                                        <x-ui.badge>Baja historica</x-ui.badge>
                                    @endif
                                </span>
                                <span class="block text-xs opacity-70">{{ $row['relationship']->center?->name }} | {{ $row['organizational_unit_name'] ?: 'Sin unidad' }}</span>
                                <span class="block text-sm {{ $this->calendarCellTextClasses($cell['assignment'], $cell['historical_only']) }}">{{ $this->assignmentSummary($cell['assignment']) }}</span>
                            </button>
                        @endif
                    @endforeach
                @endforeach
            </div>

            @if ($calendarRows->hasPages() || $calendarRows->total() > 0)
                <div class="mt-3 border-t border-zinc-200 pt-3 dark:border-zinc-800">
                    <div class="mb-2 text-xs text-zinc-500">
                        Mostrando {{ $calendarRows->firstItem() }} a {{ $calendarRows->lastItem() }} de {{ $calendarRows->total() }} trabajadores
                    </div>
                    {{ $calendarRows->links(data: ['scrollTo' => false]) }}
                </div>
            @endif
            </div>
        </section>
    @endif

    <x-side-panel wire:model="showCreatePanel" title="Nueva programacion semanal" subheading="La semana siempre cubre lunes a domingo. No se publica automaticamente." maxWidth="max-w-2xl">
        <form class="space-y-5 p-6">
            <flux:select label="Centro de trabajo" wire:model.live="batchForm.center_id">
                <flux:select.option value="">Selecciona centro</flux:select.option>
                @foreach ($centers as $center)
                    <flux:select.option value="{{ $center->id }}">{{ $center->name }} | {{ $center->timezone }}</flux:select.option>
                @endforeach
            </flux:select>
            <div class="grid gap-4 md:grid-cols-3">
                <flux:input type="date" label="Fecha de la semana" wire:model.live="batchForm.period_start" />
                <flux:input type="text" label="Semana natural" value="{{ ($batchForm['period_start'] ?? '') && ($batchForm['period_end'] ?? '') ? ($batchForm['period_start'].' a '.$batchForm['period_end']) : '' }}" disabled />
                <flux:select label="Semanas a crear" wire:model="batchForm.weeks">
                    <flux:select.option value="1">1 semana</flux:select.option>
                    <flux:select.option value="2">2 semanas</flux:select.option>
                    <flux:select.option value="3">3 semanas</flux:select.option>
                    <flux:select.option value="4">4 semanas</flux:select.option>
                </flux:select>
            </div>
            <div class="rounded-md border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-100">
                Cada semana se crea como lote independiente lunes-domingo. Se abrira la ultima semana creada para revision.
            </div>
            <flux:textarea label="Notas opcionales" wire:model="batchForm.notes" rows="3" />
            <div class="flex justify-end gap-3">
                <flux:button type="button" variant="ghost" wire:click="$set('showCreatePanel', false)">Cancelar</flux:button>
                <flux:button type="button" variant="ghost" wire:click="createEmptyBatch">Crear semana vacia</flux:button>
                <flux:button type="button" variant="primary" wire:click="createAndGenerate">Crear y generar desde perfiles</flux:button>
            </div>
        </form>
    </x-side-panel>

    <x-side-panel wire:model="showPrepareWeeksPanel" title="Preparar semanas futuras" subheading="Crea borradores lunes-domingo desde modelos. No publica automaticamente." maxWidth="max-w-xl">
        <form wire:submit="prepareFutureWeeks" class="space-y-5 p-6">
            @if ($selectedBatch)
                <div class="rounded-md border border-zinc-200 p-4 text-sm dark:border-zinc-700">
                    <p>Centro: {{ $selectedBatch->center?->name }}</p>
                    <p>Desde semana: {{ $selectedBatch->period_start->toDateString() }} a {{ $selectedBatch->period_end->toDateString() }}</p>
                </div>
            @endif

            <flux:select label="Semanas a preparar" wire:model="prepareWeeksForm.weeks">
                <flux:select.option value="1">1 semana</flux:select.option>
                <flux:select.option value="2">2 semanas</flux:select.option>
                <flux:select.option value="3">3 semanas</flux:select.option>
                <flux:select.option value="4">4 semanas</flux:select.option>
            </flux:select>

            <div class="rounded-md border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-100">
                Se generaran solo borradores desde modelos. Las semanas ya existentes se abriran o saltaran sin duplicarse.
            </div>

            @error('prepareWeeksForm.weeks')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            @error('generation')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

            <div class="flex justify-end gap-3">
                <flux:button type="button" variant="ghost" wire:click="$set('showPrepareWeeksPanel', false)">Cancelar</flux:button>
                <flux:button type="submit" variant="primary">Preparar</flux:button>
            </div>
        </form>
    </x-side-panel>

    <x-side-panel wire:model="showCloneWeekPanel" title="Clonar semana publicada" subheading="Copia una semana publicada a borrador o publicala directo si no tiene bloqueos." maxWidth="max-w-xl">
        <form wire:submit="clonePublishedWeek" class="space-y-5 p-6">
            @if ($selectedBatch)
                <div class="rounded-md border border-zinc-200 p-4 text-sm dark:border-zinc-700">
                    <p class="font-medium">Semana origen</p>
                    <p>{{ $selectedBatch->center?->name }} - {{ $selectedBatch->period_start->toDateString() }} a {{ $selectedBatch->period_end->toDateString() }}</p>
                    <p>Version: {{ $selectedBatch->version ?? 'Sin version' }}</p>
                </div>
            @endif

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input type="date" label="Fecha de la semana destino" wire:model.live="cloneWeekForm.target_date" />
                <flux:input type="text" label="Semana destino" value="{{ ($cloneWeekForm['target_date'] ?? '') && ($cloneWeekForm['target_end'] ?? '') ? ($cloneWeekForm['target_date'].' a '.$cloneWeekForm['target_end']) : '' }}" disabled />
            </div>
            <div class="rounded-md border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-100">
                Puedes dejar la copia en borrador para revisar o publicarla directo. Los trabajadores sin relacion vigente en la semana destino se omitiran.
            </div>
            @error('cloneWeekForm.target_date')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

            <div class="flex justify-end gap-3">
                <flux:button type="button" variant="ghost" wire:click="$set('showCloneWeekPanel', false)">Cancelar</flux:button>
                <flux:button type="submit" variant="ghost">Clonar a borrador</flux:button>
                <flux:button type="button" variant="primary" wire:click="clonePublishedWeekAndPublish">Clonar y publicar</flux:button>
            </div>
        </form>
    </x-side-panel>

    <x-side-panel wire:model="showCorrectionPanel" title="Crear correccion" subheading="Se creara un borrador correctivo sin modificar la publicacion vigente." maxWidth="max-w-2xl">
        <form wire:submit="createCorrection" class="space-y-5 p-6">
            @if ($selectedBatch)
                <div class="rounded-md border border-zinc-200 p-4 text-sm dark:border-zinc-700">
                    <p>Centro: {{ $selectedBatch->center?->name }}</p>
                    <p>Periodo: {{ $selectedBatch->period_start->toDateString() }} a {{ $selectedBatch->period_end->toDateString() }}</p>
                    <p>Version actual: {{ $selectedBatch->version }}</p>
                </div>
            @endif
            <p class="text-sm text-zinc-600 dark:text-zinc-300">Se creara un borrador correctivo. La version publicada continuara vigente hasta que la correccion sea revisada y publicada.</p>
            <flux:textarea label="Motivo general de correccion" wire:model="correctionForm.correction_reason" rows="4" required />
            <div class="flex justify-end gap-3">
                <flux:button type="button" variant="ghost" wire:click="$set('showCorrectionPanel', false)">Cancelar</flux:button>
                <flux:button type="submit" variant="primary">Crear correccion</flux:button>
            </div>
        </form>
    </x-side-panel>

    <x-side-panel wire:model="showDayPanel" title="Editar dia" subheading="Los cambios manuales se guardan con referencia estable." maxWidth="max-w-3xl">
        <form wire:submit="saveDay" class="space-y-5 p-6">
            <flux:select label="Tipo de dia" wire:model.live="dayForm.day_type">
                <flux:select.option value="shift">Turno</flux:select.option>
                <flux:select.option value="rest">Descanso</flux:select.option>
                <flux:select.option value="flexible">Flexible</flux:select.option>
                <flux:select.option value="on_call">Guardia bajo llamada</flux:select.option>
                <flux:select.option value="unassigned">Pendiente</flux:select.option>
            </flux:select>

            @include('livewire.scheduling.partials.daily-editor-fields', ['formRoot' => 'dayForm', 'shiftTemplates' => $shiftTemplates, 'previewTemplate' => $previewTemplate])

            <flux:textarea label="Motivo" wire:model="dayForm.reason" required />
            <div class="flex justify-end gap-3">
                <flux:button type="button" variant="ghost" wire:click="$set('showDayPanel', false)">Cancelar</flux:button>
                <flux:button type="submit" variant="primary">Guardar dia</flux:button>
            </div>
        </form>
    </x-side-panel>

    <x-side-panel wire:model="showBulkPanel" title="Cambio masivo" subheading="Aplica una configuracion a varios trabajadores y fechas dentro del lote." maxWidth="max-w-4xl">
        <form wire:submit="applyBulk" class="space-y-5 p-6">
            <livewire:workers.multi-select
                wire:model.live="bulkWorkerIds"
                heading="Trabajadores"
                subheading="Selecciona uno o varios trabajadores del centro del lote."
                :center-id="(string) ($selectedBatch?->center_id ?? '')"
                :result-limit="150"
                :key="'daily-bulk-workers-'.($selectedBatch?->id ?? 'none')"
            />
            @error('bulkForm.employment_relationship_ids')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            <div class="grid gap-4 md:grid-cols-2">
                <flux:input type="date" label="Desde" wire:model.live="bulkForm.date_from" />
                <flux:input type="date" label="Hasta" wire:model.live="bulkForm.date_to" />
            </div>
            <flux:select label="Aplicar" wire:model.live="bulkForm.day_type">
                <flux:select.option value="shift">Turno</flux:select.option>
                <flux:select.option value="rest">Descanso</flux:select.option>
                <flux:select.option value="flexible">Flexible</flux:select.option>
                <flux:select.option value="on_call">Guardia bajo llamada</flux:select.option>
                <flux:select.option value="unassigned">Pendiente</flux:select.option>
            </flux:select>

            @include('livewire.scheduling.partials.daily-editor-fields', ['formRoot' => 'bulkForm', 'shiftTemplates' => $shiftTemplates, 'previewTemplate' => $previewTemplate])

            @if ($bulkPreview)
                <div class="rounded-md border border-zinc-200 p-4 text-sm dark:border-zinc-700">
                    <p>{{ $bulkPreview['workers'] }} trabajadores, {{ $bulkPreview['dates'] }} fechas, {{ $bulkPreview['total'] }} dias a modificar.</p>
                    <p>{{ $bulkPreview['manual'] }} dias manuales existentes y {{ $bulkPreview['generated'] }} dias generados desde perfil.</p>
                </div>
            @endif

            <flux:textarea label="Motivo" wire:model="bulkForm.reason" required />
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model="confirmBulk" class="rounded border-zinc-300">
                <span>Confirmo aplicar este cambio masivo.</span>
            </label>
            @error('confirmBulk')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            <div class="flex justify-end gap-3">
                <flux:button type="button" variant="ghost" wire:click="$set('showBulkPanel', false)">Cancelar</flux:button>
                <flux:button type="submit" variant="primary">Aplicar cambio masivo</flux:button>
            </div>
        </form>
    </x-side-panel>
</section>
