<x-layouts.app>
    @php
        $metrics = $summary['metrics'];
        $segments = $summary['segments'];
        $segmentTotal = max(1, array_sum($segments));
        $activeWorkers = max(0, $metrics['active_workers']);
        $activeNow = $metrics['working_now'] + $metrics['on_break'];
        $weekStart = $summary['week_start'];
        $weekEnd = $summary['week_end'];
        $activityPercent = $activeWorkers > 0 ? min(100, (int) round(($activeNow / $activeWorkers) * 100)) : 0;
        $circleLength = 2 * pi() * 42;
        $generatedAt = \Carbon\CarbonImmutable::parse($summary['generated_at'])->isoFormat('HH:mm');
        $roleLabel = match (auth()->user()->roleKeyForCompany($company)) {
            \App\Support\RoleKey::ADMIN_EMPRESA => 'Administrador',
            \App\Support\RoleKey::RH_ADMIN => 'Administrador de RH',
            \App\Support\RoleKey::RH_OPERATIVO => 'Operador de RH',
            \App\Support\RoleKey::SUPERVISOR => 'Supervisor',
            default => 'Administrador',
        };
        $segmentStyles = [
            'attendance' => ['Asistencia', 'bg-sky-500', 'text-sky-700 dark:text-sky-300'],
            'work_day' => ['Jornada', 'bg-indigo-500', 'text-indigo-700 dark:text-indigo-300'],
            'rest' => ['Descansos', 'bg-rose-500', 'text-rose-700 dark:text-rose-300'],
            'evidence' => ['Evidencia', 'bg-violet-500', 'text-violet-700 dark:text-violet-300'],
        ];
        $dailyAlerts = collect($summary['daily_alerts']);
        $weeklyAlerts = collect($summary['weekly_alerts']);
        $kpiAlerts = collect($summary['kpi_alerts']);
        $alertCounts = $kpiAlerts
            ->merge($dailyAlerts)
            ->merge($weeklyAlerts)
            ->keyBy('key');
        $weeklyAverageWorkMinutes = (int) $summary['weekly_average_work_minutes'];
        $weeklyAverageLabel = sprintf('%dh %02dm', intdiv($weeklyAverageWorkMinutes, 60), $weeklyAverageWorkMinutes % 60);
        $incidentCompliance = $summary['weekly_incident_compliance'];
        $complianceWeeks = collect($incidentCompliance['weeks']);
        $incidenceTrends = $summary['incidence_trends'];
        $trendWeeks = collect($incidenceTrends['weeks']);
        $trendSeries = collect($incidenceTrends['series']);
        $operationalCards = [
            ['Trabajadores activos', $metrics['active_workers'], 'sky', null],
            ['Trabajando ahora', $metrics['working_now'], 'emerald', null],
            ['Personal por ingresar', $metrics['pending_entry'], 'slate', null],
            ['Entradas tardías', data_get($alertCounts->get('late_arrival_detected'), 'count', 0), 'orange', route('work-days.index', ['from' => $summary['date'], 'to' => $summary['date'], 'situacion' => 'late_arrival_detected'])],
            ['Salidas anticipadas', data_get($alertCounts->get('early_departure_detected'), 'count', 0), 'orange', route('work-days.index', ['from' => $summary['date'], 'to' => $summary['date'], 'situacion' => 'early_departure_detected'])],
            ['Jornadas incompletas', data_get($alertCounts->get('incomplete_work_day'), 'count', 0), 'red', route('work-days.index', ['from' => $summary['date'], 'to' => $summary['date'], 'situacion' => 'incomplete_work_day'])],
        ];
        $operationalStyles = [
            'sky' => 'border-sky-200 bg-gradient-to-br from-sky-50 to-white text-sky-700 dark:border-sky-900/60 dark:from-sky-950/40 dark:to-zinc-800',
            'emerald' => 'border-emerald-200 bg-gradient-to-br from-emerald-50 to-white text-emerald-700 dark:border-emerald-900/60 dark:from-emerald-950/40 dark:to-zinc-800',
            'amber' => 'border-amber-200 bg-gradient-to-br from-amber-50 to-white text-amber-700 dark:border-amber-900/60 dark:from-amber-950/40 dark:to-zinc-800',
            'slate' => 'border-slate-300 bg-gradient-to-br from-slate-50 to-white text-slate-600 dark:border-slate-700 dark:from-slate-900 dark:to-zinc-800',
            'red' => 'border-red-200 bg-gradient-to-br from-red-50 to-white text-red-600 dark:border-red-900/60 dark:from-red-950/40 dark:to-zinc-800',
            'orange' => 'border-orange-200 bg-gradient-to-br from-orange-50 to-white text-orange-600 dark:border-orange-900/60 dark:from-orange-950/40 dark:to-zinc-800',
            'rose' => 'border-rose-200 bg-gradient-to-br from-rose-50 to-white text-rose-600 dark:border-rose-900/60 dark:from-rose-950/40 dark:to-zinc-800',
            'violet' => 'border-fuchsia-200 bg-gradient-to-br from-fuchsia-50 to-white text-fuchsia-600 dark:border-fuchsia-900/60 dark:from-fuchsia-950/40 dark:to-zinc-800',
        ];
        $indicatorStyles = [
            'daily_limit_exceeded' => ['border-rose-200 bg-gradient-to-br from-rose-50 to-white dark:border-rose-900/60 dark:from-rose-950/30 dark:to-zinc-800', 'text-rose-700 dark:text-rose-300'],
            'daily_overtime_over_three_hours' => ['border-amber-200 bg-gradient-to-br from-amber-50 to-white dark:border-amber-900/60 dark:from-amber-950/30 dark:to-zinc-800', 'text-amber-700 dark:text-amber-300'],
            'overtime_detected' => ['border-sky-200 bg-gradient-to-br from-sky-50 to-white dark:border-sky-900/60 dark:from-sky-950/30 dark:to-zinc-800', 'text-sky-700 dark:text-sky-300'],
            'long_work_day' => ['border-orange-200 bg-gradient-to-br from-orange-50 to-white dark:border-orange-900/60 dark:from-orange-950/30 dark:to-zinc-800', 'text-orange-700 dark:text-orange-300'],
            'minimum_break_missing' => ['border-violet-200 bg-gradient-to-br from-violet-50 to-white dark:border-violet-900/60 dark:from-violet-950/30 dark:to-zinc-800', 'text-violet-700 dark:text-violet-300'],
            'twelve_hours_exceeded' => ['border-red-200 bg-gradient-to-br from-red-50 to-white dark:border-red-900/60 dark:from-red-950/30 dark:to-zinc-800', 'text-red-700 dark:text-red-300'],
            'sunday_work' => ['border-sky-200 bg-gradient-to-br from-sky-50 to-white dark:border-sky-900/60 dark:from-sky-950/30 dark:to-zinc-800', 'text-sky-700 dark:text-sky-300'],
            'mandatory_rest_work' => ['border-orange-200 bg-gradient-to-br from-orange-50 to-white dark:border-orange-900/60 dark:from-orange-950/30 dark:to-zinc-800', 'text-orange-700 dark:text-orange-300'],
            'scheduled_rest_work' => ['border-emerald-200 bg-gradient-to-br from-emerald-50 to-white dark:border-emerald-900/60 dark:from-emerald-950/30 dark:to-zinc-800', 'text-emerald-700 dark:text-emerald-300'],
            'minor_daily_hours_exceeded' => ['border-rose-200 bg-gradient-to-br from-rose-50 to-white dark:border-rose-900/60 dark:from-rose-950/30 dark:to-zinc-800', 'text-rose-700 dark:text-rose-300'],
            'minor_restricted_work' => ['border-red-200 bg-gradient-to-br from-red-50 to-white dark:border-red-900/60 dark:from-red-950/30 dark:to-zinc-800', 'text-red-700 dark:text-red-300'],
            'weekly_hours_exceeded' => ['border-rose-200 bg-gradient-to-br from-rose-50 to-white dark:border-rose-900/60 dark:from-rose-950/30 dark:to-zinc-800', 'text-rose-700 dark:text-rose-300'],
            'weekly_overtime_exceeded' => ['border-amber-200 bg-gradient-to-br from-amber-50 to-white dark:border-amber-900/60 dark:from-amber-950/30 dark:to-zinc-800', 'text-amber-700 dark:text-amber-300'],
            'weekly_overtime_days_exceeded' => ['border-orange-200 bg-gradient-to-br from-orange-50 to-white dark:border-orange-900/60 dark:from-orange-950/30 dark:to-zinc-800', 'text-orange-700 dark:text-orange-300'],
            'weekly_rest_missing' => ['border-violet-200 bg-gradient-to-br from-violet-50 to-white dark:border-violet-900/60 dark:from-violet-950/30 dark:to-zinc-800', 'text-violet-700 dark:text-violet-300'],
            'weekly_sunday_work' => ['border-sky-200 bg-gradient-to-br from-sky-50 to-white dark:border-sky-900/60 dark:from-sky-950/30 dark:to-zinc-800', 'text-sky-700 dark:text-sky-300'],
        ];
    @endphp

    <div class="flex w-full flex-1 flex-col gap-6 rounded-xl bg-[#eff5fc] p-2 sm:p-4 dark:bg-zinc-950">
        <p class="px-2 text-sm font-semibold text-zinc-700 dark:text-zinc-200">Dashboard</p>

        <section class="relative rounded-xl border border-[#b9cee4] bg-gradient-to-br from-white to-[#eef6ff] p-5 shadow-sm dark:border-zinc-700 dark:from-zinc-900 dark:to-zinc-900">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <flux:heading size="xl">Dashboard de {{ $roleLabel }}</flux:heading>
                    <flux:subheading class="mt-1">{{ auth()->user()->name }} | {{ $company->name }} | {{ \Carbon\CarbonImmutable::parse($summary['date'])->isoFormat('D [de] MMMM [de] YYYY') }}</flux:subheading>
                </div>
                <details class="group self-start">
                    <summary class="cursor-pointer list-none rounded-lg border border-primary-border bg-white px-3 py-2 text-sm font-semibold text-primary shadow-sm hover:bg-primary-soft dark:bg-zinc-900">Filtrar tablero</summary>
                    <form method="GET" class="mt-3 grid w-full gap-3 rounded-xl border border-zinc-200 bg-white p-3 shadow-lg sm:grid-cols-[minmax(0,1fr)_minmax(0,1.25fr)_auto] dark:border-zinc-700 dark:bg-zinc-900 lg:absolute lg:right-10 lg:z-10 lg:w-[38rem]">
                        <flux:field><flux:label>Fecha</flux:label><flux:input type="date" name="date" :value="$summary['date']" /></flux:field>
                        <flux:field><flux:label>Centro de trabajo</flux:label><flux:select name="center"><option value="">Todos los centros</option>@foreach ($centers as $center)<option value="{{ $center->id }}" @selected((string) request('center') === (string) $center->id)>{{ $center->name }}</option>@endforeach</flux:select></flux:field>
                        <div class="self-end"><flux:button type="submit" variant="primary">Aplicar</flux:button></div>
                    </form>
                </details>
            </div>
            <p class="mt-4 text-xs text-zinc-500 dark:text-zinc-400">Zona horaria {{ $summary['timezone'] }} &middot; Actualizado a las {{ $generatedAt }}</p>
        </section>

        <section class="rounded-xl border border-[#b9cee4] bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="text-center">
                <h2 class="text-xl font-semibold text-[#0067E4]">Operaci&oacute;n de la jornada</h2>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Asistencia, marcajes y situaciones preventivas de la empresa.</p>
            </div>
            <div class="mt-5 grid gap-4 xl:grid-cols-3">
                <div class="flex h-full flex-col rounded-xl border border-[#b9cee4] bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary">Cobertura de jornada</p>
                    <div class="flex flex-1 flex-col items-center justify-center py-4">
                        <div class="relative flex size-52 items-center justify-center">
                            <svg class="size-52 -rotate-90" viewBox="0 0 120 120" aria-label="Actividad actual {{ $activityPercent }} por ciento">
                                <circle cx="60" cy="60" r="42" fill="none" stroke="currentColor" stroke-width="12" class="text-slate-200 dark:text-zinc-700" />
                                <circle cx="60" cy="60" r="42" fill="none" stroke="currentColor" stroke-width="12" stroke-linecap="round" class="text-[#5e718f] dark:text-blue-400" stroke-dasharray="{{ $circleLength }}" stroke-dashoffset="{{ $circleLength - ($circleLength * ($activityPercent / 100)) }}" />
                            </svg>
                            <div class="absolute text-center"><p class="text-4xl font-bold leading-none text-[#4d6384] dark:text-blue-300">{{ $activityPercent }}%</p><p class="mt-2 text-xs uppercase tracking-wide text-zinc-500">cobertura</p></div>
                        </div>
                        <p class="mt-4 text-center text-sm text-zinc-600 dark:text-zinc-300"><strong>{{ $activeNow }}</strong> de {{ $activeWorkers }} personas con actividad registrada.</p>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:col-span-2 xl:auto-rows-fr">
                    @foreach ($operationalCards as [$label, $value, $tone, $href])
                        <article class="grid min-h-32 grid-rows-[auto_1fr_auto] rounded-xl border p-5 text-center shadow-sm transition hover:-translate-y-0.5 hover:shadow-md {{ $operationalStyles[$tone] }}">
                            <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $label }}</p>
                            <p class="flex flex-1 items-center justify-center text-3xl font-semibold leading-none">{{ $value }}</p>
                            @if ($href)<a href="{{ $href }}" wire:navigate class="justify-self-center text-xs font-medium text-slate-400 transition hover:text-primary hover:underline dark:text-zinc-500">Ver detalle</a>@endif
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section data-vera-dashboard-charts>
            <script type="application/json" data-dashboard-compliance>@json($incidentCompliance)</script>
            <article class="overflow-hidden rounded-[22px] border border-[#E4EAF2] bg-white p-5 shadow-[0_10px_30px_rgba(2,25,57,0.04)] dark:border-zinc-700 dark:bg-zinc-900 sm:p-6">
                <div class="relative flex flex-col items-center text-center">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#0067E4]">Seguimiento operativo</p>
                        <h2 class="mt-1 text-lg font-semibold tracking-tight text-[#0067E4]">Cumplimiento por semana</h2>
                        <p class="mt-1 text-sm text-[#6B7A90] dark:text-zinc-400">Proporción de incidencias cerradas en cada semana.</p>
                    </div>
                    <div class="mt-3 rounded-xl bg-[#EEF6FF] px-3.5 py-2.5 text-center dark:bg-blue-950/30 sm:absolute sm:right-0 sm:top-0 sm:mt-0">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-[#0B4EA8] dark:text-blue-300">Últimas 8 semanas</p>
                        <p class="mt-0.5 text-2xl font-bold tracking-tight text-[#0067E4]">{{ $incidentCompliance['percentage'] }}%</p>
                    </div>
                </div>
                <div class="mt-5 h-60">
                    <canvas data-dashboard-compliance-chart aria-label="Cumplimiento de incidencias de las últimas ocho semanas" role="img"></canvas>
                </div>
                <div class="mt-4 grid grid-cols-3 divide-x divide-[#E4EAF2] rounded-xl bg-[#F5F8FC] px-2 py-3 text-center dark:divide-zinc-700 dark:bg-zinc-800/70">
                    <div>
                        <p class="text-[11px] font-medium text-[#6B7A90] dark:text-zinc-400">Detectadas</p>
                        <p class="mt-0.5 text-sm font-bold text-[#0B1B2B] dark:text-white">{{ $incidentCompliance['total'] }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-medium text-[#6B7A90] dark:text-zinc-400">Cerradas</p>
                        <p class="mt-0.5 text-sm font-bold text-[#16B26A]">{{ $incidentCompliance['closed'] }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-medium text-[#6B7A90] dark:text-zinc-400">Por atender</p>
                        <p class="mt-0.5 text-sm font-bold text-[#C43D3D]">{{ $incidentCompliance['total'] - $incidentCompliance['closed'] }}</p>
                    </div>
                </div>
            </article>
        </section>

        <section class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="relative flex flex-col items-center text-center">
                <div><h2 class="text-xl font-semibold text-[#0067E4]">Indicadores diarios de jornada</h2><p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Situaciones del día que requieren revisión; no son determinaciones definitivas.</p></div>
                <a href="{{ route('work-days.index', ['from' => $summary['date'], 'to' => $summary['date'], 'atencion' => 'requires_attention']) }}" wire:navigate class="mt-2 text-sm font-semibold text-primary hover:underline sm:absolute sm:right-0 sm:top-0 sm:mt-0">Abrir bandeja de jornadas</a>
            </div>
            <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($dailyAlerts as $alert)
                    @php($tone = $indicatorStyles[$alert['key']] ?? ['border-slate-200 bg-gradient-to-br from-slate-50 to-white dark:border-slate-700 dark:from-slate-900 dark:to-zinc-800', 'text-slate-700 dark:text-slate-300'])
                    <a href="{{ route('work-days.index', ['from' => $summary['date'], 'to' => $summary['date'], 'situacion' => $alert['key'] === 'manual' ? '' : $alert['key']]) }}" wire:navigate class="flex min-h-44 flex-col rounded-xl border p-5 text-center transition hover:-translate-y-0.5 hover:shadow-md {{ $tone[0] }}">
                        <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $alert['title'] }}</p>
                        <span class="mt-3 text-4xl font-semibold leading-none {{ $tone[1] }}">{{ $alert['count'] }}</span>
                        <p class="mt-3 text-xs leading-5 text-zinc-600 dark:text-zinc-300">{{ $alert['description'] }}</p>
                    </a>
                @endforeach
                <article class="flex min-h-44 flex-col rounded-xl border border-sky-200 bg-gradient-to-br from-sky-50 to-white p-5 text-center dark:border-sky-900/60 dark:from-sky-950/30 dark:to-zinc-800">
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Horas por persona trabajadora</p>
                    <p class="mt-3 text-4xl font-semibold leading-none text-sky-700 dark:text-sky-300">{{ $weeklyAverageLabel }}</p>
                    <p class="mt-3 text-xs leading-5 text-zinc-600 dark:text-zinc-300">Promedio de horas calculadas durante la semana.</p>
                </article>
            </div>
        </section>

        <section data-vera-dashboard-charts>
            <script type="application/json" data-dashboard-trends>@json($incidenceTrends)</script>
            <article class="overflow-hidden rounded-[22px] border border-[#E4EAF2] bg-white p-5 shadow-[0_10px_30px_rgba(2,25,57,0.04)] dark:border-zinc-700 dark:bg-zinc-900 sm:p-6">
                <div class="relative flex flex-col items-center text-center">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#0067E4]">Análisis anual</p>
                        <h2 class="mt-1 text-lg font-semibold tracking-tight text-[#0067E4]">Incidencias más repetitivas</h2>
                        <p class="mt-1 text-sm text-[#6B7A90] dark:text-zinc-400">Tendencia semanal de los tipos más frecuentes en {{ $incidenceTrends['year'] }}.</p>
                    </div>
                    <span class="mt-3 rounded-lg bg-[#F5F8FC] px-2.5 py-1.5 text-xs font-semibold text-[#0B4EA8] dark:bg-zinc-800 dark:text-blue-300 sm:absolute sm:right-0 sm:top-0 sm:mt-0">{{ $incidenceTrends['year'] }}</span>
                </div>
                @if ($trendSeries->isNotEmpty())
                    <div class="mt-5 h-64">
                        <canvas data-dashboard-trends-chart aria-label="Tendencia semanal de incidencias por tipo" role="img"></canvas>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-x-4 gap-y-2 border-t border-[#E4EAF2] pt-4 dark:border-zinc-700">
                        @foreach ($trendSeries as $series)
                            <div class="flex items-center gap-2 text-xs">
                                <span class="size-2.5 shrink-0 rounded-full" style="background-color: {{ $series['color'] }}"></span>
                                <span class="max-w-40 truncate font-medium text-[#6B7A90] dark:text-zinc-300">{{ $series['label'] }}</span>
                                <span class="font-bold text-[#0B1B2B] dark:text-white">{{ $series['total'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="mt-6 flex h-56 items-center justify-center rounded-xl border border-dashed border-zinc-300 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">Aún no hay incidencias detectadas durante el año consultado.</div>
                @endif
            </article>
        </section>

        <section class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="relative flex flex-col items-center text-center">
                <div><h2 class="text-xl font-semibold text-[#0067E4]">Indicadores semanales de acumulaci&oacute;n</h2><p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Situaciones evaluadas por persona durante la semana natural consultada.</p></div>
                <a href="{{ route('work-days.index', ['from' => $weekStart, 'to' => $weekEnd, 'atencion' => 'requires_attention']) }}" wire:navigate class="mt-2 text-sm font-semibold text-primary hover:underline sm:absolute sm:right-0 sm:top-0 sm:mt-0">Ver semana</a>
            </div>
            <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($weeklyAlerts as $alert)
                    @php($tone = $indicatorStyles[$alert['key']] ?? ['border-slate-200 bg-gradient-to-br from-slate-50 to-white dark:border-slate-700 dark:from-slate-900 dark:to-zinc-800', 'text-slate-700 dark:text-slate-300'])
                    <a href="{{ route('work-days.index', ['from' => $weekStart, 'to' => $weekEnd, 'situacion' => $alert['key']]) }}" wire:navigate class="flex min-h-44 flex-col rounded-xl border p-5 text-center transition hover:-translate-y-0.5 hover:shadow-md {{ $tone[0] }}">
                        <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $alert['title'] }}</p>
                        <span class="mt-3 text-4xl font-semibold leading-none {{ $tone[1] }}">{{ $alert['count'] }}</span>
                        <p class="mt-3 text-xs leading-5 text-zinc-600 dark:text-zinc-300">{{ $alert['description'] }}</p>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.3fr_0.7fr]">
            <div class="rounded-xl border border-zinc-200 bg-white p-5 text-center shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="text-xl font-semibold text-[#0067E4]">Distribuci&oacute;n de situaciones</h2>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Agrupaci&oacute;n operativa de alertas y evidencia visible en este tablero.</p>
                <div class="mt-5 space-y-4">
                    @foreach ($segmentStyles as $key => [$label, $color, $textColor])
                        @php($percentage = $segmentTotal > 0 ? (int) round(($segments[$key] / $segmentTotal) * 100) : 0)
                        <div>
                            <div class="flex items-center justify-between text-sm"><p class="flex items-center gap-2 font-medium text-zinc-700 dark:text-zinc-200"><span class="size-2.5 rounded-full {{ $color }}"></span>{{ $label }}</p><p class="font-semibold {{ $textColor }}">{{ $segments[$key] }} <span class="text-zinc-400">({{ $percentage }}%)</span></p></div>
                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800"><div class="h-full rounded-full {{ $color }}" style="width: {{ $percentage }}%"></div></div>
                        </div>
                    @endforeach
                </div>
            </div>
            <aside class="rounded-xl border border-primary-border bg-primary-soft p-5 text-center shadow-sm dark:border-blue-900/60 dark:bg-blue-950/25">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary">Criterio de uso</p>
                <h2 class="mt-2 text-xl font-semibold text-[#0067E4]">Revisar antes de concluir</h2>
                <p class="mt-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">Este tablero ayuda a priorizar la revisi&oacute;n operativa. Las capturas originales, jornadas y dict&aacute;menes se conservan y se gestionan desde sus m&oacute;dulos correspondientes.</p>
                <a href="{{ route('work-days.index', ['from' => $summary['date'], 'to' => $summary['date']]) }}" wire:navigate class="mt-5 inline-flex rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-hover">Revisar jornadas</a>
            </aside>
        </section>
    </div>
</x-layouts.app>
