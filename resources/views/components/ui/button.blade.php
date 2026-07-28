@props([
    'variant' => 'primary',
    'type' => 'button',
])

@php
    $classes = match ($variant) {
        'primary' => 'bg-primary text-white hover:bg-primary-hover focus-visible:ring-primary',
        'secondary' => 'border border-primary-border bg-white text-primary hover:bg-primary-soft focus-visible:ring-primary',
        'danger' => 'bg-red-600 text-white hover:bg-red-700 focus-visible:ring-red-600',
        'ghost' => 'bg-transparent text-zinc-700 hover:bg-zinc-100 focus-visible:ring-primary dark:text-zinc-200 dark:hover:bg-white/10',
        default => 'bg-primary text-white hover:bg-primary-hover focus-visible:ring-primary',
    };
@endphp

<button
    type="{{ $type }}"
    {{ $attributes->class([
        'inline-flex h-10 items-center justify-center gap-2 rounded-lg px-4 text-sm font-medium transition',
        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2',
        'disabled:cursor-not-allowed disabled:opacity-60',
        $classes,
    ]) }}
>
    {{ $slot }}
</button>
