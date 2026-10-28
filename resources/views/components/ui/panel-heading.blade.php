@props(['title', 'description' => null, 'tone' => 'blue'])

<div {{ $attributes->class(['vt-panel-heading col-span-full flex items-start gap-3 border-b border-surface-line pb-4 dark:border-zinc-700']) }}>
    <span aria-hidden="true" @class([
        'vt-panel-number flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-sm font-bold',
        'bg-blue-50 text-blue-600 dark:bg-blue-950 dark:text-blue-300' => $tone === 'blue',
        'bg-indigo-50 text-indigo-600 dark:bg-indigo-950 dark:text-indigo-300' => $tone === 'indigo',
        'bg-emerald-50 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-300' => $tone === 'emerald',
    ])></span>
    <div class="min-w-0 flex-1">
        <h3 class="font-display text-sm font-semibold text-brand-navy dark:text-zinc-100">{{ $title }}</h3>
        @if ($description)
            <p class="mt-1 text-sm leading-relaxed text-surface-muted dark:text-zinc-400">{{ $description }}</p>
        @endif
        {{ $slot }}
    </div>
</div>
