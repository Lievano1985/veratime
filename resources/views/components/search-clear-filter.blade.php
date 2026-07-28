@props([
    'label',
    'inputId',
    'placeholder' => '',
    'clearAction',
    'clearDisabled' => false,
    'clearLabel' => 'Limpiar',
])

<div class="relative">
    <label for="{{ $inputId }}" class="block text-sm font-medium text-zinc-800 dark:text-white">
        {{ $label }}
    </label>

    <div class="mt-[6px] flex h-10 w-full rounded-lg shadow-xs -space-x-px" role="group">
        <input
            id="{{ $inputId }}"
            type="text"
            placeholder="{{ $placeholder }}"
            autocomplete="off"
            {{ $attributes->class([
                'h-10 min-w-0 flex-1 rounded-s-lg border border-zinc-200 border-b-zinc-300/80 bg-white px-3 py-2 text-base leading-[1.375rem] text-zinc-700 placeholder-zinc-400 focus:z-10 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary sm:text-sm dark:border-white/10 dark:bg-white/10 dark:text-zinc-300 dark:placeholder-zinc-400',
            ]) }}
        >

        <button
            type="button"
            wire:click="{{ $clearAction }}"
            @disabled($clearDisabled)
            aria-label="{{ $clearLabel }}"
            title="{{ $clearLabel }}"
            class="h-10 shrink-0 rounded-e-lg border border-zinc-200 border-b-zinc-300/80 bg-white px-3 text-sm font-medium leading-[1.375rem] text-zinc-600 hover:bg-accent hover:text-white focus:z-10 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary disabled:cursor-default disabled:opacity-50 disabled:hover:bg-white disabled:hover:text-zinc-600 dark:border-white/10 dark:bg-white/10 dark:text-zinc-300 dark:hover:bg-accent dark:hover:text-white dark:disabled:hover:bg-white/10 dark:disabled:hover:text-zinc-300"
        >
            <svg class="h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
            </svg>
        </button>
    </div>

    {{ $slot }}
</div>
