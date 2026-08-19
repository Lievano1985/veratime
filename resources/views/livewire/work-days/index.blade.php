<?php

use App\Domains\Alerts\Actions\ResolveAlertAction;
use App\Domains\Organization\Support\ScopedOperationalAccess;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Domains\WorkDays\Actions\ListWorkDaysAction;
use App\Domains\WorkDays\Actions\ProcessCompanyWorkDaysAction;
use App\Models\Alert;
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

    #[Url]
    public string $search = '';

    public array $processForm = [];

    public bool $showProcessPanel = false;

    public bool $showAlertsPanel = false;

    public ?int $selectedWorkDayId = null;

    public ?int $selectedAlertId = null;

    public array $alertResolutionForm = [
        'status' => Alert::STATUS_JUSTIFIED,
        'resolution' => '',
    ];

    public function mount(CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('viewAny', [WorkDay::class, $company]);

        $today = $this->todayForCompany($company);
        $weekStart = $today->startOfWeek(\Carbon\CarbonInterface::MONDAY);

        $this->dateFrom = $this->dateFrom !== '' ? $this->dateFrom : $weekStart->toDateString();
        $this->dateTo = $this->dateTo !== '' ? $this->dateTo : $today->toDateString();
        $this->capDateFiltersToToday($company);
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
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('viewAny', [WorkDay::class, $company]);

        $workDay = WorkDay::query()
            ->with(['alerts' => fn ($query) => $query->with(['alertType', 'resolver'])->orderByRaw("case when status in ('new', 'in_review', 'pending_information') then 1 else 2 end")->orderBy('detected_at')])
            ->where('company_id', $company->id)
            ->findOrFail($workDayId);

        Gate::authorize('view', $workDay);

        $firstAlert = $workDay->alerts
            ->first(fn (Alert $alert): bool => in_array($alert->status, Alert::OPEN_STATUSES, true))
            ?? $workDay->alerts->first();

        $this->selectedWorkDayId = $workDay->id;
        $this->selectedAlertId = $firstAlert?->id;
        $this->alertResolutionForm = [
            'status' => Alert::STATUS_JUSTIFIED,
            'resolution' => '',
        ];
        $this->showAlertsPanel = true;
    }

    public function closeAlertsPanel(): void
    {
        $this->showAlertsPanel = false;
        $this->selectedWorkDayId = null;
        $this->selectedAlertId = null;
        $this->alertResolutionForm = [
            'status' => Alert::STATUS_JUSTIFIED,
            'resolution' => '',
        ];
    }

    public function resolveSelectedAlert(ResolveAlertAction $action, CurrentCompany $currentCompany): void
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
            'alertResolutionForm.resolution' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $alert = Alert::query()
            ->where('company_id', $company->id)
            ->where('work_day_id', $this->selectedWorkDayId)
            ->findOrFail((int) $validated['selectedAlertId']);

        Gate::authorize('resolve', $alert);

        try {
            $action->handle($company, $alert, auth()->user(), $validated['alertResolutionForm']);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'alertResolutionForm.resolution' => $exception->getMessage(),
            ]);
        }

        $nextAlertId = Alert::query()
            ->where('company_id', $company->id)
            ->where('work_day_id', $this->selectedWorkDayId)
            ->whereIn('status', Alert::OPEN_STATUSES)
            ->orderBy('detected_at')
            ->value('id');

        if (! $nextAlertId) {
            $this->closeAlertsPanel();
            Session::flash('status', 'Incidencia dictaminada. La jornada quedo sin incidencias pendientes.');
            $this->resetPage();
            return;
        }

        $this->selectedAlertId = (int) $nextAlertId;
        $this->alertResolutionForm['resolution'] = '';
        Session::flash('status', 'Incidencia dictaminada. Aun quedan incidencias pendientes para la jornada.');
        $this->resetPage();
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
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
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

    public function updatedSearch(): void
    {
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
        $this->search = '';
        $this->resetPage();
    }

    public function with(CurrentCompany $currentCompany, ListWorkDaysAction $listWorkDays): array
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('viewAny', [WorkDay::class, $company]);

        $this->capDateFiltersToToday($company);

        $visibleWorkDayAccess = $this->visibleWorkDayAccess($company);

        $workDays = $listWorkDays->handle($company, [
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'center_ids' => $visibleWorkDayAccess['center_ids'],
            'relationship_ids' => $visibleWorkDayAccess['relationship_ids'],
            'center_id' => $this->centerId === '' ? null : (int) $this->centerId,
            'status' => $this->statusFilter === '' ? null : $this->statusFilter,
            'schedule_status' => $this->scheduleStatusFilter === '' ? null : $this->scheduleStatusFilter,
            'incident_type' => $this->incidentTypeFilter === '' ? null : $this->incidentTypeFilter,
            'incident_status' => $this->incidentStatusFilter === '' ? null : $this->incidentStatusFilter,
            'search' => $this->search,
        ]);

        $selectedWorkDay = $this->selectedWorkDayId
            ? WorkDay::query()
                ->with([
                    'worker',
                    'center',
                    'employmentRelationship',
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

        $visibleCenterIds = $visibleWorkDayAccess['filter_center_ids'];

        return [
            'company' => $company,
            'centers' => $company->centers()
                ->where('status', 'active')
                ->when($visibleCenterIds !== null, fn ($query) => $query->whereIn('id', $visibleCenterIds))
                ->orderBy('name')
                ->get(),
            'workDays' => $workDays,
            'selectedWorkDay' => $selectedWorkDay,
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
            'closed' => 'Cerrada / no procede',
            default => ucfirst($status),
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
            return $alert->title;
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
            if (! $this->isVisibleOperationalAlert($workDay, $alert)) {
                continue;
            }

            $badges[] = [
                'key' => 'alert:'.$alert->rule_code,
                'label' => $alert->title,
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
            <flux:subheading>Revisa jornadas e identifica solo las incidencias que requieren dictamen operativo.</flux:subheading>
        </div>

        @if ($canProcessWorkDays)
            <div class="flex flex-wrap gap-2">
                <flux:button type="button" variant="primary" wire:click="openProcessPanel">
                    Recalcular jornadas
                </flux:button>
            </div>
        @endif
    </div>

    @if (session('status'))
        <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
            {{ session('status') }}
        </div>
    @endif

    <section class="rounded-md border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-900/60">
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-[0.85fr_0.85fr_1fr_1fr_1fr_1fr_1fr_auto] xl:items-end">
            <flux:input label="Desde" type="date" max="{{ $todayDate }}" wire:model.live="dateFrom" />
            <flux:input label="Hasta" type="date" max="{{ $todayDate }}" wire:model.live="dateTo" />

            <flux:select label="Centro" wire:model.live="centerId">
                <flux:select.option value="">Todos</flux:select.option>
                @foreach ($centers as $center)
                    <flux:select.option value="{{ $center->id }}">{{ $center->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select label="Horario" wire:model.live="scheduleStatusFilter">
                <flux:select.option value="">Todos</flux:select.option>
                <flux:select.option value="scheduled">Programadas</flux:select.option>
                <flux:select.option value="unscheduled">No programadas</flux:select.option>
            </flux:select>

            <flux:select label="Resultado" wire:model.live="statusFilter">
                <flux:select.option value="">Todas</flux:select.option>
                <flux:select.option value="with_alerts">Con alertas</flux:select.option>
                <flux:select.option value="calculated">Calculadas</flux:select.option>
                <flux:select.option value="under_review">En revision</flux:select.option>
                <flux:select.option value="pending">Pendientes</flux:select.option>
            </flux:select>

            <flux:select label="Incidencia" wire:model.live="incidentTypeFilter">
                <flux:select.option value="">Todas</flux:select.option>
                <flux:select.option value="with_incidents">Solo con incidencia</flux:select.option>
                <flux:select.option value="scheduled_absence">Falta</flux:select.option>
                <flux:select.option value="incomplete_work_day">Evento incompleto</flux:select.option>
                <flux:select.option value="unscheduled_work_day">No programada</flux:select.option>
                <flux:select.option value="overtime_detected">Hora extra</flux:select.option>
                <flux:select.option value="sunday_work">Domingo trabajado</flux:select.option>
                <flux:select.option value="mandatory_rest_work">Descanso obligatorio</flux:select.option>
                <flux:select.option value="weekly_rest_missing">Semana sin descanso</flux:select.option>
            </flux:select>

            <flux:select label="Dictamen" wire:model.live="incidentStatusFilter">
                <flux:select.option value="">Todos</flux:select.option>
                <flux:select.option value="pending">Pendiente</flux:select.option>
                <flux:select.option value="dictated">Dictaminada</flux:select.option>
                <flux:select.option value="none">Sin incidencia</flux:select.option>
            </flux:select>

            <flux:input label="Trabajador" placeholder="Clave o nombre" wire:model.live.debounce.400ms="search" />

            <flux:button type="button" variant="ghost" wire:click="clearFilters">Limpiar</flux:button>
        </div>
    </section>

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
                            <th class="px-4 py-3">Dictamen</th>
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
                                        <x-ui.badge variant="neutral">Sin incidencia</x-ui.badge>
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
                                    <span class="text-sm text-zinc-700 dark:text-zinc-200">{{ $this->incidentStatusLabel($workDay) }}</span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @php($hasPendingIncidents = ($workDay->open_alerts_count ?? 0) > 0)
                                    <button
                                        type="button"
                                        wire:click="openAlertsPanel({{ $workDay->id }})"
                                        class="inline-flex items-center rounded-md border px-3 py-1.5 text-xs font-medium shadow-sm transition focus:outline-none focus:ring-2 {{ $hasPendingIncidents ? 'border-blue-200 bg-blue-50 text-blue-800 hover:border-blue-300 hover:bg-blue-100 focus:ring-blue-200 dark:border-blue-900 dark:bg-blue-950/50 dark:text-blue-200 dark:hover:bg-blue-900/60' : 'border-emerald-300 bg-emerald-100 text-emerald-800 hover:border-emerald-400 hover:bg-emerald-200 focus:ring-emerald-200 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200 dark:hover:bg-emerald-900/70' }}"
                                        title="Abrir detalle de jornada"
                                    >
                                        {{ $hasPendingIncidents && $canResolveAlerts ? 'Dictaminar' : 'Ver' }}
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

    <x-side-panel
        wire:model="showAlertsPanel"
        title="Incidencias de jornada"
        subheading="Revisa solo lo fuera de comun y consulta el seguimiento operativo."
        labelledby="work-day-alerts-title"
        maxWidth="max-w-3xl"
        closeMethod="closeAlertsPanel"
    >
        <div class="flex flex-1 flex-col overflow-y-auto">
            <div class="flex-1 space-y-5 p-6">
                @if ($selectedWorkDay)
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

                        <dl class="grid gap-3 sm:grid-cols-3">
                            <div>
                                <dt class="text-xs font-medium uppercase text-zinc-500">Eventos</dt>
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
                                <dt class="text-xs font-medium uppercase text-zinc-500">Ordinario</dt>
                                <dd class="mt-1">{{ $this->workDayCalculationSplitLabel($selectedWorkDay, 'ordinary_minutes') }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase text-zinc-500">Extra</dt>
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
                    </section>

                    <section class="space-y-3">
                        <flux:heading>Incidencias</flux:heading>

                        @forelse ($selectedWorkDay->alerts as $alert)
                            <label class="block rounded-md border border-zinc-200 p-4 dark:border-zinc-700 {{ $selectedAlertId === $alert->id ? 'bg-blue-50 dark:bg-blue-950/30' : 'bg-white dark:bg-zinc-900' }}">
                                <div class="flex items-start gap-3">
                                    @if ($canResolveAlerts && in_array($alert->status, Alert::OPEN_STATUSES, true))
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
                                            <x-ui.badge variant="{{ $this->alertStatusVariant($alert->status) }}">
                                                {{ $this->alertStatusLabel($alert->status) }}
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
                                Esta jornada no tiene incidencias registradas.
                            </div>
                        @endforelse
                    </section>

                    @if ($canResolveAlerts && $selectedWorkDay->alerts->contains(fn ($alert) => in_array($alert->status, Alert::OPEN_STATUSES, true)))
                        <form wire:submit="resolveSelectedAlert" class="space-y-4 rounded-md border border-zinc-200 p-4 dark:border-zinc-700">
                            <flux:select label="Dictamen" wire:model="alertResolutionForm.status">
                                <flux:select.option value="justified">Aprobar</flux:select.option>
                                <flux:select.option value="corrected">No aprobar</flux:select.option>
                                <flux:select.option value="closed">Cerrada / no procede</flux:select.option>
                            </flux:select>

                            <flux:textarea label="Comentario obligatorio" wire:model="alertResolutionForm.resolution" rows="4" placeholder="Describe la razon del dictamen operativo." />

                            <div class="flex justify-end gap-2">
                                <flux:button type="button" variant="ghost" wire:click="closeAlertsPanel">Cancelar</flux:button>
                                <flux:button type="submit" variant="primary">Guardar dictamen</flux:button>
                            </div>
                        </form>
                    @else
                        <div class="rounded-md border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
                            Esta jornada no tiene incidencias pendientes.
                        </div>
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
                <flux:button type="button" variant="ghost" wire:click="closeProcessPanel">Cancelar</flux:button>
                <flux:button type="submit" variant="primary">Recalcular</flux:button>
            </div>
        </form>
    </x-side-panel>
</section>
