<?php

use App\Domains\Alerts\Actions\ResolveAlertAction;
use App\Domains\Alerts\Actions\ResolveScheduledAbsenceAlertAsAttendanceIncidentAction;
use App\Domains\Organization\Support\ScopedOperationalAccess;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Domains\WorkDays\Actions\ListWorkDaysAction;
use App\Domains\WorkDays\Actions\BuildWorkDayWorkspaceAction;
use App\Domains\WorkDays\Actions\RecalculateSelectedWorkDayAction;
use App\Domains\WorkDays\Support\WorkDayWorkflow;
use App\Domains\WorkDays\Actions\ProcessCompanyWorkDaysAction;
use App\Models\Alert;
use App\Models\AttendanceIncident;
use App\Models\WorkDayCalculation;
use App\Models\WorkDay;
use App\Support\RoleKey;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    #[Url(as: 'tab')]
    public string $workspaceTab = 'jornadas';

    #[Url(as: 'jornada')]
    public string $workflowState = '';

    #[Url(as: 'revision')]
    public string $reviewState = 'open';

    public function selectWorkspaceTab(string $tab): void
    {
        abort_unless(in_array($tab, ['jornadas', 'incidencias', 'prevencion'], true), 404);
        $this->workspaceTab = $tab;
        $this->calculatedConflict = '';
        $this->situationFilter = '';
        $this->attentionFilter = '';
        $this->workflowState = '';
        $this->statusFilter = '';
        $this->scheduleStatusFilter = '';
        $this->incidentTypeFilter = '';
        $this->incidentStatusFilter = '';
        $this->closeAlertsPanel();
        $this->resetPage();
        $this->resetPage('incidentPage');
    }

    public function updatedWorkflowState(): void { $this->resetPage(); }
    public function updatedReviewState(): void { $this->resetPage('incidentPage'); }

    public function updatedCalculatedConflict(): void
    {
        $this->resetPage();
        $this->resetPage('incidentPage');
    }

    public function updating($property): void
    {
        if (in_array($property, ['dateFrom', 'dateTo', 'centerId', 'search', 'reviewState'], true)) {
            $this->resetPage('incidentPage');
            $this->closeAlertsPanel();
        }
    }

    #[Url(as: 'from')]
    public string $dateFrom = '';

    #[Url(as: 'to')]
    public string $dateTo = '';

    #[Url(as: 'center')]
    public string $centerId = '';

    #[Url(as: 'status')]
    public string $statusFilter = '';

    #[Url(as: 'schedule')]
    public string $scheduleStatusFilter = '';

    #[Url(as: 'incident')]
    public string $incidentTypeFilter = '';

    #[Url(as: 'dictamen')]
    public string $incidentStatusFilter = '';

    #[Url(as: 'situacion')]
    public string $situationFilter = '';

    #[Url(as: 'atencion')]
    public string $attentionFilter = '';

    #[Url(as: 'acumulado')]
    public string $accumulatedAlerts = '';

    #[Url]
    public string $search = '';

    #[Url(as: 'worker')]
    public string $workerIdFilter = '';

    #[Url(as: 'conflicto')]
    public string $calculatedConflict = '';

    public array $processForm = [];

    public bool $showProcessPanel = false;

    public bool $showAlertsPanel = false;

    public ?int $selectedWorkDayId = null;

    public ?int $selectedAlertId = null;


    public string $recalculationMessage = '';

    public function recalculateSelectedWorkDay(RecalculateSelectedWorkDayAction $action, CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $day = WorkDay::where('company_id', $company->id)->findOrFail($this->selectedWorkDayId);
        Gate::authorize('recalculate', $day);
        $this->recalculationMessage = '';
        try {
            $day = $action->handle($company, $day, auth()->user());
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['recalculation' => $exception->getMessage()]);
        }
        $this->resetValidation();
        $alerts = $day->alerts->filter(fn (Alert $alert) => $this->workspaceTab === 'jornadas'
            ? in_array($alert->rule_code, WorkDayWorkflow::ATTENDANCE_RULES, true)
            : ! in_array($alert->rule_code, WorkDayWorkflow::ATTENDANCE_RULES, true));
        $next = $alerts->firstWhere('id', $this->selectedAlertId)
            ?? $alerts->first(fn (Alert $alert) => in_array($alert->status, Alert::OPEN_STATUSES, true))
            ?? $alerts->first();
        $this->selectedAlertId = $next?->id;
        $this->syncAlertResolutionForm($next);
        $this->recalculationMessage = 'Jornada actualizada: '.$day->worker?->full_name.' · '.$day->work_date->format('d/m/Y').'. '
            .(WorkDayWorkflow::isCompleted($day) ? 'Asistencia completa; los avisos reflejan el nuevo cálculo.' : 'Hay asistencia o marcajes pendientes de aclarar en Jornadas.');
        $this->resetPage('incidentPage');
        $this->resetPage();
    }

    public array $alertResolutionForm = [
        'status' => Alert::STATUS_JUSTIFIED,
        'incident_type' => AttendanceIncident::TYPE_JUSTIFIED_PAID_ABSENCE,
        'payment_status' => AttendanceIncident::PAYMENT_PAID,
        'resolution' => '',
    ];


    private function defaultAlertResolutionForm(): array
    {
        return [
            'status' => Alert::STATUS_JUSTIFIED,
            'incident_type' => AttendanceIncident::TYPE_JUSTIFIED_PAID_ABSENCE,
            'payment_status' => AttendanceIncident::PAYMENT_PAID,
            'resolution' => '',
        ];
    }

    private function syncAlertResolutionForm(?Alert $alert): void
    {
        $this->alertResolutionForm = $this->defaultAlertResolutionForm();

        if (! $alert) {
            return;
        }

        $options = $this->alertResolutionOptions($alert);
        $currentStatus = array_key_exists($alert->status, $options)
            ? $alert->status
            : array_key_first($options);

        $this->alertResolutionForm['status'] = $currentStatus;
        $this->alertResolutionForm['resolution'] = (string) ($alert->resolution ?? '');

        if ($alert->rule_code === 'scheduled_absence') {
            $linkedIncident = $this->linkedAttendanceIncidentForAlert($alert);

            if ($linkedIncident) {
                $this->alertResolutionForm['incident_type'] = $linkedIncident->incident_type;
                $this->alertResolutionForm['payment_status'] = $linkedIncident->payment_status;
            }
        }
    }

    public function mount(CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('viewAny', [WorkDay::class, $company]);

        if (! in_array($this->workspaceTab, ['jornadas', 'incidencias', 'prevencion'], true)) {
            $this->workspaceTab = 'jornadas';
        }
        if ($this->workspaceTab === 'jornadas' && $this->situationFilter !== ''
            && ! in_array($this->situationFilter, WorkDayWorkflow::ATTENDANCE_RULES, true)) {
            $this->workspaceTab = 'incidencias';
        }

        if (! $this->isAccumulatedAlertsView()) {
            $today = $this->todayForCompany($company);
            $weekStart = $today->startOfWeek(\Carbon\CarbonInterface::MONDAY);

            $this->dateFrom = $this->dateFrom !== '' ? $this->dateFrom : $weekStart->toDateString();
            $this->dateTo = $this->dateTo !== '' ? $this->dateTo : $today->toDateString();
            $this->capDateFiltersToToday($company);
        }
        $this->loadProcessForm($company);
    }

    public function openProcessPanel(CurrentCompany $currentCompany, ProcessCompanyWorkDaysAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        abort_unless($this->canProcessCompanyWorkDays($company), 403);

        $this->loadProcessForm($company, $action);
        $this->showProcessPanel = true;
    }

    public function closeProcessPanel(): void
    {
        $this->showProcessPanel = false;
    }

    public function openAlertsPanel(int $workDayId, CurrentCompany $currentCompany): void
    {
        $this->recalculationMessage = '';
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('viewAny', [WorkDay::class, $company]);

        $workDay = WorkDay::query()
            ->with(['alerts' => fn ($query) => $query->with(['alertType', 'resolver'])->orderByRaw("case when status in ('new', 'in_review', 'pending_information') then 1 else 2 end")->orderBy('detected_at')])
            ->where('company_id', $company->id)
            ->findOrFail($workDayId);

        Gate::authorize('view', $workDay);

        $workDay->setRelation('alerts', $workDay->alerts->filter(fn (Alert $alert) => $this->workspaceTab === 'jornadas'
            ? in_array($alert->rule_code, WorkDayWorkflow::ATTENDANCE_RULES, true)
            : ! in_array($alert->rule_code, WorkDayWorkflow::ATTENDANCE_RULES, true)));
        $firstAlert = $workDay->alerts
            ->first(fn (Alert $alert): bool => in_array($alert->status, Alert::OPEN_STATUSES, true))
            ?? $workDay->alerts->first();

        $this->selectedWorkDayId = $workDay->id;
        $this->selectedAlertId = $firstAlert?->id;
        $this->syncAlertResolutionForm($firstAlert);
        $this->showAlertsPanel = true;
    }

    public function closeAlertsPanel(): void
    {
        $this->recalculationMessage = '';
        $this->showAlertsPanel = false;
        $this->selectedWorkDayId = null;
        $this->selectedAlertId = null;
        $this->alertResolutionForm = $this->defaultAlertResolutionForm();
    }

    public function openIncident(int $alertId, CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $alert = Alert::query()->where('company_id', $company->id)->whereNotIn('rule_code', WorkDayWorkflow::ATTENDANCE_RULES)->findOrFail($alertId);
        abort_unless($alert->workDay && Gate::allows('view', $alert->workDay), 403);
        abort_unless(in_array($alert->rule_code, WorkDayWorkflow::WEEKLY_RULES, true) || WorkDayWorkflow::isCompleted($alert->workDay), 422);
        $this->workspaceTab = 'incidencias';
        $this->openAlertsPanel($alert->work_day_id, $currentCompany);
        $this->selectedAlertId = $alert->id;
        $this->syncAlertResolutionForm($alert);
    }

    public function resolveSelectedAlert(ResolveAlertAction $action, ResolveScheduledAbsenceAlertAsAttendanceIncidentAction $absenceAction, CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('viewAny', [WorkDay::class, $company]);

        $alertForAuthorization = Alert::query()
            ->where('company_id', $company->id)
            ->where('work_day_id', $this->selectedWorkDayId)
            ->findOrFail((int) $this->selectedAlertId);
        Gate::authorize('resolve', $alertForAuthorization);

        $validated = $this->validate([
            'selectedAlertId' => [
                'required',
                'integer',
                Rule::exists('alerts', 'id')->where('company_id', $company->id),
            ],
            'alertResolutionForm.status' => ['required', Rule::in([Alert::STATUS_JUSTIFIED, Alert::STATUS_CORRECTED, Alert::STATUS_CLOSED])],
            'alertResolutionForm.incident_type' => ['nullable', Rule::in(AttendanceIncident::types())],
            'alertResolutionForm.payment_status' => ['nullable', Rule::in(AttendanceIncident::paymentStatuses())],
            'alertResolutionForm.resolution' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $alert = Alert::query()
            ->where('company_id', $company->id)
            ->where('work_day_id', $this->selectedWorkDayId)
            ->findOrFail((int) $validated['selectedAlertId']);

        Gate::authorize('resolve', $alert);

        if (! array_key_exists($validated['alertResolutionForm']['status'], $this->alertResolutionOptions($alert))) {
            throw ValidationException::withMessages(['alertResolutionForm.status' => 'Selecciona una opción válida para esta incidencia.']);
        }

        if ($alert->rule_code === 'incomplete_work_day') {
            throw ValidationException::withMessages(['alertResolutionForm.resolution' => 'Corrige los marcajes con evidencia y recalcula la jornada. Un comentario no completa los registros.']);
        }
        if (! in_array($alert->rule_code, WorkDayWorkflow::ATTENDANCE_RULES, true)
            && ! in_array($alert->rule_code, WorkDayWorkflow::WEEKLY_RULES, true)
            && ! WorkDayWorkflow::isCompleted($alert->workDay)) {
            throw ValidationException::withMessages(['alertResolutionForm.resolution' => 'Primero completa la jornada antes de revisar sus incidencias calculadas.']);
        }

        try {
            if ($alert->rule_code === 'scheduled_absence') {
                $absenceAction->handle($company, $alert, auth()->user(), [
                    'status' => $validated['alertResolutionForm']['status'],
                    'incident_type' => $validated['alertResolutionForm']['incident_type'] ?: AttendanceIncident::TYPE_JUSTIFIED_PAID_ABSENCE,
                    'payment_status' => $validated['alertResolutionForm']['payment_status'] ?: AttendanceIncident::PAYMENT_PAID,
                    'resolution' => $validated['alertResolutionForm']['resolution'],
                ]);
            } else {
                $action->handle($company, $alert, auth()->user(), $validated['alertResolutionForm']);
            }
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'alertResolutionForm.resolution' => $exception->getMessage(),
            ]);
        }

        if ($this->workspaceTab === 'incidencias') {
            $this->alertResolutionForm = $this->defaultAlertResolutionForm();
            $this->alertResolutionForm['status'] = array_key_first($this->alertResolutionOptions($alert));
            $this->resetValidation();
            Session::flash('status', 'Revisión guardada. El formulario está listo para otra revisión; la decisión se conserva en el historial del aviso.');
            $this->resetPage('incidentPage');
            return;
        }

        $nextAlertId = Alert::query()
            ->where('company_id', $company->id)
            ->where('work_day_id', $this->selectedWorkDayId)
            ->whereIn('status', Alert::OPEN_STATUSES)
            ->when($this->workspaceTab === 'jornadas', fn ($query) => $query->whereIn('rule_code', WorkDayWorkflow::ATTENDANCE_RULES))
            ->when($this->workspaceTab === 'incidencias', fn ($query) => $query->whereNotIn('rule_code', WorkDayWorkflow::ATTENDANCE_RULES))
            ->orderBy('detected_at')
            ->value('id');

        if (! $nextAlertId) {
            $this->closeAlertsPanel();
            Session::flash('status', 'Incidencia dictaminada. La jornada quedo sin incidencias pendientes.');
            $this->resetPage();
            return;
        }

        $this->selectedAlertId = (int) $nextAlertId;
        $this->syncAlertResolutionForm(Alert::query()->where('company_id', $company->id)->findOrFail($nextAlertId));
        $this->resetValidation();
        Session::flash('status', 'Incidencia dictaminada. Aun quedan incidencias pendientes para la jornada.');
        $this->resetPage();
    }

    public function confirmWeeklyRestReview(CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $alert = Alert::where('company_id', $company->id)->where('work_day_id', $this->selectedWorkDayId)->findOrFail($this->selectedAlertId);
        try {
            app(\App\Domains\Alerts\Actions\ConfirmWeeklyRestReviewAction::class)->handle($company, $alert, auth()->user());
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['weeklyRestReview' => $exception->getMessage()]);
        }
        $this->resetValidation();
        $this->resetPage('incidentPage');
        Session::flash('status', 'Revisado: semana sin descanso. Se conservaron las revisiones diarias como respaldo.');
    }

    public function updatedSelectedAlertId(CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $alert = Alert::query()->where('company_id', $company->id)->where('work_day_id', $this->selectedWorkDayId)->findOrFail((int) $this->selectedAlertId);
        Gate::authorize('view', $alert->workDay);
        $this->syncAlertResolutionForm($alert);
    }

    public function linkedAttendanceIncidentForAlert(?Alert $alert): ?AttendanceIncident
    {
        if (! $alert || $alert->rule_code !== 'scheduled_absence') {
            return null;
        }

        return AttendanceIncident::query()
            ->where('company_id', $alert->company_id)
            ->where('metadata->source_alert_id', $alert->id)
            ->latest('id')
            ->first();
    }

    public function processWorkDays(ProcessCompanyWorkDaysAction $action, CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        abort_unless($this->canProcessCompanyWorkDays($company), 403);

        $validated = $this->validate([
            'processForm.date_from' => ['nullable', 'date', 'required_with:processForm.date_to'],
            'processForm.date_to' => ['nullable', 'date', 'required_with:processForm.date_from', 'after_or_equal:processForm.date_from'],
            'processForm.reason' => ['required', 'string', 'min:5', 'max:500'],
        ])['processForm'];

        $startDate = blank($validated['date_from'] ?? null) ? null : CarbonImmutable::parse($validated['date_from'])->toDateString();
        $endDate = blank($validated['date_to'] ?? null) ? null : CarbonImmutable::parse($validated['date_to'])->toDateString();
        $today = $this->todayForCompany($company)->toDateString();

        if ($endDate !== null && $endDate > $today) {
            $endDate = $today;
        }

        $result = $action->handle(
            $company,
            $startDate,
            $endDate,
            actor: auth()->user(),
            mode: 'manual_ui',
            generatedByType: WorkDayCalculation::GENERATED_BY_USER,
            reason: trim((string) $validated['reason']),
        );

        $this->dateFrom = $result['start_date'];
        $this->dateTo = $result['end_date'];
        $this->showProcessPanel = false;
        $this->resetPage();

        Session::flash('status', "Recalculo de jornadas: {$result['total']} actualizadas, {$result['calculated']} calculadas, {$result['under_review']} en revision, {$result['skipped']} sin eventos validos, {$result['special_legal_cases']} con casos especiales y {$result['alerts_created_or_updated']} alertas revisadas.");
    }

    public function updatedDateFrom(): void
    {
        $this->accumulatedAlerts = '';
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->accumulatedAlerts = '';
        $this->resetPage();
    }

    public function updatedCenterId(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedScheduleStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedIncidentTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedIncidentStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSituationFilter(): void
    {
        $this->resetPage();
    }

    public function updatedAttentionFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->workerIdFilter = '';
        $this->resetPage();
    }

    public function clearFilters(CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $today = $this->todayForCompany($company);
        $weekStart = $today->startOfWeek(\Carbon\CarbonInterface::MONDAY);

        $this->dateFrom = $weekStart->toDateString();
        $this->dateTo = $today->toDateString();
        $this->centerId = '';
        $this->statusFilter = '';
        $this->scheduleStatusFilter = '';
        $this->incidentTypeFilter = '';
        $this->incidentStatusFilter = '';
        $this->situationFilter = '';
        $this->attentionFilter = '';
        $this->accumulatedAlerts = '';
        $this->search = '';
        $this->workerIdFilter = '';
        $this->calculatedConflict = '';
        $this->workflowState = '';
        $this->reviewState = 'open';
        $this->closeAlertsPanel();
        $this->resetPage('incidentPage');
        $this->resetPage();
    }

    public function with(CurrentCompany $currentCompany, ListWorkDaysAction $listWorkDays, BuildWorkDayWorkspaceAction $workspace): array
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('viewAny', [WorkDay::class, $company]);

        if (! $this->isAccumulatedAlertsView()) {
            $this->capDateFiltersToToday($company);
        }

        $visibleWorkDayAccess = $this->visibleWorkDayAccess($company);

        $workDays = $listWorkDays->handle($company, [
            'viewer' => auth()->user(),
            'workflow_view' => true,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'center_ids' => $visibleWorkDayAccess['center_ids'],
            'relationship_ids' => $visibleWorkDayAccess['relationship_ids'],
            'center_id' => $this->centerId === '' ? null : (int) $this->centerId,
            'status' => $this->situationFilter === '' && $this->statusFilter !== '' ? $this->statusFilter : null,
            'schedule_status' => $this->situationFilter === '' && $this->scheduleStatusFilter !== '' ? $this->scheduleStatusFilter : null,
            'incident_type' => $this->situationFilter === '' && $this->incidentTypeFilter !== '' ? $this->incidentTypeFilter : null,
            'incident_status' => $this->attentionFilter === '' && $this->incidentStatusFilter !== '' ? $this->incidentStatusFilter : null,
            'situation' => $this->situationFilter === '' ? null : $this->situationFilter,
            'attention' => $this->attentionFilter === '' ? null : $this->attentionFilter,
            'search' => $this->search,
            'worker_id' => $this->workerIdFilter === '' ? null : (int) $this->workerIdFilter,
            'calculated_conflict' => $this->calculatedConflict,
            'workflow_state' => $this->workspaceTab === 'jornadas' ? $this->workflowState : null,
        ]);
        $workspaceFilters = [
            'worker_id' => $this->workerIdFilter === '' ? null : (int) $this->workerIdFilter,
            'calculated_conflict' => $this->calculatedConflict,
            'date_from' => $this->dateFrom, 'date_to' => $this->dateTo,
            'center_ids' => $visibleWorkDayAccess['center_ids'], 'relationship_ids' => $visibleWorkDayAccess['relationship_ids'],
            'center_id' => $this->centerId === '' ? null : (int) $this->centerId, 'search' => $this->search,
        ];
        $workspaceFilters['incident_rule'] = $this->workspaceTab === 'incidencias' ? $this->situationFilter : null;
        $workspaceSummary = $workspace->handle($company, auth()->user(), $workspaceFilters, $this->reviewState, $this->workspaceTab === 'prevencion');
        foreach ($workDays as $day) {
            $day->setAttribute('attendance_completed', WorkDayWorkflow::isCompleted($day));
        }

        $selectedWorkDay = $this->selectedWorkDayId
            ? WorkDay::query()
                ->with([
                    'worker',
                    'center',
                    'employmentRelationship',
                    'dailyScheduleAssignment.segments',
                    'activeCalculation',
                    'alerts' => fn ($query) => $query->with(['alertType', 'resolver'])->orderByRaw("case status when 'new' then 1 when 'in_review' then 2 when 'pending_information' then 3 else 4 end")->orderBy('detected_at'),
                ])
                ->where('company_id', $company->id)
                ->find($this->selectedWorkDayId)
            : null;

        if ($selectedWorkDay && ! Gate::allows('view', $selectedWorkDay)) {
            $selectedWorkDay = null;
            $this->selectedWorkDayId = null;
        }
        if ($selectedWorkDay) {
            $selectedWorkDay->setRelation('reviewEvents', $selectedWorkDay->employmentRelationship
                ? app(\App\Domains\TimeRecords\Actions\ResolveValidTimeEventsForWorkDateAction::class)->handle($company, $selectedWorkDay->employmentRelationship, $selectedWorkDay->work_date)
                : collect());
            $selectedIsWeekly = in_array($selectedWorkDay->alerts->firstWhere('id', $this->selectedAlertId)?->rule_code, WorkDayWorkflow::WEEKLY_RULES, true);
            $selectedWorkDay->setRelation('alerts', $selectedWorkDay->alerts->filter(fn (Alert $alert) => $this->workspaceTab === 'jornadas'
                ? in_array($alert->rule_code, WorkDayWorkflow::ATTENDANCE_RULES, true)
                : ($selectedIsWeekly ? $alert->id === $this->selectedAlertId : ! in_array($alert->rule_code, [...WorkDayWorkflow::ATTENDANCE_RULES, ...WorkDayWorkflow::WEEKLY_RULES], true))));
            $historical = $this->workspaceTab === 'incidencias'
                ? $selectedWorkDay->alerts->filter(fn (Alert $alert) => $alert->isClosedByRecalculation()) : collect();
            $selectedWorkDay->setRelation('historicalAlerts', $historical);
            $selectedWorkDay->setRelation('alerts', $selectedWorkDay->alerts->reject(fn (Alert $alert) => $historical->contains('id', $alert->id)));
            if (! $selectedIsWeekly && ! $selectedWorkDay->alerts->contains('id', $this->selectedAlertId)) {
                $next = $selectedWorkDay->alerts->first();
                $this->selectedAlertId = $next?->id;
                $this->syncAlertResolutionForm($next);
            }
        }

        $visibleCenterIds = $visibleWorkDayAccess['filter_center_ids'];

        return [
            'company' => $company,
            'centers' => $company->centers()
                ->where('status', 'active')
                ->when($visibleCenterIds !== null, fn ($query) => $query->whereIn('id', $visibleCenterIds))
                ->orderBy('name')
                ->get(),
            'workDays' => $workDays,
            'workspaceSummary' => $workspaceSummary,
            'selectedWorkDay' => $selectedWorkDay,
            'overtimeReviewMargin' => $selectedWorkDay
                ? app(\App\Domains\WorkDays\Actions\ResolveOvertimeReviewMarginAction::class)->handle($company, $selectedWorkDay)
                : null,
            'todayDate' => $this->todayForCompany($company)->toDateString(),
            'canProcessWorkDays' => $this->canProcessCompanyWorkDays($company),
            'canResolveAlerts' => $this->canResolveAlerts($company),
        ];
    }

    private function currentCompanyOrFail(CurrentCompany $currentCompany)
    {
        $company = $currentCompany->get();

        abort_unless($company, 403);

        return $company;
    }

    private function todayForCompany($company): CarbonImmutable
    {
        return CarbonImmutable::now($company->setting?->default_timezone ?: $company->timezone)->startOfDay();
    }

    private function capDateFiltersToToday($company): void
    {
        $today = $this->todayForCompany($company)->toDateString();

        if ($this->dateTo === '' || CarbonImmutable::parse($this->dateTo)->toDateString() > $today) {
            $this->dateTo = $today;
        }

        if ($this->dateFrom !== '' && CarbonImmutable::parse($this->dateFrom)->toDateString() > $this->dateTo) {
            $this->dateFrom = $this->dateTo;
        }
    }

    private function isAccumulatedAlertsView(): bool
    {
        return $this->accumulatedAlerts === '1';
    }

    private function visibleWorkDayAccess($company): array
    {
        if (in_array(auth()->user()->roleKeyForCompany($company), RoleKey::companyManagers(), true)) {
            return [
                'center_ids' => null,
                'relationship_ids' => null,
                'filter_center_ids' => null,
            ];
        }

        if (! in_array(auth()->user()->roleKeyForCompany($company), [...RoleKey::scopedOperators(), ...RoleKey::scopedViewers()], true)) {
            return [
                'center_ids' => [],
                'relationship_ids' => [],
                'filter_center_ids' => [],
            ];
        }

        $scope = app(ScopedOperationalAccess::class)->scope(auth()->user(), $company, $this->dateTo ?: now()->toDateString());
        $unitIds = $scope['organizational_unit_ids'];
        $relationshipIds = [];

        if ($unitIds !== []) {
            $relationshipIds = \App\Models\EmploymentUnitAssignment::query()
                ->where('company_id', $company->id)
                ->where('status', 'active')
                ->whereIn('organizational_unit_id', $unitIds)
                ->when($this->dateTo !== '', fn ($query) => $query->whereDate('effective_from', '<=', $this->dateTo))
                ->when($this->dateFrom !== '', function ($query): void {
                    $query->where(function ($dateQuery): void {
                        $dateQuery->whereNull('effective_to')->orWhereDate('effective_to', '>=', $this->dateFrom);
                    });
                })
                ->pluck('employment_relationship_id')
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        $unitCenterIds = $unitIds === []
            ? []
            : \App\Models\OrganizationalUnit::query()
                ->where('company_id', $company->id)
                ->whereIn('id', $unitIds)
                ->pluck('center_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

        return [
            'center_ids' => $scope['center_ids'],
            'relationship_ids' => $relationshipIds,
            'filter_center_ids' => array_values(array_unique([...$scope['center_ids'], ...$unitCenterIds])),
        ];
    }

    private function canResolveAlerts($company): bool
    {
        return in_array(auth()->user()->roleKeyForCompany($company), [...RoleKey::companyManagers(), ...RoleKey::scopedOperators()], true);
    }

    private function canProcessCompanyWorkDays($company): bool
    {
        return in_array(auth()->user()->roleKeyForCompany($company), RoleKey::companyManagers(), true);
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'pending' => 'Pendiente',
            'calculated' => 'Calculada',
            'with_alerts' => 'Con alertas',
            'under_review' => 'En revision',
            'closed' => 'Cerrada',
            default => 'Pendiente de calculo',
        };
    }

    private function workDayStatusLabel(WorkDay $workDay): string
    {
        if ($this->isScheduledAbsenceCandidate($workDay)) {
            return 'Falta';
        }

        return $this->statusLabel($workDay->status);
    }

    private function scheduleStatusLabel(?string $status): string
    {
        return match ($status) {
            'scheduled' => 'Programada',
            'unscheduled' => 'No programada',
            default => 'Sin horario',
        };
    }

    private function dayTypeLabel(?string $dayType): string
    {
        return match ($dayType) {
            'shift' => 'Turno',
            'rest' => 'Descanso',
            'flexible' => 'Flexible',
            'on_call' => 'Guardia',
            'unassigned' => 'Pendiente',
            default => 'Sin tipo',
        };
    }

    private function statusVariant(?string $status): string
    {
        return match ($status) {
            'calculated', 'closed' => 'success',
            'with_alerts' => 'warning',
            'under_review' => 'info',
            default => 'neutral',
        };
    }

    private function workDayStatusVariant(WorkDay $workDay): string
    {
        if ($this->isScheduledAbsenceCandidate($workDay)) {
            return 'danger';
        }

        return $this->statusVariant($workDay->status);
    }

    private function alertStatusLabel(string $status): string
    {
        return match ($status) {
            'new' => 'Nueva',
            'in_review' => 'En revision',
            'pending_information' => 'Pendiente info',
            'justified' => 'Aprobada',
            'corrected' => 'No aprobada',
            'closed' => 'No procede',
            default => ucfirst($status),
        };
    }

    private function alertResolutionOptions(?Alert $alert): array
    {
        if ($alert && ! in_array($alert->rule_code, WorkDayWorkflow::ATTENDANCE_RULES, true)
            && ! in_array($alert->rule_code, WorkDayWorkflow::ADMINISTRATIVE_AUTHORIZATION_RULES, true)) {
            return [Alert::STATUS_CLOSED => 'Registrar revisión y seguimiento'];
        }
        return match ($alert?->rule_code) {
            'scheduled_absence' => [
                Alert::STATUS_CORRECTED => 'Confirmar falta',
                Alert::STATUS_JUSTIFIED => 'Enviar a incidencia/ausencia',
                Alert::STATUS_CLOSED => 'No procede',
            ],
            'overtime_detected' => [
                Alert::STATUS_JUSTIFIED => 'Autorización administrativa confirmada',
                Alert::STATUS_CORRECTED => 'Sin autorización administrativa',
                Alert::STATUS_CLOSED => 'Registrar revisión y seguimiento',
            ],
            'scheduled_rest_work' => [
                Alert::STATUS_JUSTIFIED => 'Confirmar trabajo en descanso autorizado',
                Alert::STATUS_CORRECTED => 'Confirmar trabajo en descanso sin autorización',
                Alert::STATUS_CLOSED => 'Registrar revisión y seguimiento',
            ],
            'incomplete_work_day' => [
                Alert::STATUS_CORRECTED => 'Confirmar jornada incompleta',
                Alert::STATUS_JUSTIFIED => 'Justificar jornada incompleta',
                Alert::STATUS_CLOSED => 'No procede',
            ],
            'late_arrival_detected' => [
                Alert::STATUS_CORRECTED => 'Confirmar retardo',
                Alert::STATUS_JUSTIFIED => 'Justificar retardo',
                Alert::STATUS_CLOSED => 'No procede',
            ],
            'early_departure_detected' => [
                Alert::STATUS_CORRECTED => 'Confirmar salida anticipada',
                Alert::STATUS_JUSTIFIED => 'Justificar salida anticipada',
                Alert::STATUS_CLOSED => 'No procede',
            ],
            'twelve_hours_exceeded' => [
                Alert::STATUS_CORRECTED => 'Confirmar jornada mayor a 12 horas',
                Alert::STATUS_JUSTIFIED => 'Justificar jornada mayor a 12 horas',
                Alert::STATUS_CLOSED => 'No procede',
            ],
            'sunday_work' => [
                Alert::STATUS_JUSTIFIED => 'Aprobar domingo trabajado',
                Alert::STATUS_CORRECTED => 'No aprobar domingo trabajado',
                Alert::STATUS_CLOSED => 'No procede',
            ],
            'mandatory_rest_work' => [
                Alert::STATUS_JUSTIFIED => 'Aprobar descanso trabajado',
                Alert::STATUS_CORRECTED => 'No aprobar descanso trabajado',
                Alert::STATUS_CLOSED => 'No procede',
            ],
            'weekly_rest_missing' => [
                Alert::STATUS_CORRECTED => 'Confirmar incidencia',
                Alert::STATUS_JUSTIFIED => 'Justificar descanso',
                Alert::STATUS_CLOSED => 'No procede',
            ],
            default => [
                Alert::STATUS_JUSTIFIED => 'Aprobar',
                Alert::STATUS_CORRECTED => 'No aprobar',
                Alert::STATUS_CLOSED => 'No procede',
            ],
        };
    }

    private function alertResolutionOptionLabel(?Alert $alert, string $status): string
    {
        return $this->alertResolutionOptions($alert)[$status] ?? $this->alertStatusLabel($status);
    }
    private function contextualAlertStatusLabel(Alert $alert): string
    {
        if ($alert->rule_code === 'scheduled_rest_work') {
            return match ($alert->status) {
                Alert::STATUS_JUSTIFIED => 'Trabajo en descanso autorizado',
                Alert::STATUS_CORRECTED => 'Trabajo en descanso sin autorización',
                Alert::STATUS_CLOSED => 'Seguimiento registrado',
                default => $this->alertStatusLabel($alert->status),
            };
        }
        if (! in_array($alert->rule_code, WorkDayWorkflow::ATTENDANCE_RULES, true)
            && $alert->rule_code !== 'overtime_detected' && ! in_array($alert->status, Alert::OPEN_STATUSES, true)) {
            return 'Revisada; resultado conservado';
        }
        if (! in_array($alert->status, [Alert::STATUS_JUSTIFIED, Alert::STATUS_CORRECTED, Alert::STATUS_CLOSED], true)) {
            return $this->alertStatusLabel($alert->status);
        }

        return match ($alert->rule_code) {
            'scheduled_absence' => match ($alert->status) {
                Alert::STATUS_JUSTIFIED => 'Ausencia justificada',
                Alert::STATUS_CORRECTED => 'Falta confirmada',
                Alert::STATUS_CLOSED => 'No procede',
                default => $this->alertStatusLabel($alert->status),
            },
            'overtime_detected' => match ($alert->status) {
                Alert::STATUS_JUSTIFIED => 'Tiempo extra autorizado',
                Alert::STATUS_CORRECTED => 'Tiempo extra no autorizado',
                Alert::STATUS_CLOSED => 'No procede',
                default => $this->alertStatusLabel($alert->status),
            },
            'incomplete_work_day' => match ($alert->status) {
                Alert::STATUS_JUSTIFIED => 'Jornada incompleta justificada',
                Alert::STATUS_CORRECTED => 'Incidencia confirmada',
                Alert::STATUS_CLOSED => 'No procede',
                default => $this->alertStatusLabel($alert->status),
            },
            'late_arrival_detected' => match ($alert->status) {
                Alert::STATUS_JUSTIFIED => 'Retardo justificado',
                Alert::STATUS_CORRECTED => 'Retardo confirmado',
                Alert::STATUS_CLOSED => 'No procede',
                default => $this->alertStatusLabel($alert->status),
            },
            'early_departure_detected' => match ($alert->status) {
                Alert::STATUS_JUSTIFIED => 'Salida anticipada justificada',
                Alert::STATUS_CORRECTED => 'Salida anticipada confirmada',
                Alert::STATUS_CLOSED => 'No procede',
                default => $this->alertStatusLabel($alert->status),
            },
            'twelve_hours_exceeded' => match ($alert->status) {
                Alert::STATUS_JUSTIFIED => 'Jornada mayor a 12 horas justificada',
                Alert::STATUS_CORRECTED => 'Jornada mayor a 12 horas confirmada',
                Alert::STATUS_CLOSED => 'No procede',
                default => $this->alertStatusLabel($alert->status),
            },
            'sunday_work' => match ($alert->status) {
                Alert::STATUS_JUSTIFIED => 'Domingo trabajado aprobado',
                Alert::STATUS_CORRECTED => 'Domingo trabajado no aprobado',
                Alert::STATUS_CLOSED => 'No procede',
                default => $this->alertStatusLabel($alert->status),
            },
            'mandatory_rest_work' => match ($alert->status) {
                Alert::STATUS_JUSTIFIED => 'Descanso trabajado aprobado',
                Alert::STATUS_CORRECTED => 'Descanso trabajado no aprobado',
                Alert::STATUS_CLOSED => 'No procede',
                default => $this->alertStatusLabel($alert->status),
            },
            'weekly_rest_missing' => match ($alert->status) {
                Alert::STATUS_JUSTIFIED => 'Descanso justificado',
                Alert::STATUS_CORRECTED => 'Incidencia confirmada',
                Alert::STATUS_CLOSED => 'No procede',
                default => $this->alertStatusLabel($alert->status),
            },
            default => $this->alertStatusLabel($alert->status),
        };
    }

    private function alertStatusVariant(string $status): string
    {
        return match ($status) {
            'justified', 'corrected', 'closed' => 'success',
            'in_review', 'pending_information' => 'warning',
            default => 'info',
        };
    }

    private function alertSeverityLabel(string $severity): string
    {
        return match ($severity) {
            'critical' => 'Critica',
            'high' => 'Alta',
            'warning' => 'Preventiva',
            default => 'Informativa',
        };
    }

    private function alertSeverityVariant(string $severity): string
    {
        return match ($severity) {
            'critical' => 'danger',
            'high' => 'warning',
            'warning' => 'info',
            default => 'neutral',
        };
    }

    private function scheduleStatusVariant(?string $status): string
    {
        return match ($status) {
            'scheduled' => 'success',
            'unscheduled' => 'warning',
            default => 'neutral',
        };
    }

    private function minutesLabel(?int $minutes): string
    {
        if ($minutes === null) {
            return 'No aplica';
        }

        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return $remaining === 0 ? "{$hours} h" : "{$hours} h {$remaining} min";
    }

    private function autoRefreshTimeLabel($company): string
    {
        $time = $company->setting?->work_days_auto_refresh_time;

        return $time ? substr((string) $time, 0, 5) : 'Sin configurar';
    }

    private function calculationMinutesLabel(?WorkDayCalculation $calculation): string
    {
        if (! $calculation) {
            return 'Sin calculo';
        }

        return $this->minutesLabel($calculation->total_work_minutes);
    }

    private function workedMinutesLabel(WorkDay $workDay): string
    {
        if ($this->hasAttendanceIncident($workDay)) {
            return $this->attendanceIncidentTypeLabel($workDay);
        }

        if ($this->isScheduledAbsenceCandidate($workDay)) {
            return 'Falta';
        }

        if (! $workDay->activeCalculation && $workDay->valid_time_event_count === 0) {
            return 'Sin eventos';
        }

        return $this->calculationMinutesLabel($workDay->activeCalculation);
    }

    private function legalClassificationLabel(?WorkDayCalculation $calculation): string
    {
        return match ($calculation?->classification) {
            WorkDayCalculation::CLASSIFICATION_DIURNAL => 'Diurna',
            WorkDayCalculation::CLASSIFICATION_NOCTURNAL => 'Nocturna',
            WorkDayCalculation::CLASSIFICATION_MIXED => 'Mixta',
            default => 'Pendiente',
        };
    }

    private function workDayLegalClassificationLabel(WorkDay $workDay): string
    {
        if (! $workDay->activeCalculation && $workDay->valid_time_event_count === 0) {
            return 'No aplica';
        }

        return $this->legalClassificationLabel($workDay->activeCalculation);
    }

    private function legalClassificationVariant(?WorkDayCalculation $calculation): string
    {
        return match ($calculation?->classification) {
            WorkDayCalculation::CLASSIFICATION_DIURNAL => 'success',
            WorkDayCalculation::CLASSIFICATION_NOCTURNAL => 'info',
            WorkDayCalculation::CLASSIFICATION_MIXED => 'warning',
            default => 'neutral',
        };
    }

    private function workDayLegalClassificationVariant(WorkDay $workDay): string
    {
        if (! $workDay->activeCalculation && $workDay->valid_time_event_count === 0) {
            return 'neutral';
        }

        return $this->legalClassificationVariant($workDay->activeCalculation);
    }

    private function legalNightMinutesLabel(?WorkDayCalculation $calculation): string
    {
        if (! $calculation || $calculation->classification === WorkDayCalculation::CLASSIFICATION_PENDING) {
            return '';
        }

        return $calculation->night_minutes > 0
            ? 'Nocturna: '.$this->minutesLabel($calculation->night_minutes)
            : 'Sin nocturna';
    }

    private function calculationSplitLabel(?WorkDayCalculation $calculation, string $field): string
    {
        if (! $calculation || $calculation->classification === WorkDayCalculation::CLASSIFICATION_PENDING) {
            return 'Pendiente';
        }

        return $this->minutesLabel((int) $calculation->{$field});
    }

    private function workDayCalculationSplitLabel(WorkDay $workDay, string $field): string
    {
        if (! $workDay->activeCalculation && $workDay->valid_time_event_count === 0) {
            return 'No aplica';
        }

        return $this->calculationSplitLabel($workDay->activeCalculation, $field);
    }

    private function specialCasesLabel(?WorkDayCalculation $calculation): string
    {
        if (! $calculation || $calculation->classification === WorkDayCalculation::CLASSIFICATION_PENDING) {
            return 'Pendiente';
        }

        $labels = [];

        if ($calculation->sunday_minutes > 0) {
            $labels[] = 'Dom '.$this->minutesLabel($calculation->sunday_minutes);
        }

        if ($calculation->mandatory_rest_minutes > 0) {
            $labels[] = 'Obl '.$this->minutesLabel($calculation->mandatory_rest_minutes);
        }

        if ((bool) data_get($calculation->result_snapshot, 'special_legal_cases.weekly_rest.requires_review')) {
            $labels[] = 'Sin descanso semanal';
        }

        return $labels === [] ? 'Sin especiales' : implode(' | ', $labels);
    }

    private function workDaySpecialCasesLabel(WorkDay $workDay): string
    {
        if (! $workDay->activeCalculation && $workDay->valid_time_event_count === 0) {
            return 'No aplica';
        }

        return $this->specialCasesLabel($workDay->activeCalculation);
    }

    private function primaryIncidentLabel(WorkDay $workDay): string
    {
        if ($this->hasAttendanceIncident($workDay)) {
            return $this->attendanceIncidentTypeLabel($workDay);
        }

        $alert = $this->primaryIncidentAlert($workDay);

        if ($alert) {
            return $this->operationalAlertLabel($alert);
        }

        if ($this->isScheduledAbsenceCandidate($workDay)) {
            return 'Falta';
        }

        if ($workDay->schedule_status === WorkDay::SCHEDULE_STATUS_UNSCHEDULED) {
            return 'Jornada no programada';
        }

        if ($workDay->status === WorkDay::STATUS_UNDER_REVIEW) {
            return 'En revision';
        }

        return 'Sin incidencia';
    }

    private function primaryIncidentVariant(WorkDay $workDay): string
    {
        if ($this->hasAttendanceIncident($workDay)) {
            return 'success';
        }

        $alert = $this->primaryIncidentAlert($workDay);

        if ($alert) {
            if (in_array($alert->rule_code, WorkDayWorkflow::ATTENDANCE_RULES, true)) {
                return $this->alertIncidentVariant($alert);
            }
            return match ($alert->rule_code) {
                'scheduled_absence' => 'rose',
                'incomplete_work_day' => 'orange',
                'overtime_detected' => 'violet',
                'twelve_hours_exceeded' => 'danger',
                'sunday_work' => 'cyan',
                'mandatory_rest_work' => 'warning',
                'weekly_rest_missing' => 'lime',
                default => $this->alertStatusVariant($alert->status),
            };
        }

        if ($this->isScheduledAbsenceCandidate($workDay)) {
            return 'danger';
        }

        if ($workDay->schedule_status === WorkDay::SCHEDULE_STATUS_UNSCHEDULED) {
            return 'warning';
        }

        if ($workDay->status === WorkDay::STATUS_UNDER_REVIEW) {
            return 'info';
        }

        return 'neutral';
    }

    /**
     * @return array<int, array{key: string, label: string, variant: string}>
     */
    private function incidentBadges(WorkDay $workDay): array
    {
        $badges = [];

        if ($this->hasAttendanceIncident($workDay)) {
            $badges[] = [
                'key' => 'attendance_incident',
                'label' => $this->attendanceIncidentTypeLabel($workDay),
                'variant' => 'success',
            ];
        }

        foreach ($workDay->alerts as $alert) {
            if ($this->workspaceTab === 'jornadas' && ! in_array($alert->rule_code, WorkDayWorkflow::ATTENDANCE_RULES, true)) {
                continue;
            }
            if (! $this->isVisibleOperationalAlert($workDay, $alert)) {
                continue;
            }

            $badges[] = [
                'key' => 'alert:'.$alert->rule_code,
                'label' => $this->operationalAlertLabel($alert),
                'variant' => $this->alertIncidentVariant($alert),
            ];
        }

        if ($this->isScheduledAbsenceCandidate($workDay)) {
            $badges[] = [
                'key' => 'alert:scheduled_absence',
                'label' => 'Falta',
                'variant' => 'danger',
            ];
        }

        if ($workDay->schedule_status === WorkDay::SCHEDULE_STATUS_UNSCHEDULED) {
            $badges[] = [
                'key' => 'schedule:unscheduled',
                'label' => 'Jornada no programada',
                'variant' => 'warning',
            ];
        }

        if ($workDay->status === WorkDay::STATUS_UNDER_REVIEW) {
            $badges[] = [
                'key' => 'work_day:under_review',
                'label' => 'En revision',
                'variant' => 'info',
            ];
        }

        return collect($badges)
            ->unique('key')
            ->values()
            ->all();
    }

    private function alertIncidentVariant(Alert $alert): string
    {
        if (in_array($alert->rule_code, WorkDayWorkflow::ATTENDANCE_RULES, true)) {
            if ($alert->status === Alert::STATUS_CORRECTED) {
                return 'warning';
            }
            if ($alert->status === Alert::STATUS_JUSTIFIED) {
                return 'info';
            }
        }
        return match ($alert->rule_code) {
            'scheduled_absence' => 'rose',
            'incomplete_work_day' => 'orange',
            'overtime_detected' => 'violet',
            'twelve_hours_exceeded' => 'danger',
            'sunday_work' => 'cyan',
            'mandatory_rest_work' => 'warning',
            'weekly_rest_missing' => 'lime',
            default => $this->alertStatusVariant($alert->status),
        };
    }

    private function operationalAlertLabel(Alert $alert): string
    {
        return in_array($alert->rule_code, WorkDayWorkflow::ATTENDANCE_RULES, true)
            && in_array($alert->status, [Alert::STATUS_CORRECTED, Alert::STATUS_JUSTIFIED], true)
                ? $this->contextualAlertStatusLabel($alert) : $alert->title;
    }

    private function incidentStatusLabel(WorkDay $workDay): string
    {
        if ($this->hasAttendanceIncident($workDay)) {
            return 'Aprobada';
        }

        if (($workDay->open_alerts_count ?? 0) > 0) {
            return 'Pendiente';
        }

        if (($workDay->resolved_alerts_count ?? 0) > 0) {
            return 'Dictaminada';
        }

        return 'Sin dictamen';
    }

    private function hasOperationalIncident(WorkDay $workDay): bool
    {
        return $this->hasAttendanceIncident($workDay)
            || $workDay->alerts->contains(fn (Alert $alert): bool => $alert->status !== Alert::STATUS_CLOSED)
            || $this->isScheduledAbsenceCandidate($workDay)
            || $workDay->schedule_status === WorkDay::SCHEDULE_STATUS_UNSCHEDULED
            || $workDay->status === WorkDay::STATUS_UNDER_REVIEW;
    }

    private function hasAttendanceIncident(WorkDay $workDay): bool
    {
        return is_array(data_get($workDay->metadata, 'attendance_incident'))
            || is_array(data_get($workDay->activeCalculation?->result_snapshot, 'attendance_incident'));
    }

    private function attendanceIncidentTypeLabel(WorkDay $workDay): string
    {
        $type = data_get($workDay->metadata, 'attendance_incident.incident_type')
            ?: data_get($workDay->activeCalculation?->result_snapshot, 'attendance_incident.incident_type');

        return match ($type) {
            'vacation' => 'Vacaciones',
            'incapacity' => 'Incapacidad',
            'paid_permission' => 'Permiso con goce',
            'unpaid_permission' => 'Permiso sin goce',
            'justified_paid_absence' => 'Falta justificada pagada',
            'justified_unpaid_absence' => 'Falta justificada no pagada',
            'unjustified_absence' => 'Falta injustificada',
            'maternity_paternity' => 'Maternidad / paternidad',
            default => 'Ausencia justificada',
        };
    }

    private function primaryIncidentAlert(WorkDay $workDay): ?Alert
    {
        return $workDay->alerts->first(fn (Alert $alert): bool => $this->isVisibleOperationalAlert($workDay, $alert) && in_array($alert->status, Alert::OPEN_STATUSES, true))
            ?? $workDay->alerts->first(fn (Alert $alert): bool => $this->isVisibleOperationalAlert($workDay, $alert) && in_array($alert->status, [Alert::STATUS_JUSTIFIED, Alert::STATUS_CORRECTED], true));
    }

    private function isScheduledAbsenceCandidate(WorkDay $workDay): bool
    {
        return ! $workDay->activeCalculation
            && $workDay->valid_time_event_count === 0
            && $this->hasWorkDatePassed($workDay)
            && $workDay->schedule_status === WorkDay::SCHEDULE_STATUS_SCHEDULED
            && $workDay->day_type === 'shift'
            && (int) $workDay->expected_work_minutes > 0;
    }

    private function isVisibleOperationalAlert(WorkDay $workDay, Alert $alert): bool
    {
        if ($alert->status === Alert::STATUS_CLOSED) {
            return false;
        }

        if ($alert->rule_code === 'scheduled_absence' && ! $this->hasWorkDatePassed($workDay)) {
            return false;
        }

        return true;
    }

    private function hasWorkDatePassed(WorkDay $workDay): bool
    {
        $timezone = $workDay->timezone ?: config('app.timezone');
        $today = CarbonImmutable::now($timezone)->toDateString();

        return $workDay->work_date?->toDateString() < $today;
    }

    private function lastRefreshLabel($company): string
    {
        if (! $company->setting?->work_days_last_refreshed_at) {
            return 'Sin ejecuciones registradas';
        }

        return $company->setting->work_days_last_refreshed_at
            ->timezone($company->setting->default_timezone ?: $company->timezone)
            ->format('Y-m-d H:i');
    }

    private function loadProcessForm($company, ?ProcessCompanyWorkDaysAction $action = null): void
    {
        $range = $action?->defaultAvailableRange($company);

        $this->processForm = [
            'date_from' => $range['start_date'] ?? '',
            'date_to' => $range['end_date'] ?? '',
            'reason' => '',
        ];
    }
}; ?>

<section class="flex h-full w-full flex-1 flex-col gap-6 p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">Jornadas</flux:heading>
            <flux:subheading>Completa la asistencia, revisa las incidencias calculadas y anticipa riesgos semanales.</flux:subheading>
        </div>

        @if ($canProcessWorkDays)
            <div class="flex flex-wrap gap-2">
                <button type="button" class="btn-primary" wire:click="openProcessPanel">
                    Recalcular jornadas
                </button>
            </div>
        @endif
    </div>

    @if (session('status'))
        <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
            {{ session('status') }}
        </div>
    @endif

    <nav aria-label="Flujo de jornadas" class="flex flex-wrap gap-2 border-b border-zinc-200 pb-3 dark:border-zinc-700">
        @foreach (['jornadas' => 'Jornadas', 'incidencias' => 'Incidencias', 'prevencion' => 'Prevención'] as $key => $label)
            <button type="button" wire:click="selectWorkspaceTab('{{ $key }}')" aria-current="{{ $workspaceTab === $key ? 'page' : 'false' }}"
                @class(['rounded-lg px-4 py-2 text-sm font-semibold transition', 'bg-blue-600 text-white' => $workspaceTab === $key, 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-200' => $workspaceTab !== $key])>{{ $label }}</button>
        @endforeach
    </nav>
    <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700"><p class="text-sm text-zinc-500">Jornadas por completar</p><p class="text-2xl font-bold">{{ $workspaceSummary['pending'] }}</p></div>
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700"><p class="text-sm text-zinc-500">Jornadas finalizadas</p><p class="text-2xl font-bold">{{ $workspaceSummary['completed'] }}</p></div>
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700"><p class="text-sm text-zinc-500">Incidencias por revisar</p><p class="text-2xl font-bold">{{ $workspaceSummary['open_incidents'] }}</p></div>
    </div>
    <p class="text-sm text-zinc-500">Finalizada significa asistencia aclarada y registros completos; no confirma cumplimiento ni cierra las incidencias calculadas.</p>
    @if ($calculatedConflict === 'twelve_hours_exceeded')
        <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
            <span>Filtro del dashboard: jornadas mayores a 12 horas en el período seleccionado.</span>
            <button type="button" wire:click="$set('calculatedConflict', '')" class="font-semibold underline">Quitar filtro de conflicto</button>
        </div>
    @elseif ($calculatedConflict === 'pending_evaluation')
        <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
            <span>Filtro del dashboard: jornadas que impiden evaluar el cumplimiento de la semana seleccionada.</span>
            <button type="button" wire:click="$set('calculatedConflict', '')" class="font-semibold underline">Quitar filtro de evaluación</button>
        </div>
    @endif
    <section class="rounded-md border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-900/60">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 xl:items-end">
            <flux:input label="Desde" type="date" max="{{ $todayDate }}" wire:model.live="dateFrom" />
            <flux:input label="Hasta" type="date" max="{{ $todayDate }}" wire:model.live="dateTo" />

            <flux:select label="Centro" wire:model.live="centerId">
                <flux:select.option value="">Todos</flux:select.option>
                @foreach ($centers as $center)
                    <flux:select.option value="{{ $center->id }}">{{ $center->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input label="Trabajador" placeholder="Clave o nombre" wire:model.live.debounce.400ms="search" />

            @if ($workspaceTab === 'jornadas')
                <flux:select label="Situación de asistencia" wire:model.live="situationFilter">
                    <flux:select.option value="">Todas</flux:select.option>
                    <flux:select.option value="scheduled_absence">Falta</flux:select.option>
                    <flux:select.option value="late_arrival_detected">Retardo</flux:select.option>
                    <flux:select.option value="early_departure_detected">Salida anticipada</flux:select.option>
                    <flux:select.option value="incomplete_work_day">Marcajes incompletos</flux:select.option>
                </flux:select>
                <flux:select label="Estado de jornada" wire:model.live="workflowState">
                    <flux:select.option value="">Todas</flux:select.option>
                    <flux:select.option value="pending">Por completar</flux:select.option>
                    <flux:select.option value="completed">Finalizadas</flux:select.option>
                </flux:select>
            @elseif ($workspaceTab === 'incidencias')
                <flux:select label="Revisión" wire:model.live="reviewState">
                    <flux:select.option value="open">Pendientes</flux:select.option>
                    <flux:select.option value="reviewed">Revisadas</flux:select.option>
                    <flux:select.option value="all">Todas</flux:select.option>
                </flux:select>
            @endif
            <div class="md:col-start-4 xl:col-start-auto xl:self-end">
                <flux:button type="button" variant="ghost" wire:click="clearFilters" class="w-full xl:w-auto">Limpiar</flux:button>
            </div>
        </div>
    </section>

    @if ($workspaceTab === 'jornadas')
    <section class="space-y-3">
        <div class="flex items-center justify-between">
            <flux:heading>Listado operativo</flux:heading>
            <p class="text-sm text-zinc-500">{{ $workDays->total() }} encontradas</p>
        </div>

        <div class="overflow-hidden rounded-md border border-zinc-200 dark:border-zinc-700">
            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead class="bg-zinc-50 text-left text-xs font-medium uppercase text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                        <tr>
                            <th class="px-4 py-3">Fecha</th>
                            <th class="px-4 py-3">Trabajador / Centro</th>
                            <th class="px-4 py-3">Horario</th>
                            <th class="px-4 py-3">Esperado</th>
                            <th class="px-4 py-3">Trabajado</th>
                            <th class="px-4 py-3">Incidencia</th>
                            <th class="px-4 py-3">Estado de jornada</th>
                            <th class="px-4 py-3 text-right">Accion</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 [&>tr:nth-child(odd)]:bg-white [&>tr:nth-child(even)]:bg-zinc-50/60 dark:divide-zinc-700 dark:[&>tr:nth-child(odd)]:bg-zinc-900 dark:[&>tr:nth-child(even)]:bg-zinc-800/40">
                        @forelse ($workDays as $workDay)
                            <tr>
                                <td class="whitespace-nowrap px-4 py-3">{{ $workDay->work_date?->toDateString() }}</td>
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ $workDay->worker?->employee_code }} - {{ $workDay->worker?->full_name }}</div>
                                    <div class="text-xs text-zinc-500">{{ $workDay->center?->name ?? 'Sin centro' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <x-ui.badge variant="{{ $this->scheduleStatusVariant($workDay->schedule_status) }}">
                                        {{ $this->scheduleStatusLabel($workDay->schedule_status) }}
                                    </x-ui.badge>
                                </td>
                                <td class="px-4 py-3">{{ $this->minutesLabel($workDay->expected_work_minutes) }}</td>
                                <td class="px-4 py-3">{{ $this->workedMinutesLabel($workDay) }}</td>
                                <td class="px-4 py-3">
                                    @php($incidentBadges = $this->incidentBadges($workDay))

                                    @if ($incidentBadges === [])
                                        <x-ui.badge variant="neutral">{{ $workDay->attendance_completed ? 'Sin pendientes' : 'Completar registros' }}</x-ui.badge>
                                    @else
                                        <div class="flex max-w-[220px] flex-col items-start gap-1">
                                            @foreach ($incidentBadges as $badge)
                                                <x-ui.badge variant="{{ $badge['variant'] }}">
                                                    {{ $badge['label'] }}
                                                </x-ui.badge>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @php($hasReviewedAttendance = $workDay->alerts->contains(fn ($alert) => in_array($alert->rule_code, WorkDayWorkflow::ATTENDANCE_RULES, true) && in_array($alert->status, [Alert::STATUS_CORRECTED, Alert::STATUS_JUSTIFIED], true)))
                                    <span class="text-sm text-zinc-700 dark:text-zinc-200">{{ $workDay->attendance_completed ? ($hasReviewedAttendance ? 'Finalizada con aclaración' : 'Finalizada') : 'Por completar' }}</span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @php($hasPendingIncidents = $workDay->alerts->contains(fn ($alert) => in_array($alert->rule_code, WorkDayWorkflow::ATTENDANCE_RULES, true) && in_array($alert->status, Alert::OPEN_STATUSES, true)))
                                    <button
                                        type="button"
                                        wire:click="openAlertsPanel({{ $workDay->id }})"
                                        class="inline-flex items-center rounded-md border px-3 py-1.5 text-xs font-medium shadow-sm transition focus:outline-none focus:ring-2 {{ $hasPendingIncidents ? 'border-blue-200 bg-blue-50 text-blue-800 hover:border-blue-300 hover:bg-blue-100 focus:ring-blue-200 dark:border-blue-900 dark:bg-blue-950/50 dark:text-blue-200 dark:hover:bg-blue-900/60' : 'border-zinc-200 bg-white text-zinc-700 hover:border-zinc-300 hover:bg-zinc-50 focus:ring-zinc-200 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800' }}"
                                        title="Abrir detalle de jornada"
                                    >
                                        {{ $hasPendingIncidents && $canResolveAlerts ? 'Completar / aclarar' : 'Ver jornada' }}
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-zinc-500">Sin jornadas en el rango seleccionado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{ $workDays->links() }}
    </section>

    @else
        @include('livewire.work-days.workspace-results')
    @endif

    <x-side-panel
        wire:model="showAlertsPanel"
        title="Incidencias de jornada"
        subheading="Revisa los registros, corrige si hace falta y atiende cada pendiente."
        labelledby="work-day-alerts-title"
        maxWidth="max-w-3xl"
        closeMethod="closeAlertsPanel"
    >
        <div class="flex flex-1 flex-col overflow-y-auto">
            <div class="flex-1 space-y-5 p-6">
                @if ($selectedWorkDay)
                    @php($panelAlert = $selectedWorkDay->alerts->firstWhere('id', $selectedAlertId))
                    @php($isWeeklyPanel = $panelAlert && in_array($panelAlert->rule_code, WorkDayWorkflow::WEEKLY_RULES, true))
                    @if (! $isWeeklyPanel)
                    <section class="space-y-4 rounded-xl border border-zinc-200 p-4 text-sm dark:border-zinc-700">
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-zinc-100 pb-3 dark:border-zinc-800">
                            <div><h3 class="text-xs font-semibold uppercase tracking-wide text-zinc-500">1. Revisar la jornada</h3><p class="mt-1 font-semibold">{{ $selectedWorkDay->worker?->full_name }}</p></div>
                            <span class="rounded-md bg-zinc-100 px-2 py-1 font-medium dark:bg-zinc-800">{{ $selectedWorkDay->work_date->format('d/m/Y') }}</span>
                        </div>
                        <dl class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800"><dt class="text-xs text-zinc-500">Horas programadas</dt><dd class="mt-1 text-lg font-semibold">{{ $this->minutesLabel($selectedWorkDay->expected_work_minutes) }}</dd></div>
                            <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800"><dt class="text-xs text-zinc-500">Horas trabajadas calculadas</dt><dd class="mt-1 text-lg font-semibold">{{ $this->workedMinutesLabel($selectedWorkDay) }}</dd></div>
                            <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800"><dt class="text-xs text-zinc-500">Pausa registrada en el cálculo</dt><dd class="mt-1 text-lg font-semibold">{{ $this->minutesLabel($selectedWorkDay->activeCalculation?->break_minutes) }}</dd></div>
                            <div class="rounded-lg bg-blue-50 p-3 dark:bg-blue-950"><dt class="text-xs text-blue-700 dark:text-blue-300">Horas extra calculadas</dt><dd class="mt-1 text-lg font-semibold text-blue-800 dark:text-blue-200">{{ $this->workDayCalculationSplitLabel($selectedWorkDay, 'overtime_minutes') }}</dd></div>
                        </dl>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                <h4 class="mb-2 font-semibold">Horario asignado</h4>
                                <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                    @forelse ($selectedWorkDay->dailyScheduleAssignment?->segments ?? collect() as $segment)
                                        <li class="flex flex-wrap items-center justify-between gap-2 py-2"><span class="text-zinc-500">{{ $segment->segment_type === 'break' ? 'Pausa' : 'Trabajo' }}</span><span class="font-medium tabular-nums">@if ($segment->start_local_time && $segment->end_local_time){{ substr($segment->start_local_time, 0, 5) }} – {{ substr($segment->end_local_time, 0, 5) }}@if ($segment->end_day_offset) (+{{ $segment->end_day_offset }} día)@endif @else{{ $this->minutesLabel($segment->duration_minutes) }}@endif</span></li>
                                    @empty<li class="py-2 text-zinc-500">Sin tramos de horario asignados.</li>@endforelse
                                </ul>
                            </div>
                            <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                <h4 class="mb-2 font-semibold">Marcajes válidos actuales</h4>
                                <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                    @forelse ($selectedWorkDay->reviewEvents as $event)
                                        <li class="flex flex-wrap items-center justify-between gap-2 py-2"><span class="text-zinc-500">{{ match ($event->event_type) { 'clock_in' => 'Entrada', 'clock_out' => 'Salida', 'break_start' => 'Inicio de pausa', 'break_end' => 'Fin de pausa', default => 'Registro manual' } }}</span><span class="font-medium tabular-nums">{{ $event->occurred_at_utc->timezone($selectedWorkDay->timezone ?: $company->timezone)->format('d/m H:i') }}</span></li>
                                    @empty<li class="py-2 text-zinc-500">Sin marcajes válidos.</li>@endforelse
                                </ul>
                            </div>
                        </div>
                        <p class="text-xs text-zinc-500">Si modificaste marcajes, recalcula para actualizar las horas mostradas.</p>
                    </section>
                    <section class="space-y-3 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-950 dark:bg-blue-950 dark:text-blue-100">
                        <h3 class="font-semibold">2. Corregir y actualizar si es necesario</h3>
                        <p>Si falta o está mal un registro, corrígelo con evidencia. Después recalcula esta jornada; también se actualizarán los acumulados semanales de esta persona.</p>
                        <div class="flex flex-wrap items-center gap-3">
                            <a class="font-semibold underline" href="{{ route('time-events.manual', ['from' => $selectedWorkDay->work_date->toDateString(), 'to' => $selectedWorkDay->work_date->toDateString(), 'worker' => $selectedWorkDay->worker_id, 'search' => $selectedWorkDay->worker?->employee_code ?? '']) }}">Corregir marcajes</a>
                            @if (Gate::allows('recalculate', $selectedWorkDay))
                                <button type="button" class="btn-primary" wire:click="recalculateSelectedWorkDay" wire:loading.attr="disabled" wire:target="recalculateSelectedWorkDay"><span wire:loading.remove wire:target="recalculateSelectedWorkDay">Recalcular esta jornada</span><span wire:loading wire:target="recalculateSelectedWorkDay">Recalculando…</span></button>
                            @endif
                        </div>
                        @error('recalculation')<p role="alert" class="text-red-600">{{ $message }}</p>@enderror
                        @if ($recalculationMessage !== '')<p role="status">{{ $recalculationMessage }}</p>@endif
                    </section>
                    @endif
                    @if ($isWeeklyPanel)
                        @php($caseWeek = CarbonImmutable::parse($panelAlert->metadata['week_start'] ?? $selectedWorkDay->work_date)->startOfWeek())
                        <section class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-950 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-100">
                            <p class="mb-2 font-semibold">{{ $selectedWorkDay->worker?->full_name }}</p>
                            <h3 class="font-semibold">Caso semanal: {{ $caseWeek->format('d/m/Y') }} al {{ $caseWeek->endOfWeek()->format('d/m/Y') }}</h3>
                            <p class="mt-2">{{ $panelAlert->description }}</p>
                            @if ($panelAlert->rule_code === 'weekly_rest_missing')
                                @php($weeklyRestSummary = app(\App\Domains\Alerts\Actions\ConfirmWeeklyRestReviewAction::class)->evidence($company, $panelAlert))
                                <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                                    <div><dt>Días trabajados</dt><dd class="font-semibold">{{ $weeklyRestSummary['worked_days'] }}</dd></div>
                                    <div><dt>Días sin trabajo registrado</dt><dd class="font-semibold">{{ $weeklyRestSummary['days_without_work'] }}</dd></div>
                                    <div><dt>Revisiones diarias de respaldo</dt><dd class="font-semibold">{{ $weeklyRestSummary['reviews']->count() }}</dd></div>
                                    <div><dt>Estado de revisión semanal</dt><dd class="font-semibold">{{ in_array($panelAlert->status, Alert::OPEN_STATUSES, true) ? 'Pendiente de confirmación' : 'Revisado: semana sin descanso' }}</dd></div>
                                </dl>
                                @if ($weeklyRestSummary['pending_days'] > 0)<p class="mt-2">{{ $weeklyRestSummary['pending_days'] }} días sin cálculo concluyente. No se consideran descanso confirmado.</p>@endif
                            @else
                            <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                                @foreach (['total_work_minutes' => 'Total trabajado (sin duplicar conceptos)', 'ordinary_minutes' => 'Acumulado en días laborables', 'weekly_limit_minutes' => 'Referencia semanal de jornada', 'overtime_minutes' => 'Horas extra totales', 'daily_overtime_minutes' => 'Extra por prolongación diaria', 'weekly_overtime_minutes' => 'Extra por límite semanal', 'rest_work_minutes' => 'Trabajo en descanso (concepto separado)', 'weekly_overtime_limit_minutes' => 'Umbral de revisión de horas extra', 'excess_minutes' => 'Exceso del aviso seleccionado'] as $key => $label)
                                    @if (isset($panelAlert->metadata[$key]))<div><dt>{{ $label }}</dt><dd class="font-semibold">{{ $this->minutesLabel((int) $panelAlert->metadata[$key]) }}</dd></div>@endif
                                @endforeach
                            </dl>
                            <p class="mt-3 text-xs">Las horas extra distinguen prolongación diaria y límite semanal en días laborables. Trabajar una jornada en descanso no la convierte automáticamente en extra. No sumes nuevamente los conceptos al total trabajado.</p>
                            @endif
                            <a class="mt-3 inline-block font-semibold underline" href="{{ route('work-days.index', ['tab' => 'jornadas', 'from' => $caseWeek->toDateString(), 'to' => $caseWeek->endOfWeek()->toDateString(), 'search' => $selectedWorkDay->worker?->employee_code, 'center' => $centerId ?: null]) }}" wire:navigate>Consultar jornadas de esta semana</a>
                            <p class="mt-2 text-xs">Este caso abarca toda la semana. Consulta las jornadas para revisar un día concreto.</p>
                        </section>
                    @endif

                    <section class="space-y-3">
                        <flux:heading>{{ $isWeeklyPanel ? 'Revisión del caso semanal' : '3. Atender cada pendiente' }}</flux:heading>
                        <p class="text-sm text-zinc-500">{{ $isWeeklyPanel ? 'Revisa el resultado de toda la semana y sus antecedentes.' : 'Si los marcajes y el cálculo están correctos, selecciona un asunto y registra su decisión. Cada revisión se guarda por separado.' }}</p>

                        @forelse ($selectedWorkDay->alerts as $alert)
                            <label class="block rounded-md border border-zinc-200 p-4 dark:border-zinc-700 {{ $selectedAlertId === $alert->id ? 'bg-blue-50 dark:bg-blue-950/30' : 'bg-white dark:bg-zinc-900' }}">
                                <div class="flex items-start gap-3">
                                    @if ($canResolveAlerts)
                                        <input type="radio" class="mt-1" wire:model.live="selectedAlertId" value="{{ $alert->id }}">
                                    @else
                                        <span class="mt-1 h-4 w-4 rounded-full bg-zinc-200 dark:bg-zinc-700"></span>
                                    @endif

                                    <div class="min-w-0 flex-1 space-y-2">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="font-medium">{{ $alert->title }}</p>
                                            <x-ui.badge variant="{{ $this->alertSeverityVariant($alert->severity) }}">
                                                {{ $this->alertSeverityLabel($alert->severity) }}
                                            </x-ui.badge>
                                            <x-ui.badge variant="{{ in_array($alert->rule_code, WorkDayWorkflow::ATTENDANCE_RULES, true) ? $this->alertIncidentVariant($alert) : $this->alertStatusVariant($alert->status) }}">
                                                {{ $this->contextualAlertStatusLabel($alert) }}
                                            </x-ui.badge>
                                        </div>

                                        <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ $alert->description }}</p>
                                        <p class="text-xs text-zinc-500">{{ $alert->alertType?->code }} - Detectada {{ $alert->detected_at?->timezone($selectedWorkDay->timezone ?: $company->timezone)->format('Y-m-d H:i') }}</p>

                                        @if ($alert->resolution)
                                            <div class="rounded-md bg-zinc-50 p-3 text-sm dark:bg-zinc-800">
                                                <p class="font-medium">Dictamen</p>
                                                <p class="mt-1 text-zinc-600 dark:text-zinc-300">{{ $alert->resolution }}</p>
                                                <p class="mt-1 text-xs text-zinc-500">
                                                    {{ $alert->resolver?->name ?? 'Usuario no disponible' }}
                                                    @if ($alert->resolved_at)
                                                        - {{ $alert->resolved_at->timezone($selectedWorkDay->timezone ?: $company->timezone)->format('Y-m-d H:i') }}
                                                    @endif
                                                </p>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </label>
                        @empty
                            <div class="rounded-md border border-zinc-200 bg-white p-4 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                                Esta jornada no tiene avisos vigentes.
                            </div>
                        @endforelse
                    </section>

                    @if ($selectedWorkDay->alerts->firstWhere('id', $selectedAlertId)?->rule_code === 'incomplete_work_day')
                            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Corrige los marcajes con evidencia en <a class="font-semibold underline" href="{{ route('time-events.manual', ['from' => $selectedWorkDay->work_date->toDateString(), 'to' => $selectedWorkDay->work_date->toDateString(), 'worker' => $selectedWorkDay->worker_id, 'search' => $selectedWorkDay->worker?->employee_code ?? '']) }}">Eventos</a> y recalcula la jornada. Un comentario no sustituye el marcaje faltante.</div>
                    @elseif ($canResolveAlerts && $panelAlert?->rule_code === 'weekly_rest_missing')
                        @php($restEvidence = app(\App\Domains\Alerts\Actions\ConfirmWeeklyRestReviewAction::class)->evidence($company, $panelAlert))
                        <section class="space-y-3 rounded-xl border border-zinc-200 p-4 text-sm dark:border-zinc-700">
                            <h3 class="font-semibold">Confirmar revisión semanal</h3>
                            <p>La semana no tuvo descanso. Usa las revisiones diarias como respaldo, sin repetir comentarios. Confirmar no acredita descanso disfrutado ni modifica las horas.</p>
                            @foreach ($restEvidence['reviews'] as $review)
                                <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800"><p class="font-medium">{{ $review->workDay->work_date->format('d/m/Y') }} · {{ $review->title }}</p><p>{{ $this->contextualAlertStatusLabel($review) }}</p><p class="text-zinc-500">{{ $review->resolution }}</p></div>
                            @endforeach
                            @if (in_array($panelAlert->status, Alert::OPEN_STATUSES, true))
                                @if ($restEvidence['can_confirm'])
                                    <button type="button" class="btn-primary" wire:click="confirmWeeklyRestReview" wire:loading.attr="disabled" wire:target="confirmWeeklyRestReview">Confirmar revisión semanal</button>
                                @else<p class="text-amber-700">Completa la semana y revisa los avisos diarios de descanso trabajado y domingo antes de confirmar.</p>@endif
                            @else<p role="status" class="font-semibold text-emerald-700">Revisado: semana sin descanso</p>@endif
                            @error('weeklyRestReview')<p role="alert" class="text-red-600">{{ $message }}</p>@enderror
                        </section>
                    @elseif ($canResolveAlerts && $selectedWorkDay->alerts->isNotEmpty())
                        @php($selectedAlertForResolution = $selectedWorkDay->alerts->firstWhere('id', $selectedAlertId))
                        @php($linkedAbsenceIncident = $this->linkedAttendanceIncidentForAlert($selectedAlertForResolution))
                        <form wire:submit="resolveSelectedAlert" class="space-y-4 rounded-md border border-zinc-200 p-4 dark:border-zinc-700">
                            <flux:select label="{{ $workspaceTab === 'jornadas' ? 'Dictamen' : ($selectedAlertForResolution?->rule_code === 'scheduled_rest_work' ? 'Evaluación del trabajo en descanso' : 'Acción para el aviso seleccionado') }}" wire:model.live="alertResolutionForm.status">
                                @foreach ($this->alertResolutionOptions($selectedAlertForResolution) as $status => $label)
                                    <flux:select.option value="{{ $status }}">{{ $label }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            @if ($selectedAlertForResolution?->rule_code === 'scheduled_absence' && $alertResolutionForm['status'] === Alert::STATUS_JUSTIFIED)
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <flux:select label="Tipo de ausencia" wire:model="alertResolutionForm.incident_type">
                                        <flux:select.option value="{{ AttendanceIncident::TYPE_JUSTIFIED_PAID_ABSENCE }}">Falta justificada pagada</flux:select.option>
                                        <flux:select.option value="{{ AttendanceIncident::TYPE_JUSTIFIED_UNPAID_ABSENCE }}">Falta justificada no pagada</flux:select.option>
                                        <flux:select.option value="{{ AttendanceIncident::TYPE_VACATION }}">Vacaciones</flux:select.option>
                                        <flux:select.option value="{{ AttendanceIncident::TYPE_INCAPACITY }}">Incapacidad</flux:select.option>
                                        <flux:select.option value="{{ AttendanceIncident::TYPE_PAID_PERMISSION }}">Permiso con goce</flux:select.option>
                                        <flux:select.option value="{{ AttendanceIncident::TYPE_UNPAID_PERMISSION }}">Permiso sin goce</flux:select.option>
                                        <flux:select.option value="{{ AttendanceIncident::TYPE_MATERNITY_PATERNITY }}">Maternidad / paternidad</flux:select.option>
                                        <flux:select.option value="{{ AttendanceIncident::TYPE_OTHER }}">Otra ausencia autorizada</flux:select.option>
                                    </flux:select>

                                    <flux:select label="Tratamiento operativo" wire:model="alertResolutionForm.payment_status">
                                        <flux:select.option value="{{ AttendanceIncident::PAYMENT_PAID }}">Pagada</flux:select.option>
                                        <flux:select.option value="{{ AttendanceIncident::PAYMENT_UNPAID }}">No pagada</flux:select.option>
                                        <flux:select.option value="{{ AttendanceIncident::PAYMENT_NOT_APPLICABLE }}">No aplica</flux:select.option>
                                    </flux:select>
                                </div>

                                <div class="rounded-md border border-amber-100 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-950 dark:bg-amber-950/40 dark:text-amber-100">
                                    @if ($linkedAbsenceIncident)
                                        Se actualizara la incidencia/ausencia vinculada y la jornada se recalculara. No se creara un duplicado.
                                    @else
                                        Se creara una incidencia/ausencia aprobada para esta fecha y la jornada se recalculara. No se registran horas trabajadas.
                                    @endif
                                </div>
                            @elseif ($selectedAlertForResolution?->rule_code === 'scheduled_absence' && $alertResolutionForm['status'] === Alert::STATUS_CORRECTED)
                                <p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">Se registrará la falta confirmada como ausencia injustificada, sin agregar horas trabajadas, y se recalculará la jornada.</p>
                            @elseif ($selectedAlertForResolution?->rule_code === 'scheduled_absence' && $linkedAbsenceIncident && $linkedAbsenceIncident->status === AttendanceIncident::STATUS_APPROVED)
                                <div class="rounded-md border border-amber-100 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-950 dark:bg-amber-950/40 dark:text-amber-100">
                                    Esta falta tiene una incidencia/ausencia vinculada. Al guardar este dictamen, esa incidencia quedara cancelada y la jornada se recalculara.
                                </div>
                            @endif

                            @if ($workspaceTab === 'incidencias')
                                @if ($selectedAlertForResolution?->rule_code === 'scheduled_rest_work')
                                    <p class="rounded-lg bg-blue-50 p-3 text-sm text-blue-900 dark:bg-blue-950 dark:text-blue-200">Registra si el trabajo en el descanso asignado estaba autorizado y explica el motivo. La evaluación conserva las horas trabajadas y el descanso programado; no determina por sí sola el pago.</p>
                                @elseif ($selectedAlertForResolution?->rule_code === 'minimum_break_missing')
                                    <p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">Si faltan marcajes de pausa, corrígelos y recalcula antes de guardar. Si los registros son correctos, explica si no hubo pausa y qué medida se tomó. Un comentario no agrega ni descuenta tiempo.</p>
                                @elseif ($selectedAlertForResolution?->rule_code === 'overtime_detected')
                                    <p class="rounded-lg bg-blue-50 p-3 text-sm text-blue-900 dark:bg-blue-950 dark:text-blue-200">Autorizar las horas extra conserva el tiempo trabajado. La duración de la jornada, las pausas y los casos semanales requieren su propia revisión.</p>
                                @else
                                    <p class="rounded-lg bg-blue-50 p-3 text-sm text-blue-900 dark:bg-blue-950 dark:text-blue-200">La revisión no modifica los marcajes ni elimina las horas trabajadas o el exceso detectado. Si el cálculo cambia se solicitará otra revisión conservando el historial.</p>
                                @endif
                            @endif
                            <flux:textarea label="Comentario obligatorio" wire:model="alertResolutionForm.resolution" rows="4" placeholder="Describe el motivo y las acciones de seguimiento." />

                            <div class="flex justify-end gap-2">
                                <button type="button" class="btn-ghost" wire:click="closeAlertsPanel">Cancelar</button>
                                <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="resolveSelectedAlert">
                                    <span wire:loading wire:target="resolveSelectedAlert" class="btn-spinner"></span>
                                    <span wire:loading.remove wire:target="resolveSelectedAlert">{{ $workspaceTab === 'jornadas' ? 'Guardar aclaración' : ($selectedAlertForResolution?->rule_code === 'scheduled_rest_work' ? 'Guardar evaluación' : 'Guardar revisión') }}</span>
                                    <span wire:loading wire:target="resolveSelectedAlert">Guardando</span>
                                </button>
                            </div>
                        </form>
                    @else
                        <div class="rounded-md border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
                            Esta jornada no tiene incidencias pendientes.
                        </div>
                    @endif
                    @if (! $isWeeklyPanel || $selectedWorkDay->historicalAlerts->isNotEmpty())
                    <section class="space-y-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                        <h3 class="font-semibold">{{ $isWeeklyPanel ? 'Antecedentes del caso semanal' : '4. Consultar antecedentes' }}</h3>
                    @if ($selectedWorkDay->historicalAlerts->isNotEmpty())
                        <details class="space-y-3 rounded-xl border border-zinc-200 p-4 text-sm dark:border-zinc-700">
                            <summary class="cursor-pointer font-semibold text-zinc-500">Ver historial ({{ $selectedWorkDay->historicalAlerts->count() }})</summary>
                            <p class="text-xs text-zinc-500">Estos avisos corresponden a un cálculo anterior y ya no requieren atención.</p>
                            @foreach ($selectedWorkDay->historicalAlerts as $historicalAlert)
                                <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800">
                                    <p class="font-medium">{{ $historicalAlert->title }}</p>
                                    <p class="text-xs text-zinc-500">Ya no aplica tras recálculo</p>
                                    @if ($historicalAlert->resolved_at)<p class="mt-1 text-xs text-zinc-500">Cierre automático: {{ $historicalAlert->resolved_at->timezone($selectedWorkDay->timezone ?: $company->timezone)->format('d/m/Y H:i') }}</p>@endif
                                </div>
                            @endforeach
                        </details>
                    @endif

                    @if (! $isWeeklyPanel)
                    <details class="space-y-3"><summary class="cursor-pointer text-sm font-semibold text-blue-700 dark:text-blue-300">Ver detalle técnico del cálculo</summary>
                    <section class="rounded-md border border-zinc-200 bg-zinc-50 p-4 text-sm dark:border-zinc-700 dark:bg-zinc-800/60">
                        <dl class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <dt class="text-xs font-medium uppercase text-zinc-500">Trabajador</dt>
                                <dd class="mt-1 font-medium">{{ $selectedWorkDay->worker?->employee_code }} - {{ $selectedWorkDay->worker?->full_name }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase text-zinc-500">Fecha</dt>
                                <dd class="mt-1 font-medium">{{ $selectedWorkDay->work_date?->toDateString() }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase text-zinc-500">Centro</dt>
                                <dd class="mt-1">{{ $selectedWorkDay->center?->name ?? 'Sin centro' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase text-zinc-500">Calculado</dt>
                                <dd class="mt-1">{{ $this->workedMinutesLabel($selectedWorkDay) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase text-zinc-500">Horario</dt>
                                <dd class="mt-1">{{ $this->scheduleStatusLabel($selectedWorkDay->schedule_status) }} - {{ $this->dayTypeLabel($selectedWorkDay->day_type) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase text-zinc-500">Esperado</dt>
                                <dd class="mt-1">{{ $this->minutesLabel($selectedWorkDay->expected_work_minutes) }}</dd>
                            </div>
                        </dl>
                    </section>

                    <section class="rounded-md border border-zinc-200 p-4 text-sm dark:border-zinc-700">
                        <div class="mb-3 flex items-center justify-between">
                            <flux:heading>Detalle operativo</flux:heading>
                            <x-ui.badge variant="{{ $this->primaryIncidentVariant($selectedWorkDay) }}">
                                {{ $this->primaryIncidentLabel($selectedWorkDay) }}
                            </x-ui.badge>
                        </div>

                        @if ($overtimeReviewMargin['within_margin'] ?? false)
                            <p class="mb-3 rounded-lg bg-blue-50 p-3 text-sm text-blue-900 dark:bg-blue-950 dark:text-blue-200">Tiempo adicional dentro del margen: {{ $overtimeReviewMargin['overtime_minutes'] }} min de {{ $overtimeReviewMargin['minutes'] }} min configurados. Se conservan los minutos reales, sin autorización automática. Al recalcular no se solicita revisión diaria por este tiempo; los indicadores semanales y demás avisos se conservan.</p>
                        @endif
                        <dl class="grid gap-3 sm:grid-cols-3">
                            <div>
                                <dt class="text-xs font-medium uppercase">
                                    <a class="text-blue-600 underline hover:text-blue-800" href="{{ route('time-events.manual', ['from' => $selectedWorkDay->work_date->toDateString(), 'to' => $selectedWorkDay->work_date->toDateString(), 'worker' => $selectedWorkDay->worker_id, 'search' => $selectedWorkDay->worker?->employee_code ?? '']) }}">Eventos</a>
                                </dt>
                                <dd class="mt-1">
                                    {{ $selectedWorkDay->valid_time_event_count }}
                                    @if ($selectedWorkDay->first_event_at_utc)
                                        <span class="block text-xs text-zinc-500">
                                            {{ $selectedWorkDay->first_event_at_utc->timezone($selectedWorkDay->timezone ?: $company->timezone)->format('H:i') }}
                                            -
                                            {{ $selectedWorkDay->last_event_at_utc?->timezone($selectedWorkDay->timezone ?: $company->timezone)->format('H:i') }}
                                        </span>
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase text-zinc-500">Jornada sin extra</dt>
                                <dd class="mt-1">{{ $this->workDayCalculationSplitLabel($selectedWorkDay, 'ordinary_minutes') }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase text-zinc-500">Extra total del día</dt>
                                <dd class="mt-1">{{ $this->workDayCalculationSplitLabel($selectedWorkDay, 'overtime_minutes') }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase text-zinc-500">Especiales</dt>
                                <dd class="mt-1">{{ $this->workDaySpecialCasesLabel($selectedWorkDay) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase text-zinc-500">Legal</dt>
                                <dd class="mt-1">
                                    {{ $this->workDayLegalClassificationLabel($selectedWorkDay) }}
                                    @if ($this->legalNightMinutesLabel($selectedWorkDay->activeCalculation) !== '')
                                        <span class="block text-xs text-zinc-500">{{ $this->legalNightMinutesLabel($selectedWorkDay->activeCalculation) }}</span>
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase text-zinc-500">Dictamen</dt>
                                <dd class="mt-1">{{ $this->incidentStatusLabel($selectedWorkDay) }}</dd>
                            </div>
                        </dl>
                        @if ((int) data_get($selectedWorkDay->activeCalculation?->result_snapshot, 'ordinary_overtime.weekly_ordinary_excess_minutes', 0) > 0)
                            <p class="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">Este día tiene {{ $this->minutesLabel((int) data_get($selectedWorkDay->activeCalculation->result_snapshot, 'ordinary_overtime.weekly_ordinary_excess_minutes')) }} extra por límite semanal en días laborables. Ya están incluidas en el extra total; no significan una prolongación diaria.</p>
                        @endif
                        @if ((int) data_get($selectedWorkDay->activeCalculation?->result_snapshot, 'ordinary_overtime.schema_version', 0) >= 3)
                            <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">Origen del extra: {{ $this->minutesLabel((int) data_get($selectedWorkDay->activeCalculation->result_snapshot, 'ordinary_overtime.daily_overtime_minutes', 0)) }} por prolongación diaria y {{ $this->minutesLabel((int) data_get($selectedWorkDay->activeCalculation->result_snapshot, 'ordinary_overtime.weekly_overtime_minutes', 0)) }} por límite semanal.</p>
                        @endif
                        @if (data_get($selectedWorkDay->activeCalculation?->result_snapshot, 'special_legal_cases.scheduled_rest.worked', false))
                            <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">Trabajo en descanso asignado: {{ $this->workedMinutesLabel($selectedWorkDay) }}. Se conserva como concepto separado; no implica autorización de horas extra.</p>
                        @endif
                    </section>

                    </details>
                    @endif
                    </section>
                    @endif
                @else
                    <p class="text-sm text-zinc-500">Selecciona una jornada.</p>
                @endif
            </div>
        </div>
    </x-side-panel>

    <x-side-panel
        wire:model="showProcessPanel"
        title="Recalcular jornadas"
        subheading="Reprocesa jornadas disponibles y conserva motivo de auditoria."
        labelledby="process-work-days-title"
    >
        <form wire:submit="processWorkDays" class="flex flex-1 flex-col overflow-y-auto">
            <div class="flex-1 space-y-4 p-6">
                <div class="rounded-md border border-zinc-200 bg-zinc-50 p-4 text-sm dark:border-zinc-700 dark:bg-zinc-800/60">
                    <dl class="grid gap-3">
                        <div>
                            <dt class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">Empresa</dt>
                            <dd class="mt-1 font-medium text-zinc-900 dark:text-zinc-100">{{ $company->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">Alcance</dt>
                            <dd class="mt-1 text-zinc-700 dark:text-zinc-200">Datos disponibles hasta hoy. Las fechas permiten reprocesar un rango puntual.</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">Hora automatica</dt>
                            <dd class="mt-1 text-zinc-700 dark:text-zinc-200">{{ $this->autoRefreshTimeLabel($company) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">Ultima ejecucion</dt>
                            <dd class="mt-1 text-zinc-700 dark:text-zinc-200">{{ $this->lastRefreshLabel($company) }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <flux:input wire:model="processForm.date_from" label="Desde" type="date" max="{{ $todayDate }}" />
                    <flux:input wire:model="processForm.date_to" label="Hasta" type="date" max="{{ $todayDate }}" />
                </div>
                <flux:textarea wire:model="processForm.reason" label="Motivo obligatorio" placeholder="Ej. Reproceso por checadas capturadas tarde o revision de RH." rows="3" />

                @if ($company->setting?->work_days_last_refreshed_at)
                    <div class="rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                        Ultima ejecucion: {{ $company->setting->work_days_last_refreshed_at->timezone($company->setting->default_timezone ?: $company->timezone)->format('Y-m-d H:i') }}
                        - {{ $company->setting->work_days_last_refresh_status }}
                    </div>
                @endif

                <div class="rounded-md border border-amber-100 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-950 dark:bg-amber-950/40 dark:text-amber-100">
                    <p class="font-medium">Al ejecutar se procesara:</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        <li>Jornadas programadas desde horarios publicados.</li>
                        <li>Jornadas no programadas cuando existan eventos validos sin horario.</li>
                        <li>Usa eventos validos ordenados por fecha real del hecho.</li>
                        <li>Genera una version activa y conserva versiones anteriores.</li>
                        <li>Clasifica la jornada como diurna, nocturna o mixta.</li>
                        <li>Calcula minutos ordinarios y extra con reglas versionadas.</li>
                        <li>Identifica trabajo en domingo, descanso obligatorio y semanas sin descanso detectado.</li>
                        <li>Genera o cierra alertas preventivas segun el calculo vigente.</li>
                        <li>No genera incidencias ni cierres.</li>
                    </ul>
                </div>
            </div>

            <div class="flex justify-end gap-3 border-t border-zinc-200 p-4 dark:border-zinc-700">
                <button type="button" class="btn-ghost" wire:click="closeProcessPanel">Cancelar</button>
                <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="processWorkDays">
                    <span wire:loading wire:target="processWorkDays" class="btn-spinner"></span>
                    <span wire:loading.remove wire:target="processWorkDays">Recalcular</span>
                    <span wire:loading wire:target="processWorkDays">Recalculando</span>
                </button>
            </div>
        </form>
    </x-side-panel>
</section>
