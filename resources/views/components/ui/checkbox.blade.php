<input
    type="checkbox"
    {{ $attributes->class([
        'rounded border-zinc-300 text-primary shadow-xs',
        'focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary',
        'disabled:cursor-not-allowed disabled:opacity-60',
    ]) }}
>
