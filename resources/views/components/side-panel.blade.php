@props([
    'title',
    'subheading' => null,
    'labelledby' => 'side-panel-title',
    'maxWidth' => 'max-w-md',
])

<div
    x-data="{ open: @entangle($attributes->wire('model')) }"
    x-show="open"
    x-on:keydown.escape.window="open = false"
    x-transition.opacity.duration.200ms
    style="display: none"
    class="fixed inset-0 z-50"
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $labelledby }}"
>
    <button
        type="button"
        class="absolute inset-0 bg-zinc-950/40"
        aria-label="Cerrar panel"
        x-on:click="open = false"
        x-show="open"
        x-transition.opacity.duration.200ms
    ></button>

    <aside
        class="absolute right-0 top-0 flex h-full w-full {{ $maxWidth }} flex-col overflow-y-auto border-l border-zinc-200 bg-white shadow-xl dark:border-zinc-700 dark:bg-zinc-900"
        x-show="open"
        x-transition:enter="transform transition ease-out duration-300"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transform transition ease-in duration-200"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
    >
        <div class="flex items-start justify-between gap-4 border-b border-zinc-200 p-6 dark:border-zinc-700">
            <div>
                <flux:heading id="{{ $labelledby }}">{{ $title }}</flux:heading>

                @if ($subheading)
                    <flux:subheading>{{ $subheading }}</flux:subheading>
                @endif
            </div>

            <flux:button type="button" size="sm" variant="ghost" x-on:click="open = false">
                Cerrar
            </flux:button>
        </div>

        {{ $slot }}
    </aside>
</div>
