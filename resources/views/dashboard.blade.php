<x-layouts.app>
    @php
        $metrics = $summary['metrics'];
        $coverage = $summary['attendance_coverage'];
        $coveragePercent = $coverage['percentage'] ?? 0;
        $coverageArcLength = pi() * 76;
        $pendingAlerts = collect($summary['pending_alerts']);
        $workerIncidents = $summary['worker_incidents'];
        $laborCompliance = $summary['labor_compliance'];
        $formatHours = fn (?int $minutes): string => $minutes === null ? '—' : rtrim(rtrim(number_format($minutes / 60, 1, '.', ''), '0'), '.').' h';
        $operationalCards = [
            ['Trabajadores activos', $metrics['active_workers'], 'users'],
            ['Personal por ingresar', $metrics['pending_entry'], 'user-plus'],
            ['Trabajando ahora', $metrics['working_now'], 'clock'],
            ['Jornadas finalizadas', $metrics['completed_work_days'], 'check-circle'],
        ];
        $pendingAlertIcons = [
            'late_arrival_detected' => 'clock',
            'early_departure_detected' => 'arrow-right-circle',
            'incomplete_work_day' => 'exclamation-triangle',
            'daily_limit_exceeded' => 'exclamation-triangle',
            'daily_overtime_over_three_hours' => 'bolt',
            'overtime_detected' => 'clock',
            'long_work_day' => 'clock',
            'minimum_break_missing' => 'pause-circle',
            'twelve_hours_exceeded' => 'exclamation-circle',
            'sunday_work' => 'sun',
            'mandatory_rest_work' => 'calendar-days',
            'scheduled_rest_work' => 'calendar',
        ];
    @endphp

    <div class="flex w-full flex-1 flex-col gap-6 rounded-xl p-2 sm:p-4 dark:bg-zinc-950">

        <flux:heading size="xl">Dashboard Vera Time</flux:heading>



        <section class="rounded-[22px] border border-[#E4EAF2] bg-[#F7F9FC] p-5 shadow-[0_10px_30px_rgba(2,25,57,0.04)] dark:border-zinc-700 dark:bg-zinc-900 sm:p-6">
            <div class="text-center">
                <h2 class="text-xl font-semibold text-[#5B73F6]">Operaci&oacute;n de la jornada</h2>
                <p class="mt-1 text-sm text-[#6B7A90] dark:text-zinc-400">Asistencia y marcajes del día consultado.</p>
            </div>
            <div class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @foreach ($operationalCards as [$label, $value, $icon])
                        <article class="flex min-h-40 flex-col rounded-2xl border border-[#E4EAF2] bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                            <x-dashboard.metric-icon :icon="$icon" />
                            <p class="mt-4 text-center text-sm font-medium text-[#53617A] dark:text-zinc-300">{{ $label }}</p>
                            <p class="mt-1 text-center text-3xl font-bold tracking-tight text-brand-blue dark:text-blue-300">{{ $value }}</p>
                        </article>
                    @endforeach
                </div>

                <article class="flex min-h-[21rem] flex-col overflow-hidden rounded-2xl border border-[#E4EAF2] bg-white shadow-[0_6px_18px_rgba(2,25,57,0.035)] dark:border-zinc-700 dark:bg-zinc-800">
                    <div class="px-5 pt-5 text-center">
                        <p class="text-lg font-semibold tracking-tight text-[#10213A] dark:text-white">Cobertura de asistencia</p>
                        <p class="mt-1 text-sm text-[#6B7A90] dark:text-zinc-400">Personal esperado que ya registró su entrada.</p>
                    </div>
                    <div class="relative mx-auto mt-2 flex h-56 w-80 max-w-full items-end justify-center">
                        <svg class="h-52 w-80 max-w-full" viewBox="0 0 200 120" aria-label="{{ $coverage['percentage'] === null ? 'Sin personal esperado' : 'Cobertura de asistencia: '.$coveragePercent.' por ciento' }}">
                            <path d="M 24 100 A 76 76 0 0 1 176 100" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="9" class="text-[#E7EBF2] dark:text-zinc-700" />
                            <path d="M 24 100 A 76 76 0 0 1 176 100" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="9" class="text-[#5B73F6]" stroke-dasharray="{{ $coverageArcLength }}" stroke-dashoffset="{{ $coverageArcLength - ($coverageArcLength * ($coveragePercent / 100)) }}" />
                        </svg>
                        <div class="absolute bottom-3 text-center">
                            <p class="text-4xl font-bold tracking-tight text-[#10213A] dark:text-white">{{ $coverage['percentage'] === null ? '—' : $coverage['percentage'].'%' }}</p>
                            <p class="mt-1 text-[11px] font-medium uppercase tracking-wide text-[#6B7A90]">Cobertura</p>
                        </div>
                    </div>
                    <p class="px-5 pb-5 text-center text-sm leading-6 text-[#53617A] dark:text-zinc-300">
                        @if ($coverage['expected_workers'] > 0)
                            <strong class="font-semibold text-[#10213A] dark:text-white">{{ $coverage['attended_workers'] }}</strong> de {{ $coverage['expected_workers'] }} personas esperadas registraron entrada, incluidas quienes ya finalizaron.
                        @else
                            Sin personal esperado según la programación publicada y las ausencias autorizadas.
                        @endif
                    </p>
                    <div class="mt-auto grid grid-cols-3 divide-x divide-[#E4EAF2] bg-[#F4F6FA] px-2 py-3 text-center dark:divide-zinc-700 dark:bg-zinc-900/60">
                        <div><p class="text-[11px] text-[#6B7A90]">Esperados</p><p class="mt-0.5 text-sm font-bold text-[#10213A] dark:text-white">{{ $coverage['expected_workers'] }}</p></div>
                        <div><p class="text-[11px] text-[#6B7A90]">En turno</p><p class="mt-0.5 text-sm font-bold text-[#5B73F6]">{{ $metrics['working_now'] }}</p></div>
                        <div><p class="text-[11px] text-[#6B7A90]">Completadas</p><p class="mt-0.5 text-sm font-bold text-[#5B73F6]">{{ $metrics['completed_work_days'] }}</p></div>
                    </div>
                </article>
            </div>
        </section>

        <section class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="relative flex flex-col items-center text-center">
                <div><h2 class="text-xl font-semibold text-[#0067E4]">Incidencias pendientes</h2><p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Conteo acumulado de incidencias que siguen sin resolver, por tipo.</p></div>
                <a href="{{ route('work-days.index', ['acumulado' => 1, 'atencion' => 'requires_attention']) }}" wire:navigate class="mt-2 text-sm font-semibold text-primary hover:underline sm:absolute sm:right-0 sm:top-0 sm:mt-0">Abrir bandeja de jornadas</a>
            </div>
            <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                @forelse ($pendingAlerts->filter(fn (array $alert): bool => $alert['count'] > 0) as $alert)
                    @php($icon = $pendingAlertIcons[$alert['key']] ?? 'exclamation-circle')
                    <a href="{{ route('work-days.index', ['acumulado' => 1, 'atencion' => 'requires_attention', 'situacion' => $alert['key'] === 'manual' ? '' : $alert['key']]) }}" wire:navigate class="flex min-h-40 flex-col rounded-2xl border border-[#E4EAF2] bg-white p-5 text-left shadow-[0_6px_18px_rgba(2,25,57,0.035)] transition hover:-translate-y-0.5 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800">
                        <x-dashboard.metric-icon :icon="$icon" :tone="in_array($alert['level'], ['critical', 'high'], true) ? 'rose' : 'amber'" />
                        <p class="mt-4 text-center text-sm font-medium text-[#53617A] dark:text-zinc-300">{{ $alert['title'] }}</p>
                        <span class="mt-1 text-center text-3xl font-bold tracking-tight text-brand-blue dark:text-blue-300">{{ $alert['count'] }}</span>
                    </a>
                @empty
                    <p class="col-span-full py-4 text-center text-sm text-zinc-500 dark:text-zinc-400">No hay incidencias pendientes por atender.</p>
                @endforelse
            </div>
        </section>

        <section id="labor-compliance" class="scroll-mt-6 rounded-[22px] border border-[#E4EAF2] bg-[#F7F9FC] p-5 shadow-[0_10px_30px_rgba(2,25,57,0.04)] dark:border-zinc-700 dark:bg-zinc-900 sm:p-6">
            <div class="text-center">
                <h2 class="text-xl font-semibold text-[#5B73F6]">Cumplimiento laboral semanal</h2>
                <p class="mt-1 text-sm text-[#6B7A90] dark:text-zinc-400">Indicadores preventivos de jornada conforme a los límites legales vigentes.</p>
                <p class="mt-2 text-xs text-[#53617A] dark:text-zinc-300">Semana {{ \Carbon\CarbonImmutable::parse($laborCompliance['week_start'])->isoWeek() }} · Resultados cerrados del {{ \Carbon\CarbonImmutable::parse($laborCompliance['week_start'])->isoFormat('D [de] MMMM') }} al {{ \Carbon\CarbonImmutable::parse($laborCompliance['week_end'])->isoFormat('D [de] MMMM [de] YYYY') }}.</p>
            </div>
            <form method="GET" action="{{ route('dashboard') }}#labor-compliance" class="mt-3 flex justify-center sm:justify-end">
                <input type="hidden" name="date" value="{{ $summary['date'] }}" />
                @if (request()->filled('center'))<input type="hidden" name="center" value="{{ request('center') }}" />@endif
                <label for="compliance-week" class="sr-only">Semana de cumplimiento</label>
                <select id="compliance-week" name="compliance_week" onchange="this.form.requestSubmit()" class="max-w-full rounded-lg border border-[#E4EAF2] bg-transparent px-3 py-1.5 text-xs text-[#53617A] focus:border-blue-400 focus:ring-blue-400 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                    @foreach ($laborCompliance['week_options'] as $week)
                        <option value="{{ $week['value'] }}" @selected($week['value'] === $laborCompliance['week_start'])>{{ $week['label'] }}</option>
                    @endforeach
                </select>
                <noscript><button type="submit" class="ml-2 text-xs font-semibold text-blue-700">Consultar</button></noscript>
            </form>

            <div class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                <article class="flex min-h-[21rem] flex-col overflow-hidden rounded-2xl border border-[#E4EAF2] bg-white shadow-[0_6px_18px_rgba(2,25,57,0.035)] dark:border-zinc-700 dark:bg-zinc-800 xl:order-1">
                    <div class="px-6 pb-5 pt-6 text-center">
                        <p class="text-base font-semibold text-[#0B1B2B] dark:text-white">Cumplimiento semanal</p>
                        <p class="mt-1 text-sm text-[#6B7A90] dark:text-zinc-400">Personas sin desviaciones preventivas.</p>

                        <div class="relative mx-auto mt-4 h-52 w-80 max-w-full">
                            <svg viewBox="0 0 160 96" class="size-full" aria-hidden="true">
                                <path d="M 16 80 A 64 64 0 0 1 144 80" fill="none" stroke="#EAFBF2" stroke-linecap="round" stroke-width="10" />
                                <path d="M 16 80 A 64 64 0 0 1 144 80" fill="none" pathLength="100" stroke="#16B26A" stroke-dasharray="{{ $laborCompliance['compliance_percentage'] ?? 0 }} 100" stroke-linecap="round" stroke-width="10" />
                            </svg>
                            <div class="absolute inset-x-0 bottom-0 text-center">
                                <p class="text-4xl font-bold tracking-tight text-[#0F7A47] dark:text-[#5FE3A1]">{{ $laborCompliance['compliance_percentage'] === null ? '—' : $laborCompliance['compliance_percentage'].'%' }}</p>
                                <p class="mt-1 text-[10px] font-medium uppercase tracking-[0.14em] text-[#0F7A47] dark:text-[#5FE3A1]">Cumplimiento confirmado</p>
                            </div>
                        </div>

                        <p class="mt-3 text-sm leading-5 text-[#53617A] dark:text-zinc-300">{{ $laborCompliance['compliant_workers'] }} de {{ $laborCompliance['total_workers'] }} personas tienen cumplimiento confirmado en los límites revisados.</p>
                        @if ($laborCompliance['pending_workers'] > 0)
                            <p class="mt-2 text-sm text-amber-700 dark:text-amber-300">{{ $laborCompliance['pending_workers'] }} personas pendientes de evaluación; falta completar o dictaminar alguna jornada.</p>
                            @if ($laborCompliance['pending_workers_with_excess'] > 0)
                                <p class="mt-2 text-xs text-[#53617A] dark:text-zinc-300">{{ $laborCompliance['pending_workers_with_excess'] }} de estas personas también {{ $laborCompliance['pending_workers_with_excess'] === 1 ? 'tiene excesos confirmados y aparece' : 'tienen excesos confirmados y aparecen' }} en ambos contadores.</p>
                            @endif
                        @elseif ($laborCompliance['total_workers'] === 0)
                            <p class="mt-2 text-sm text-surface-muted">Sin jornadas para evaluar en esta semana.</p>
                        @endif
                    </div>
                    <div class="mt-auto grid grid-cols-3 border-t border-[#E4EAF2] bg-[#F7F9FC] px-4 py-4 text-center dark:border-zinc-700 dark:bg-zinc-900/70">
                        <div class="border-r border-[#E4EAF2] px-1 dark:border-zinc-700">
                            <p class="flex min-h-8 items-center justify-center text-xs text-[#6B7A90]">Cumplen</p>
                            <p class="mt-0.5 text-sm font-semibold text-[#0F7A47] dark:text-[#5FE3A1]">{{ $laborCompliance['compliant_workers'] }}</p>
                        </div>
                        <div class="border-r border-[#E4EAF2] px-1 dark:border-zinc-700">
                            <p class="flex min-h-8 items-center justify-center text-xs text-[#6B7A90]">Con excesos confirmados</p>
                            <p class="mt-0.5 text-sm font-semibold text-red-600 dark:text-red-300">{{ $laborCompliance['review_workers'] }}</p>
                        </div>
                        <div class="px-1">
                            @if ($laborCompliance['pending_workers'] > 0)
                                <a href="{{ $laborCompliance['pending_work_days_url'] }}" class="block rounded-lg hover:bg-amber-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600 dark:hover:bg-amber-950" title="Ver jornadas pendientes de evaluación">
                            @endif
                            <p class="flex min-h-8 items-center justify-center text-xs text-[#6B7A90]">Pendientes de evaluación</p>
                            <p class="mt-0.5 text-sm font-semibold text-[#93650B] dark:text-amber-300">{{ $laborCompliance['pending_workers'] }}</p>
                            @if ($laborCompliance['pending_workers'] > 0)</a>@endif
                        </div>
                    </div>
                </article>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:order-2">
                <x-dashboard.compliance-card title="Exceso del acumulado semanal" :count="$laborCompliance['exceeded_workers']"
                    :description="'Personas con más de '.$formatHours($laborCompliance['weekly_limit_minutes']).' de jornada en días laborables, sin contar prolongaciones diarias.'"
                    icon="users" :people="$laborCompliance['worker_details']['ordinary']" measure="Acumulado en días laborables" />
                <x-dashboard.compliance-card title="Sobre límite de tiempo extra" :count="$laborCompliance['overtime_limit_exceeded_workers']"
                    :description="'Personas con más de '.$formatHours($laborCompliance['weekly_overtime_limit_minutes']).' extra en la semana.'"
                    icon="clock" :people="$laborCompliance['worker_details']['overtime']" measure="Tiempo extra semanal" />
                <x-dashboard.compliance-card title="Jornadas mayores a 12 h" :count="$laborCompliance['over_twelve_hour_days']"
                    :description="$laborCompliance['over_twelve_hours_workers'].' '.($laborCompliance['over_twelve_hours_workers'] === 1 ? 'persona involucrada' : 'personas involucradas').' en el período.'"
                    icon="exclamation-circle" :people="$laborCompliance['worker_details']['long_days']" measure="Jornada de mayor duración" />
                <x-dashboard.compliance-card title="Alertas abiertas anteriores" :count="$laborCompliance['older_open_alerts']"
                    description="Pendientes de resolver desde antes de esta semana."
                    icon="clock" :people="$laborCompliance['worker_details']['older_alerts']" :danger="true" />
                </div>
            </div>

            <div class="mt-5 flex flex-col gap-2 rounded-xl border border-[#DCE5F2] bg-white px-4 py-3 text-sm text-[#53617A] dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 sm:flex-row sm:items-center sm:justify-between">
                <span>Límite de la semana analizada: <strong class="font-semibold text-[#10213A] dark:text-white">{{ $formatHours($laborCompliance['weekly_limit_minutes'] ?: null) }}</strong>. Vigente en la fecha consultada: <strong>{{ $formatHours($laborCompliance['current_weekly_limit_minutes'] ?: null) }}</strong>.</span>
                @if ($laborCompliance['next_weekly_limit_effective_from'])
                    <span>Próximo ajuste: <strong class="font-semibold text-[#10213A] dark:text-white">{{ $formatHours($laborCompliance['next_weekly_limit_minutes']) }}</strong> desde {{ \Carbon\CarbonImmutable::parse($laborCompliance['next_weekly_limit_effective_from'])->isoFormat('D [de] MMMM [de] YYYY') }} · {{ $laborCompliance['days_until_next_limit'] }} días restantes.</span>
                @endif
            </div>
        </section>



        <section data-vera-dashboard-charts>
            <script type="application/json" data-dashboard-workers>@json($workerIncidents)</script>
            <article class="overflow-hidden rounded-[22px] border border-[#E4EAF2] bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 sm:p-6">
                <h2 class="text-lg font-semibold">Personal con más incidencias en 30 días</h2>
                <p class="mt-1 text-sm text-zinc-500">Del {{ \Carbon\CarbonImmutable::parse($workerIncidents['start'])->format('d/m/Y') }} al {{ \Carbon\CarbonImmutable::parse($workerIncidents['end'])->format('d/m/Y') }} · {{ $workerIncidents['total'] }} alertas.</p>
                <p class="mt-1 text-xs text-zinc-500">Incluye alertas pendientes y revisadas según la fecha de la jornada. Excluye avisos retirados por recálculo. Pasa el mouse o toca una barra para ver los tipos y cantidades.</p>
                @if (count($workerIncidents['workers']) > 0)
                    <div class="mt-4 flex gap-4 text-xs text-zinc-500"><span class="flex items-center gap-2"><span class="size-2.5 rounded-full bg-indigo-500"></span>Pendientes</span><span class="flex items-center gap-2"><span class="size-2.5 rounded-full bg-sky-400"></span>Revisadas</span></div>
                    <div class="mt-5 overflow-x-auto"><div style="height: 320px; min-width: {{ max(500, count($workerIncidents['workers']) * 120) }}px">
                        <canvas data-dashboard-workers-chart aria-label="Alertas por trabajador en los últimos 30 días" role="img"></canvas>
                    </div></div>
                    <details class="mt-4 border-t border-zinc-200 pt-3 text-sm dark:border-zinc-700"><summary class="cursor-pointer font-medium">Ver conteo por trabajador</summary><ul class="mt-3 space-y-3">@foreach ($workerIncidents['workers'] as $person)<li><p class="font-semibold">{{ $person['name'] }} · {{ $person['code'] }}: {{ $person['total'] }} alertas</p><p class="text-zinc-500">@foreach ($person['types'] as $type){{ $type['label'] }}: {{ $type['count'] }}{{ $loop->last ? '' : ' · ' }}@endforeach</p></li>@endforeach</ul></details>
                @else
                    <p class="mt-6 rounded-xl border border-dashed border-zinc-300 p-8 text-center text-sm text-zinc-500">No hay incidencias registradas en este periodo de 30 días.</p>
                @endif
            </article>
        </section>

    </div>
</x-layouts.app>
