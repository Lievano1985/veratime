@if ($workspaceTab === 'incidencias')
    <section class="space-y-4">
        <h2 class="text-lg font-semibold">Incidencias calculadas</h2>
        <p class="text-sm text-zinc-500">Primero atiende los casos diarios pendientes, del más antiguo al más reciente. Después revisa los acumulados semanales.</p>
        <p class="rounded-lg bg-blue-50 p-3 text-sm text-blue-900 dark:bg-blue-950 dark:text-blue-200">Al corregir los marcajes y recalcular una jornada se actualizan automáticamente los acumulados y avisos de esa semana para esa persona. Registrar una revisión no cambia las horas calculadas.</p>
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700"><table class="w-full text-left text-sm">
            <thead class="bg-zinc-50 dark:bg-zinc-800"><tr><th class="p-3">Fecha / semana</th><th class="p-3">Trabajador</th><th class="p-3">Incidencia</th><th class="p-3">Revisión</th><th class="p-3">Acción</th></tr></thead>
            <tbody>@forelse ($workspaceSummary['incident_cases'] as $group)
                @php($case = $group['alert'])
                @php($periodStart = \Carbon\CarbonImmutable::parse($case->workDay->work_date)->startOfWeek())
                <tr class="border-t border-zinc-200 dark:border-zinc-700" wire:key="incident-{{ $group['key'] }}">
                    <td class="p-3">{{ $group['weekly'] ? $periodStart->format('d/m/Y').' – '.$periodStart->endOfWeek()->format('d/m/Y') : $case->workDay->work_date->format('d/m/Y') }}<span class="block text-xs text-zinc-500">{{ $group['weekly'] ? 'Semanal · Acumulado sujeto a recálculo' : 'Diaria' }}</span></td>
                    <td class="p-3">{{ $case->worker?->full_name }}<span class="block text-xs text-zinc-500">{{ $case->worker?->employee_code }} · {{ $case->workDay?->center?->name }}</span></td>
                    <td class="p-3">
                        @if (! $group['weekly'])<p class="mb-2 font-medium">{{ $this->workedMinutesLabel($case->workDay) }} trabajadas · {{ $this->workDayCalculationSplitLabel($case->workDay, 'overtime_minutes') }} extra</p>@endif
                        @if ($group['weekly'])<p class="mb-1 font-medium">{{ $case->title }}</p><p class="mb-2 text-xs text-zinc-500">{{ $case->description }}</p>@endif
                        @if ($group['current_alerts']->isNotEmpty())
                            <details><summary class="cursor-pointer text-blue-700 dark:text-blue-300">Ver {{ $group['current_alerts']->count() }} avisos vigentes</summary><ul class="mt-2 space-y-1">@foreach ($group['current_alerts'] as $item)<li>{{ $item->title }} <span class="text-xs text-zinc-500">· {{ in_array($item->status, \App\Models\Alert::OPEN_STATUSES, true) ? 'Pendiente' : 'Revisado' }}</span></li>@endforeach</ul></details>
                        @else
                            <p class="text-sm text-zinc-500">Sin avisos vigentes.</p>
                        @endif
                        @if ($group['historical_alerts']->isNotEmpty())
                            <details class="mt-3"><summary class="cursor-pointer text-zinc-500">Ver historial ({{ $group['historical_alerts']->count() }})</summary><ul class="mt-2 space-y-2">@foreach ($group['historical_alerts'] as $item)<li>{{ $item->title }}<span class="block text-xs text-zinc-500">Ya no aplica tras recálculo</span></li>@endforeach</ul></details>
                        @endif
                    </td>
                    <td class="p-3">{{ $group['pending'] ? $group['pending'].' pendientes' : ($group['current_alerts']->isEmpty() ? 'Sin avisos vigentes' : 'Seguimiento cerrado') }}@if (! $group['pending'])<span class="block text-xs text-zinc-500">Se conserva el historial.</span>@endif</td>
                    <td class="p-3"><button type="button" wire:click="openIncident({{ $case->id }})" class="rounded-lg border border-blue-200 px-3 py-2 font-semibold text-blue-700 dark:text-blue-300">{{ $group['pending'] ? 'Atender caso' : 'Ver seguimiento' }}</button></td>
                </tr>
            @empty <tr><td colspan="5" class="p-6 text-center text-zinc-500">Sin incidencias para estos filtros.</td></tr> @endforelse</tbody>
        </table></div>
        {{ $workspaceSummary['incident_cases']->links() }}
    </section>
@else
    @php($prevention = $workspaceSummary['prevention'])
    <section class="space-y-4">
        <h2 class="text-lg font-semibold">Prevención semanal</h2>
        <p class="text-sm text-zinc-500">Semana {{ $prevention['start'] }} al {{ $prevention['end'] }} · Acumulado al {{ $prevention['as_of'] }}. Aviso desde el 80 % del umbral configurado. Los límites se resuelven para la fecha de esa semana.</p>
        <p class="rounded-lg bg-blue-50 p-3 text-sm text-blue-900 dark:bg-blue-950 dark:text-blue-200">La proyección suma tiempo registrado y duración de días futuros publicados. No predice la asistencia ni clasifica ese tiempo futuro como extra. Sin programación restante se muestra “Sin proyección”.</p>
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700"><table class="w-full text-left text-sm">
            <thead class="bg-zinc-50 dark:bg-zinc-800"><tr><th class="p-3">Trabajador</th><th class="p-3">Registrado</th><th class="p-3">Margen disponible</th><th class="p-3">Programación restante</th><th class="p-3">Proyección total</th><th class="p-3">Seguimiento</th></tr></thead>
            <tbody>@forelse ($prevention['people'] as $person)
                <tr class="border-t border-zinc-200 dark:border-zinc-700">
                    <td class="p-3">{{ $person['name'] }}<span class="block text-xs text-zinc-500">{{ $person['code'] }}</span></td>
                    <td class="p-3">Ordinarias: {{ $this->minutesLabel($person['ordinary_minutes']) }}<br>Extra: {{ $this->minutesLabel($person['overtime_minutes']) }}</td>
                    <td class="p-3">Ordinarias: {{ $this->minutesLabel($person['ordinary_margin_minutes']) }}<span class="block text-xs text-zinc-500">Umbral: {{ $this->minutesLabel($person['ordinary_limit_minutes']) }}</span>Extra: {{ $this->minutesLabel($person['overtime_margin_minutes']) }}<span class="block text-xs text-zinc-500">Umbral: {{ $this->minutesLabel($person['overtime_limit_minutes']) }}</span></td>
                    <td class="p-3">{{ $person['remaining_days'] }} días<br>{{ $this->minutesLabel($person['planned_minutes']) }} programadas @if ($person['unknown_schedule_days'] > 0)<span class="block text-xs text-amber-700">{{ $person['unknown_schedule_days'] }} días sin duración definida</span>@endif</td>
                    <td class="p-3">{{ $person['projected_total_minutes'] === null ? 'Sin proyección' : $this->minutesLabel($person['projected_total_minutes']) }}</td>
                    <td class="p-3">{{ $person['remaining_days'] > 0 ? 'Riesgo preventivo' : 'Acumulado cercano al umbral' }}<span class="block text-xs text-amber-700 dark:text-amber-300">{{ $person['pending_days'] }} jornadas pendientes de completar</span></td>
                </tr>
            @empty <tr><td colspan="6" class="p-6 text-center text-zinc-500">Sin personas cercanas al umbral ni riesgo proyectado con los datos disponibles.</td></tr> @endforelse</tbody>
        </table></div>
    </section>
@endif
