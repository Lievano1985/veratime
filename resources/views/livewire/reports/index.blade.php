<?php

use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\AttendancePeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Url(except: 'generate')]
    public string $tab = 'generate';

    public function with(CurrentCompany $currentCompany): array
    {
        $company = $currentCompany->get();
        abort_unless($company, 403);
        Gate::authorize('viewAny', [AttendancePeriod::class, $company]);

        if ($this->tab === 'history') {
            return ['issuedReports' => DB::table('issued_attendance_reports')->where('company_id', $company->id)
                ->select('id', 'title', 'scope', 'period_start', 'period_end', 'issuer_name', 'created_at')
                ->orderByDesc('created_at')->orderByDesc('id')->paginate(15), 'reportTimezone' => $company->timezone];
        }

        return [
            'centers' => $company->centers()->orderBy('name')->get(['id', 'name']),
            'units' => $company->organizationalUnits()->with('center')->orderBy('name')->get(),
            'workers' => $company->workers()->orderBy('full_name')->get(['id', 'full_name', 'employee_code']),
        ];
    }
};
?>

<section class="space-y-5 p-4 lg:p-6">
    <div>
        <flux:heading size="xl">Reportes de jornada</flux:heading>
        <flux:subheading>Consulta las opciones de reporte y exportación de Vera Time.</flux:subheading>
    </div>

    <nav class="flex flex-wrap gap-2" aria-label="Secciones de reportes">
        <a href="{{ route('reports.index') }}" wire:navigate class="{{ $tab !== 'history' ? 'btn-primary' : 'btn-outline' }}">Generar reporte</a>
        <a href="{{ route('reports.index', ['tab' => 'history']) }}" wire:navigate class="{{ $tab === 'history' ? 'btn-primary' : 'btn-outline' }}">Historial de expedientes</a>
    </nav>

    @if ($tab === 'history')
        <section class="space-y-4 rounded-2xl border border-surface-line bg-white p-5">
            <flux:heading>Expedientes emitidos</flux:heading>
            <div class="table-wrap">
                <table class="w-full">
                    <thead><tr>
                        @foreach (['Folio y reporte', 'Periodo', 'Alcance', 'Emitido por', 'Fecha de emisión', 'Acciones'] as $column)
                            <th scope="col" class="table-head-cell">{{ $column }}</th>
                        @endforeach
                    </tr></thead>
                    <tbody>
                        @forelse ($issuedReports as $issued)
                            <tr class="table-row">
                                <td class="table-cell"><strong>{{ $issued->title }}</strong><span class="block text-xs font-mono">{{ $issued->id }}</span></td>
                                <td class="table-cell whitespace-nowrap">{{ $issued->period_start }} al {{ $issued->period_end }}</td>
                                <td class="table-cell">{{ $issued->scope }}</td>
                                <td class="table-cell">{{ $issued->issuer_name }}</td>
                                <td class="table-cell whitespace-nowrap">{{ \Carbon\CarbonImmutable::parse($issued->created_at, 'UTC')->timezone($reportTimezone)->format('d/m/Y H:i') }}</td>
                                <td class="table-cell">
                                    <div class="flex gap-2">
                                        <a class="btn-outline btn-sm" href="{{ route('reports.issued', $issued->id) }}">Ver</a>
                                        <a class="btn-outline btn-sm" href="{{ route('reports.issued', ['folio' => $issued->id, 'format' => 'xlsx']) }}">Excel</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="table-cell text-center">Todavía no hay expedientes emitidos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $issuedReports->links() }}
        </section>
    @else
    <section class="space-y-4 rounded-2xl border border-surface-line bg-white p-5" aria-labelledby="inspection-report-title">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 id="inspection-report-title" class="font-semibold text-brand-navy">Registro de jornada y cumplimiento semanal</h2>
        </div>
        <p class="text-sm text-surface-muted">Consulta las jornadas, el detalle de entradas, salidas y pausas, y las capturas manuales o anulaciones del rango seleccionado.</p>
        @if ($errors->any())
            <ul role="alert" class="space-y-1 text-sm text-red-700">
                @foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach
            </ul>
        @endif
        <form action="{{ route('reports.inspection') }}" method="get" class="space-y-4" x-data="{ scope: @js(old('scope', 'company')) }">
            <flux:select name="report" label="Tipo de reporte">
                <option value="inspection" @selected(old('report') !== 'weekly')>Registro de jornada para inspección STPS</option>
                <option value="weekly" @selected(old('report') === 'weekly')>Cumplimiento semanal</option>
            </flux:select>
            <div class="grid gap-4 sm:grid-cols-3">
                <flux:input type="date" name="from" label="Desde" :value="old('from')" required />
                <flux:input type="date" name="to" label="Hasta" :value="old('to')" required />
                <flux:select name="scope" label="Consultar" x-model="scope">
                    <flux:select.option value="company">Por periodo · Toda la empresa</flux:select.option>
                    <flux:select.option value="center">Por centro</flux:select.option>
                    <flux:select.option value="unit">Por unidad o departamento</flux:select.option>
                    <flux:select.option value="worker">Por empleado</flux:select.option>
                </flux:select>
            </div>
            <div x-show="scope === 'center'" x-cloak>
                <flux:select name="center" label="Centro" x-bind:disabled="scope !== 'center'">
                    <flux:select.option value="">Selecciona un centro</flux:select.option>
                    @foreach ($centers as $center)<option value="{{ $center->id }}" @selected(old('center') == $center->id)>{{ $center->name }}</option>@endforeach
                </flux:select>
            </div>
            <div x-show="scope === 'unit'" x-cloak>
                <flux:select name="unit" label="Unidad o departamento" x-bind:disabled="scope !== 'unit'">
                    <flux:select.option value="">Selecciona una unidad</flux:select.option>
                    @foreach ($units as $unit)<option value="{{ $unit->id }}" @selected(old('unit') == $unit->id)>{{ $unit->center?->name }} · {{ $unit->name }}</option>@endforeach
                </flux:select>
            </div>
            <div x-show="scope === 'worker'" x-cloak>
                <flux:select name="worker" label="Empleado" x-bind:disabled="scope !== 'worker'">
                    <flux:select.option value="">Selecciona un empleado</flux:select.option>
                    @foreach ($workers as $worker)<option value="{{ $worker->id }}" @selected(old('worker') == $worker->id)>{{ $worker->employee_code }} · {{ $worker->full_name }}</option>@endforeach
                </flux:select>
            </div>
            <button type="submit" class="btn-primary">Vista previa</button>
        </form>
    </section>

    <a href="{{ route('attendance-periods.index') }}" wire:navigate class="btn-outline">Ir a Exportar períodos</a>
    @endif
</section>
