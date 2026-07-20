<?php

use App\Domains\Organization\Actions\ResolveUserOperationalScopeAction;
use App\Domains\Scheduling\Actions\BulkReplaceDraftDailyScheduleAssignmentsAction;
use App\Domains\Scheduling\Actions\BuildDailyScheduleSegmentsFromShiftTemplateAction;
use App\Domains\Scheduling\Actions\CreateScheduleBatchAction;
use App\Domains\Scheduling\Actions\GenerateDraftScheduleBatchFromProfilesAction;
use App\Domains\Scheduling\Actions\PublishScheduleBatchAction;
use App\Domains\Scheduling\Actions\ReplaceDraftDailyScheduleAssignmentAction;
use App\Domains\Scheduling\Actions\ResolveScheduleBatchExpectedRelationshipDatesAction;
use App\Domains\Scheduling\Actions\ValidateScheduleBatchForPublicationAction;
use App\Domains\Scheduling\Actions\VerifyPublishedScheduleBatchSnapshotAction;
use App\Domains\Scheduling\Data\ScheduleBatchPublicationValidationResult;
use App\Domains\Scheduling\Exceptions\ScheduleBatchPublicationValidationException;
use App\Domains\Scheduling\Support\ShiftTemplateTimeline;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\DailyScheduleAssignment;
use App\Models\EmploymentRelationship;
use App\Models\OrganizationalUnit;
use App\Models\ScheduleBatch;
use App\Models\ShiftTemplate;
use App\Support\RoleKey;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public array $filters = [];
    public array $batchForm = [];
    public array $dayForm = [];
    public array $bulkForm = [];
    public array $validationPanel = [];
    public array $integrityPanel = [];
    public ?int $selectedBatchId = null;
    public ?int $editingAssignmentId = null;
    public ?int $editingRelationshipId = null;
    public ?string $editingWorkDate = null;
    public ?string $weekStart = null;
    public bool $showCreatePanel = false;
    public bool $showDayPanel = false;
    public bool $showBulkPanel = false;
    public bool $confirmBulk = false;
    public bool $confirmPublish = false;

    public function mount(): void
    {
        $this->filters = [
            'center_id' => '',
            'period_start' => '',
            'period_end' => '',
            'status' => 'draft',
            'worker_search' => '',
            'organizational_unit_id' => '',
            'day_type' => 'all',
            'pending_only' => false,
        ];
        $this->batchForm = $this->emptyBatchForm();
        $this->dayForm = $this->emptyDayForm();
        $this->bulkForm = $this->emptyBulkForm();
        $this->validationPanel = [];
        $this->integrityPanel = [];
    }

    public function updated($property): void
    {
        if (str_starts_with((string) $property, 'filters.')) {
            $this->resetPage();
        }

        if ($property === 'selectedBatchId') {
            $this->validationPanel = [];
            $this->integrityPanel = [];
        }
    }

    public function openCreatePanel(CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        Gate::authorize('create', [ScheduleBatch::class, $company]);

        $this->batchForm = $this->emptyBatchForm();
        $this->showCreatePanel = true;
        $this->resetValidation();
    }

    public function createEmptyBatch(CurrentCompany $currentCompany, CreateScheduleBatchAction $action): void
    {
        $batch = $this->createBatch($currentCompany, $action);
        $this->selectedBatchId = $batch->id;
        $this->showCreatePanel = false;
        Session::flash('status', 'Lote creado en borrador.');
    }

    public function createAndGenerate(
        CurrentCompany $currentCompany,
        CreateScheduleBatchAction $createAction,
        GenerateDraftScheduleBatchFromProfilesAction $generateAction,
    ): void {
        $company = $this->currentCompanyOrFail($currentCompany);
        $batch = $this->createBatch($currentCompany, $createAction);

        try {
            $result = $generateAction->handle(auth()->user(), $company, $batch, GenerateDraftScheduleBatchFromProfilesAction::MODE_MISSING_ONLY);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['batchForm.center_id' => $exception->getMessage()]);
        }

        $this->selectedBatchId = $batch->id;
        $this->showCreatePanel = false;
        Session::flash('status', $this->generationMessage($result));
    }

    public function selectBatch(int $batchId, CurrentCompany $currentCompany): void
    {
        $batch = $this->authorizedBatch($batchId, $currentCompany, false);
        $this->selectedBatchId = $batch->id;
        $this->weekStart = $batch->period_start->toDateString();
        $this->validationPanel = [];
        $this->integrityPanel = [];
    }

    public function editBatchInfo(CurrentCompany $currentCompany, \App\Domains\Scheduling\Actions\UpdateDraftScheduleBatchAction $action): void
    {
        $batch = $this->selectedBatch($currentCompany, true);
        $validated = $this->validate([
            'batchForm.period_start' => ['required', 'date'],
            'batchForm.period_end' => ['required', 'date', 'after_or_equal:batchForm.period_start'],
            'batchForm.notes' => ['nullable', 'string', 'max:2000'],
        ])['batchForm'];

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

    public function previousWeek(CurrentCompany $currentCompany): void
    {
        $batch = $this->selectedBatch($currentCompany, false);
        $current = CarbonImmutable::parse($this->weekStart ?: $batch->period_start->toDateString());
        $previous = $current->subDays(7);
        $this->weekStart = $previous->lt($batch->period_start) ? $batch->period_start->toDateString() : $previous->toDateString();
    }

    public function nextWeek(CurrentCompany $currentCompany): void
    {
        $batch = $this->selectedBatch($currentCompany, false);
        $current = CarbonImmutable::parse($this->weekStart ?: $batch->period_start->toDateString());
        $next = $current->addDays(7);
        $this->weekStart = $next->gt($batch->period_end) ? $batch->period_end->toDateString() : $next->toDateString();
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
        $this->confirmBulk = false;
        $this->showBulkPanel = true;
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

        $result = $action->handle(auth()->user(), $company, $batch);
        $this->validationPanel = $result->toArray();
        $this->confirmPublish = false;
    }

    public function publishBatch(CurrentCompany $currentCompany, PublishScheduleBatchAction $action, ValidateScheduleBatchForPublicationAction $validator): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $batch = $this->selectedBatch($currentCompany, true);
        Gate::authorize('publish', $batch);

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
            ? $company->scheduleBatches()->with(['center', 'publisher', 'dailyAssignments.segments', 'dailyAssignments.employmentRelationship.worker', 'dailyAssignments.organizationalUnit', 'dailyAssignments.shiftTemplate'])->whereKey($this->selectedBatchId)->first()
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
            'previewTemplate' => $this->previewTemplate($company),
            'bulkPreview' => $selectedBatch ? $this->bulkPreview($selectedBatch) : null,
        ];
    }

    private function createBatch(CurrentCompany $currentCompany, CreateScheduleBatchAction $action): ScheduleBatch
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        Gate::authorize('create', [ScheduleBatch::class, $company]);

        $validated = $this->validate([
            'batchForm.center_id' => ['required', 'integer', Rule::exists('centers', 'id')->where('company_id', $company->id)->where('status', 'active')],
            'batchForm.period_start' => ['required', 'date'],
            'batchForm.period_end' => ['required', 'date', 'after_or_equal:batchForm.period_start'],
            'batchForm.notes' => ['nullable', 'string', 'max:2000'],
        ])['batchForm'];
        $center = $company->centers()->where('status', 'active')->whereKey((int) $validated['center_id'])->firstOrFail();

        try {
            return $action->handle($company, $center, [
                'period_start' => $validated['period_start'],
                'period_end' => $validated['period_end'],
                'creation_source' => 'manual',
                'notes' => $validated['notes'] ?: null,
            ], auth()->user());
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['batchForm.period_start' => $exception->getMessage()]);
        }
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
        $pendingOnly = (bool) ($this->filters['pending_only'] ?? false);
        $centerIds = $this->visibleCenterIds($company);

        return $company->scheduleBatches()
            ->with(['center', 'publisher'])
            ->withCount([
                'dailyAssignments as total_days',
                'dailyAssignments as pending_days' => fn ($query) => $query->where('day_type', 'unassigned'),
            ])
            ->when($centerIds !== null, fn ($query) => $query->whereIn('center_id', $centerIds))
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($centerId !== '', fn ($query) => $query->where('center_id', (int) $centerId))
            ->when($periodStart !== '', fn ($query) => $query->whereDate('period_end', '>=', $periodStart))
            ->when($periodEnd !== '', fn ($query) => $query->whereDate('period_start', '<=', $periodEnd))
            ->when($workerSearch !== '', fn ($query) => $query->whereHas('dailyAssignments.employmentRelationship.worker', fn ($workerQuery) => $workerQuery
                ->where('employee_code', 'like', "%{$workerSearch}%")
                ->orWhere('full_name', 'like', "%{$workerSearch}%")))
            ->when($unitId !== '', fn ($query) => $query->whereHas('dailyAssignments', fn ($assignmentQuery) => $assignmentQuery->where('organizational_unit_id', (int) $unitId)))
            ->when($dayType !== 'all', fn ($query) => $query->whereHas('dailyAssignments', fn ($assignmentQuery) => $assignmentQuery->where('day_type', $dayType)))
            ->when($pendingOnly, fn ($query) => $query->whereHas('dailyAssignments', fn ($assignmentQuery) => $assignmentQuery->where('day_type', 'unassigned')))
            ->orderByDesc('period_start')
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
        $this->weekStart ??= $batch->period_start->toDateString();

        return $batch;
    }

    private function authorizedBatch(int $batchId, CurrentCompany $currentCompany, bool $forUpdate): ScheduleBatch
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $batch = $company->scheduleBatches()
            ->with(['center', 'dailyAssignments.segments', 'dailyAssignments.employmentRelationship.worker', 'dailyAssignments.organizationalUnit', 'dailyAssignments.shiftTemplate'])
            ->whereKey($batchId)
            ->firstOrFail();

        Gate::authorize($forUpdate ? 'update' : 'view', $batch);

        return $batch;
    }

    private function calendarRows($company, ScheduleBatch $batch): array
    {
        $expected = app(ResolveScheduleBatchExpectedRelationshipDatesAction::class)->handle($company, $batch);
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
                    $cells[] = ['date' => $date, 'assignment' => null, 'outside_vigence' => true];
                    continue;
                }

                $assignment = $assignments[$relationship->id.'|'.$date] ?? null;
                $cells[] = ['date' => $date, 'assignment' => $assignment, 'outside_vigence' => false];
            }

            $rows[] = ['relationship' => $relationship, 'cells' => $cells];
        }

        return $rows;
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
        $start = CarbonImmutable::parse($this->weekStart ?: $batch->period_start->toDateString());
        if ($start->lt($batch->period_start)) {
            $start = CarbonImmutable::parse($batch->period_start->toDateString());
        }
        $end = $start->addDays(6)->gt($batch->period_end) ? CarbonImmutable::parse($batch->period_end->toDateString()) : $start->addDays(6);

        return collect(CarbonPeriod::create($start, $end))->map(fn ($date) => [
            'date' => $date->toDateString(),
            'label' => ucfirst($date->locale('es_MX')->isoFormat('ddd DD/MM')),
        ])->values()->all();
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
        return EmploymentRelationship::query()
            ->where('company_id', $batch->company_id)
            ->where('center_id', $batch->center_id)
            ->where('status', 'active')
            ->whereDate('started_at', '<=', $workDate)
            ->where(function ($query) use ($workDate): void {
                $query->whereNull('ended_at')->orWhereDate('ended_at', '>=', $workDate);
            })
            ->whereKey($relationshipId)
            ->firstOrFail();
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

        $existing = $batch->dailyAssignments()
            ->whereIn('employment_relationship_id', $ids)
            ->whereIn('work_date', $dates)
            ->get();

        return [
            'workers' => count($ids),
            'dates' => count($dates),
            'total' => count($ids) * count($dates),
            'manual' => $existing->where('source_type', 'manual')->count(),
            'generated' => $existing->where('source_type', 'profile')->count() + $existing->where('source_type', 'system')->count(),
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
            'period_start' => now()->toDateString(),
            'period_end' => now()->addDays(6)->toDateString(),
            'notes' => '',
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

    private function generationMessage($result): string
    {
        return "Generacion lista: {$result->relationshipsConsidered} relaciones, {$result->assignmentsCreated} dias creados, {$result->assignmentsRefreshed} actualizados, {$result->assignmentsPreserved} preservados.";
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
            <flux:heading size="xl">Programacion diaria</flux:heading>
            <flux:subheading>Los perfiles representan la forma habitual de trabajo. La programacion diaria define lo que corresponde trabajar en cada fecha.</flux:subheading>
        </div>

        @if ($canCreateBatch)
            <flux:button type="button" icon="plus" wire:click="openCreatePanel">Nuevo lote</flux:button>
        @endif
    </div>

    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-100">{{ session('status') }}</div>
    @endif

    <section class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="grid gap-4 lg:grid-cols-4">
            <flux:select label="Centro de trabajo" wire:model.live="filters.center_id">
                <flux:select.option value="">Todos</flux:select.option>
                @foreach ($centers as $center)
                    <flux:select.option value="{{ $center->id }}">{{ $center->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input type="date" label="Desde" wire:model.live="filters.period_start" />
            <flux:input type="date" label="Hasta" wire:model.live="filters.period_end" />
            <flux:select label="Estado" wire:model.live="filters.status">
                <flux:select.option value="draft">Borrador</flux:select.option>
                <flux:select.option value="published">Publicado</flux:select.option>
                <flux:select.option value="superseded">Sustituido</flux:select.option>
                <flux:select.option value="cancelled">Cancelado</flux:select.option>
                <flux:select.option value="all">Todos</flux:select.option>
            </flux:select>
            <flux:input label="Buscar trabajador" placeholder="Clave o nombre" wire:model.live.debounce.350ms="filters.worker_search" />
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
            <label class="flex items-end gap-2 pb-2 text-sm">
                <input type="checkbox" wire:model.live="filters.pending_only" class="rounded border-zinc-300">
                <span>Solo pendientes</span>
            </label>
        </div>
    </section>

    <section class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
        <table class="w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
            <thead class="bg-zinc-50 text-left text-xs font-medium uppercase text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                <tr>
                    <th class="px-4 py-3">Centro</th>
                    <th class="px-4 py-3">Periodo</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3">Resumen</th>
                    <th class="px-4 py-3">Publicacion</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($batches as $batch)
                    <tr>
                        <td class="px-4 py-3">
                            <span class="block font-medium">{{ $batch->center?->name }}</span>
                            <span class="text-xs text-zinc-500">Origen: {{ $this->sourceLabel($batch->creation_source) }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="block">{{ $batch->period_start->toDateString() }} - {{ $batch->period_end->toDateString() }}</span>
                            <span class="text-xs text-zinc-500">Version {{ $batch->version }}</span>
                        </td>
                        <td class="px-4 py-3">{{ $this->statusLabel($batch->status) }}</td>
                        <td class="px-4 py-3">
                            <span class="block">{{ $batch->total_days }} dias programados</span>
                            <span class="text-xs text-zinc-500">{{ $batch->pending_days }} pendientes</span>
                        </td>
                        <td class="px-4 py-3">
                            @if ($batch->published_at)
                                <span class="block">{{ $batch->published_at->format('Y-m-d H:i') }}</span>
                                <span class="text-xs text-zinc-500">{{ $batch->publisher?->name ?? 'Usuario' }}</span>
                            @else
                                <span class="text-zinc-500">Sin publicar</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <flux:button size="xs" variant="ghost" wire:click="selectBatch({{ $batch->id }})">{{ $batch->status === 'draft' ? 'Abrir calendario' : 'Consultar' }}</flux:button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-zinc-500">No hay lotes con los filtros actuales.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    {{ $batches->links() }}

    @if ($selectedBatch)
        <section class="space-y-5 rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div>
                    <flux:heading>{{ $selectedBatch->center?->name }} - {{ $selectedBatch->period_start->toDateString() }} a {{ $selectedBatch->period_end->toDateString() }}</flux:heading>
                    <flux:subheading>Version {{ $selectedBatch->version }} - {{ $this->statusLabel($selectedBatch->status) }}</flux:subheading>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($canEditSelectedBatch)
                        <flux:button size="sm" variant="ghost" wire:click="generateMissing">Generar faltantes</flux:button>
                        <flux:button size="sm" variant="ghost" wire:click="refreshGenerated" wire:confirm="Actualiza los dias generados desde perfiles. Los cambios manuales y cargas externas se conservaran.">Actualizar desde perfiles</flux:button>
                        <flux:button size="sm" variant="ghost" wire:click="openBulkPanel">Cambio masivo</flux:button>
                    @endif
                    <flux:button size="sm" variant="ghost" wire:click="reviewBatch">Revisar antes de publicar</flux:button>
                    @if ($selectedBatch->status === 'published')
                        <flux:button size="sm" variant="ghost" wire:click="verifyIntegrity">Verificar integridad</flux:button>
                    @endif
                </div>
            </div>

            <div class="grid gap-3 md:grid-cols-4 xl:grid-cols-7">
                <div class="rounded-md bg-zinc-50 p-3 dark:bg-zinc-800"><p class="text-xs text-zinc-500">Trabajadores</p><p class="font-semibold">{{ $selectedSummary['workers'] }}</p></div>
                <div class="rounded-md bg-zinc-50 p-3 dark:bg-zinc-800"><p class="text-xs text-zinc-500">Dias</p><p class="font-semibold">{{ $selectedSummary['days'] }}</p></div>
                <div class="rounded-md bg-zinc-50 p-3 dark:bg-zinc-800"><p class="text-xs text-zinc-500">Turnos</p><p class="font-semibold">{{ $selectedSummary['shift'] }}</p></div>
                <div class="rounded-md bg-zinc-50 p-3 dark:bg-zinc-800"><p class="text-xs text-zinc-500">Descansos</p><p class="font-semibold">{{ $selectedSummary['rest'] }}</p></div>
                <div class="rounded-md bg-zinc-50 p-3 dark:bg-zinc-800"><p class="text-xs text-zinc-500">Flexibles</p><p class="font-semibold">{{ $selectedSummary['flexible'] }}</p></div>
                <div class="rounded-md bg-zinc-50 p-3 dark:bg-zinc-800"><p class="text-xs text-zinc-500">Guardias</p><p class="font-semibold">{{ $selectedSummary['on_call'] }}</p></div>
                <div class="rounded-md bg-zinc-50 p-3 dark:bg-zinc-800"><p class="text-xs text-zinc-500">Pendientes</p><p class="font-semibold">{{ $selectedSummary['unassigned'] }}</p></div>
            </div>

            @if ($selectedSummary['unassigned'] > 0)
                <p class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100">Existen {{ $selectedSummary['unassigned'] }} dias pendientes de definicion.</p>
            @endif

            @if ($selectedBatch->status === 'published')
                <div class="rounded-md border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-100">
                    <p>Publicado: {{ $selectedBatch->published_at?->format('Y-m-d H:i') }} por {{ $selectedBatch->publisher?->name ?? 'Usuario' }}</p>
                    <p class="break-all">SHA-256: {{ $selectedBatch->snapshot_sha256 }}</p>
                </div>
            @endif

            @if ($validationPanel !== [])
                <div class="grid gap-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700 md:grid-cols-3">
                    <div>
                        <p class="font-medium">{{ $validationPanel['valid'] ? 'Listo para publicar' : 'Bloqueos para publicar' }}</p>
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
                    </div>
                    <div class="text-sm">
                        <p>Turnos: {{ $validationPanel['assignments_shift'] }}</p>
                        <p>Descansos: {{ $validationPanel['assignments_rest'] }}</p>
                        <p>Flexibles: {{ $validationPanel['assignments_flexible'] }}</p>
                        <p>Guardias: {{ $validationPanel['assignments_on_call'] }}</p>
                        <p>Conflictos: {{ $validationPanel['conflicting_assignments'] }}</p>
                    </div>
                    @if ($validationPanel['valid'] && $canPublishSelectedBatch)
                        <div class="md:col-span-3 rounded-md border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-100">
                            <p class="font-medium">Publicar programacion</p>
                            <p>Despues de publicar, esta version no podra modificarse. Las correcciones posteriores se realizaran mediante una nueva version.</p>
                            <label class="mt-3 flex items-center gap-2">
                                <input type="checkbox" wire:model="confirmPublish" class="rounded border-zinc-300">
                                <span>Confirmo publicar esta version.</span>
                            </label>
                            @error('confirmPublish')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                            @error('publication')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                            <flux:button class="mt-3" size="sm" variant="primary" wire:click="publishBatch">Publicar</flux:button>
                        </div>
                    @endif
                </div>
            @endif

            @if ($integrityPanel !== [])
                <div class="rounded-lg border {{ $integrityPanel['valid'] ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-red-200 bg-red-50 text-red-900' }} p-4 text-sm dark:bg-zinc-900">
                    <p class="font-medium">{{ $integrityPanel['valid'] ? 'Integridad verificada' : 'No fue posible verificar la integridad' }}</p>
                    <p class="break-all">Hash actual: {{ $integrityPanel['actual_hash'] }}</p>
                    @foreach ($integrityPanel['errors'] as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <div class="flex items-center justify-between">
                <flux:button size="sm" variant="ghost" wire:click="previousWeek">Semana anterior</flux:button>
                <p class="text-sm text-zinc-500">Vista semanal dentro del periodo</p>
                <flux:button size="sm" variant="ghost" wire:click="nextWeek">Semana siguiente</flux:button>
            </div>

            <div class="hidden overflow-x-auto lg:block">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead>
                        <tr>
                            <th class="sticky left-0 bg-white px-3 py-2 text-left dark:bg-zinc-900">Trabajador</th>
                            @foreach ($weekDates as $date)
                                <th class="px-3 py-2 text-left">{{ $date['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($calendarRows as $row)
                            <tr>
                                <td class="sticky left-0 bg-white px-3 py-3 dark:bg-zinc-900">
                                    <span class="block font-medium">{{ $row['relationship']->worker?->employee_code }} - {{ $row['relationship']->worker?->full_name }}</span>
                                    <span class="text-xs text-zinc-500">{{ $row['relationship']->position_name }} | {{ $row['relationship']->center?->name }}</span>
                                </td>
                                @foreach ($row['cells'] as $cell)
                                    <td class="min-w-44 px-3 py-3 align-top">
                                        @if ($cell['outside_vigence'])
                                            <span class="text-xs text-zinc-400">Fuera de vigencia</span>
                                        @else
                                            <button type="button" wire:click="openDayEditor({{ $row['relationship']->id }}, '{{ $cell['date'] }}')" @disabled(! $canEditSelectedBatch) class="w-full rounded-md border border-zinc-200 p-3 text-left hover:border-sky-400 disabled:cursor-default disabled:hover:border-zinc-200 dark:border-zinc-700">
                                                <span class="block font-medium">{{ $this->dayTypeLabel($cell['assignment']?->day_type) }}</span>
                                                <span class="block text-xs text-zinc-500">{{ $this->assignmentSummary($cell['assignment']) }}</span>
                                                <span class="block text-xs text-zinc-400">{{ $this->sourceLabel($cell['assignment']?->source_type) }}</span>
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
                            <button type="button" wire:click="openDayEditor({{ $row['relationship']->id }}, '{{ $cell['date'] }}')" @disabled(! $canEditSelectedBatch) class="rounded-md border border-zinc-200 p-4 text-left dark:border-zinc-700">
                                <span class="block text-xs text-zinc-500">{{ $cell['date'] }}</span>
                                <span class="block font-medium">{{ $row['relationship']->worker?->employee_code }} - {{ $row['relationship']->worker?->full_name }}</span>
                                <span class="block text-sm">{{ $this->assignmentSummary($cell['assignment']) }}</span>
                            </button>
                        @endif
                    @endforeach
                @endforeach
            </div>
        </section>
    @endif

    <x-side-panel wire:model="showCreatePanel" title="Nuevo lote de programacion" subheading="El lote cubre un centro y un periodo. No se publica automaticamente." maxWidth="max-w-2xl">
        <form class="space-y-5 p-6">
            <flux:select label="Centro de trabajo" wire:model.live="batchForm.center_id">
                <flux:select.option value="">Selecciona centro</flux:select.option>
                @foreach ($centers as $center)
                    <flux:select.option value="{{ $center->id }}">{{ $center->name }} | {{ $center->timezone }}</flux:select.option>
                @endforeach
            </flux:select>
            <div class="grid gap-4 md:grid-cols-2">
                <flux:input type="date" label="Fecha inicial" wire:model="batchForm.period_start" />
                <flux:input type="date" label="Fecha final" wire:model="batchForm.period_end" />
            </div>
            <flux:textarea label="Notas opcionales" wire:model="batchForm.notes" rows="3" />
            <div class="flex justify-end gap-3">
                <flux:button type="button" variant="ghost" wire:click="$set('showCreatePanel', false)">Cancelar</flux:button>
                <flux:button type="button" variant="ghost" wire:click="createEmptyBatch">Crear lote vacio</flux:button>
                <flux:button type="button" variant="primary" wire:click="createAndGenerate">Crear y generar desde perfiles</flux:button>
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
            <div class="grid gap-2 md:grid-cols-2">
                @foreach ($calendarRows as $row)
                    <label class="flex items-center gap-2 rounded-md border border-zinc-200 p-3 text-sm dark:border-zinc-700">
                        <input type="checkbox" value="{{ $row['relationship']->id }}" wire:model.live="bulkForm.employment_relationship_ids" class="rounded border-zinc-300">
                        <span>{{ $row['relationship']->worker?->employee_code }} - {{ $row['relationship']->worker?->full_name }}</span>
                    </label>
                @endforeach
            </div>
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
