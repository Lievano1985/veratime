<?php

use App\Domains\Tenancy\Support\CurrentCompany;
use App\Domains\TimeRecords\Actions\ConfigureAttendanceClockAction;
use App\Models\AttendanceClock;
use App\Models\Company;
use Illuminate\Support\Facades\Gate;
use Livewire\Volt\Component;

new class extends Component {
    public string $clockName = '';
    public string $serial = '';
    public string $centerId = '';
    public string $clockId = '';
    public string $clockUserId = '';
    public string $employeeCode = '';

    public function register(ConfigureAttendanceClockAction $action): void
    {
        $company = $this->company();
        $clock = $action->register($company, $this->validate([
            'clockName' => 'required|string|max:100',
            'serial' => ['required', 'regex:/\A[A-Za-z0-9_-]{1,64}\z/'],
            'centerId' => 'required|integer',
        ]));
        $this->reset('clockName', 'serial', 'centerId');
        $this->clockId = (string) $clock->id;
        session()->flash('status', 'Reloj registrado.');
    }

    public function linkWorker(ConfigureAttendanceClockAction $action): void
    {
        $company = $this->company();
        $action->link($company, $this->validate([
            'clockId' => 'required|integer',
            'clockUserId' => ['required', 'regex:/\A[0-9]{1,32}\z/'],
            'employeeCode' => 'required|string|max:255',
        ]));
        $this->reset('clockUserId', 'employeeCode');
        session()->flash('status', 'Trabajador vinculado al reloj.');
    }

    public function unlinkWorker(int $clockId, int $workerId, string $clockUserId, ConfigureAttendanceClockAction $action): void
    {
        $removed = $action->unlink($this->company(), $clockId, $workerId, $clockUserId);
        session()->flash('status', $removed ? 'Vínculo eliminado. Se conservan las checadas registradas.' : 'El vínculo ya no existe.');
    }

    public function with(): array
    {
        $company = $this->company();

        return [
            'centers' => $company->centers()->where('status', 'active')->orderBy('name')->get(),
            'clocks' => AttendanceClock::query()->where('company_id', $company->id)
                ->with(['center' => fn ($query) => $query->where('company_id', $company->id),
                    'workers' => fn ($query) => $query->where('workers.company_id', $company->id)])
                ->orderBy('name')->get(),
        ];
    }

    private function company(): Company
    {
        $company = app(CurrentCompany::class)->get();
        abort_unless($company, 403);
        Gate::authorize('update', $company);

        return $company;
    }
}; ?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl">Relojes de asistencia</flux:heading>
        <flux:link :href="route('company-settings.index')">Volver a configuración de empresa</flux:link>
    </div>

    @if (session('status'))
        <p role="status">{{ session('status') }}</p>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <form wire:submit="register" class="space-y-4 rounded-lg border border-surface-line p-5">
            <flux:heading>Registrar reloj</flux:heading>
            <flux:input wire:model="clockName" label="Nombre del reloj" required />
            <flux:input wire:model="serial" label="Número de serie" required />
            <flux:select wire:model="centerId" label="Centro de trabajo" required>
                <option value="">Seleccionar centro</option>
                @foreach ($centers as $center)
                    <option value="{{ $center->id }}">{{ $center->name }}</option>
                @endforeach
            </flux:select>
            <flux:button type="submit" variant="primary">Registrar reloj</flux:button>
        </form>

        <form wire:submit="linkWorker" class="space-y-4 rounded-lg border border-surface-line p-5">
            <flux:heading>Vincular trabajador</flux:heading>
            <flux:select wire:model="clockId" label="Reloj" required>
                <option value="">Seleccionar reloj</option>
                @foreach ($clocks as $clock)
                    <option value="{{ $clock->id }}">{{ $clock->name }} · {{ $clock->serial }}</option>
                @endforeach
            </flux:select>
            <flux:input wire:model="clockUserId" label="ID de usuario en el reloj" inputmode="numeric" required />
            <flux:input wire:model="employeeCode" label="Código del trabajador en Vera Time" required />
            <flux:button type="submit" variant="primary" :disabled="$clocks->isEmpty()">Vincular trabajador</flux:button>
        </form>
    </div>

    <section class="space-y-3">
        <flux:heading>Relojes registrados</flux:heading>
        @forelse ($clocks as $clock)
            <div wire:key="clock-{{ $clock->id }}" class="rounded-lg border border-surface-line p-4">
                <flux:heading>{{ $clock->name }}</flux:heading>
                <flux:text>{{ $clock->serial }} · {{ $clock->center?->name }}</flux:text>
                <ul class="mt-2 space-y-1 text-sm">
                    @forelse ($clock->workers as $worker)
                        <li class="flex items-center gap-3">
                            <span>ID {{ $worker->pivot->clock_user_id }} → {{ $worker->employee_code }}</span>
                            <flux:button type="button" size="sm"
                                wire:click="unlinkWorker({{ $clock->id }}, {{ $worker->id }}, @js($worker->pivot->clock_user_id))"
                                wire:confirm="¿Desvincular este ID del trabajador? Las checadas registradas se conservan.">
                                Desvincular
                            </flux:button>
                        </li>
                    @empty
                        <li>Sin trabajadores vinculados.</li>
                    @endforelse
                </ul>
            </div>
        @empty
            <flux:text>Aún no hay relojes registrados en esta empresa.</flux:text>
        @endforelse
    </section>
</div>
