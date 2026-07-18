<?php

use App\Domains\Scheduling\Actions\CreateScheduleProfileAction;
use App\Domains\Scheduling\Actions\InactivateScheduleProfileAction;
use App\Domains\Scheduling\Actions\ReactivateScheduleProfileAction;
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
    public array $filters = [];
    public bool $showFormPanel = false;
    public ?int $editingProfileId = null;
    public ?int $viewingProfileId = null;

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
        $this->filters = ['search' => '', 'profile_type' => 'all', 'status' => 'active'];
    }

    public function updated($property): void
    {
        if (str_starts_with((string) $property, 'filters.')) {
            $this->resetPage();
        }

        if ($property === 'form.profile_type' && ($this->form['profile_type'] ?? 'pattern') === 'calendar') {
            $this->weeklyRules = $this->defaultWeeklyRules();
            $this->form['pattern_mode'] = null;
        }

        if ($property === 'form.profile_type' && ($this->form['profile_type'] ?? 'pattern') === 'pattern') {
            $this->form['pattern_mode'] = 'weekly';
        }

        if (str_contains((string) $property, '.day_type')) {
            $this->normalizeWeeklyRules();
        }
    }

    public function openCreatePanel(CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        Gate::authorize('create', [ScheduleProfile::class, $company]);

        $this->editingProfileId = null;
        $this->viewingProfileId = null;
        $this->form = $this->emptyForm();
        $this->weeklyRules = $this->defaultWeeklyRules();
        $this->showFormPanel = true;
    }

    public function loadEditForm(int $profileId, CurrentCompany $currentCompany): void
    {
        $profile = $this->authorizedProfile($profileId, $currentCompany, true);

        $this->editingProfileId = $profile->id;
        $this->viewingProfileId = null;
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

    public function save(CurrentCompany $currentCompany, CreateScheduleProfileAction $createAction, UpdateScheduleProfileAction $updateAction): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $profile = $this->editingProfileId ? $this->authorizedProfile($this->editingProfileId, $currentCompany, true) : null;

        $profile ? Gate::authorize('update', $profile) : Gate::authorize('create', [ScheduleProfile::class, $company]);

        $rules = $this->formIsWeeklyPattern() ? $this->preparedWeeklyRules() : [];
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
            'form.profile_type' => ['required', Rule::in(['pattern', 'calendar'])],
            'form.pattern_mode' => [Rule::requiredIf(($this->form['profile_type'] ?? 'pattern') === 'pattern'), 'nullable', Rule::in(['weekly'])],
            'form.status' => ['required', Rule::in(['active', 'inactive'])],
            'weeklyRules' => ['array', Rule::requiredIf($this->formIsWeeklyPattern()), 'size:7'],
            'weeklyRules.*.day_of_week' => ['required', 'integer', 'between:1,7'],
            'weeklyRules.*.day_type' => ['required', Rule::in(['shift', 'rest'])],
            'weeklyRules.*.shift_template_id' => ['nullable', 'integer'],
        ]);

        try {
            $profile
                ? $updateAction->handle($company, $profile, $validated['form'], $validated['form']['profile_type'] === 'pattern' ? $rules : null)
                : $createAction->handle($company, $validated['form'], $validated['form']['profile_type'] === 'pattern' ? $rules : []);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['weeklyRules' => $exception->getMessage()]);
        }

        $this->showFormPanel = false;
        $this->editingProfileId = null;
        $this->form = $this->emptyForm();
        $this->weeklyRules = $this->defaultWeeklyRules();
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

        $viewingProfile = $this->viewingProfileId
            ? $company->scheduleProfiles()->with('weeklyRules.shiftTemplate')->whereKey($this->viewingProfileId)->first()
            : null;

        if ($viewingProfile && ! Gate::allows('view', $viewingProfile)) {
            $viewingProfile = null;
            $this->viewingProfileId = null;
        }

        return [
            'profiles' => $company->scheduleProfiles()
                ->with('weeklyRules.shiftTemplate')
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
            'viewingProfile' => $viewingProfile,
        ];
    }

    private function authorizedProfile(int $profileId, CurrentCompany $currentCompany, bool $forUpdate): ScheduleProfile
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $profile = $company->scheduleProfiles()->with('weeklyRules.shiftTemplate')->whereKey($profileId)->firstOrFail();

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

    private function normalizeWeeklyRules(): void
    {
        foreach ($this->weeklyRules as $index => $rule) {
            if (($rule['day_type'] ?? 'shift') === 'rest') {
                $this->weeklyRules[$index]['shift_template_id'] = '';
            }
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
            'pattern' => $profile->pattern_mode === 'weekly' ? 'Por patron - patron semanal' : 'Por patron',
            'calendar' => 'Por calendario',
            'flexible' => 'Flexible',
            'on_call' => 'Bajo demanda',
            default => 'Tipo no reconocido',
        };
    }

    private function isWeeklyPattern(ScheduleProfile $profile): bool
    {
        return $profile->profile_type === 'pattern' && $profile->pattern_mode === 'weekly';
    }

    private function formIsWeeklyPattern(): bool
    {
        return ($this->form['profile_type'] ?? 'pattern') === 'pattern'
            && ($this->form['pattern_mode'] ?? 'weekly') === 'weekly';
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
}; ?>

<section class="flex h-full w-full flex-1 flex-col gap-6 p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">Perfiles de horario</flux:heading>
            <flux:subheading>Configura perfiles por patron semanal o por calendario. Los perfiles generan borradores futuros; no publican dias.</flux:subheading>
        </div>

        @if ($canManageProfiles)
            <flux:button wire:click="openCreatePanel" icon="plus">Nuevo perfil</flux:button>
        @endif
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">{{ session('status') }}</div>
    @endif

    @error('profile')
        <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">{{ $message }}</div>
    @enderror

    <div class="grid gap-4 rounded-md border border-zinc-200 p-4 dark:border-zinc-700 md:grid-cols-3">
        <flux:input label="Buscar" placeholder="Codigo o nombre" wire:model.live.debounce.350ms="filters.search" />
        <flux:select label="Tipo" wire:model.live="filters.profile_type">
            <flux:select.option value="all">Todos</flux:select.option>
            <flux:select.option value="pattern">Por patron</flux:select.option>
            <flux:select.option value="calendar">Por calendario</flux:select.option>
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
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($profiles as $profile)
                    <tr>
                        <td class="px-4 py-3">
                            <span class="block font-medium text-zinc-900 dark:text-zinc-100">{{ $profile->code }} - {{ $profile->name }}</span>
                            <span class="text-xs text-zinc-500">{{ $profile->description ?: 'Sin descripcion' }}</span>
                        </td>
                        <td class="px-4 py-3">{{ $this->profileTypeLabel($profile) }}</td>
                        <td class="px-4 py-3">{{ $this->isWeeklyPattern($profile) ? $profile->weeklyRules->count().' dias' : 'Sin reglas semanales' }}</td>
                        <td class="px-4 py-3">{{ $profile->status === 'active' ? 'Activo' : 'Inactivo' }}</td>
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
                    <flux:subheading>{{ $this->isWeeklyPattern($viewingProfile) ? 'Reglas semanales del perfil por patron.' : 'Perfil por calendario sin reglas semanales.' }}</flux:subheading>
                </div>
                <flux:button size="sm" variant="ghost" wire:click="closeDetail">Cerrar</flux:button>
            </div>

            @if ($this->isWeeklyPattern($viewingProfile))
                <div class="grid gap-2 text-sm md:grid-cols-2">
                    @foreach ($viewingProfile->weeklyRules as $rule)
                        <p><span class="font-medium">{{ $dayNames[$rule->day_of_week] }}</span>: {{ $rule->day_type === 'rest' ? 'Descanso' : $rule->shiftTemplate?->name }}</p>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-zinc-600 dark:text-zinc-300">Este perfil se captura por calendario. La programacion se definira por periodo, importacion o API en bloques posteriores.</p>
            @endif
        </section>
    @endif

    @if ($canManageProfiles)
        <x-side-panel wire:model="showFormPanel" maxWidth="max-w-5xl" title="{{ $editingProfileId ? 'Editar perfil de horario' : 'Nuevo perfil de horario' }}" subheading="Configura el perfil; la publicacion diaria se implementara despues.">
            <form wire:submit="save" class="space-y-6 p-6">
                <div class="grid gap-4 md:grid-cols-4">
                    <flux:input label="Codigo" wire:model="form.code" required />
                    <flux:input label="Nombre" wire:model="form.name" required />
                    <flux:select label="Tipo" wire:model.live="form.profile_type" :disabled="$editingProfileId !== null">
                        <flux:select.option value="pattern">Por patron</flux:select.option>
                        <flux:select.option value="calendar">Por calendario</flux:select.option>
                    </flux:select>
                    <flux:select label="Estado" wire:model="form.status">
                        <flux:select.option value="active">Activo</flux:select.option>
                        <flux:select.option value="inactive">Inactivo</flux:select.option>
                    </flux:select>
                </div>

                <flux:textarea label="Descripcion" wire:model="form.description" rows="2" />

                @if (($form['profile_type'] ?? 'pattern') === 'pattern')
                    <div class="rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-100">
                        Modalidad disponible en este bloque: <span class="font-medium">Patron semanal</span>. Ciclo rotativo, flexible y bajo demanda quedan preparados para bloques posteriores.
                    </div>

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
                @else
                    <div class="rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-100">
                        Este perfil se captura por calendario. La programacion se definira por periodo, importacion o API en bloques posteriores.
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
