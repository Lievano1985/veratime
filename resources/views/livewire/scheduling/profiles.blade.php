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
        3 => 'Miercoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sabado',
        7 => 'Domingo',
    ];

    public function mount(): void
    {
        $this->form = $this->emptyForm();
        $this->weeklyRules = $this->defaultWeeklyRules();
        $this->cycleRules = $this->defaultCycleRules();
        $this->flexibleRules = $this->defaultFlexibleRules();
        $this->onCallRules = $this->defaultOnCallRules();
        $this->filters = ['search' => '', 'profile_type' => 'all', 'status' => 'active'];
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
                'confirmMethodChange' => 'Confirma que deseas reemplazar la configuracion del metodo anterior.',
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

        Session::flash('status', $profile ? 'Perfil actualizado.' : 'Perfil creado.');
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

        Session::flash('status', 'Perfil inactivado.');
    }

    public function delete(int $profileId, CurrentCompany $currentCompany, DeleteScheduleProfileIfUnusedAction $action): void
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
        Session::flash('status', 'Perfil eliminado.');
    }

    public function reactivate(int $profileId, CurrentCompany $currentCompany, ReactivateScheduleProfileAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $profile = $this->authorizedProfile($profileId, $currentCompany, true);

        Gate::authorize('reactivate', $profile);
        $action->handle($company, $profile);

        Session::flash('status', 'Perfil reactivado.');
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
        $type = trim((string) ($this->filters['profile_type'] ?? 'all'));
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
                ->when($type !== 'all', fn ($query) => $query->where('profile_type', $type))
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
                'day' => self::DAY_NAMES[$day] ?? 'Dia',
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
                'day' => 'Dia '.(int) ($rule['cycle_day'] ?? 0),
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
                return ['day' => self::DAY_NAMES[$day] ?? 'Dia', 'value' => 'Descanso'];
            }

            $minutes = (int) ($rule['required_minutes'] ?? 0);
            $value = 'Trabajo esperado: '.$this->formatMinutes($minutes);
            if (($rule['uses_window'] ?? false) && filled($rule['window_start_local_time'] ?? null) && filled($rule['window_end_local_time'] ?? null)) {
                $value .= ' | Ventana '.$rule['window_start_local_time'].'-'.$rule['window_end_local_time'].$this->offsetSuffix((int) ($rule['window_end_day_offset'] ?? 0));
            }

            return ['day' => self::DAY_NAMES[$day] ?? 'Dia', 'value' => $value];
        })->values()->all();
    }

    private function onCallPreview(): array
    {
        return collect($this->onCallRules)->sortBy('day_of_week')->map(function (array $rule): array {
            $day = (int) ($rule['day_of_week'] ?? 0);
            if (($rule['day_type'] ?? 'on_call') === 'rest') {
                return ['day' => self::DAY_NAMES[$day] ?? 'Dia', 'value' => 'Descanso'];
            }

            $value = 'Disponible '.$rule['availability_start_local_time'].'-'.$rule['availability_end_local_time'].$this->offsetSuffix((int) ($rule['availability_end_day_offset'] ?? 0));
            $value .= ' | Maximo al activarse: '.$this->formatMinutes((int) ($rule['max_work_minutes'] ?? 0));

            return ['day' => self::DAY_NAMES[$day] ?? 'Dia', 'value' => $value];
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
            return 'Sin perfil';
        }

        return match ($profile->profile_type) {
            'pattern' => $profile->pattern_mode === 'weekly'
                ? 'Por patron semanal'
                : 'Por patron - ciclo de '.$profile->cycleRules->count().' dias',
            'calendar' => 'Por calendario',
            'flexible' => 'Flexible',
            'on_call' => 'Bajo demanda',
            default => 'Tipo no reconocido',
        };
    }

    private function rulesSummary(ScheduleProfile $profile): string
    {
        return match ($profile->profile_type) {
            'pattern' => $profile->pattern_mode === 'weekly'
                ? $profile->weeklyRules->count().' dias semanales'
                : 'Ciclo de '.$profile->cycleRules->count().' dias',
            'calendar' => 'Dias pendientes por calendario',
            'flexible' => $profile->flexibleRules->where('day_type', 'work')->count().' dias laborales',
            'on_call' => $profile->onCallRules->where('day_type', 'on_call')->count().' dias disponibles',
            default => 'Sin reglas',
        };
    }

    private function profileDetailSubtitle(ScheduleProfile $profile): string
    {
        return match ($profile->profile_type) {
            'pattern' => $profile->pattern_mode === 'weekly' ? 'Reglas semanales reutilizables. Al generar programacion diaria para otra semana, estas reglas se aplican nuevamente segun la fecha.' : 'Ciclo repetitivo. La fecha inicial de asignacion representa el Dia 1.',
            'calendar' => 'Perfil por calendario: no se repite automaticamente; deja los dias pendientes para definirlos por fecha.',
            'flexible' => 'Minutos requeridos y ventanas por dia. No representa un turno fijo.',
            'on_call' => 'Disponibilidad bajo demanda; no cuenta automaticamente como tiempo trabajado.',
            default => 'Perfil de horario.',
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
        return $offset === 1 ? ' (+1 dia)' : '';
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

<section class="flex h-full w-full flex-1 flex-col gap-6 p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">Perfiles de horario</flux:heading>
            <flux:subheading>Configura reglas reutilizables. Un perfil por patron se repite cada semana al generar nuevos periodos de programacion diaria.</flux:subheading>
        </div>

        @if ($canManageProfiles)
            <flux:button wire:click="openCreatePanel" icon="plus" variant="primary">Nuevo perfil</flux:button>
        @endif
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">{{ session('status') }}</div>
    @endif

    @error('profile')
        <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">{{ $message }}</div>
    @enderror

    <div class="grid gap-4 rounded-md border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-900/60 md:grid-cols-3">
        <flux:input label="Buscar" placeholder="Codigo o nombre" wire:model.live.debounce.350ms="filters.search" />
        <flux:select label="Tipo" wire:model.live="filters.profile_type">
            <flux:select.option value="all">Todos</flux:select.option>
            <flux:select.option value="pattern">Por patron</flux:select.option>
            <flux:select.option value="calendar">Por calendario</flux:select.option>
            <flux:select.option value="flexible">Flexible</flux:select.option>
            <flux:select.option value="on_call">Bajo demanda</flux:select.option>
        </flux:select>
        <flux:select label="Estado" wire:model.live="filters.status">
            <flux:select.option value="active">Activos</flux:select.option>
            @if ($canManageProfiles)
                <flux:select.option value="inactive">Inactivos</flux:select.option>
                <flux:select.option value="all">Todos</flux:select.option>
            @endif
        </flux:select>
    </div>

    <div class="overflow-hidden rounded-md border border-zinc-200 dark:border-zinc-700">
        <table class="w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
            <thead class="bg-zinc-50 text-left text-xs font-medium uppercase text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                <tr>
                    <th class="px-4 py-3">Perfil</th>
                    <th class="px-4 py-3">Tipo</th>
                    <th class="px-4 py-3">Reglas</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 [&>tr:nth-child(odd)]:bg-white [&>tr:nth-child(even)]:bg-zinc-50/60 dark:divide-zinc-700 dark:[&>tr:nth-child(odd)]:bg-zinc-900 dark:[&>tr:nth-child(even)]:bg-zinc-800/40">
                @forelse ($profiles as $profile)
                    <tr>
                        <td class="px-4 py-3">
                            <span class="block font-medium text-zinc-900 dark:text-zinc-100">{{ $profile->code }} - {{ $profile->name }}</span>
                            <span class="text-xs text-zinc-500">{{ $profile->description ?: 'Sin descripcion' }}</span>
                        </td>
                        <td class="px-4 py-3">{{ $this->profileTypeLabel($profile) }}</td>
                        <td class="px-4 py-3">{{ $this->rulesSummary($profile) }}</td>
                        <td class="px-4 py-3">
                            <x-ui.badge variant="{{ $profile->status === 'active' ? 'success' : 'neutral' }}">
                                {{ $profile->status === 'active' ? 'Activo' : 'Inactivo' }}
                            </x-ui.badge>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <flux:button size="xs" variant="ghost" wire:click="showDetail({{ $profile->id }})">Ver</flux:button>
                                @if ($canManageProfiles)
                                    <flux:button size="xs" variant="ghost" wire:click="loadEditForm({{ $profile->id }})">Editar</flux:button>
                                    @if ($profile->status === 'active')
                                        <flux:button size="xs" variant="danger" wire:click="inactivate({{ $profile->id }})" wire:confirm="Inactivar este perfil?">Inactivar</flux:button>
                                    @else
                                        <flux:button size="xs" variant="primary" wire:click="reactivate({{ $profile->id }})">Reactivar</flux:button>
                                    @endif
                                    <flux:button size="xs" variant="danger" wire:click="delete({{ $profile->id }})" wire:confirm="Eliminar este perfil solo si no tiene uso? Esta accion no se puede deshacer.">Eliminar</flux:button>
                                @else
                                    <span class="text-xs text-zinc-500">Solo consulta</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-zinc-500">No hay perfiles con los filtros actuales.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $profiles->links() }}

    @if ($viewingProfile)
        <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="mb-4 flex items-start justify-between gap-4">
                <div>
                    <flux:heading>{{ $viewingProfile->code }} - {{ $viewingProfile->name }}</flux:heading>
                    <flux:subheading>{{ $this->profileDetailSubtitle($viewingProfile) }}</flux:subheading>
                </div>
                <flux:button size="sm" variant="ghost" wire:click="closeDetail">Cerrar</flux:button>
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
                        <p><span class="font-medium">Dia {{ $rule->cycle_day }}</span>: {{ $rule->day_type === 'rest' ? 'Descanso' : $rule->shiftTemplate?->name }}</p>
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
                        <p><span class="font-medium">{{ $dayNames[$rule->day_of_week] }}</span>: {{ $rule->day_type === 'rest' ? 'Descanso' : 'Disponible '.$this->formatTimeForInput($rule->availability_start_local_time).'-'.$this->formatTimeForInput($rule->availability_end_local_time).' | maximo '.$this->formatMinutes((int) $rule->max_work_minutes) }}</p>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-zinc-600 dark:text-zinc-300">Este perfil se usa cuando la programacion cambia por fecha. No se repite automaticamente; al generar el calendario, los dias quedan pendientes hasta definirlos manualmente o mediante importacion CSV.</p>
            @endif
        </section>
    @endif

    @if ($canManageProfiles)
        <x-side-panel wire:model="showFormPanel" maxWidth="max-w-5xl" title="{{ $editingProfileId ? 'Editar perfil de horario' : 'Nuevo perfil de horario' }}" subheading="Define la regla que se usara al generar la programacion diaria de cada periodo.">
            <form wire:submit="save" class="space-y-6 p-6">
                <div class="grid gap-4 md:grid-cols-4">
                    <flux:input label="Codigo" wire:model="form.code" required />
                    <flux:input label="Nombre" wire:model="form.name" required />
                    <flux:select label="Metodo" wire:model.live="form.profile_type">
                        <flux:select.option value="pattern">Por patron</flux:select.option>
                        <flux:select.option value="calendar">Por calendario</flux:select.option>
                        <flux:select.option value="flexible">Flexible</flux:select.option>
                        <flux:select.option value="on_call">Bajo demanda</flux:select.option>
                    </flux:select>
                    <flux:select label="Estado" wire:model="form.status">
                        <flux:select.option value="active">Activo</flux:select.option>
                        <flux:select.option value="inactive">Inactivo</flux:select.option>
                    </flux:select>
                </div>

                <flux:textarea label="Descripcion" wire:model="form.description" rows="2" />

                @if (($form['profile_type'] ?? 'pattern') === 'pattern')
                <div class="rounded-md border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-100">
                    Este patron se reutiliza al generar programacion diaria para nuevas semanas. Si cambias la regla, solo afectara los lotes futuros que vuelvas a generar; los lotes ya publicados conservan su version.
                </div>
                    <flux:select label="Modalidad del patron" wire:model.live="form.pattern_mode">
                        <flux:select.option value="weekly">Patron semanal</flux:select.option>
                        <flux:select.option value="cycle">Ciclo repetitivo</flux:select.option>
                    </flux:select>
                @endif

                @if ($this->methodChanged())
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100">
                        Cambiar el metodo reemplazara la configuracion anterior de reglas de este perfil. Las asignaciones e historicos no se modifican.
                        <label class="mt-3 flex items-center gap-2">
                            <input type="checkbox" wire:model="confirmMethodChange" class="rounded border-zinc-300">
                            <span>Confirmo que deseo reemplazar la configuracion del metodo anterior.</span>
                        </label>
                        @error('confirmMethodChange')
                            <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                @error('profileRules')
                    <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror

                @if ($this->formIsWeeklyPattern())
                    @error('weeklyRules')
                        <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror

                    <section class="space-y-4">
                        <flux:heading>Reglas semanales</flux:heading>

                        <div class="grid gap-3">
                            @foreach ($weeklyRules as $index => $rule)
                                <div class="grid items-end gap-3 rounded-md border border-zinc-200 p-3 dark:border-zinc-700 md:grid-cols-3">
                                    <div>
                                        <p class="text-sm font-medium">{{ $dayNames[$rule['day_of_week']] }}</p>
                                        <p class="text-xs text-zinc-500">Dia ISO {{ $rule['day_of_week'] }}</p>
                                    </div>

                                    <flux:select label="Tipo de dia" wire:model.live="weeklyRules.{{ $index }}.day_type">
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

                        <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800">
                            <flux:heading>Vista previa semanal</flux:heading>
                            <div class="mt-3 grid gap-2 text-sm md:grid-cols-2">
                                @foreach ($weeklyPreview as $line)
                                    <p><span class="font-medium">{{ $line['day'] }}</span>: {{ $line['value'] }}</p>
                                @endforeach
                            </div>
                        </div>
                    </section>
                @elseif ($this->formIsCyclePattern())
                    @error('cycleRules')
                        <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror

                    <section class="space-y-4">
                        <div class="rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-100">
                            La fecha inicial de la asignacion representa el Dia 1 del ciclo. Longitud actual: <span class="font-medium">{{ count($cycleRules) }} dias</span>.
                        </div>

                        <div class="flex items-center justify-between">
                            <flux:heading>Ciclo repetitivo</flux:heading>
                            <flux:button type="button" size="sm" variant="ghost" wire:click="addCycleDay">Agregar dia</flux:button>
                        </div>

                        <div class="grid gap-3">
                            @foreach ($cycleRules as $index => $rule)
                                <div class="grid items-end gap-3 rounded-md border border-zinc-200 p-3 dark:border-zinc-700 md:grid-cols-[1fr_1fr_2fr_auto]">
                                    <div>
                                        <p class="text-sm font-medium">Dia {{ $rule['cycle_day'] }}</p>
                                        <p class="text-xs text-zinc-500">Numeracion automatica</p>
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
                                        <flux:button type="button" size="xs" variant="ghost" wire:click="moveCycleDay({{ $index }}, 'up')" :disabled="$index === 0">Subir</flux:button>
                                        <flux:button type="button" size="xs" variant="ghost" wire:click="moveCycleDay({{ $index }}, 'down')" :disabled="$index === count($cycleRules) - 1">Bajar</flux:button>
                                        <flux:button type="button" size="xs" variant="danger" wire:click="removeCycleDay({{ $index }})" :disabled="count($cycleRules) <= 2">Quitar</flux:button>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800">
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
                                <div class="grid items-end gap-3 rounded-md border border-zinc-200 p-3 dark:border-zinc-700 md:grid-cols-4">
                                    <div>
                                        <p class="text-sm font-medium">{{ $dayNames[$rule['day_of_week']] }}</p>
                                        <p class="text-xs text-zinc-500">{{ filled($rule['required_minutes'] ?? null) ? $this->formatMinutes((int) $rule['required_minutes']) : 'Sin minutos' }}</p>
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
                                        <div class="text-sm text-zinc-500">Descanso sin configuracion.</div>
                                        <div></div>
                                    @endif

                                    @if (($rule['day_type'] ?? 'work') === 'work' && ($rule['uses_window'] ?? false))
                                        <flux:input label="Inicio de ventana" type="time" wire:model="flexibleRules.{{ $index }}.window_start_local_time" />
                                        <flux:select label="Dia inicial" wire:model="flexibleRules.{{ $index }}.window_start_day_offset">
                                            <flux:select.option value="0">Mismo dia</flux:select.option>
                                            <flux:select.option value="1">Dia siguiente</flux:select.option>
                                        </flux:select>
                                        <flux:input label="Fin de ventana" type="time" wire:model="flexibleRules.{{ $index }}.window_end_local_time" />
                                        <flux:select label="Dia final" wire:model="flexibleRules.{{ $index }}.window_end_day_offset">
                                            <flux:select.option value="0">Mismo dia</flux:select.option>
                                            <flux:select.option value="1">Dia siguiente</flux:select.option>
                                        </flux:select>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800">
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
                            La disponibilidad no se contabiliza automaticamente como tiempo trabajado. El trabajo comenzara unicamente cuando exista una activacion.
                        </div>

                        <flux:heading>Reglas bajo demanda</flux:heading>
                        <div class="grid gap-3">
                            @foreach ($onCallRules as $index => $rule)
                                <div class="grid items-end gap-3 rounded-md border border-zinc-200 p-3 dark:border-zinc-700 md:grid-cols-4">
                                    <div>
                                        <p class="text-sm font-medium">{{ $dayNames[$rule['day_of_week']] }}</p>
                                        <p class="text-xs text-zinc-500">{{ ($rule['day_type'] ?? 'on_call') === 'rest' ? 'Descanso' : 'Disponible' }}</p>
                                    </div>

                                    <flux:select label="Tipo" wire:model.live="onCallRules.{{ $index }}.day_type">
                                        <flux:select.option value="on_call">Disponible</flux:select.option>
                                        <flux:select.option value="rest">Descanso</flux:select.option>
                                    </flux:select>

                                    @if (($rule['day_type'] ?? 'on_call') === 'on_call')
                                        <flux:input label="Inicio de disponibilidad" type="time" wire:model="onCallRules.{{ $index }}.availability_start_local_time" />
                                        <flux:select label="Dia inicial" wire:model="onCallRules.{{ $index }}.availability_start_day_offset">
                                            <flux:select.option value="0">Mismo dia</flux:select.option>
                                            <flux:select.option value="1">Dia siguiente</flux:select.option>
                                        </flux:select>
                                        <flux:input label="Fin de disponibilidad" type="time" wire:model="onCallRules.{{ $index }}.availability_end_local_time" />
                                        <flux:select label="Dia final" wire:model="onCallRules.{{ $index }}.availability_end_day_offset">
                                            <flux:select.option value="0">Mismo dia</flux:select.option>
                                            <flux:select.option value="1">Dia siguiente</flux:select.option>
                                        </flux:select>
                                        <flux:input label="Maximo al activarse" type="number" min="1" max="1440" wire:model="onCallRules.{{ $index }}.max_work_minutes" />
                                    @else
                                        <div class="text-sm text-zinc-500">Descanso sin disponibilidad.</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800">
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
                        Este perfil se usa cuando la programacion cambia por fecha. No se repite automaticamente; al generar el calendario, los dias quedan pendientes hasta definirlos manualmente o mediante importacion CSV.
                    </div>
                @endif

                <div class="flex justify-end gap-3">
                    <flux:button type="button" variant="ghost" wire:click="closeFormPanel">Cancelar</flux:button>
                    <flux:button type="submit" variant="primary">Guardar</flux:button>
                </div>
            </form>
        </x-side-panel>
    @endif
</section>
