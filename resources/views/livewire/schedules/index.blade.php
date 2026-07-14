<?php

use App\Domains\Schedules\Actions\CreateScheduleAction;
use App\Domains\Schedules\Actions\InactivateScheduleAction;
use App\Domains\Schedules\Actions\SaveScheduleBreaksAction;
use App\Domains\Schedules\Actions\SaveScheduleDaysAction;
use App\Domains\Schedules\Actions\UpdateScheduleAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\Schedule;
use App\Models\ScheduleDay;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component {
    public array $form = [];
    public array $daysForm = [];
    public array $breakForm = [];
    public bool $showFormPanel = false;
    public ?int $editingScheduleId = null;
    public ?int $selectedScheduleDayId = null;

    private array $dayLabels = [
        0 => 'Domingo',
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miercoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sabado',
    ];

    public function mount(): void
    {
        $this->form = $this->emptyForm();
        $this->daysForm = $this->emptyDaysForm();
        $this->breakForm = $this->emptyBreakForm();
    }

    public function openCreatePanel(CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('create', [Schedule::class, $company]);

        $this->editingScheduleId = null;
        $this->selectedScheduleDayId = null;
        $this->form = $this->emptyForm($company->timezone);
        $this->daysForm = $this->emptyDaysForm();
        $this->breakForm = $this->emptyBreakForm();
        $this->showFormPanel = true;
    }

    public function loadEditForm(int $scheduleId, CurrentCompany $currentCompany): void
    {
        $schedule = $this->authorizedSchedule($scheduleId, $currentCompany);

        $this->editingScheduleId = $schedule->id;
        $this->form = [
            'code' => $schedule->code,
            'name' => $schedule->name,
            'legal_type' => $schedule->legal_type,
            'timezone' => $schedule->timezone ?? '',
            'status' => $schedule->status,
            'effective_from' => $schedule->effective_from?->toDateString() ?? '',
            'effective_to' => $schedule->effective_to?->toDateString() ?? '',
        ];
        $this->daysForm = $this->daysFormForSchedule($schedule);
        $this->selectedScheduleDayId = $schedule->days()->orderBy('day_of_week')->value('id');
        $this->breakForm = $this->emptyBreakForm();
        $this->showFormPanel = true;
    }

    public function save(
        CurrentCompany $currentCompany,
        CreateScheduleAction $createAction,
        UpdateScheduleAction $updateAction,
    ): void {
        $company = $this->currentCompanyOrFail($currentCompany);

        $schedule = $this->editingScheduleId
            ? $this->authorizedSchedule($this->editingScheduleId, $currentCompany)
            : null;

        $schedule
            ? Gate::authorize('update', $schedule)
            : Gate::authorize('create', [Schedule::class, $company]);

        $validated = $this->validate([
            'form.code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('schedules', 'code')
                    ->where('company_id', $company->id)
                    ->ignore($schedule?->id),
            ],
            'form.name' => ['required', 'string', 'max:255'],
            'form.legal_type' => ['required', Rule::in(['diurnal', 'nocturnal', 'mixed', 'variable'])],
            'form.timezone' => ['nullable', 'string', 'max:100'],
            'form.status' => ['required', Rule::in(['active', 'inactive'])],
            'form.effective_from' => ['nullable', 'date'],
            'form.effective_to' => ['nullable', 'date', 'after_or_equal:form.effective_from'],
        ])['form'];

        $data = [
            'code' => $validated['code'],
            'name' => $validated['name'],
            'legal_type' => $validated['legal_type'],
            'timezone' => $validated['timezone'] ?: null,
            'status' => $validated['status'],
            'effective_from' => $validated['effective_from'] ?: null,
            'effective_to' => $validated['effective_to'] ?: null,
        ];

        $savedSchedule = $schedule
            ? $updateAction->handle($company, $schedule, $data)
            : $createAction->handle($company, $data);

        $this->editingScheduleId = $savedSchedule->id;
        $this->daysForm = $this->daysFormForSchedule($savedSchedule);
        $this->showFormPanel = true;

        Session::flash('status', $schedule ? 'Horario actualizado.' : 'Horario creado.');
    }

    public function saveDays(CurrentCompany $currentCompany, SaveScheduleDaysAction $action): void
    {
        $schedule = $this->editingScheduleOrFail($currentCompany);

        Gate::authorize('update', $schedule);

        $validated = $this->validate($this->dayValidationRules())['daysForm'];
        $this->assertValidCrossMidnightRules($validated);

        try {
            $action->handle($schedule->company, $schedule, $validated);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'daysForm' => $exception->getMessage(),
            ]);
        }

        $schedule->refresh();
        $this->daysForm = $this->daysFormForSchedule($schedule);
        $this->selectedScheduleDayId = $schedule->days()->orderBy('day_of_week')->value('id');

        Session::flash('status', 'Dias del horario guardados.');
    }

    public function saveBreak(CurrentCompany $currentCompany, SaveScheduleBreaksAction $action): void
    {
        $schedule = $this->editingScheduleOrFail($currentCompany);

        Gate::authorize('update', $schedule);

        $validated = $this->validate([
            'selectedScheduleDayId' => [
                'required',
                Rule::exists('schedule_days', 'id')
                    ->where('company_id', $schedule->company_id)
                    ->where('schedule_id', $schedule->id),
            ],
            'breakForm.name' => ['nullable', 'string', 'max:255'],
            'breakForm.start_time' => ['nullable', 'date_format:H:i'],
            'breakForm.end_time' => ['nullable', 'date_format:H:i'],
            'breakForm.duration_minutes' => ['nullable', 'integer', 'min:1'],
            'breakForm.is_paid' => ['boolean'],
            'breakForm.is_required' => ['boolean'],
        ]);

        $scheduleDay = $schedule->days()
            ->whereKey($validated['selectedScheduleDayId'])
            ->where('company_id', $schedule->company_id)
            ->firstOrFail();

        $action->handle($schedule->company, $scheduleDay, [$validated['breakForm']]);

        $this->breakForm = $this->emptyBreakForm();

        Session::flash('status', 'Pausa guardada.');
    }

    public function inactivate(
        int $scheduleId,
        CurrentCompany $currentCompany,
        InactivateScheduleAction $action,
    ): void {
        $company = $this->currentCompanyOrFail($currentCompany);
        $schedule = $this->authorizedSchedule($scheduleId, $currentCompany);

        Gate::authorize('inactivate', $schedule);

        $action->handle($company, $schedule);

        Session::flash('status', 'Horario inactivado.');
    }

    public function closeFormPanel(): void
    {
        $this->showFormPanel = false;
        $this->editingScheduleId = null;
        $this->selectedScheduleDayId = null;
        $this->resetValidation();
    }

    public function with(CurrentCompany $currentCompany): array
    {
        $company = $currentCompany->get();

        abort_unless($company, 403);

        Gate::authorize('viewAny', [Schedule::class, $company]);

        $selectedDay = $this->selectedScheduleDayId
            ? ScheduleDay::query()
                ->with('breaks')
                ->where('company_id', $company->id)
                ->where('schedule_id', $this->editingScheduleId)
                ->whereKey($this->selectedScheduleDayId)
                ->first()
            : null;

        $availableScheduleDays = $this->editingScheduleId
            ? $company->scheduleDays()
                ->where('schedule_id', $this->editingScheduleId)
                ->orderBy('day_of_week')
                ->get()
            : collect();

        return [
            'schedules' => $company->schedules()
                ->withCount('days')
                ->orderBy('name')
                ->get(),
            'selectedDay' => $selectedDay,
            'availableScheduleDays' => $availableScheduleDays,
            'dayLabels' => $this->dayLabels,
            'currentCompany' => $company,
            'canManageSchedules' => Gate::allows('create', [Schedule::class, $company]),
        ];
    }

    private function authorizedSchedule(int $scheduleId, CurrentCompany $currentCompany): Schedule
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        $schedule = $company->schedules()
            ->whereKey($scheduleId)
            ->firstOrFail();

        Gate::authorize('update', $schedule);

        return $schedule;
    }

    private function editingScheduleOrFail(CurrentCompany $currentCompany): Schedule
    {
        if (! $this->editingScheduleId) {
            throw ValidationException::withMessages([
                'form.code' => 'Guarda el horario antes de editar dias o pausas.',
            ]);
        }

        return $this->authorizedSchedule($this->editingScheduleId, $currentCompany);
    }

    private function currentCompanyOrFail(CurrentCompany $currentCompany)
    {
        $company = $currentCompany->get();

        abort_unless($company, 403);

        return $company;
    }

    private function dayValidationRules(): array
    {
        $rules = [];

        foreach ($this->daysForm as $index => $day) {
            $rules["daysForm.$index.day_of_week"] = ['required', 'integer', 'between:0,6'];
            $rules["daysForm.$index.is_working_day"] = ['boolean'];
            $rules["daysForm.$index.start_time"] = [
                Rule::requiredIf(fn (): bool => (bool) ($this->daysForm[$index]['is_working_day'] ?? false)),
                'nullable',
                'date_format:H:i',
            ];
            $rules["daysForm.$index.end_time"] = [
                Rule::requiredIf(fn (): bool => (bool) ($this->daysForm[$index]['is_working_day'] ?? false)),
                'nullable',
                'date_format:H:i',
            ];
            $rules["daysForm.$index.crosses_midnight"] = ['boolean'];
        }

        return $rules;
    }

    private function daysFormForSchedule(Schedule $schedule): array
    {
        $days = $schedule->days()->get()->keyBy('day_of_week');

        return collect($this->emptyDaysForm())
            ->map(function (array $emptyDay) use ($days): array {
                $day = $days->get($emptyDay['day_of_week']);

                if (! $day) {
                    return $emptyDay;
                }

                return [
                    'day_of_week' => $day->day_of_week,
                    'is_working_day' => $day->is_working_day,
                    'start_time' => $day->start_time ? substr((string) $day->start_time, 0, 5) : '',
                    'end_time' => $day->end_time ? substr((string) $day->end_time, 0, 5) : '',
                    'crosses_midnight' => $day->crosses_midnight,
                ];
            })
            ->values()
            ->all();
    }

    private function assertValidCrossMidnightRules(array $days): void
    {
        foreach ($days as $index => $day) {
            if (! (bool) ($day['is_working_day'] ?? false)) {
                continue;
            }

            if (blank($day['start_time'] ?? null) || blank($day['end_time'] ?? null)) {
                continue;
            }

            if (($day['end_time'] <= $day['start_time']) && ! (bool) ($day['crosses_midnight'] ?? false)) {
                throw ValidationException::withMessages([
                    "daysForm.$index.crosses_midnight" => 'Activa cruce de medianoche cuando la salida es menor o igual a la entrada.',
                ]);
            }
        }
    }

    private function emptyForm(?string $timezone = null): array
    {
        return [
            'code' => '',
            'name' => '',
            'legal_type' => 'diurnal',
            'timezone' => $timezone ?? '',
            'status' => 'active',
            'effective_from' => '',
            'effective_to' => '',
        ];
    }

    private function emptyDaysForm(): array
    {
        return collect($this->dayLabels)
            ->keys()
            ->map(fn (int $day): array => [
                'day_of_week' => $day,
                'is_working_day' => in_array($day, [1, 2, 3, 4, 5], true),
                'start_time' => in_array($day, [1, 2, 3, 4, 5], true) ? '09:00' : '',
                'end_time' => in_array($day, [1, 2, 3, 4, 5], true) ? '18:00' : '',
                'crosses_midnight' => false,
            ])
            ->all();
    }

    private function emptyBreakForm(): array
    {
        return [
            'name' => '',
            'start_time' => '',
            'end_time' => '',
            'duration_minutes' => '',
            'is_paid' => false,
            'is_required' => false,
        ];
    }
}; ?>

<section class="w-full space-y-8 p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">Horarios</flux:heading>
            <flux:subheading>Administra horarios base y pausas programadas de la empresa activa.</flux:subheading>
        </div>

        @if ($canManageSchedules)
            <flux:button type="button" variant="primary" wire:click="openCreatePanel">
                Nuevo horario
            </flux:button>
        @endif
    </div>

    @if (session('status'))
        <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
            {{ session('status') }}
        </div>
    @endif

    <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="mb-4">
            <flux:heading>Horarios de {{ $currentCompany->name }}</flux:heading>
            <flux:subheading>Solo se muestran horarios asociados a la empresa activa.</flux:subheading>
        </div>

        <div class="overflow-hidden rounded-md border border-zinc-200 dark:border-zinc-700">
            <table class="w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 text-left text-xs font-medium uppercase text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                    <tr>
                        <th class="px-4 py-3">Codigo</th>
                        <th class="px-4 py-3">Nombre</th>
                        <th class="px-4 py-3">Tipo legal</th>
                        <th class="px-4 py-3">Zona horaria</th>
                        <th class="px-4 py-3">Vigencia</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($schedules as $schedule)
                        <tr>
                            <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">{{ $schedule->code }}</td>
                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ $schedule->name }}</td>
                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ $schedule->legal_type }}</td>
                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ $schedule->timezone ?: 'Sin zona' }}</td>
                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">
                                {{ $schedule->effective_from?->toDateString() ?? 'Sin inicio' }}
                                -
                                {{ $schedule->effective_to?->toDateString() ?? 'Sin fin' }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-1 text-xs font-medium {{ $schedule->status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200' : 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300' }}">
                                    {{ $schedule->status }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <flux:button type="button" size="sm" variant="ghost" wire:click="loadEditForm({{ $schedule->id }})">
                                        Editar
                                    </flux:button>

                                    @if ($schedule->status === 'active')
                                        <flux:button type="button" size="sm" variant="danger" wire:click="inactivate({{ $schedule->id }})">
                                            Inactivar
                                        </flux:button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-sm text-zinc-500 dark:text-zinc-400">
                                Aun no hay horarios registrados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <x-side-panel
        wire:model="showFormPanel"
        :title="$editingScheduleId ? 'Editar horario' : 'Nuevo horario'"
        subheading="Guarda el horario para administrar sus dias y pausas."
        max-width="max-w-3xl"
    >
        <div class="space-y-8 p-6">
                <form wire:submit="save" class="space-y-5">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input label="Codigo" wire:model="form.code" />
                        <flux:input label="Nombre" wire:model="form.name" />
                        <flux:select label="Tipo legal" wire:model="form.legal_type">
                            <flux:select.option value="diurnal">Diurno</flux:select.option>
                            <flux:select.option value="nocturnal">Nocturno</flux:select.option>
                            <flux:select.option value="mixed">Mixto</flux:select.option>
                            <flux:select.option value="variable">Variable</flux:select.option>
                        </flux:select>
                        <flux:input label="Zona horaria" wire:model="form.timezone" />
                        <flux:select label="Estado" wire:model="form.status">
                            <flux:select.option value="active">Activo</flux:select.option>
                            <flux:select.option value="inactive">Inactivo</flux:select.option>
                        </flux:select>
                        <div></div>
                        <flux:input type="date" label="Vigente desde" wire:model="form.effective_from" />
                        <flux:input type="date" label="Vigente hasta" wire:model="form.effective_to" />
                    </div>

                    <div class="flex justify-end">
                        <flux:button type="submit" variant="primary">
                            Guardar horario
                        </flux:button>
                    </div>
                </form>

                @if ($editingScheduleId)
                    <section class="space-y-4 border-t border-zinc-200 pt-6 dark:border-zinc-700">
                        <div>
                            <flux:heading>Dias del horario</flux:heading>
                            <flux:subheading>Define entrada, salida y si el dia cruza medianoche.</flux:subheading>
                        </div>

                        <form wire:submit="saveDays" class="space-y-3">
                            @foreach ($daysForm as $index => $day)
                                <div class="grid items-end gap-3 rounded-md border border-zinc-200 p-3 dark:border-zinc-700 sm:grid-cols-[1.2fr_1fr_1fr_1fr_1fr]">
                                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $dayLabels[$day['day_of_week']] }}
                                    </div>
                                    <flux:checkbox label="Laboral" wire:model="daysForm.{{ $index }}.is_working_day" />
                                    <flux:input type="time" label="Entrada" wire:model="daysForm.{{ $index }}.start_time" />
                                    <flux:input type="time" label="Salida" wire:model="daysForm.{{ $index }}.end_time" />
                                    <flux:checkbox label="Cruza medianoche" wire:model="daysForm.{{ $index }}.crosses_midnight" />
                                </div>
                            @endforeach

                            <div class="flex justify-end">
                                <flux:button type="submit" variant="primary">
                                    Guardar dias
                                </flux:button>
                            </div>
                        </form>
                    </section>

                    <section class="space-y-4 border-t border-zinc-200 pt-6 dark:border-zinc-700">
                        <div>
                            <flux:heading>Pausas programadas</flux:heading>
                            <flux:subheading>Registra pausas por dia sin calcular efectos legales todavia.</flux:subheading>
                        </div>

                        <form wire:submit="saveBreak" class="space-y-4">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <flux:select label="Dia" wire:model="selectedScheduleDayId">
                                    @foreach ($availableScheduleDays as $day)
                                        <flux:select.option value="{{ $day->id }}">
                                            {{ $dayLabels[$day->day_of_week] }}
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:input label="Nombre" wire:model="breakForm.name" />
                                <flux:input type="time" label="Inicio" wire:model="breakForm.start_time" />
                                <flux:input type="time" label="Fin" wire:model="breakForm.end_time" />
                                <flux:input type="number" min="1" label="Duracion minutos" wire:model="breakForm.duration_minutes" />
                                <div class="grid gap-3 pt-7">
                                    <flux:checkbox label="Computable/pagada" wire:model="breakForm.is_paid" />
                                    <flux:checkbox label="Requerida" wire:model="breakForm.is_required" />
                                </div>
                            </div>

                            <div class="flex justify-end">
                                <flux:button type="submit" variant="primary">
                                    Guardar pausa
                                </flux:button>
                            </div>
                        </form>

                        @if ($selectedDay)
                            <div class="overflow-hidden rounded-md border border-zinc-200 dark:border-zinc-700">
                                <table class="w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                                    <thead class="bg-zinc-50 text-left text-xs font-medium uppercase text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                                        <tr>
                                            <th class="px-4 py-3">Nombre</th>
                                            <th class="px-4 py-3">Inicio</th>
                                            <th class="px-4 py-3">Fin</th>
                                            <th class="px-4 py-3">Duracion</th>
                                            <th class="px-4 py-3">Tipo</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                        @forelse ($selectedDay->breaks as $break)
                                            <tr>
                                                <td class="px-4 py-3">{{ $break->name ?: 'Pausa' }}</td>
                                                <td class="px-4 py-3">{{ $break->start_time ? substr((string) $break->start_time, 0, 5) : 'Sin hora' }}</td>
                                                <td class="px-4 py-3">{{ $break->end_time ? substr((string) $break->end_time, 0, 5) : 'Sin hora' }}</td>
                                                <td class="px-4 py-3">{{ $break->duration_minutes ? $break->duration_minutes.' min' : 'Sin duracion' }}</td>
                                                <td class="px-4 py-3">{{ $break->is_paid ? 'Computable' : 'No computable' }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="px-4 py-6 text-center text-sm text-zinc-500 dark:text-zinc-400">
                                                    Sin pausas registradas para este dia.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </section>
                @endif
        </div>
    </x-side-panel>
</section>
