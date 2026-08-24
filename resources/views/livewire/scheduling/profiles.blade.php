<?php

use App\Domains\Scheduling\Actions\CreateScheduleProfileAction;
use App\Domains\Scheduling\Actions\DeleteScheduleProfileIfUnusedAction;
use App\Domains\Scheduling\Actions\InactivateScheduleProfileAction;
use App\Domains\Scheduling\Actions\ReactivateScheduleProfileAction;
use App\Domains\Scheduling\Actions\ReplaceScheduleProfileCycleRulesAction;
use App\Domains\Scheduling\Actions\ReplaceScheduleProfileFlexibleRulesAction;
use App\Domains\Scheduling\Actions\ReplaceScheduleProfileOnCallRulesAction;
use App\Domains\Scheduling\Actions\UpdateScheduleProfileAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\ScheduleProfile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public array $form = [];
    public array $weeklyRules = [];
    public array $cycleRules = [];
    public array $flexibleRules = [];
    public array $onCallRules = [];
    public array $filters = [];
    public bool $showFormPanel = false;
    public bool $confirmMethodChange = false;
    public ?int $editingProfileId = null;
    public ?int $viewingProfileId = null;
    public ?string $originalProfileType = null;
    public ?string $originalPatternMode = null;

    private const DAY_NAMES = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    public function mount(): void
    {
        $this->form = $this->emptyForm();
        $this->weeklyRules = $this->defaultWeeklyRules();
        $this->cycleRules = $this->defaultCycleRules();
        $this->flexibleRules = $this->defaultFlexibleRules();
        $this->onCallRules = $this->defaultOnCallRules();
        $this->filters = ['search' => '', 'operating_model' => 'all', 'status' => 'active'];
    }

    public function updated($property): void
    {
        if (str_starts_with((string) $property, 'filters.')) {
            $this->resetPage();
        }

        if ($property === 'form.profile_type' && ($this->form['profile_type'] ?? 'pattern') !== 'pattern') {
            $this->form['pattern_mode'] = null;
        }

        if ($property === 'form.profile_type' && ($this->form['profile_type'] ?? 'pattern') === 'pattern') {
            $this->form['pattern_mode'] = $this->form['pattern_mode'] ?: 'weekly';
        }

        if (str_contains((string) $property, '.day_type')) {
            $this->normalizeRuleRows();
        }
    }

    public function openCreatePanel(CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        Gate::authorize('create', [ScheduleProfile::class, $company]);

        $this->editingProfileId = null;
        $this->viewingProfileId = null;
        $this->originalProfileType = null;
        $this->originalPatternMode = null;
        $this->confirmMethodChange = false;
        $this->form = $this->emptyForm();
        $this->weeklyRules = $this->defaultWeeklyRules();
        $this->cycleRules = $this->defaultCycleRules();
        $this->flexibleRules = $this->defaultFlexibleRules();
        $this->onCallRules = $this->defaultOnCallRules();
        $this->showFormPanel = true;
    }

    public function loadEditForm(int $profileId, CurrentCompany $currentCompany): void
    {
        $profile = $this->authorizedProfile($profileId, $currentCompany, true);

        $this->editingProfileId = $profile->id;
        $this->viewingProfileId = null;
        $this->originalProfileType = $profile->profile_type;
        $this->originalPatternMode = $profile->pattern_mode;
        $this->confirmMethodChange = false;
        $this->form = [
            'code' => $profile->code,
            'name' => $profile->name,
            'description' => $profile->description ?? '',
            'profile_type' => $profile->profile_type,
            'pattern_mode' => $profile->pattern_mode,
            'status' => $profile->status,
        ];
        $this->weeklyRules = $this->isWeeklyPattern($profile)
            ? $profile->weeklyRules->map(fn ($rule) => [
                'day_of_week' => (int) $rule->day_of_week,
                'day_type' => $rule->day_type,
                'shift_template_id' => $rule->shift_template_id ? (string) $rule->shift_template_id : '',
            ])->values()->all()
            : $this->defaultWeeklyRules();
        $this->cycleRules = $this->isCyclePattern($profile)
            ? $profile->cycleRules->map(fn ($rule) => [
                'cycle_day' => (int) $rule->cycle_day,
                'day_type' => $rule->day_type,
                'shift_template_id' => $rule->shift_template_id ? (string) $rule->shift_template_id : '',
            ])->values()->all()
            : $this->defaultCycleRules();
        $this->flexibleRules = $profile->profile_type === 'flexible'
            ? $profile->flexibleRules->map(fn ($rule) => [
                'day_of_week' => (int) $rule->day_of_week,
                'day_type' => $rule->day_type,
                'required_minutes' => $rule->required_minutes ? (string) $rule->required_minutes : '',
                'uses_window' => filled($rule->window_start_local_time) && filled($rule->window_end_local_time),
                'window_start_local_time' => $this->formatTimeForInput($rule->window_start_local_time),
                'window_end_local_time' => $this->formatTimeForInput($rule->window_end_local_time),
                'window_start_day_offset' => (string) $rule->window_start_day_offset,
                'window_end_day_offset' => (string) $rule->window_end_day_offset,
            ])->values()->all()
            : $this->defaultFlexibleRules();
        $this->onCallRules = $profile->profile_type === 'on_call'
            ? $profile->onCallRules->map(fn ($rule) => [
                'day_of_week' => (int) $rule->day_of_week,
                'day_type' => $rule->day_type,
                'availability_start_local_time' => $this->formatTimeForInput($rule->availability_start_local_time),
                'availability_end_local_time' => $this->formatTimeForInput($rule->availability_end_local_time),
                'availability_start_day_offset' => (string) $rule->availability_start_day_offset,
                'availability_end_day_offset' => (string) $rule->availability_end_day_offset,
                'max_work_minutes' => $rule->max_work_minutes ? (string) $rule->max_work_minutes : '',
            ])->values()->all()
            : $this->defaultOnCallRules();
        $this->showFormPanel = true;
    }

    public function showDetail(int $profileId, CurrentCompany $currentCompany): void
    {
        $profile = $this->authorizedProfile($profileId, $currentCompany, false);
        $this->viewingProfileId = $profile->id;
    }

    public function closeDetail(): void
    {
        $this->viewingProfileId = null;
    }

    public function addCycleDay(): void
    {
        $this->cycleRules[] = [
            'cycle_day' => count($this->cycleRules) + 1,
            'day_type' => 'shift',
            'shift_template_id' => '',
        ];
        $this->renumberCycleRules();
    }

    public function removeCycleDay(int $index): void
    {
        unset($this->cycleRules[$index]);
        $this->cycleRules = array_values($this->cycleRules);
        $this->renumberCycleRules();
        $this->normalizeRuleRows();
    }

    public function moveCycleDay(int $index, string $direction): void
    {
        $target = $direction === 'up' ? $index - 1 : $index + 1;
        if (! isset($this->cycleRules[$index], $this->cycleRules[$target])) {
            return;
        }

        [$this->cycleRules[$index], $this->cycleRules[$target]] = [$this->cycleRules[$target], $this->cycleRules[$index]];
        $this->renumberCycleRules();
    }

    public function save(
        CurrentCompany $currentCompany,
        CreateScheduleProfileAction $createAction,
        UpdateScheduleProfileAction $updateAction,
        ReplaceScheduleProfileCycleRulesAction $replaceCycleRules,
        ReplaceScheduleProfileFlexibleRulesAction $replaceFlexibleRules,
        ReplaceScheduleProfileOnCallRulesAction $replaceOnCallRules,
    ): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $profile = $this->editingProfileId ? $this->authorizedProfile($this->editingProfileId, $currentCompany, true) : null;

        $profile ? Gate::authorize('update', $profile) : Gate::authorize('create', [ScheduleProfile::class, $company]);

        if (($this->form['profile_type'] ?? 'pattern') !== 'pattern') {
            $this->form['pattern_mode'] = null;
        }
        $this->normalizeRuleRows();
        $methodChanged = $this->editingProfileId !== null
            && ($this->originalProfileType !== ($this->form['profile_type'] ?? null)
                || $this->originalPatternMode !== ($this->form['pattern_mode'] ?? null));

        if ($methodChanged && ! $this->confirmMethodChange) {
            throw ValidationException::withMessages([
                'confirmMethodChange' => 'Confirma que deseas reemplazar la configuración del método anterior.',
            ]);
        }

        $weeklyRules = $this->formIsWeeklyPattern() ? $this->preparedWeeklyRules() : [];
        $cycleRules = $this->formIsCyclePattern() ? $this->preparedCycleRules() : [];
        $flexibleRules = $this->formIsFlexible() ? $this->preparedFlexibleRules() : [];
        $onCallRules = $this->formIsOnCall() ? $this->preparedOnCallRules() : [];
        $validated = $this->validate([
            'form.code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Za-z0-9][A-Za-z0-9_-]{1,49}$/',
                Rule::unique('schedule_profiles', 'code')->where('company_id', $company->id)->ignore($profile?->id),
            ],
            'form.name' => ['required', 'string', 'max:255'],
            'form.description' => ['nullable', 'string', 'max:2000'],
            'form.profile_type' => ['required', Rule::in(['pattern', 'calendar', 'flexible', 'on_call'])],
            'form.pattern_mode' => [Rule::requiredIf(($this->form['profile_type'] ?? 'pattern') === 'pattern'), 'nullable', Rule::in(['weekly', 'cycle'])],
            'form.status' => ['required', Rule::in(['active', 'inactive'])],
            'weeklyRules' => ['array', Rule::requiredIf($this->formIsWeeklyPattern()), 'size:7'],
            'weeklyRules.*.day_of_week' => ['required', 'integer', 'between:1,7'],
            'weeklyRules.*.day_type' => ['required', Rule::in(['shift', 'rest'])],
            'weeklyRules.*.shift_template_id' => ['nullable', 'integer'],
            'cycleRules' => ['array', Rule::requiredIf($this->formIsCyclePattern()), 'min:2'],
            'cycleRules.*.cycle_day' => ['required', 'integer', 'min:1'],
            'cycleRules.*.day_type' => ['required', Rule::in(['shift', 'rest'])],
            'cycleRules.*.shift_template_id' => ['nullable', 'integer'],
            'flexibleRules' => ['array', Rule::requiredIf($this->formIsFlexible()), 'size:7'],
            'flexibleRules.*.day_of_week' => ['required', 'integer', 'between:1,7'],
            'flexibleRules.*.day_type' => ['required', Rule::in(['work', 'rest'])],
            'flexibleRules.*.required_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'flexibleRules.*.uses_window' => ['boolean'],
            'flexibleRules.*.window_start_local_time' => ['nullable', 'date_format:H:i'],
            'flexibleRules.*.window_end_local_time' => ['nullable', 'date_format:H:i'],
            'flexibleRules.*.window_start_day_offset' => ['nullable', 'integer', Rule::in([0, 1, '0', '1'])],
            'flexibleRules.*.window_end_day_offset' => ['nullable', 'integer', Rule::in([0, 1, '0', '1'])],
            'onCallRules' => ['array', Rule::requiredIf($this->formIsOnCall()), 'size:7'],
            'onCallRules.*.day_of_week' => ['required', 'integer', 'between:1,7'],
            'onCallRules.*.day_type' => ['required', Rule::in(['on_call', 'rest'])],
            'onCallRules.*.availability_start_local_time' => ['nullable', 'date_format:H:i'],
            'onCallRules.*.availability_end_local_time' => ['nullable', 'date_format:H:i'],
            'onCallRules.*.availability_start_day_offset' => ['nullable', 'integer', Rule::in([0, 1, '0', '1'])],
            'onCallRules.*.availability_end_day_offset' => ['nullable', 'integer', Rule::in([0, 1, '0', '1'])],
            'onCallRules.*.max_work_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ]);

        try {
            $savedProfile = $profile
                ? $updateAction->handle($company, $profile, $validated['form'], $this->formIsWeeklyPattern() ? $weeklyRules : null)
                : $createAction->handle($company, $validated['form'], $this->formIsWeeklyPattern() ? $weeklyRules : []);

            if ($this->formIsCyclePattern()) {
                $replaceCycleRules->handle($company, $savedProfile, $cycleRules);
            }
            if ($this->formIsFlexible()) {
                $replaceFlexibleRules->handle($company, $savedProfile, $flexibleRules);
            }
            if ($this->formIsOnCall()) {
                $replaceOnCallRules->handle($company, $savedProfile, $onCallRules);
            }
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['profileRules' => $exception->getMessage()]);
        }

        $this->showFormPanel = false;
        $this->editingProfileId = null;
        $this->originalProfileType = null;
        $this->originalPatternMode = null;
        $this->confirmMethodChange = false;
        $this->form = $this->emptyForm();
        $this->weeklyRules = $this->defaultWeeklyRules();
        $this->cycleRules = $this->defaultCycleRules();
        $this->flexibleRules = $this->defaultFlexibleRules();
        $this->onCallRules = $this->defaultOnCallRules();
        $this->resetPage();

        Session::flash('status', $profile ? 'Modelo actualizado.' : 'Modelo creado.');
    }

    public function inactivate(int $profileId, CurrentCompany $currentCompany, InactivateScheduleProfileAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $profile = $this->authorizedProfile($profileId, $currentCompany, true);

        Gate::authorize('inactivate', $profile);

        try {
            $action->handle($company, $profile);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['profile' => $exception->getMessage()]);
        }

        Session::flash('status', 'Modelo inactivado.');
    }

    public function deleteProfile(int $profileId, CurrentCompany $currentCompany, DeleteScheduleProfileIfUnusedAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $profile = $this->authorizedProfile($profileId, $currentCompany, true);

        Gate::authorize('delete', $profile);

        try {
            $action->handle($company, $profile);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['profile' => $exception->getMessage()]);
        }

        if ($this->viewingProfileId === $profile->id) {
            $this->viewingProfileId = null;
        }

        $this->resetPage();
        Session::flash('status', 'Modelo eliminado.');
    }

    public function reactivate(int $profileId, CurrentCompany $currentCompany, ReactivateScheduleProfileAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $profile = $this->authorizedProfile($profileId, $currentCompany, true);

        Gate::authorize('reactivate', $profile);
        $action->handle($company, $profile);

        Session::flash('status', 'Modelo reactivado.');
    }

    public function closeFormPanel(): void
    {
        $this->showFormPanel = false;
        $this->editingProfileId = null;
        $this->resetValidation();
    }

    public function with(CurrentCompany $currentCompany): array
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        Gate::authorize('viewAny', [ScheduleProfile::class, $company]);

        $search = trim((string) ($this->filters['search'] ?? ''));
        $operatingModel = trim((string) ($this->filters['operating_model'] ?? 'all'));
        $status = trim((string) ($this->filters['status'] ?? 'active'));
        $canManage = Gate::allows('create', [ScheduleProfile::class, $company]);

        $relations = ['weeklyRules.shiftTemplate', 'cycleRules.shiftTemplate', 'flexibleRules', 'onCallRules'];

        $viewingProfile = $this->viewingProfileId
            ? $company->scheduleProfiles()->with($relations)->whereKey($this->viewingProfileId)->first()
            : null;

        if ($viewingProfile && ! Gate::allows('view', $viewingProfile)) {
            $viewingProfile = null;
            $this->viewingProfileId = null;
        }

        return [
            'profiles' => $company->scheduleProfiles()
                ->with($relations)
                ->when(! $canManage, fn ($query) => $query->where('status', 'active'))
                ->when($status !== 'all', fn ($query) => $query->where('status', $status))
                ->when($operatingModel !== 'all', function ($query) use ($operatingModel): void {
                    match ($operatingModel) {
                        'weekly' => $query->where('profile_type', 'pattern')->where('pattern_mode', 'weekly'),
                        'cycle' => $query->where('profile_type', 'pattern')->where('pattern_mode', 'cycle'),
                        'calendar' => $query->where('profile_type', 'calendar'),
                        'flexible' => $query->where('profile_type', 'flexible'),
                        'on_call' => $query->where('profile_type', 'on_call'),
                        default => null,
                    };
                })
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(fn ($searchQuery) => $searchQuery
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%"));
                })
                ->orderBy('name')
                ->paginate(10),
            'shiftTemplates' => $company->shiftTemplates()->where('status', 'active')->orderBy('name')->get(),
            'canManageProfiles' => $canManage,
            'dayNames' => self::DAY_NAMES,
            'weeklyPreview' => $this->weeklyPreview($company),
            'cyclePreview' => $this->cyclePreview($company),
            'flexiblePreview' => $this->flexiblePreview(),
            'onCallPreview' => $this->onCallPreview(),
            'viewingProfile' => $viewingProfile,
        ];
    }

    private function authorizedProfile(int $profileId, CurrentCompany $currentCompany, bool $forUpdate): ScheduleProfile
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $profile = $company->scheduleProfiles()->with(['weeklyRules.shiftTemplate', 'cycleRules.shiftTemplate', 'flexibleRules', 'onCallRules'])->whereKey($profileId)->first();
        abort_unless($profile, 403);

        Gate::authorize($forUpdate ? 'update' : 'view', $profile);

        return $profile;
    }

    private function preparedWeeklyRules(): array
    {
        $this->normalizeWeeklyRules();

        return collect($this->weeklyRules)->map(fn (array $rule) => [
            'day_of_week' => (int) $rule['day_of_week'],
            'day_type' => $rule['day_type'],
            'shift_template_id' => ($rule['day_type'] ?? 'shift') === 'shift' && filled($rule['shift_template_id'] ?? null)
                ? (int) $rule['shift_template_id']
                : null,
            'metadata' => [],
        ])->all();
    }

    private function preparedCycleRules(): array
    {
        $this->normalizeCycleRules();

        return collect($this->cycleRules)->map(fn (array $rule) => [
            'cycle_day' => (int) $rule['cycle_day'],
            'day_type' => $rule['day_type'],
            'shift_template_id' => ($rule['day_type'] ?? 'shift') === 'shift' && filled($rule['shift_template_id'] ?? null)
                ? (int) $rule['shift_template_id']
                : null,
            'metadata' => [],
        ])->all();
    }

    private function preparedFlexibleRules(): array
    {
        $this->normalizeFlexibleRules();

        return collect($this->flexibleRules)->map(fn (array $rule) => [
            'day_of_week' => (int) $rule['day_of_week'],
            'day_type' => $rule['day_type'],
            'required_minutes' => ($rule['day_type'] ?? 'work') === 'work' && filled($rule['required_minutes'] ?? null)
                ? (int) $rule['required_minutes']
                : null,
            'window_start_local_time' => ($rule['day_type'] ?? 'work') === 'work' && ($rule['uses_window'] ?? false)
                ? ($rule['window_start_local_time'] ?: null)
                : null,
            'window_end_local_time' => ($rule['day_type'] ?? 'work') === 'work' && ($rule['uses_window'] ?? false)
                ? ($rule['window_end_local_time'] ?: null)
                : null,
            'window_start_day_offset' => ($rule['day_type'] ?? 'work') === 'work' && ($rule['uses_window'] ?? false)
                ? (int) ($rule['window_start_day_offset'] ?? 0)
                : 0,
            'window_end_day_offset' => ($rule['day_type'] ?? 'work') === 'work' && ($rule['uses_window'] ?? false)
                ? (int) ($rule['window_end_day_offset'] ?? 0)
                : 0,
            'metadata' => [],
        ])->all();
    }

    private function preparedOnCallRules(): array
    {
        $this->normalizeOnCallRules();

        return collect($this->onCallRules)->map(fn (array $rule) => [
            'day_of_week' => (int) $rule['day_of_week'],
            'day_type' => $rule['day_type'],
            'availability_start_local_time' => ($rule['day_type'] ?? 'on_call') === 'on_call'
                ? ($rule['availability_start_local_time'] ?: null)
                : null,
            'availability_end_local_time' => ($rule['day_type'] ?? 'on_call') === 'on_call'
                ? ($rule['availability_end_local_time'] ?: null)
                : null,
            'availability_start_day_offset' => ($rule['day_type'] ?? 'on_call') === 'on_call'
                ? (int) ($rule['availability_start_day_offset'] ?? 0)
                : 0,
            'availability_end_day_offset' => ($rule['day_type'] ?? 'on_call') === 'on_call'
                ? (int) ($rule['availability_end_day_offset'] ?? 0)
                : 0,
            'max_work_minutes' => ($rule['day_type'] ?? 'on_call') === 'on_call' && filled($rule['max_work_minutes'] ?? null)
                ? (int) $rule['max_work_minutes']
                : null,
            'metadata' => [],
        ])->all();
    }

    private function normalizeRuleRows(): void
    {
        $this->normalizeWeeklyRules();
        $this->normalizeCycleRules();
        $this->normalizeFlexibleRules();
        $this->normalizeOnCallRules();
    }

    private function normalizeWeeklyRules(): void
    {
        foreach ($this->weeklyRules as $index => $rule) {
            if (($rule['day_type'] ?? 'shift') === 'rest') {
                $this->weeklyRules[$index]['shift_template_id'] = '';
            }
        }
    }

    private function normalizeCycleRules(): void
    {
        $this->renumberCycleRules();
        foreach ($this->cycleRules as $index => $rule) {
            if (($rule['day_type'] ?? 'shift') === 'rest') {
                $this->cycleRules[$index]['shift_template_id'] = '';
            }
        }
    }

    private function normalizeFlexibleRules(): void
    {
        foreach ($this->flexibleRules as $index => $rule) {
            if (($rule['day_type'] ?? 'work') === 'rest') {
                $this->flexibleRules[$index]['required_minutes'] = '';
                $this->flexibleRules[$index]['uses_window'] = false;
                $this->flexibleRules[$index]['window_start_local_time'] = '';
                $this->flexibleRules[$index]['window_end_local_time'] = '';
                $this->flexibleRules[$index]['window_start_day_offset'] = '0';
                $this->flexibleRules[$index]['window_end_day_offset'] = '0';
            }
            if (! ($this->flexibleRules[$index]['uses_window'] ?? false)) {
                $this->flexibleRules[$index]['window_start_local_time'] = '';
                $this->flexibleRules[$index]['window_end_local_time'] = '';
                $this->flexibleRules[$index]['window_start_day_offset'] = '0';
                $this->flexibleRules[$index]['window_end_day_offset'] = '0';
            }
        }
    }

    private function normalizeOnCallRules(): void
    {
        foreach ($this->onCallRules as $index => $rule) {
            if (($rule['day_type'] ?? 'on_call') === 'rest') {
                $this->onCallRules[$index]['availability_start_local_time'] = '';
                $this->onCallRules[$index]['availability_end_local_time'] = '';
                $this->onCallRules[$index]['availability_start_day_offset'] = '0';
                $this->onCallRules[$index]['availability_end_day_offset'] = '0';
                $this->onCallRules[$index]['max_work_minutes'] = '';
            }
        }
    }

    private function renumberCycleRules(): void
    {
        $this->cycleRules = array_values($this->cycleRules);
        foreach ($this->cycleRules as $index => $rule) {
            $this->cycleRules[$index]['cycle_day'] = $index + 1;
        }
    }

    private function weeklyPreview($company): array
    {
        $templates = $company->shiftTemplates()->where('status', 'active')->get()->keyBy('id');

        return collect($this->weeklyRules)->sortBy('day_of_week')->map(function (array $rule) use ($templates): array {
            $day = (int) ($rule['day_of_week'] ?? 0);
            $template = filled($rule['shift_template_id'] ?? null) ? $templates->get((int) $rule['shift_template_id']) : null;

            return [
                'day' => self::DAY_NAMES[$day] ?? 'Día',
                'value' => ($rule['day_type'] ?? 'shift') === 'rest'
                    ? 'Descanso'
                    : ($template ? "{$template->code} - {$template->name}" : 'Selecciona plantilla'),
            ];
        })->values()->all();
    }

    private function cyclePreview($company): array
    {
        $templates = $company->shiftTemplates()->where('status', 'active')->get()->keyBy('id');

        return collect($this->cycleRules)->sortBy('cycle_day')->map(function (array $rule) use ($templates): array {
            $template = filled($rule['shift_template_id'] ?? null) ? $templates->get((int) $rule['shift_template_id']) : null;

            return [
                'day' => 'Día '.(int) ($rule['cycle_day'] ?? 0),
                'value' => ($rule['day_type'] ?? 'shift') === 'rest'
                    ? 'Descanso'
                    : ($template ? "{$template->code} - {$template->name}" : 'Selecciona plantilla'),
            ];
        })->values()->all();
    }

    private function flexiblePreview(): array
    {
        return collect($this->flexibleRules)->sortBy('day_of_week')->map(function (array $rule): array {
            $day = (int) ($rule['day_of_week'] ?? 0);
            if (($rule['day_type'] ?? 'work') === 'rest') {
                return ['day' => self::DAY_NAMES[$day] ?? 'Día', 'value' => 'Descanso'];
            }

            $minutes = (int) ($rule['required_minutes'] ?? 0);
            $value = 'Trabajo esperado: '.$this->formatMinutes($minutes);
            if (($rule['uses_window'] ?? false) && filled($rule['window_start_local_time'] ?? null) && filled($rule['window_end_local_time'] ?? null)) {
                $value .= ' | Ventana '.$rule['window_start_local_time'].'-'.$rule['window_end_local_time'].$this->offsetSuffix((int) ($rule['window_end_day_offset'] ?? 0));
            }

            return ['day' => self::DAY_NAMES[$day] ?? 'Día', 'value' => $value];
        })->values()->all();
    }

    private function onCallPreview(): array
    {
        return collect($this->onCallRules)->sortBy('day_of_week')->map(function (array $rule): array {
            $day = (int) ($rule['day_of_week'] ?? 0);
            if (($rule['day_type'] ?? 'on_call') === 'rest') {
                return ['day' => self::DAY_NAMES[$day] ?? 'Día', 'value' => 'Descanso'];
            }

            $value = 'Disponible '.$rule['availability_start_local_time'].'-'.$rule['availability_end_local_time'].$this->offsetSuffix((int) ($rule['availability_end_day_offset'] ?? 0));
            $value .= ' | Máximo al activarse: '.$this->formatMinutes((int) ($rule['max_work_minutes'] ?? 0));

            return ['day' => self::DAY_NAMES[$day] ?? 'Día', 'value' => $value];
        })->values()->all();
    }

    private function currentCompanyOrFail(CurrentCompany $currentCompany)
    {
        $company = $currentCompany->get();
        abort_unless($company, 403);

        return $company;
    }

    private function profileTypeLabel(?ScheduleProfile $profile): string
    {
        if (! $profile) {
            return 'Sin modelo';
        }

        return match ($profile->profile_type) {
            'pattern' => $profile->pattern_mode === 'weekly'
                ? 'Horario fijo semanal'
                : 'Rol rotativo - ciclo de '.$profile->cycleRules->count().' días',
            'calendar' => 'Programación semanal manual',
            'flexible' => 'Flexible avanzado',
            'on_call' => 'Guardia avanzada',
            default => 'Tipo no reconocido',
        };
    }

    private function formOperatingModelSummary(): string
    {
        if ($this->formIsWeeklyPattern()) {
            return 'Horario fijo semanal: se captura la semana base y se repite automáticamente en cada semana nueva.';
        }

        if ($this->formIsCyclePattern()) {
            return 'Rol rotativo / ciclo: captura la secuencia completa; al aplicarlo, la fecha inicial será el Día 1.';
        }

        return match ($this->form['profile_type'] ?? 'pattern') {
            'calendar' => 'Programación semanal manual: deja días pendientes para armar la semana por demanda o CSV.',
            'flexible' => 'Flexible avanzado: define minutos esperados y ventana opcional, sin turno fijo.',
            'on_call' => 'Guardia avanzada: define disponibilidad; el tiempo real dependerá de activaciones futuras.',
            default => 'Selecciona la forma de operar.',
        };
    }

    private function rulesSummary(ScheduleProfile $profile): string
    {
        return match ($profile->profile_type) {
            'pattern' => $profile->pattern_mode === 'weekly'
                ? $profile->weeklyRules->count().' días semanales'
                : 'Ciclo de '.$profile->cycleRules->count().' días',
            'calendar' => 'Días pendientes por calendario',
            'flexible' => $profile->flexibleRules->where('day_type', 'work')->count().' días laborales',
            'on_call' => $profile->onCallRules->where('day_type', 'on_call')->count().' días disponibles',
            default => 'Sin reglas',
        };
    }

    private function profileDetailSubtitle(ScheduleProfile $profile): string
    {
        return match ($profile->profile_type) {
            'pattern' => $profile->pattern_mode === 'weekly' ? 'Se repite cada semana. Las excepciones se corrigen en el lote semanal, sin tocar la base.' : 'Ciclo rotativo que se repite desde una fecha de inicio. La asignación marca el Día 1.',
            'calendar' => 'No se repite automáticamente. Deja los días pendientes para capturarlos en la programación semanal.',
            'flexible' => 'Minutos requeridos y ventanas por día. No representa un turno fijo.',
            'on_call' => 'Disponibilidad o guardia avanzada; no cuenta automáticamente como tiempo trabajado.',
            default => 'Modelo de horario.',
        };
    }

    private function isWeeklyPattern(ScheduleProfile $profile): bool
    {
        return $profile->profile_type === 'pattern' && $profile->pattern_mode === 'weekly';
    }

    private function isCyclePattern(ScheduleProfile $profile): bool
    {
        return $profile->profile_type === 'pattern' && $profile->pattern_mode === 'cycle';
    }

    private function formIsWeeklyPattern(): bool
    {
        return ($this->form['profile_type'] ?? 'pattern') === 'pattern'
            && ($this->form['pattern_mode'] ?? 'weekly') === 'weekly';
    }

    private function formIsCyclePattern(): bool
    {
        return ($this->form['profile_type'] ?? 'pattern') === 'pattern'
            && ($this->form['pattern_mode'] ?? 'weekly') === 'cycle';
    }

    private function formIsFlexible(): bool
    {
        return ($this->form['profile_type'] ?? 'pattern') === 'flexible';
    }

    private function formIsOnCall(): bool
    {
        return ($this->form['profile_type'] ?? 'pattern') === 'on_call';
    }

    private function methodChanged(): bool
    {
        return $this->editingProfileId !== null
            && ($this->originalProfileType !== ($this->form['profile_type'] ?? null)
                || $this->originalPatternMode !== ($this->form['pattern_mode'] ?? null));
    }

    private function formatMinutes(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0 min';
        }

        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return trim(($hours > 0 ? $hours.' h ' : '').($remaining > 0 ? $remaining.' min' : ''));
    }

    private function offsetSuffix(int $offset): string
    {
        return $offset === 1 ? ' (+1 día)' : '';
    }

    private function formatTimeForInput(?string $time): string
    {
        return $time ? substr($time, 0, 5) : '';
    }

    private function emptyForm(): array
    {
        return ['code' => '', 'name' => '', 'description' => '', 'profile_type' => 'pattern', 'pattern_mode' => 'weekly', 'status' => 'active'];
    }

    private function defaultWeeklyRules(): array
    {
        return collect(self::DAY_NAMES)->map(fn (string $name, int $day) => [
            'day_of_week' => $day,
            'day_type' => $day <= 5 ? 'shift' : 'rest',
            'shift_template_id' => '',
        ])->values()->all();
    }

    private function defaultCycleRules(): array
    {
        return [
            ['cycle_day' => 1, 'day_type' => 'shift', 'shift_template_id' => ''],
            ['cycle_day' => 2, 'day_type' => 'rest', 'shift_template_id' => ''],
        ];
    }

    private function defaultFlexibleRules(): array
    {
        return collect(self::DAY_NAMES)->map(fn (string $name, int $day) => [
            'day_of_week' => $day,
            'day_type' => $day <= 5 ? 'work' : 'rest',
            'required_minutes' => $day <= 5 ? '480' : '',
            'uses_window' => false,
            'window_start_local_time' => '',
            'window_end_local_time' => '',
            'window_start_day_offset' => '0',
            'window_end_day_offset' => '0',
        ])->values()->all();
    }

    private function defaultOnCallRules(): array
    {
        return collect(self::DAY_NAMES)->map(fn (string $name, int $day) => [
            'day_of_week' => $day,
            'day_type' => 'on_call',
            'availability_start_local_time' => '06:00',
            'availability_end_local_time' => '22:00',
            'availability_start_day_offset' => '0',
            'availability_end_day_offset' => '0',
            'max_work_minutes' => '480',
        ])->values()->all();
    }
}; ?>

<section class="flex h-full w-full flex-1 flex-col gap-6 bg-surface-bg p-6 text-surface-text">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="font-display text-2xl font-bold text-brand-navy">Modelos de horario</h1>
            <p class="mt-1.5 text-[13.5px] text-surface-muted">Elige como opera la empresa: horario fijo semanal, rol rotativo o captura semanal por demanda.</p>
        </div>

        @if ($canManageProfiles)
            <button type="button" class="btn-primary" wire:click="openCreatePanel">
                <span class="text-base leading-none">+</span>
                Nuevo modelo
            </button>
        @endif
    </div>

    @if (session('status'))
        <div class="rounded-xl border border-status-rest-line bg-status-rest-bg px-4 py-3 text-sm font-medium text-status-rest-text">{{ session('status') }}</div>
    @endif

    @error('profile')
        <div class="rounded-xl border border-status-pending-line bg-status-pending-bg px-4 py-3 text-sm font-medium text-status-pending-text">{{ $message }}</div>
    @enderror

    <section class="rounded-2xl border border-surface-line bg-surface-card p-6 shadow-[0_20px_50px_-34px_rgba(2,25,57,0.22)]">
        <div class="grid gap-4 md:grid-cols-3">
        <flux:input label="Buscar" placeholder="Código o nombre" wire:model.live.debounce.350ms="filters.search" />
        <flux:select label="Camino" wire:model.live="filters.operating_model">
            <flux:select.option value="all">Todos</flux:select.option>
            <flux:select.option value="weekly">Horario fijo semanal</flux:select.option>
            <flux:select.option value="cycle">Rol rotativo / ciclo</flux:select.option>
            <flux:select.option value="calendar">Programación semanal manual</flux:select.option>
            <flux:select.option value="flexible">Flexible avanzado</flux:select.option>
            <flux:select.option value="on_call">Guardia avanzada</flux:select.option>
        </flux:select>
        <flux:select label="Estado" wire:model.live="filters.status">
            <flux:select.option value="active">Activos</flux:select.option>
            @if ($canManageProfiles)
                <flux:select.option value="inactive">Inactivos</flux:select.option>
                <flux:select.option value="all">Todos</flux:select.option>
            @endif
        </flux:select>
        </div>

        <div class="table-wrap mt-6">
        <table class="w-full min-w-[900px] border-collapse">
            <thead>
                <tr class="table-row">
                    <th class="table-head-cell">Modelo</th>
                    <th class="table-head-cell">Forma</th>
                    <th class="table-head-cell">Reglas</th>
                    <th class="table-head-cell">Estado</th>
                    <th class="table-head-cell text-right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($profiles as $profile)
                    <tr class="table-row">
                        <td class="table-cell">
                            <span class="block font-semibold text-brand-navy">{{ $profile->code }} - {{ $profile->name }}</span>
                            <span class="text-xs text-surface-muted">{{ $profile->description ?: 'Sin descripción' }}</span>
                        </td>
                        <td class="table-cell">{{ $this->profileTypeLabel($profile) }}</td>
                        <td class="table-cell">{{ $this->rulesSummary($profile) }}</td>
                        <td class="table-cell">
                            <span class="{{ $profile->status === 'active' ? 'badge-success' : 'badge-muted' }}"><span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ $profile->status === 'active' ? 'Activo' : 'Inactivo' }}</span>
                        </td>
                        <td class="table-cell">
                            <div class="flex justify-end gap-2">
                                <button type="button" class="btn-icon" wire:click="showDetail({{ $profile->id }})" aria-label="Ver modelo" title="Ver"><svg class="h-4 w-4 text-surface-muted" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" stroke="currentColor" stroke-width="1.8"/><path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" stroke="currentColor" stroke-width="1.8"/></svg></button>
                                @if ($canManageProfiles)
                                    <button type="button" class="btn-icon" wire:click="loadEditForm({{ $profile->id }})" aria-label="Editar modelo" title="Editar"><svg class="h-4 w-4 text-surface-muted" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                                    @if ($profile->status === 'active')
                                        <button type="button" class="btn-icon" wire:click="inactivate({{ $profile->id }})" wire:confirm="¿Inactivar este modelo?" aria-label="Inactivar modelo" title="Inactivar"><svg class="h-4 w-4 text-status-warn-text" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M18.36 5.64 5.64 18.36M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                                    @else
                                        <button type="button" class="btn-icon" wire:click="reactivate({{ $profile->id }})" aria-label="Reactivar modelo" title="Reactivar"><svg class="h-4 w-4 text-status-rest-text" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 6 9 17l-5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                                    @endif
                                    <button type="button" class="btn-icon" wire:click="deleteProfile({{ $profile->id }})" wire:confirm="Eliminar este modelo solo si no tiene uso? Esta acción no se puede deshacer." aria-label="Eliminar modelo" title="Eliminar"><svg class="h-4 w-4 text-status-pending-text" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6h16Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                                @else
                                    <span class="text-xs text-surface-muted">Solo consulta</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="table-row">
                        <td colspan="5" class="table-cell py-8 text-center text-surface-muted">No hay modelos con los filtros actuales.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </section>

    {{ $profiles->links() }}

    @if ($viewingProfile)
        <section class="rounded-2xl border border-surface-line bg-surface-card p-5 shadow-[0_20px_50px_-34px_rgba(2,25,57,0.22)]">
            <div class="mb-4 flex items-start justify-between gap-4">
                <div>
                    <flux:heading>{{ $viewingProfile->code }} - {{ $viewingProfile->name }}</flux:heading>
                    <flux:subheading>{{ $this->profileDetailSubtitle($viewingProfile) }}</flux:subheading>
                </div>
                <button type="button" class="btn-ghost btn-sm" wire:click="closeDetail">Cerrar</button>
            </div>

            @if ($this->isWeeklyPattern($viewingProfile))
                <div class="grid gap-2 text-sm md:grid-cols-2">
                    @foreach ($viewingProfile->weeklyRules as $rule)
                        <p><span class="font-medium">{{ $dayNames[$rule->day_of_week] }}</span>: {{ $rule->day_type === 'rest' ? 'Descanso' : $rule->shiftTemplate?->name }}</p>
                    @endforeach
                </div>
            @elseif ($this->isCyclePattern($viewingProfile))
                <div class="grid gap-2 text-sm md:grid-cols-2">
                    @foreach ($viewingProfile->cycleRules as $rule)
                        <p><span class="font-medium">Día {{ $rule->cycle_day }}</span>: {{ $rule->day_type === 'rest' ? 'Descanso' : $rule->shiftTemplate?->name }}</p>
                    @endforeach
                </div>
            @elseif ($viewingProfile->profile_type === 'flexible')
                <div class="grid gap-2 text-sm md:grid-cols-2">
                    @foreach ($viewingProfile->flexibleRules as $rule)
                        <p><span class="font-medium">{{ $dayNames[$rule->day_of_week] }}</span>: {{ $rule->day_type === 'rest' ? 'Descanso' : 'Trabajo esperado '.$this->formatMinutes((int) $rule->required_minutes) }}</p>
                    @endforeach
                </div>
            @elseif ($viewingProfile->profile_type === 'on_call')
                <div class="grid gap-2 text-sm md:grid-cols-2">
                    @foreach ($viewingProfile->onCallRules as $rule)
                        <p><span class="font-medium">{{ $dayNames[$rule->day_of_week] }}</span>: {{ $rule->day_type === 'rest' ? 'Descanso' : 'Disponible '.$this->formatTimeForInput($rule->availability_start_local_time).'-'.$this->formatTimeForInput($rule->availability_end_local_time).' | máximo '.$this->formatMinutes((int) $rule->max_work_minutes) }}</p>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-surface-muted">Este modelo se usa cuando la programación cambia por fecha. No se repite automáticamente; al generar el calendario, los días quedan pendientes hasta definirlos manualmente o mediante importación CSV.</p>
            @endif
        </section>
    @endif

    @if ($canManageProfiles)
        <x-side-panel wire:model="showFormPanel" maxWidth="max-w-5xl" title="{{ $editingProfileId ? 'Editar modelo de horario' : 'Nuevo modelo de horario' }}" subheading="Define si este horario se repite cada semana, rota por ciclo o se capturará desde la programación semanal.">
            <form wire:submit="save" class="space-y-6 p-6">
                <div class="grid gap-4 md:grid-cols-4">
                    <flux:input label="Código" wire:model="form.code" required />
                    <flux:input label="Nombre" wire:model="form.name" required />
                    <flux:select label="Forma de operar" wire:model.live="form.profile_type">
                        <flux:select.option value="pattern">Horario fijo o rol rotativo</flux:select.option>
                        <flux:select.option value="calendar">Programación semanal manual</flux:select.option>
                        <flux:select.option value="flexible">Horario flexible avanzado</flux:select.option>
                        <flux:select.option value="on_call">Guardia avanzada</flux:select.option>
                    </flux:select>
                    <flux:select label="Estado" wire:model="form.status">
                        <flux:select.option value="active">Activo</flux:select.option>
                        <flux:select.option value="inactive">Inactivo</flux:select.option>
                    </flux:select>
                </div>

                <flux:textarea label="Descripción" wire:model="form.description" rows="2" />

                <div class="rounded-xl border border-brand-blue/20 bg-brand-blue/5 px-4 py-3 text-sm text-brand-navy">
                    {{ $this->formOperatingModelSummary() }}
                </div>

                @if (($form['profile_type'] ?? 'pattern') === 'pattern')
                <div class="rounded-xl border border-brand-blue/20 bg-brand-blue/5 px-4 py-3 text-sm text-brand-navy">
                    Este modelo se reutiliza al generar nuevas semanas. Los horarios publicados conservan su versión y las excepciones se corrigen en el lote semanal.
                </div>
                    <flux:select label="Tipo de modelo" wire:model.live="form.pattern_mode">
                        <flux:select.option value="weekly">Horario fijo semanal</flux:select.option>
                        <flux:select.option value="cycle">Rol rotativo / ciclo</flux:select.option>
                    </flux:select>
                @endif

                @if ($this->methodChanged())
                    <div class="rounded-2xl border border-status-warn-line bg-status-warn-bg p-4 text-sm text-status-warn-text">
                        Cambiar la forma de operar reemplazará la configuración anterior de reglas de este modelo. Las asignaciones e históricos no se modifican.
                        <label class="mt-3 flex items-center gap-2">
                            <input type="checkbox" wire:model="confirmMethodChange" class="rounded border-zinc-300">
                            <span>Confirmo que deseo reemplazar la configuración del método anterior.</span>
                        </label>
                        @error('confirmMethodChange')
                            <p class="form-error mt-2">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                @error('profileRules')
                    <p class="form-error">{{ $message }}</p>
                @enderror

                @if ($this->formIsWeeklyPattern())
                    @error('weeklyRules')
                        <p class="form-error">{{ $message }}</p>
                    @enderror

                    <section class="space-y-4">
                        <flux:heading>Semana base</flux:heading>

                        <div class="grid gap-3">
                            @foreach ($weeklyRules as $index => $rule)
                                <div class="grid items-end gap-3 rounded-2xl border border-surface-line bg-white p-3 md:grid-cols-3">
                                    <div>
                                        <p class="text-sm font-medium">{{ $dayNames[$rule['day_of_week']] }}</p>
                                        <p class="text-xs text-surface-muted">Día ISO {{ $rule['day_of_week'] }}</p>
                                    </div>

                                    <flux:select label="Tipo de día" wire:model.live="weeklyRules.{{ $index }}.day_type">
                                        <flux:select.option value="shift">Turno</flux:select.option>
                                        <flux:select.option value="rest">Descanso</flux:select.option>
                                    </flux:select>

                                    <flux:select label="Plantilla de turno" wire:model="weeklyRules.{{ $index }}.shift_template_id" :disabled="$rule['day_type'] === 'rest'">
                                        <flux:select.option value="">Selecciona plantilla</flux:select.option>
                                        @foreach ($shiftTemplates as $template)
                                            <flux:select.option value="{{ $template->id }}">{{ $template->code }} - {{ $template->name }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                </div>
                            @endforeach
                        </div>

                        <div class="rounded-2xl border border-brand-blue/20 bg-brand-blue/5 p-4">
                            <flux:heading>Vista previa de semana base</flux:heading>
                            <div class="mt-3 grid gap-2 text-sm md:grid-cols-2">
                                @foreach ($weeklyPreview as $line)
                                    <p><span class="font-medium">{{ $line['day'] }}</span>: {{ $line['value'] }}</p>
                                @endforeach
                            </div>
                        </div>
                    </section>
                @elseif ($this->formIsCyclePattern())
                    @error('cycleRules')
                        <p class="form-error">{{ $message }}</p>
                    @enderror

                    <section class="space-y-4">
                        <div class="rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-100">
                            La fecha inicial de la asignación representa el Día 1 del ciclo. Longitud actual: <span class="font-medium">{{ count($cycleRules) }} días</span>.
                        </div>

                        <div class="flex items-center justify-between">
                            <flux:heading>Rol rotativo / ciclo</flux:heading>
                            <button type="button" class="btn-ghost btn-sm" wire:click="addCycleDay">Agregar día</button>
                        </div>

                        <div class="grid gap-3">
                            @foreach ($cycleRules as $index => $rule)
                                <div class="grid items-end gap-3 rounded-2xl border border-surface-line bg-white p-3 md:grid-cols-[1fr_1fr_2fr_auto]">
                                    <div>
                                        <p class="text-sm font-medium">Día {{ $rule['cycle_day'] }}</p>
                                        <p class="text-xs text-surface-muted">Numeración automática</p>
                                    </div>

                                    <flux:select label="Tipo" wire:model.live="cycleRules.{{ $index }}.day_type">
                                        <flux:select.option value="shift">Turno</flux:select.option>
                                        <flux:select.option value="rest">Descanso</flux:select.option>
                                    </flux:select>

                                    <flux:select label="Plantilla de turno" wire:model="cycleRules.{{ $index }}.shift_template_id" :disabled="$rule['day_type'] === 'rest'">
                                        <flux:select.option value="">Selecciona plantilla</flux:select.option>
                                        @foreach ($shiftTemplates as $template)
                                            <flux:select.option value="{{ $template->id }}">{{ $template->code }} - {{ $template->name }}</flux:select.option>
                                        @endforeach
                                    </flux:select>

                                    <div class="flex gap-1">
                                        <button type="button" class="btn-ghost btn-sm" wire:click="moveCycleDay({{ $index }}, 'up')" @disabled($index === 0)>Subir</button>
                                        <button type="button" class="btn-ghost btn-sm" wire:click="moveCycleDay({{ $index }}, 'down')" @disabled($index === count($cycleRules) - 1)>Bajar</button>
                                        <button type="button" class="btn-danger btn-sm" wire:click="removeCycleDay({{ $index }})" @disabled(count($cycleRules) <= 2)>Quitar</button>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="rounded-2xl border border-brand-blue/20 bg-brand-blue/5 p-4">
                            <flux:heading>Vista previa del ciclo</flux:heading>
                            <div class="mt-3 grid gap-2 text-sm md:grid-cols-2">
                                @foreach ($cyclePreview as $line)
                                    <p><span class="font-medium">{{ $line['day'] }}</span>: {{ $line['value'] }}</p>
                                @endforeach
                            </div>
                        </div>
                    </section>
                @elseif ($this->formIsFlexible())
                    <section class="space-y-4">
                        <div class="rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-100">
                            <p>Los minutos requeridos indican el trabajo esperado.</p>
                            <p>La ventana indica el periodo permitido para iniciar o realizar la jornada; no representa una hora fija.</p>
                        </div>

                        <flux:heading>Reglas flexibles</flux:heading>
                        <div class="grid gap-3">
                            @foreach ($flexibleRules as $index => $rule)
                                <div class="grid items-end gap-3 rounded-2xl border border-surface-line bg-white p-3 md:grid-cols-4">
                                    <div>
                                        <p class="text-sm font-medium">{{ $dayNames[$rule['day_of_week']] }}</p>
                                        <p class="text-xs text-surface-muted">{{ filled($rule['required_minutes'] ?? null) ? $this->formatMinutes((int) $rule['required_minutes']) : 'Sin minutos' }}</p>
                                    </div>

                                    <flux:select label="Tipo" wire:model.live="flexibleRules.{{ $index }}.day_type">
                                        <flux:select.option value="work">Trabajo</flux:select.option>
                                        <flux:select.option value="rest">Descanso</flux:select.option>
                                    </flux:select>

                                    @if (($rule['day_type'] ?? 'work') === 'work')
                                        <flux:input label="Minutos requeridos" type="number" min="1" max="1440" wire:model="flexibleRules.{{ $index }}.required_minutes" />
                                        <label class="flex items-center gap-2 text-sm">
                                            <input type="checkbox" wire:model.live="flexibleRules.{{ $index }}.uses_window" class="rounded border-zinc-300">
                                            <span>Usar ventana</span>
                                        </label>
                                    @else
                                        <div class="text-sm text-surface-muted">Descanso sin configuración.</div>
                                        <div></div>
                                    @endif

                                    @if (($rule['day_type'] ?? 'work') === 'work' && ($rule['uses_window'] ?? false))
                                        <flux:input label="Inicio de ventana" type="time" wire:model="flexibleRules.{{ $index }}.window_start_local_time" />
                                        <flux:select label="Día inicial" wire:model="flexibleRules.{{ $index }}.window_start_day_offset">
                                            <flux:select.option value="0">Mismo día</flux:select.option>
                                            <flux:select.option value="1">Día siguiente</flux:select.option>
                                        </flux:select>
                                        <flux:input label="Fin de ventana" type="time" wire:model="flexibleRules.{{ $index }}.window_end_local_time" />
                                        <flux:select label="Día final" wire:model="flexibleRules.{{ $index }}.window_end_day_offset">
                                            <flux:select.option value="0">Mismo día</flux:select.option>
                                            <flux:select.option value="1">Día siguiente</flux:select.option>
                                        </flux:select>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <div class="rounded-2xl border border-brand-blue/20 bg-brand-blue/5 p-4">
                            <flux:heading>Vista previa flexible</flux:heading>
                            <div class="mt-3 grid gap-2 text-sm md:grid-cols-2">
                                @foreach ($flexiblePreview as $line)
                                    <p><span class="font-medium">{{ $line['day'] }}</span>: {{ $line['value'] }}</p>
                                @endforeach
                            </div>
                        </div>
                    </section>
                @elseif ($this->formIsOnCall())
                    <section class="space-y-4">
                        <div class="rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-100">
                            La disponibilidad no se contabiliza automáticamente como tiempo trabajado. El trabajo comenzará únicamente cuando exista una activación.
                        </div>

                        <flux:heading>Reglas bajo demanda</flux:heading>
                        <div class="grid gap-3">
                            @foreach ($onCallRules as $index => $rule)
                                <div class="grid items-end gap-3 rounded-2xl border border-surface-line bg-white p-3 md:grid-cols-4">
                                    <div>
                                        <p class="text-sm font-medium">{{ $dayNames[$rule['day_of_week']] }}</p>
                                        <p class="text-xs text-surface-muted">{{ ($rule['day_type'] ?? 'on_call') === 'rest' ? 'Descanso' : 'Disponible' }}</p>
                                    </div>

                                    <flux:select label="Tipo" wire:model.live="onCallRules.{{ $index }}.day_type">
                                        <flux:select.option value="on_call">Disponible</flux:select.option>
                                        <flux:select.option value="rest">Descanso</flux:select.option>
                                    </flux:select>

                                    @if (($rule['day_type'] ?? 'on_call') === 'on_call')
                                        <flux:input label="Inicio de disponibilidad" type="time" wire:model="onCallRules.{{ $index }}.availability_start_local_time" />
                                        <flux:select label="Día inicial" wire:model="onCallRules.{{ $index }}.availability_start_day_offset">
                                            <flux:select.option value="0">Mismo día</flux:select.option>
                                            <flux:select.option value="1">Día siguiente</flux:select.option>
                                        </flux:select>
                                        <flux:input label="Fin de disponibilidad" type="time" wire:model="onCallRules.{{ $index }}.availability_end_local_time" />
                                        <flux:select label="Día final" wire:model="onCallRules.{{ $index }}.availability_end_day_offset">
                                            <flux:select.option value="0">Mismo día</flux:select.option>
                                            <flux:select.option value="1">Día siguiente</flux:select.option>
                                        </flux:select>
                                        <flux:input label="Máximo al activarse" type="number" min="1" max="1440" wire:model="onCallRules.{{ $index }}.max_work_minutes" />
                                    @else
                                        <div class="text-sm text-surface-muted">Descanso sin disponibilidad.</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <div class="rounded-2xl border border-brand-blue/20 bg-brand-blue/5 p-4">
                            <flux:heading>Vista previa bajo demanda</flux:heading>
                            <div class="mt-3 grid gap-2 text-sm md:grid-cols-2">
                                @foreach ($onCallPreview as $line)
                                    <p><span class="font-medium">{{ $line['day'] }}</span>: {{ $line['value'] }}</p>
                                @endforeach
                            </div>
                        </div>
                    </section>
                @else
                    <div class="rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-100">
                        Este modelo se usa cuando el horario cambia por demanda. No se repite automáticamente; al generar el calendario, los días quedan pendientes hasta definirlos en la programación semanal o mediante importación CSV.
                    </div>
                @endif

                <div class="flex justify-end gap-3">
                    <button type="button" class="btn-ghost" wire:click="closeFormPanel">Cancelar</button>
                    <button type="submit" class="btn-primary">Guardar</button>
                </div>
            </form>
        </x-side-panel>
    @endif
</section>
