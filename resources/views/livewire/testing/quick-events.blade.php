<?php

use App\Domains\Scheduling\Actions\ResolveDailyScheduleForRelationshipDateAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Domains\Testing\Actions\ResetOperationalTestDataAction;
use App\Domains\TimeRecords\Actions\CreateQuickTestTimeEventsAction;
use App\Models\Worker;
use App\Support\RoleKey;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component {
    public string $workDate = '';
    public array $rows = [];

    public function mount(CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $this->authorizeQuickTool($company);

        $this->workDate = CarbonImmutable::now($company->timezone)->toDateString();
    }

    public function updatedWorkDate(): void
    {
        $this->rows = [];
    }

    public function createEvents(int $workerId, CurrentCompany $currentCompany, CreateQuickTestTimeEventsAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $this->authorizeQuickTool($company);

        $worker = Worker::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->whereKey($workerId)
            ->firstOrFail();

        $row = $this->rows[$workerId] ?? [];

        try {
            $result = $action->handle($company, auth()->user(), $worker, [
                'work_date' => $this->workDate,
                'source_mode' => $row['source_mode'] ?? 'assisted',
                'clock_in' => $row['clock_in'] ?? null,
                'clock_out' => $row['clock_out'] ?? null,
                'break1_start' => $row['break1_start'] ?? null,
                'break1_end' => $row['break1_end'] ?? null,
                'break2_start' => $row['break2_start'] ?? null,
                'break2_end' => $row['break2_end'] ?? null,
            ]);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(["rows.{$workerId}.clock_in" => $exception->getMessage()]);
        }

        Session::flash('status', "Eventos cargados para {$worker->employee_code}. Eventos: {$result['events']}. Jornadas refrescadas: {$result['refresh']['total']}.");
    }

    public function deletePublishedSchedules(CurrentCompany $currentCompany, ResetOperationalTestDataAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $this->authorizeQuickTool($company);

        try {
            $result = $action->deletePublishedSchedules($company, auth()->user());
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['reset' => $exception->getMessage()]);
        }

        Session::flash('status', "Horarios publicados eliminados. Lotes: {$result['schedule_batches']}. Dias publicados: {$result['daily_schedule_assignments']}.");
    }

    public function deleteTimeEvents(CurrentCompany $currentCompany, ResetOperationalTestDataAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $this->authorizeQuickTool($company);

        $result = $action->deleteTimeEvents($company, auth()->user());

        Session::flash('status', "Eventos eliminados: {$result['time_events']}.");
    }

    public function deleteWorkDays(CurrentCompany $currentCompany, ResetOperationalTestDataAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $this->authorizeQuickTool($company);

        $result = $action->deleteWorkDays($company, auth()->user());

        Session::flash('status', "Jornadas eliminadas: {$result['work_days']}. Calculos: {$result['work_day_calculations']}. Alertas: {$result['alerts']}.");
    }

    public function deleteAttendancePeriods(CurrentCompany $currentCompany, ResetOperationalTestDataAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $this->authorizeQuickTool($company);

        $result = $action->deleteAttendancePeriods($company, auth()->user());

        Session::flash('status', "Periodos eliminados: {$result['attendance_periods']}.");
    }

    public function fillFromSchedule(int $workerId, CurrentCompany $currentCompany, ResolveDailyScheduleForRelationshipDateAction $resolver): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $worker = Worker::query()
            ->with('activeEmploymentRelationship.center')
            ->where('company_id', $company->id)
            ->whereKey($workerId)
            ->firstOrFail();

        $relationship = $worker->activeEmploymentRelationship;
        if (! $relationship) {
            return;
        }

        $schedule = $resolver->handle($company, $relationship, $this->workDate);
        $defaults = $this->defaultsFromSchedule($schedule);
        $this->rows[$workerId] = array_merge($this->defaultRow(), $this->rows[$workerId] ?? [], $defaults);
    }

    public function with(CurrentCompany $currentCompany, ResolveDailyScheduleForRelationshipDateAction $resolver): array
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $this->authorizeQuickTool($company);

        $workers = Worker::query()
            ->with(['activeEmploymentRelationship.center'])
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->orderBy('employee_code')
            ->get();

        $schedules = [];
        foreach ($workers as $worker) {
            $this->rows[$worker->id] ??= $this->defaultRow();

            $relationship = $worker->activeEmploymentRelationship;
            if (! $relationship) {
                $schedules[$worker->id] = ['label' => 'Sin relacion activa', 'defaults' => []];
                continue;
            }

            try {
                $schedule = $resolver->handle($company, $relationship, $this->workDate);
                $defaults = $this->defaultsFromSchedule($schedule);
                $schedules[$worker->id] = [
                    'label' => $this->scheduleLabel($schedule),
                    'defaults' => $defaults,
                ];

                if (blank($this->rows[$worker->id]['clock_in'] ?? null) && $defaults !== []) {
                    $this->rows[$worker->id] = array_merge($this->rows[$worker->id], $defaults);
                }
            } catch (\Throwable) {
                $schedules[$worker->id] = ['label' => 'No se pudo resolver horario', 'defaults' => []];
            }
        }

        return [
            'workers' => $workers,
            'schedules' => $schedules,
        ];
    }

    private function currentCompanyOrFail(CurrentCompany $currentCompany)
    {
        $company = $currentCompany->get();
        abort_unless($company, 403);

        return $company;
    }

    private function authorizeQuickTool($company): void
    {
        $role = auth()->user()?->roleKeyForCompany($company);
        abort_unless(in_array($role, [...RoleKey::companyManagers(), RoleKey::SUPER_ADMIN], true), 403);
    }

    private function defaultRow(): array
    {
        return [
            'source_mode' => 'assisted',
            'clock_in' => '',
            'clock_out' => '',
            'break1_start' => '',
            'break1_end' => '',
            'break2_start' => '',
            'break2_end' => '',
        ];
    }

    private function scheduleLabel(array $schedule): string
    {
        if (($schedule['resolution_status'] ?? null) !== 'published') {
            return 'Sin programacion publicada';
        }

        return match ($schedule['day_type']) {
            'shift' => 'Turno '.($schedule['shift_template']?->code ?? $schedule['shift_template']?->name ?? ''),
            'rest' => 'Descanso',
            'flexible' => 'Flexible',
            'on_call' => 'Guardia',
            default => 'Pendiente',
        };
    }

    private function defaultsFromSchedule(array $schedule): array
    {
        if (($schedule['day_type'] ?? null) !== 'shift') {
            return [];
        }

        $workSegments = $schedule['segments']->where('segment_type', 'work')->values();
        if ($workSegments->isEmpty()) {
            return [];
        }

        $breakSegments = $schedule['segments']->where('segment_type', 'break')->where('timing_mode', 'fixed')->values();
        $firstWork = $workSegments->first();
        $lastWork = $workSegments->last();

        return [
            'clock_in' => substr((string) $firstWork->start_local_time, 0, 5),
            'clock_out' => substr((string) $lastWork->end_local_time, 0, 5),
            'break1_start' => $breakSegments->get(0)?->start_local_time ? substr((string) $breakSegments->get(0)->start_local_time, 0, 5) : '',
            'break1_end' => $breakSegments->get(0)?->end_local_time ? substr((string) $breakSegments->get(0)->end_local_time, 0, 5) : '',
            'break2_start' => $breakSegments->get(1)?->start_local_time ? substr((string) $breakSegments->get(1)->start_local_time, 0, 5) : '',
            'break2_end' => $breakSegments->get(1)?->end_local_time ? substr((string) $breakSegments->get(1)->end_local_time, 0, 5) : '',
        ];
    }
}; ?>

<section class="w-full space-y-6 p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">Eventos rapidos de prueba</flux:heading>
            <flux:subheading>Herramienta provisional para cargar eventos y refrescar jornadas durante pruebas internas.</flux:subheading>
        </div>
        <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
            Provisional. No usar como flujo operativo real.
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    @error('reset')
        <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $message }}
        </div>
    @enderror

    <section class="rounded-lg border border-zinc-200 bg-zinc-50 p-4">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-xs">
                <flux:input type="date" label="Fecha de prueba" wire:model.live="workDate" />
            </div>

            <div class="flex flex-wrap gap-2">
                <flux:button type="button" size="sm" variant="danger" wire:click="deletePublishedSchedules" wire:confirm="Esto borrara solo lotes publicados de programacion diaria de la empresa activa. No borra trabajadores, turnos ni perfiles. ¿Continuar?">
                    Borrar horarios publicados
                </flux:button>
                <flux:button type="button" size="sm" variant="danger" wire:click="deleteTimeEvents" wire:confirm="Esto borrara todos los eventos de jornada de la empresa activa. ¿Continuar?">
                    Borrar eventos
                </flux:button>
                <flux:button type="button" size="sm" variant="danger" wire:click="deleteWorkDays" wire:confirm="Esto borrara jornadas, calculos y alertas ligadas de la empresa activa. ¿Continuar?">
                    Borrar jornadas
                </flux:button>
                <flux:button type="button" size="sm" variant="danger" wire:click="deleteAttendancePeriods" wire:confirm="Esto borrara todos los periodos de asistencia de la empresa activa. ¿Continuar?">
                    Borrar periodos
                </flux:button>
            </div>
        </div>
    </section>

    <section class="overflow-x-auto rounded-lg border border-zinc-200 bg-white">
        <table class="min-w-[1400px] w-full divide-y divide-zinc-200 text-sm">
            <thead class="bg-zinc-50 text-left text-xs font-medium uppercase text-zinc-500">
                <tr>
                    <th class="px-4 py-3">Trabajador</th>
                    <th class="px-4 py-3">Centro</th>
                    <th class="px-4 py-3">Horario esperado</th>
                    <th class="px-4 py-3">Metodo</th>
                    <th class="px-4 py-3">Entrada</th>
                    <th class="px-4 py-3">Pausa 1</th>
                    <th class="px-4 py-3">Pausa 2</th>
                    <th class="px-4 py-3">Salida</th>
                    <th class="px-4 py-3 text-right">Accion</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200">
                @forelse ($workers as $worker)
                    <tr>
                        <td class="px-4 py-3">
                            <div class="font-medium text-zinc-900">{{ $worker->employee_code }} - {{ $worker->full_name }}</div>
                            <div class="text-xs text-zinc-500">{{ $worker->status }}</div>
                        </td>
                        <td class="px-4 py-3 text-zinc-700">
                            {{ $worker->activeEmploymentRelationship?->center?->name ?? 'Sin centro' }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-zinc-700">{{ $schedules[$worker->id]['label'] ?? 'Sin dato' }}</div>
                            <button type="button" class="mt-1 text-xs text-primary hover:underline" wire:click="fillFromSchedule({{ $worker->id }})">
                                Rellenar desde horario
                            </button>
                        </td>
                        <td class="px-4 py-3">
                            <select wire:model="rows.{{ $worker->id }}.source_mode" class="w-32 rounded-md border border-zinc-300 bg-white px-2 py-2 text-sm">
                                <option value="web">Web</option>
                                <option value="assisted">Asistido</option>
                                <option value="kiosk">Kiosco</option>
                            </select>
                        </td>
                        <td class="px-4 py-3">
                            <input type="time" wire:model="rows.{{ $worker->id }}.clock_in" class="w-28 rounded-md border border-zinc-300 px-2 py-2 text-sm">
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex gap-2">
                                <input type="time" wire:model="rows.{{ $worker->id }}.break1_start" class="w-28 rounded-md border border-zinc-300 px-2 py-2 text-sm">
                                <input type="time" wire:model="rows.{{ $worker->id }}.break1_end" class="w-28 rounded-md border border-zinc-300 px-2 py-2 text-sm">
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex gap-2">
                                <input type="time" wire:model="rows.{{ $worker->id }}.break2_start" class="w-28 rounded-md border border-zinc-300 px-2 py-2 text-sm">
                                <input type="time" wire:model="rows.{{ $worker->id }}.break2_end" class="w-28 rounded-md border border-zinc-300 px-2 py-2 text-sm">
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <input type="time" wire:model="rows.{{ $worker->id }}.clock_out" class="w-28 rounded-md border border-zinc-300 px-2 py-2 text-sm">
                            @error("rows.{$worker->id}.clock_in")
                                <div class="mt-1 text-xs text-red-600">{{ $message }}</div>
                            @enderror
                        </td>
                        <td class="px-4 py-3 text-right">
                            <flux:button type="button" size="sm" variant="primary" wire:click="createEvents({{ $worker->id }})">
                                Cargar eventos
                            </flux:button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-8 text-center text-zinc-500">
                            No hay trabajadores activos.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>
</section>
