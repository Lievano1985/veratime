<?php

use App\Domains\AttendanceIncidents\Actions\CancelAttendanceIncidentAction;
use App\Domains\AttendanceIncidents\Actions\CreateAttendanceIncidentAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\AttendanceIncident;
use App\Models\Worker;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url(as: 'status')]
    public string $statusFilter = '';
    public bool $showCreatePanel = false;

    public array $form = [
        'worker_id' => '',
        'start_date' => '',
        'end_date' => '',
        'incident_type' => AttendanceIncident::TYPE_VACATION,
        'payment_status' => AttendanceIncident::PAYMENT_PAID,
        'reference' => '',
        'notes' => '',
    ];

    public function mount(CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('viewAny', [AttendanceIncident::class, $company]);
    }

    public function createIncident(CreateAttendanceIncidentAction $action, CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('create', [AttendanceIncident::class, $company]);

        $validated = $this->validate([
            'form.worker_id' => [
                'required',
                'integer',
                Rule::exists('workers', 'id')->where('company_id', $company->id)->where('status', 'active'),
            ],
            'form.start_date' => ['required', 'date'],
            'form.end_date' => ['required', 'date', 'after_or_equal:form.start_date'],
            'form.incident_type' => ['required', Rule::in(AttendanceIncident::types())],
            'form.payment_status' => ['required', Rule::in(AttendanceIncident::paymentStatuses())],
            'form.reference' => ['nullable', 'string', 'max:120'],
            'form.notes' => ['nullable', 'string', 'max:1000'],
        ])['form'];

        try {
            $action->handle($company, auth()->user(), $validated);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'form.worker_id' => $exception->getMessage(),
            ]);
        }

        $this->resetForm();
        $this->showCreatePanel = false;
        $this->resetPage();
        Session::flash('status', 'Incidencia registrada. Recalcula jornadas para aplicar el efecto operativo.');
    }

    public function openCreatePanel(CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('create', [AttendanceIncident::class, $company]);

        $this->resetForm();
        $this->resetValidation();
        $this->showCreatePanel = true;
    }

    public function closeCreatePanel(): void
    {
        $this->showCreatePanel = false;
        $this->resetForm();
        $this->resetValidation();
    }

    public function cancelIncident(int $incidentId, CancelAttendanceIncidentAction $action, CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $incident = AttendanceIncident::query()
            ->where('company_id', $company->id)
            ->findOrFail($incidentId);

        Gate::authorize('cancel', $incident);

        try {
        $action->handle($company, $incident, auth()->user(), 'Cancelación operativa desde pantalla.');
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'form.worker_id' => $exception->getMessage(),
            ]);
        }

        $this->resetPage();
        Session::flash('status', 'Incidencia cancelada. Recalcula jornadas si el rango ya había sido procesado.');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->resetPage();
    }

    public function with(CurrentCompany $currentCompany): array
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('viewAny', [AttendanceIncident::class, $company]);

        $incidents = AttendanceIncident::query()
            ->with(['worker', 'employmentRelationship.center', 'creator', 'canceller'])
            ->where('company_id', $company->id)
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->when(trim($this->search) !== '', function ($query): void {
                $term = trim($this->search);
                $query->whereHas('worker', function ($workerQuery) use ($term): void {
                    $workerQuery
                        ->where('employee_code', 'like', "%{$term}%")
                        ->orWhere('full_name', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->paginate(10);

        return [
            'company' => $company,
            'workers' => Worker::query()
                ->where('company_id', $company->id)
                ->where('status', 'active')
                ->orderBy('employee_code')
                ->get(),
            'incidents' => $incidents,
        ];
    }

    private function resetForm(): void
    {
        $this->form = [
            'worker_id' => '',
            'start_date' => '',
            'end_date' => '',
            'incident_type' => AttendanceIncident::TYPE_VACATION,
            'payment_status' => AttendanceIncident::PAYMENT_PAID,
            'reference' => '',
            'notes' => '',
        ];
    }

    private function currentCompanyOrFail(CurrentCompany $currentCompany)
    {
        $company = $currentCompany->get();

        abort_unless($company, 403);

        return $company;
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            AttendanceIncident::TYPE_VACATION => 'Vacaciones',
            AttendanceIncident::TYPE_INCAPACITY => 'Incapacidad',
            AttendanceIncident::TYPE_PAID_PERMISSION => 'Permiso con goce',
            AttendanceIncident::TYPE_UNPAID_PERMISSION => 'Permiso sin goce',
            AttendanceIncident::TYPE_JUSTIFIED_PAID_ABSENCE => 'Falta justificada pagada',
            AttendanceIncident::TYPE_JUSTIFIED_UNPAID_ABSENCE => 'Falta justificada no pagada',
            AttendanceIncident::TYPE_UNJUSTIFIED_ABSENCE => 'Falta injustificada',
            AttendanceIncident::TYPE_MATERNITY_PATERNITY => 'Maternidad / paternidad',
            default => 'Otro',
        };
    }

    private function paymentLabel(string $paymentStatus): string
    {
        return match ($paymentStatus) {
            AttendanceIncident::PAYMENT_PAID => 'Pagada',
            AttendanceIncident::PAYMENT_UNPAID => 'No pagada',
            default => 'No aplica',
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            AttendanceIncident::STATUS_APPROVED => 'Aprobada',
            AttendanceIncident::STATUS_CANCELLED => 'Cancelada',
            default => ucfirst($status),
        };
    }

    private function statusVariant(string $status): string
    {
        return $status === AttendanceIncident::STATUS_APPROVED ? 'success' : 'neutral';
    }
}; ?>

<section class="space-y-6 bg-surface-bg p-6 text-surface-text">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="font-display text-2xl font-bold text-brand-navy">Incidencias y ausencias</h1>
            <p class="mt-1.5 text-[13.5px] text-surface-muted">
                Registra causas operativas por fecha para que las jornadas y periodos no traten esos días como faltas pendientes.
            </p>
        </div>

        <button type="button" class="btn-primary" wire:click="openCreatePanel">
            <span class="text-base leading-none">+</span>
            Agregar incidencia
        </button>
    </div>

    @if (session('status'))
        <div class="rounded-xl border border-status-rest-line bg-status-rest-bg px-4 py-3 text-sm font-medium text-status-rest-text">
            {{ session('status') }}
        </div>
    @endif

    <section class="rounded-2xl border border-surface-line bg-surface-card shadow-[0_20px_50px_-34px_rgba(2,25,57,0.22)]">
        <div class="border-b border-surface-line p-5">
            <div class="grid gap-3 md:grid-cols-[1fr_220px_auto]">
                <flux:input label="Buscar" wire:model.live.debounce.300ms="search" placeholder="Clave o nombre" />
                <flux:select label="Estado" wire:model.live="statusFilter">
                    <flux:select.option value="">Todos</flux:select.option>
                    <flux:select.option value="approved">Aprobadas</flux:select.option>
                    <flux:select.option value="cancelled">Canceladas</flux:select.option>
                </flux:select>
                <div class="flex items-end">
                    <button type="button" class="btn-ghost" wire:click="clearFilters">Limpiar</button>
                </div>
            </div>
        </div>

        <div class="table-wrap rounded-none border-x-0 border-t-0">
            <table class="w-full min-w-[1100px] border-collapse text-left text-sm">
                <thead>
                    <tr>
                        <th class="table-head-cell">Trabajador</th>
                        <th class="table-head-cell">Rango</th>
                        <th class="table-head-cell">Tipo</th>
                        <th class="table-head-cell">Pago</th>
                        <th class="table-head-cell">Referencia</th>
                        <th class="table-head-cell">Estado</th>
                        <th class="table-head-cell text-right">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($incidents as $incident)
                        <tr class="table-row">
                            <td class="table-cell">
                                <div class="font-semibold text-brand-navy">{{ $incident->worker?->employee_code }} - {{ $incident->worker?->full_name }}</div>
                                <div class="text-xs text-surface-muted">{{ $incident->employmentRelationship?->center?->name ?? 'Sin centro vigente' }}</div>
                            </td>
                            <td class="table-cell whitespace-nowrap">
                                {{ $incident->start_date?->toDateString() }} a {{ $incident->end_date?->toDateString() }}
                            </td>
                            <td class="table-cell">{{ $this->typeLabel($incident->incident_type) }}</td>
                            <td class="table-cell">{{ $this->paymentLabel($incident->payment_status) }}</td>
                            <td class="table-cell">{{ $incident->reference ?: 'Sin referencia' }}</td>
                            <td class="table-cell">
                                <x-ui.badge variant="{{ $this->statusVariant($incident->status) }}">
                                    {{ $this->statusLabel($incident->status) }}
                                </x-ui.badge>
                            </td>
                            <td class="table-cell text-right">
                                @if ($incident->status === AttendanceIncident::STATUS_APPROVED)
                                    <button
                                        type="button"
                                        class="btn-icon"
                                        wire:click="cancelIncident({{ $incident->id }})"
                                        wire:confirm="Cancelar esta incidencia no borra jornadas ya calculadas. Deberás recalcular el rango si aplica. ¿Continuar?"
                                        aria-label="Cancelar incidencia"
                                        title="Cancelar"
                                    >
                                        <svg class="h-4 w-4 text-status-pending-text" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M18.36 5.64 5.64 18.36M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </button>
                                @else
                                    <span class="text-xs text-surface-muted">Sin acción</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="table-cell py-8 text-center text-surface-muted">Sin incidencias registradas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-surface-line p-4">
            {{ $incidents->links() }}
        </div>
    </section>

    <x-side-panel wire:model="showCreatePanel" title="Registrar incidencia" subheading="Clasifica ausencias o permisos para el cierre operativo." labelledby="attendance-incident-create-title" max-width="max-w-3xl">
        <form wire:submit="createIncident" class="flex flex-1 flex-col overflow-y-auto">
            <div class="flex-1 space-y-5 p-6">
                <div class="rounded-xl border border-brand-blue/20 bg-brand-blue/5 px-4 py-3 text-sm text-brand-navy">
                    Esto no calcula nómina. Solo clasifica la asistencia del periodo para cierre y exportación posterior.
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <flux:select label="Trabajador" wire:model="form.worker_id">
                        <flux:select.option value="">Selecciona trabajador</flux:select.option>
                        @foreach ($workers as $worker)
                            <flux:select.option value="{{ $worker->id }}">{{ $worker->employee_code }} - {{ $worker->full_name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select label="Tipo" wire:model="form.incident_type">
                        @foreach (AttendanceIncident::types() as $type)
                            <flux:select.option value="{{ $type }}">{{ $this->typeLabel($type) }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input label="Desde" type="date" wire:model="form.start_date" />
                    <flux:input label="Hasta" type="date" wire:model="form.end_date" />

                    <flux:select label="Pago operativo" wire:model="form.payment_status">
                        @foreach (AttendanceIncident::paymentStatuses() as $paymentStatus)
                            <flux:select.option value="{{ $paymentStatus }}">{{ $this->paymentLabel($paymentStatus) }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input label="Referencia o folio opcional" wire:model="form.reference" placeholder="Ej. IMSS, autorización interna o folio RH" />
                </div>

                <flux:textarea label="Comentario" wire:model="form.notes" rows="4" placeholder="Contexto operativo para RH. No captures datos sensibles innecesarios." />
            </div>

            <div class="flex justify-end gap-3 border-t border-surface-line p-6">
                <button type="button" class="btn-ghost" wire:click="closeCreatePanel">Cancelar</button>
                <button type="submit" class="btn-primary">Guardar incidencia</button>
            </div>
        </form>
    </x-side-panel>
</section>
