@props([
    'variant' => 'neutral',
])

@php
    $classes = match ($variant) {
        'info' => 'border-sky-100 bg-sky-100 text-sky-800 dark:border-sky-950 dark:bg-sky-950 dark:text-sky-200',
        'success' => 'border-emerald-100 bg-emerald-100 text-emerald-800 dark:border-emerald-950 dark:bg-emerald-950 dark:text-emerald-200',
        'warning' => 'border-amber-100 bg-amber-100 text-amber-800 dark:border-amber-950 dark:bg-amber-950 dark:text-amber-200',
        'danger' => 'border-red-100 bg-red-100 text-red-800 dark:border-red-950 dark:bg-red-950 dark:text-red-200',
        'rose' => 'border-rose-100 bg-rose-100 text-rose-800 dark:border-rose-950 dark:bg-rose-950 dark:text-rose-200',
        'orange' => 'border-orange-100 bg-orange-100 text-orange-800 dark:border-orange-950 dark:bg-orange-950 dark:text-orange-200',
        'violet' => 'border-violet-100 bg-violet-100 text-violet-800 dark:border-violet-950 dark:bg-violet-950 dark:text-violet-200',
        'cyan' => 'border-cyan-100 bg-cyan-100 text-cyan-800 dark:border-cyan-950 dark:bg-cyan-950 dark:text-cyan-200',
        'lime' => 'border-lime-100 bg-lime-100 text-lime-800 dark:border-lime-950 dark:bg-lime-950 dark:text-lime-200',
        default => 'border-zinc-100 bg-zinc-100 text-zinc-700 dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-200',
    };
@endphp

<span {{ $attributes->class([
    'inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium',
    $classes,
]) }}>
    {{ $slot }}
</span>
