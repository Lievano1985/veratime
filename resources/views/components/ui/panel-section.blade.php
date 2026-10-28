@props(['title', 'description' => null, 'tone' => 'blue'])

<section {{ $attributes->class(['space-y-4 rounded-2xl border border-surface-line bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900']) }}>
    <x-ui.panel-heading :title="$title" :description="$description" :tone="$tone" />
    <div class="space-y-4">{{ $slot }}</div>
</section>
