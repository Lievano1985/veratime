<x-layouts.app>
    @php
        $metrics = $summary['metrics'];
        $segments = $summary['segments'];
        $segmentTotal = max(1, array_sum($segments));
        $attendancePercent = (int) round(($segments['attendance'] / $segmentTotal) * 100);
        $workDayPercent = (int) round(($segments['work_day'] / $segmentTotal) * 100);
        $restPercent = (int) round(($segments['rest'] / $segmentTotal) * 100);
        $chartStyle = "background:conic-gradient(#2563eb 0 {$attendancePercent}%,#f59e0b {$attendancePercent}% ".($attendancePercent + $workDayPercent)."%,#ef4444 ".($attendancePercent + $workDayPercent)."% ".($attendancePercent + $workDayPercent + $restPercent)."%,#8b5cf6 ".($attendancePercent + $workDayPercent + $restPercent)."% 100%)";
        $metricCards = [
            ['Trabajadores activos', $metrics['active_workers'], null, 'blue', null],
            ['Trabajando ahora', $metrics['working_now'], 'En pausa: '.$metrics['on_break'], 'emerald', null],
            ['Ausencias por validar', $metrics['absence_pending'], 'Sin entrada después de tolerancia', 'amber', route('work-days.index', ['from' => $summary['date'], 'to' => $summary['date'], 'situacion' => 'scheduled_absence'])],
            ['Personal por ingresar', $metrics['pending_entry'], 'Dentro de horario o tolerancia', 'violet', null],
        ];
        $toneClasses = ['blue' => 'border-blue-200 bg-blue-50 dark:border-blue-900/60 dark:bg-blue-950/30', 'emerald' => 'border-emerald-200 bg-emerald-50 dark:border-emerald-900/60 dark:bg-emerald-950/30', 'amber' => 'border-amber-200 bg-amber-50 dark:border-amber-900/60 dark:bg-amber-950/30', 'violet' => 'border-violet-200 bg-violet-50 dark:border-violet-900/60 dark:bg-violet-950/30'];
    @endphp

    <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 sm:p-6">
        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 sm:p-6">
            <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
                <div>
                    <flux:heading size="xl">Dashboard operativo</flux:heading>
                    <flux:subheading class="mt-1">{{ $company->name }} · Situaciones preventivas y pendientes de revisión.</flux:subheading>
                </div>
                <form method="GET" class="grid gap-3 sm:grid-cols-2">
                    <flux:field><flux:label>Fecha</flux:label><flux:input type="date" name="date" :value="$summary['date']" /></flux:field>
                    <flux:field><flux:label>Centro de trabajo</flux:label><flux:select name="center"><option value="">Todos los centros</option>@foreach ($centers as $center)<option value="{{ $center->id }}" @selected((string) request('center') === (string) $center->id)>{{ $center->name }}</option>@endforeach</flux:select></flux:field>
                    <div class="sm:col-span-2 sm:text-right"><flux:button type="submit" variant="primary">Actualizar</flux:button></div>
                </form>
            </div>
            <p class="mt-4 text-xs text-zinc-500 dark:text-zinc-400">Datos del {{ \Carbon\CarbonImmutable::parse($summary['date'])->isoFormat('D [de] MMMM [de] YYYY') }} · Zona horaria {{ $summary['timezone'] }}.</p>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($metricCards as [$label, $value, $detail, $tone, $href])
                <div class="rounded-2xl border p-5 shadow-sm {{ $toneClasses[$tone] }}">
                    <p class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ $label }}</p><p class="mt-2 text-3xl font-bold tracking-tight text-zinc-950 dark:text-white">{{ $value }}</p>
                    @if ($detail)<p class="mt-2 text-xs text-zinc-600 dark:text-zinc-300">{{ $detail }}</p>@endif
                    @if ($href)<a href="{{ $href }}" wire:navigate class="mt-3 inline-block text-sm font-semibold text-primary hover:underline">Ver jornadas</a>@endif
                </div>
            @endforeach
        </section>

        <section class="grid gap-6 xl:grid-cols-[1fr_310px]">
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-4"><div><flux:heading size="lg">Alertas preventivas de jornada</flux:heading><flux:subheading>Son situaciones para revisión; no determinaciones definitivas.</flux:subheading></div><a href="{{ route('work-days.index', ['from' => $summary['date'], 'to' => $summary['date'], 'atencion' => 'requires_attention']) }}" wire:navigate class="text-sm font-semibold text-primary hover:underline">Ver jornadas</a></div>
                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    @foreach ($summary['alerts'] as $alert)
                        @php($tone = match ($alert['level']) { 'critical' => 'border-red-200 bg-red-50 dark:border-red-900/70 dark:bg-red-950/30', 'high' => 'border-amber-200 bg-amber-50 dark:border-amber-900/70 dark:bg-amber-950/30', 'warning' => 'border-orange-200 bg-orange-50 dark:border-orange-900/70 dark:bg-orange-950/30', default => 'border-violet-200 bg-violet-50 dark:border-violet-900/70 dark:bg-violet-950/30' })
                        <a href="{{ route('work-days.index', ['from' => $summary['date'], 'to' => $summary['date'], 'situacion' => $alert['key'] === 'manual' ? '' : $alert['key']]) }}" wire:navigate class="rounded-xl border p-4 transition hover:-translate-y-0.5 hover:shadow-sm {{ $tone }}"><div class="flex items-start justify-between gap-3"><div><p class="font-semibold text-zinc-900 dark:text-white">{{ $alert['title'] }}</p><p class="mt-1 text-xs leading-5 text-zinc-600 dark:text-zinc-300">{{ $alert['description'] }}</p></div><span class="rounded-full bg-white/80 px-3 py-1 text-lg font-bold text-zinc-900 dark:bg-zinc-900/70 dark:text-white">{{ $alert['count'] }}</span></div></a>
                    @endforeach
                </div>
            </div>

            <aside class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">Distribución</flux:heading><flux:subheading>Por segmento de revisión.</flux:subheading>
                <div class="mx-auto mt-6 grid size-44 place-items-center rounded-full" style="{{ $chartStyle }}"><div class="grid size-28 place-items-center rounded-full bg-white text-center dark:bg-zinc-900"><span class="text-2xl font-bold text-zinc-900 dark:text-white">{{ array_sum($segments) }}</span><span class="text-xs text-zinc-500">situaciones</span></div></div>
                <dl class="mt-6 space-y-3 text-sm">@foreach (['attendance' => ['Asistencia', 'bg-blue-600'], 'work_day' => ['Jornada', 'bg-amber-500'], 'rest' => ['Descansos', 'bg-red-500'], 'evidence' => ['Evidencia', 'bg-violet-500']] as $key => [$label, $color])<div class="flex items-center justify-between gap-3"><dt class="flex items-center gap-2 text-zinc-600 dark:text-zinc-300"><span class="size-2.5 rounded-full {{ $color }}"></span>{{ $label }}</dt><dd class="font-semibold text-zinc-900 dark:text-white">{{ $segments[$key] }}</dd></div>@endforeach</dl>
            </aside>
        </section>
    </div>
</x-layouts.app>
