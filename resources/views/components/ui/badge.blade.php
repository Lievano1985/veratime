@props([
    'variant' => 'neutral',
])

@php
    $classes = match ($variant) {
        'info' => 'border-primary-border bg-primary-soft text-primary',
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-800',
        'danger' => 'border-red-200 bg-red-50 text-red-700',
        default => 'border-zinc-200 bg-zinc-50 text-zinc-700',
    };
@endphp

<span {{ $attributes->class([
    'inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium',
    $classes,
]) }}>
    {{ $slot }}
</span>
