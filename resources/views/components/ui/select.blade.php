<select
    {{ $attributes->class([
        'w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-xs',
        'focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary',
        'disabled:cursor-not-allowed disabled:bg-zinc-50 disabled:text-zinc-500',
        'aria-invalid:border-red-300 aria-invalid:ring-red-500',
    ]) }}
>
    {{ $slot }}
</select>
