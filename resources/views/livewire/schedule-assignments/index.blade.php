<?php

use App\Domains\Schedules\Actions\InactivateScheduleAssignmentAction;
use App\Domains\Schedules\Actions\ReplaceScheduleAssignmentAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\EmploymentRelationship;
use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use App\Models\Worker;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component {
    public array $form = [];

    public function mount(): void
    {
        $this->form = $this->emptyForm();
    }

    public function save(CurrentCompany $currentCompany, ReplaceScheduleAssignmentAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('create', [ScheduleAssignment::class, $company]);

        $validated = $this->validate([
            'form.worker_id' => [
                'required',
                'integer',
                Rule::exists('workers', 'id')->where('company_id', $company->id),
            ],
            'form.schedule_id' => [
                'required',
                'integer',
                Rule::exists('schedules', 'id')->where('company_id', $company->id),
            ],
            'form.employment_relationship_id' => [
                'nullable',
                'integer',
                Rule::exists('employment_relationships', 'id')->where('company_id', $company->id),
            ],
            'form.effective_from' => ['required', 'date'],
            'form.effective_to' => ['nullable', 'date', 'after_or_equal:form.effective_from'],
        ])['form'];

        $worker = Worker::query()
            ->where('company_id', $company->id)
            ->findOrFail((int) $validated['worker_id']);
        $schedule = Schedule::query()
            ->where('company_id', $company->id)
            ->findOrFail((int) $validated['schedule_id']);
        $employmentRelationship = filled($validated['employment_relationship_id'] ?? null)
            ? EmploymentRelationship::query()
                ->where('company_id', $company->id)
                ->where('worker_id', $worker->id)
                ->findOrFail((int) $validated['employment_relationship_id'])
            : null;

        try {
            $action->handle($company, $worker, $schedule, $employmentRelationship, [
                'effective_from' => $validated['effective_from'],
                'effective_to' => $validated['effective_to'] ?? null,
                'source' => 'web',
            ]);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'form.effective_from' => $exception->getMessage(),
            ]);
        }

        $this->form = $this->emptyForm();

        Session::flash('status', 'Asignacion de horario guardada.');
    }

    public function inactivate(
        int $assignmentId,
        CurrentCompany $currentCompany,
        InactivateScheduleAssignmentAction $action
    ): void {
        $company = $this->currentCompanyOrFail($currentCompany);
        $assignment = ScheduleAssignment::query()
            ->where('company_id', $company->id)
            ->findOrFail($assignmentId);

        Gate::authorize('inactivate', $assignment);

        $action->handle($company, $assignment);

        Session::flash('status', 'Asignacion de horario inactivada.');
    }

    public function with(CurrentCompany $currentCompany): array
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('viewAny', [ScheduleAssignment::class, $company]);

        return [
            'assignments' => $company->scheduleAssignments()
                ->with(['worker', 'schedule', 'employmentRelationship'])
                ->orderByDesc('effective_from')
                ->orderByDesc('id')
                ->get(),
            'workers' => $company->workers()
                ->where('status', 'active')
                ->orderBy('full_name')
                ->get(),
            'schedules' => $company->schedules()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(),
            'relationships' => $company->employmentRelationships()
                ->where('status', 'active')
                ->with('worker')
                ->orderBy('started_at')
                ->get(),
        ];
    }

    private function currentCompanyOrFail(CurrentCompany $currentCompany)
    {
        $company = $currentCompany->get();

        abort_unless($company, 403);

        return $company;
    }

    private function emptyForm(): array
    {
        return [
            'worker_id' => '',
            'schedule_id' => '',
            'employment_relationship_id' => '',
            'effective_from' => now()->toDateString(),
            'effective_to' => '',
        ];
    }
}; ?>

<section class="flex h-full w-full flex-1 flex-col gap-6 p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl">Asignaciones de horario</flux:heading>
                <flux:subheading>Administra la vigencia del horario por trabajador sin calcular jornadas.</flux:subheading>
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
                {{ session('status') }}
            </div>
        @endif

        <section class="space-y-4">
            <flux:heading>Asignar o reemplazar horario</flux:heading>

            <form wire:submit="save" class="grid gap-4 lg:grid-cols-[1fr_1fr_1fr_0.8fr_0.8fr_auto] lg:items-end">
                <flux:select label="Trabajador" wire:model="form.worker_id">
                    <flux:select.option value="">Selecciona</flux:select.option>
                    @foreach ($workers as $worker)
                        <flux:select.option value="{{ $worker->id }}">{{ $worker->full_name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select label="Horario" wire:model="form.schedule_id">
                    <flux:select.option value="">Selecciona</flux:select.option>
                    @foreach ($schedules as $schedule)
                        <flux:select.option value="{{ $schedule->id }}">{{ $schedule->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select label="Relacion laboral" wire:model="form.employment_relationship_id">
                    <flux:select.option value="">Sin relacion</flux:select.option>
                    @foreach ($relationships as $relationship)
                        <flux:select.option value="{{ $relationship->id }}">
                            {{ $relationship->worker->full_name }} - {{ $relationship->position_name }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input type="date" label="Desde" wire:model="form.effective_from" />
                <flux:input type="date" label="Hasta" wire:model="form.effective_to" />

                <flux:button type="submit" variant="primary">Guardar</flux:button>
            </form>
        </section>

        <section class="space-y-4">
            <flux:heading>Historial</flux:heading>

            <div class="overflow-hidden rounded-md border border-zinc-200 dark:border-zinc-700">
                <table class="w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead class="bg-zinc-50 text-left text-xs font-medium uppercase text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                        <tr>
                            <th class="px-4 py-3">Trabajador</th>
                            <th class="px-4 py-3">Horario</th>
                            <th class="px-4 py-3">Vigencia</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse ($assignments as $assignment)
                            <tr>
                                <td class="px-4 py-3">{{ $assignment->worker->full_name }}</td>
                                <td class="px-4 py-3">{{ $assignment->schedule->name }}</td>
                                <td class="px-4 py-3">
                                    {{ $assignment->effective_from?->toDateString() }}
                                    -
                                    {{ $assignment->effective_to?->toDateString() ?? 'Vigente' }}
                                </td>
                                <td class="px-4 py-3">{{ ucfirst($assignment->status) }}</td>
                                <td class="px-4 py-3 text-right">
                                    @if ($assignment->status === 'active')
                                        <flux:button type="button" size="sm" variant="ghost" wire:click="inactivate({{ $assignment->id }})">
                                            Inactivar
                                        </flux:button>
                                    @else
                                        <span class="text-xs text-zinc-500">Sin accion</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-zinc-500">
                                    No hay asignaciones registradas.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
</section>
