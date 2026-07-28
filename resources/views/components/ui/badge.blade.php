@props([
    'variant' => 'neutral',
])

@php
    $classes = match ($variant) {
        'info' => 'border-primary bg-primary text-white',
        'success' => 'border-emerald-600 bg-emerald-600 text-white',
        'warning' => 'border-amber-500 bg-amber-500 text-amber-950',
        'danger' => 'border-red-600 bg-red-600 text-white',
        default => 'border-zinc-500 bg-zinc-600 text-white',
    };
@endphp

<span {{ $attributes->class([
    'inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-semibold shadow-sm',
    $classes,
]) }}>
    {{ $slot }}
</span>
