@props(['title', 'count', 'description', 'icon', 'people' => [], 'measure' => 'Horas registradas', 'danger' => false])

<article x-data="{ selected: null, hours(minutes) { return minutes === null ? '—' : Math.floor(minutes / 60) + ' h ' + String(minutes % 60).padStart(2, '0') + ' min' } }"
    x-id="['people', 'summary-title']"
    @class(['flex min-h-40 flex-col rounded-2xl border bg-white p-5 text-left shadow-sm dark:bg-zinc-800', 'border-red-200 dark:border-red-900' => $danger, 'border-zinc-200 dark:border-zinc-700' => ! $danger])>
    <x-dashboard.metric-icon :icon="$icon" :tone="$danger ? 'rose' : null" />
    <p class="mt-4 text-center text-sm font-medium text-[#53617A] dark:text-zinc-300">{{ $title }}</p>
    <p class="mt-1 text-center text-3xl font-bold tracking-tight text-brand-blue dark:text-blue-300">{{ $count }}</p>
    <p class="mt-2 text-center text-xs leading-5 text-[#6B7A90] dark:text-zinc-400">{{ $description }}</p>

    @if ($count > 0 && count($people) > 0)
        <details class="group mt-4 border-t border-zinc-200 pt-3 dark:border-zinc-700">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-2 rounded-md text-sm font-semibold text-primary focus-visible:outline-2 focus-visible:outline-offset-4" :aria-controls="$id('people')">
                <span>Ver personas ({{ count($people) }})</span>
                <flux:icon icon="chevron-down" class="size-4 transition-transform group-open:rotate-180" />
            </summary>
            <ul :id="$id('people')" class="mt-2 max-h-52 space-y-1 overflow-y-auto">
                @foreach ($people as $person)
                    <li><button type="button" class="w-full rounded-lg px-3 py-2 text-left text-sm text-primary hover:bg-blue-50 focus-visible:outline-2 dark:text-blue-300 dark:hover:bg-zinc-700"
                        x-on:click="selected = {{ Illuminate\Support\Js::from($person) }}; $refs.summary.showModal()">
                        {{ $person['name'] }}
                        @if (count($person['alerts']) > 0)<span class="text-xs text-zinc-500">({{ count($person['alerts']) }} alertas)</span>@endif
                    </button></li>
                @endforeach
            </ul>
        </details>

        <dialog x-ref="summary" :aria-labelledby="$id('summary-title')" x-on:click="if ($event.target === $el) $el.close()"
            class="fixed inset-0 m-auto max-h-[85vh] w-[calc(100%_-_2rem)] max-w-3xl overflow-y-auto rounded-2xl border border-zinc-200 bg-white p-6 text-zinc-800 shadow-xl backdrop:bg-black/50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
            <div class="flex items-start justify-between gap-4">
                <div><h2 :id="$id('summary-title')" class="text-xl font-semibold" x-text="selected?.name"></h2><p class="mt-1 text-sm text-zinc-500">{{ $title }}</p></div>
                <button type="button" autofocus x-on:click="$refs.summary.close()" aria-label="Cerrar resumen" class="rounded-lg p-2 hover:bg-zinc-100 dark:hover:bg-zinc-800"><flux:icon icon="x-mark" class="size-5" /></button>
            </div>
            <p class="mt-4 text-sm" x-text="selected?.period"></p>
            <template x-if="selected?.minutes !== null && selected">
                <dl class="mt-4 grid grid-cols-1 gap-3 rounded-xl bg-zinc-50 p-4 sm:grid-cols-3 dark:bg-zinc-800">
                    <div><dt class="text-sm text-zinc-500">{{ $measure }}</dt><dd class="mt-1 font-semibold" x-text="hours(selected.minutes)"></dd></div>
                    <div><dt class="text-sm text-zinc-500">Límite aplicable</dt><dd class="mt-1 font-semibold" x-text="hours(selected.limit_minutes)"></dd></div>
                    <div><dt class="text-sm text-zinc-500">Exceso</dt><dd class="mt-1 font-semibold text-red-600 dark:text-red-300" x-text="hours(selected.excess_minutes)"></dd></div>
                </dl>
            </template>
            <template x-if="selected?.pending"><p class="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-950 dark:text-amber-200">Hay cálculos pendientes. Los importes mostrados son parciales y no confirman el resultado de toda la semana.</p></template>
            <template x-if="selected?.days.length">
                <div class="mt-5 overflow-x-auto"><table class="w-full min-w-[32rem] text-left text-sm">
                    <caption class="mb-3 text-left font-semibold" x-text="selected?.days_caption || 'Jornadas de la semana analizada'"></caption>
                    <thead><tr class="border-b border-zinc-200 dark:border-zinc-700"><th class="p-2">Fecha / centro</th><th class="p-2">Total</th><th class="p-2">Jornada sin extra</th><th class="p-2">Extra total</th></tr></thead>
                    <tbody><template x-for="day in selected.days" :key="day.id"><tr class="border-b border-zinc-100 dark:border-zinc-800">
                        <td class="p-2"><span x-text="day.date"></span><span class="block text-xs text-zinc-500 dark:text-zinc-400" x-text="day.center"></span><span class="block text-xs text-zinc-500 dark:text-zinc-400" x-text="day.pending ? 'Cálculo pendiente' : 'Evaluado'"></span></td>
                        <td class="p-2 whitespace-nowrap" x-text="hours(day.total_minutes)"></td><td class="p-2 whitespace-nowrap" x-text="hours(day.ordinary_minutes)"></td><td class="p-2 whitespace-nowrap" x-text="hours(day.overtime_minutes)"></td>
                    </tr></template></tbody>
                </table></div>
            </template>
            <template x-if="selected?.alerts.length"><ul class="mt-4 space-y-2"><template x-for="(alert, index) in selected.alerts" :key="index"><li class="rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-800"><span class="font-semibold" x-text="alert.title"></span><span class="ml-2 text-zinc-500" x-text="alert.date"></span></li></template></ul></template>
            <div class="mt-5 flex flex-wrap justify-end gap-2">
                <template x-if="selected?.work_days_url"><a :href="selected.work_days_url" class="rounded-lg border border-primary px-4 py-2 text-sm font-semibold text-primary hover:bg-primary-soft">Ver esas jornadas</a></template>
                <button type="button" x-on:click="$refs.summary.close()" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white">Cerrar</button>
            </div>
        </dialog>
    @endif
</article>
