@props([
    'variant' => 'neutral',
])

@php
    $classes = match ($variant) {
        'info' => 'border-sky-100 bg-sky-100 text-sky-800 dark:border-sky-950 dark:bg-sky-950 dark:text-sky-200',
        'success' => 'border-emerald-100 bg-emerald-100 text-emerald-800 dark:border-emerald-950 dark:bg-emerald-950 dark:text-emerald-200',
        'warning' => 'border-amber-100 bg-amber-100 text-amber-800 dark:border-amber-950 dark:bg-amber-950 dark:text-amber-200',
        'danger' => 'border-red-100 bg-red-100 text-red-800 dark:border-red-950 dark:bg-red-950 dark:text-red-200',
        default => 'border-zinc-100 bg-zinc-100 text-zinc-700 dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-200',
    };
@endphp

<span {{ $attributes->class([
    'inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium',
    $classes,
]) }}>
    {{ $slot }}
</span>
