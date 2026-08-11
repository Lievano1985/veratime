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
        $this->resetPage();
        Session::flash('status', 'Incidencia registrada. Recalcula jornadas para aplicar el efecto operativo.');
    }

    public function cancelIncident(int $incidentId, CancelAttendanceIncidentAction $action, CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $incident = AttendanceIncident::query()
            ->where('company_id', $company->id)
            ->findOrFail($incidentId);

        Gate::authorize('cancel', $incident);

        try {
            $action->handle($company, $incident, auth()->user(), 'Cancelacion operativa desde pantalla.');
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'form.worker_id' => $exception->getMessage(),
            ]);
        }

        $this->resetPage();
        Session::flash('status', 'Incidencia cancelada. Recalcula jornadas si el rango ya habia sido procesado.');
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

<section class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">Incidencias y ausencias</flux:heading>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                Registra causas operativas por fecha para que las jornadas y periodos no traten esos dias como faltas pendientes.
            </p>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    <form wire:submit="createIncident" class="rounded-md border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="mb-4">
            <flux:heading>Registrar incidencia</flux:heading>
            <p class="mt-1 text-sm text-zinc-500">
                Esto no calcula nomina. Solo clasifica la asistencia del periodo para cierre y exportacion posterior.
            </p>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <flux:select label="Trabajador" wire:model="form.worker_id">
                <flux:select.option value="">Selecciona trabajador</flux:select.option>
                @foreach ($workers as $worker)
                    <flux:select.option value="{{ $worker->id }}">{{ $worker->employee_code }} - {{ $worker->full_name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input label="Desde" type="date" wire:model="form.start_date" />
            <flux:input label="Hasta" type="date" wire:model="form.end_date" />

            <flux:select label="Tipo" wire:model="form.incident_type">
                @foreach (AttendanceIncident::types() as $type)
                    <flux:select.option value="{{ $type }}">{{ $this->typeLabel($type) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select label="Pago operativo" wire:model="form.payment_status">
                @foreach (AttendanceIncident::paymentStatuses() as $paymentStatus)
                    <flux:select.option value="{{ $paymentStatus }}">{{ $this->paymentLabel($paymentStatus) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input label="Referencia o folio opcional" wire:model="form.reference" placeholder="Ej. IMSS, autorizacion interna o folio RH" />
        </div>

        <div class="mt-4">
            <flux:textarea label="Comentario" wire:model="form.notes" rows="3" placeholder="Contexto operativo para RH. No captures datos sensibles innecesarios." />
        </div>

        <div class="mt-4 flex justify-end">
            <flux:button type="submit" variant="primary">Guardar incidencia</flux:button>
        </div>
    </form>

    <section class="rounded-md border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 p-4 dark:border-zinc-700">
            <div class="grid gap-3 md:grid-cols-[1fr_220px_auto]">
                <flux:input label="Buscar" wire:model.live.debounce.300ms="search" placeholder="Clave o nombre" />
                <flux:select label="Estado" wire:model.live="statusFilter">
                    <flux:select.option value="">Todos</flux:select.option>
                    <flux:select.option value="approved">Aprobadas</flux:select.option>
                    <flux:select.option value="cancelled">Canceladas</flux:select.option>
                </flux:select>
                <div class="flex items-end">
                    <flux:button type="button" variant="ghost" wire:click="clearFilters">Limpiar</flux:button>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-zinc-50 text-xs uppercase text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                    <tr>
                        <th class="px-4 py-3">Trabajador</th>
                        <th class="px-4 py-3">Rango</th>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3">Pago</th>
                        <th class="px-4 py-3">Referencia</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3 text-right">Accion</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($incidents as $incident)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ $incident->worker?->employee_code }} - {{ $incident->worker?->full_name }}</div>
                                <div class="text-xs text-zinc-500">{{ $incident->employmentRelationship?->center?->name ?? 'Sin centro vigente' }}</div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                {{ $incident->start_date?->toDateString() }} a {{ $incident->end_date?->toDateString() }}
                            </td>
                            <td class="px-4 py-3">{{ $this->typeLabel($incident->incident_type) }}</td>
                            <td class="px-4 py-3">{{ $this->paymentLabel($incident->payment_status) }}</td>
                            <td class="px-4 py-3">{{ $incident->reference ?: 'Sin referencia' }}</td>
                            <td class="px-4 py-3">
                                <x-ui.badge variant="{{ $this->statusVariant($incident->status) }}">
                                    {{ $this->statusLabel($incident->status) }}
                                </x-ui.badge>
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if ($incident->status === AttendanceIncident::STATUS_APPROVED)
                                    <flux:button
                                        type="button"
                                        size="xs"
                                        variant="danger"
                                        wire:click="cancelIncident({{ $incident->id }})"
                                        wire:confirm="Cancelar esta incidencia no borra jornadas ya calculadas. Deberas recalcular el rango si aplica. ¿Continuar?"
                                    >
                                        Cancelar
                                    </flux:button>
                                @else
                                    <span class="text-xs text-zinc-500">Sin accion</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-zinc-500">Sin incidencias registradas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-zinc-200 p-4 dark:border-zinc-700">
            {{ $incidents->links() }}
        </div>
    </section>
</section>
