<x-layouts.app>
    @php
        $metrics = $summary['metrics'];
        $segments = $summary['segments'];
        $segmentTotal = max(1, array_sum($segments));
        $activeWorkers = max(0, $metrics['active_workers']);
        $activeNow = $metrics['working_now'] + $metrics['on_break'];
        $activityPercent = $activeWorkers > 0 ? min(100, (int) round(($activeNow / $activeWorkers) * 100)) : 0;
        $circleLength = 2 * pi() * 42;
        $generatedAt = \Carbon\CarbonImmutable::parse($summary['generated_at'])->isoFormat('HH:mm');
        $metricCards = [
            ['Trabajadores activos', $metrics['active_workers'], 'Personal con relaci&oacute;n laboral vigente.', 'sky', null],
            ['Trabajando ahora', $metrics['working_now'], 'En pausa: '.$metrics['on_break'], 'emerald', null],
            ['Ausencias por validar', $metrics['absence_pending'], 'Sin entrada despu&eacute;s de tolerancia.', 'amber', route('work-days.index', ['from' => $summary['date'], 'to' => $summary['date'], 'situacion' => 'scheduled_absence'])],
            ['Personal por ingresar', $metrics['pending_entry'], 'Dentro de horario o tolerancia.', 'violet', null],
        ];
        $metricStyles = [
            'sky' => 'border-sky-200 bg-gradient-to-br from-sky-50 to-white text-sky-700 dark:border-sky-900/60 dark:from-sky-950/40 dark:to-zinc-800 dark:text-sky-300',
            'emerald' => 'border-emerald-200 bg-gradient-to-br from-emerald-50 to-white text-emerald-700 dark:border-emerald-900/60 dark:from-emerald-950/40 dark:to-zinc-800 dark:text-emerald-300',
            'amber' => 'border-amber-200 bg-gradient-to-br from-amber-50 to-white text-amber-700 dark:border-amber-900/60 dark:from-amber-950/40 dark:to-zinc-800 dark:text-amber-300',
            'violet' => 'border-violet-200 bg-gradient-to-br from-violet-50 to-white text-violet-700 dark:border-violet-900/60 dark:from-violet-950/40 dark:to-zinc-800 dark:text-violet-300',
        ];
        $segmentStyles = [
            'attendance' => ['Asistencia', 'bg-sky-500', 'text-sky-700 dark:text-sky-300'],
            'work_day' => ['Jornada', 'bg-indigo-500', 'text-indigo-700 dark:text-indigo-300'],
            'rest' => ['Descansos', 'bg-rose-500', 'text-rose-700 dark:text-rose-300'],
            'evidence' => ['Evidencia', 'bg-violet-500', 'text-violet-700 dark:text-violet-300'],
        ];
    @endphp

    <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 sm:p-6">
        <section class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary">Vera Time</p>
                    <flux:heading size="xl" class="mt-2">Dashboard operativo</flux:heading>
                    <flux:subheading class="mt-1">{{ $company->name }} &middot; Control diario preventivo de la jornada.</flux:subheading>
                </div>
                <form method="GET" class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1.25fr)_auto]">
                    <flux:field>
                        <flux:label>Fecha</flux:label>
                        <flux:input type="date" name="date" :value="$summary['date']" />
                    </flux:field>
                    <flux:field>
                        <flux:label>Centro de trabajo</flux:label>
                        <flux:select name="center">
                            <option value="">Todos los centros</option>
                            @foreach ($centers as $center)
                                <option value="{{ $center->id }}" @selected((string) request('center') === (string) $center->id)>{{ $center->name }}</option>
                            @endforeach
                        </flux:select>
                    </flux:field>
                    <div class="self-end"><flux:button type="submit" variant="primary">Actualizar</flux:button></div>
                </form>
            </div>
            <div class="mt-5 flex flex-wrap gap-x-5 gap-y-2 border-t border-zinc-100 pt-4 text-xs text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">
                <span>{{ \Carbon\CarbonImmutable::parse($summary['date'])->isoFormat('D [de] MMMM [de] YYYY') }}</span>
                <span>Zona horaria: {{ $summary['timezone'] }}</span>
                <span>Actualizado a las {{ $generatedAt }}</span>
            </div>
        </section>

        <section class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div>
                <h2 class="text-xl font-semibold text-zinc-800 dark:text-white">Panorama de jornada</h2>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Estado de las personas trabajadoras y sus marcajes para la fecha consultada.</p>
            </div>
            <div class="mt-5 grid gap-6 xl:grid-cols-[0.8fr_1.8fr]">
                <div class="rounded-xl border border-sky-100 bg-sky-50/50 p-5 dark:border-sky-900/50 dark:bg-sky-950/20">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-sky-700 dark:text-sky-300">Actividad actual</p>
                    <div class="mt-4 flex flex-col items-center justify-center">
                        <div class="relative flex size-52 items-center justify-center">
                            <svg class="size-52 -rotate-90" viewBox="0 0 120 120" aria-label="Actividad actual {{ $activityPercent }} por ciento">
                                <circle cx="60" cy="60" r="42" fill="none" stroke="currentColor" stroke-width="12" class="text-sky-100 dark:text-zinc-700" />
                                <circle cx="60" cy="60" r="42" fill="none" stroke="currentColor" stroke-width="12" stroke-linecap="round" class="text-primary" stroke-dasharray="{{ $circleLength }}" stroke-dashoffset="{{ $circleLength - ($circleLength * ($activityPercent / 100)) }}" />
                            </svg>
                            <div class="absolute text-center"><p class="text-4xl font-bold leading-none text-primary">{{ $activityPercent }}%</p><p class="mt-2 text-xs uppercase tracking-wide text-zinc-500">con actividad</p></div>
                        </div>
                        <p class="mt-4 text-center text-sm text-zinc-600 dark:text-zinc-300"><strong>{{ $activeNow }}</strong> de {{ $activeWorkers }} personas activas tienen una jornada abierta o una pausa en curso.</p>
                    </div>
                </div>

                <div>
                    <div class="mb-3 flex items-center justify-between"><h3 class="text-base font-semibold text-zinc-700 dark:text-zinc-100">Indicadores del d&iacute;a</h3><a href="{{ route('work-days.index', ['from' => $summary['date'], 'to' => $summary['date']]) }}" wire:navigate class="text-sm font-semibold text-primary hover:underline">Ver jornadas</a></div>
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        @foreach ($metricCards as [$label, $value, $detail, $tone, $href])
                            <div class="rounded-xl border p-4 {{ $metricStyles[$tone] }}">
                                <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $label }}</p>
                                <p class="mt-2 text-3xl font-semibold">{{ $value }}</p>
                                <p class="mt-2 min-h-8 text-xs leading-4 text-zinc-600 dark:text-zinc-300">{{ $detail }}</p>
                                @if ($href)<a href="{{ $href }}" wire:navigate class="mt-3 inline-block text-sm font-semibold text-primary hover:underline">Revisar detalle</a>@endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div><h2 class="text-xl font-semibold text-zinc-800 dark:text-white">Alertas preventivas de jornada</h2><p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Situaciones que requieren revisi&oacute;n; no son determinaciones definitivas.</p></div>
                <a href="{{ route('work-days.index', ['from' => $summary['date'], 'to' => $summary['date'], 'atencion' => 'requires_attention']) }}" wire:navigate class="self-start text-sm font-semibold text-primary hover:underline">Abrir bandeja de jornadas</a>
            </div>
            <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($summary['alerts'] as $alert)
                    @php($tone = match ($alert['level']) { 'critical' => ['border-red-200 bg-gradient-to-br from-red-50 to-white dark:border-red-900/60 dark:from-red-950/30 dark:to-zinc-800', 'text-red-700 dark:text-red-300'], 'high' => ['border-amber-200 bg-gradient-to-br from-amber-50 to-white dark:border-amber-900/60 dark:from-amber-950/30 dark:to-zinc-800', 'text-amber-700 dark:text-amber-300'], 'warning' => ['border-orange-200 bg-gradient-to-br from-orange-50 to-white dark:border-orange-900/60 dark:from-orange-950/30 dark:to-zinc-800', 'text-orange-700 dark:text-orange-300'], default => ['border-violet-200 bg-gradient-to-br from-violet-50 to-white dark:border-violet-900/60 dark:from-violet-950/30 dark:to-zinc-800', 'text-violet-700 dark:text-violet-300'] })
                    <a href="{{ route('work-days.index', ['from' => $summary['date'], 'to' => $summary['date'], 'situacion' => $alert['key'] === 'manual' ? '' : $alert['key']]) }}" wire:navigate class="rounded-xl border p-4 transition hover:-translate-y-0.5 hover:shadow-md {{ $tone[0] }}">
                        <div class="flex items-start justify-between gap-4"><div><p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Revisi&oacute;n preventiva</p><p class="mt-2 font-semibold text-zinc-800 dark:text-white">{{ $alert['title'] }}</p><p class="mt-1 text-xs leading-5 text-zinc-600 dark:text-zinc-300">{{ $alert['description'] }}</p></div><span class="text-3xl font-semibold {{ $tone[1] }}">{{ $alert['count'] }}</span></div>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.3fr_0.7fr]">
            <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="text-xl font-semibold text-zinc-800 dark:text-white">Distribuci&oacute;n de situaciones</h2>
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
            <aside class="rounded-xl border border-primary-border bg-primary-soft p-5 shadow-sm dark:border-blue-900/60 dark:bg-blue-950/25">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary">Criterio de uso</p>
                <h2 class="mt-2 text-xl font-semibold text-zinc-800 dark:text-white">Revisar antes de concluir</h2>
                <p class="mt-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">Este tablero ayuda a priorizar la revisi&oacute;n operativa. Las capturas originales, jornadas y dict&aacute;menes se conservan y se gestionan desde sus m&oacute;dulos correspondientes.</p>
                <a href="{{ route('work-days.index', ['from' => $summary['date'], 'to' => $summary['date']]) }}" wire:navigate class="mt-5 inline-flex rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-hover">Revisar jornadas</a>
            </aside>
        </section>
    </div>
</x-layouts.app>
