<x-layouts.app>
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl p-6">
        <div>
            <flux:heading size="xl">Inicio</flux:heading>
            <flux:subheading>Vista inicial de Vera Time.</flux:subheading>
        </div>

        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <div class="relative overflow-hidden rounded-xl border border-primary-border bg-primary-soft p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm font-medium text-primary">Empresa activa</p>
                <p class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-white">Lista para operar</p>
            </div>
            <div class="relative overflow-hidden rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Acciones principales</p>
                <p class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-white">Usan azul Vera Time</p>
            </div>
            <div class="relative overflow-hidden rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Estados</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <x-ui.badge variant="info">Info</x-ui.badge>
                    <x-ui.badge variant="success">Exito</x-ui.badge>
                    <x-ui.badge variant="warning">Aviso</x-ui.badge>
                </div>
            </div>
        </div>
        <div class="relative h-full flex-1 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <p class="max-w-2xl text-sm text-zinc-600 dark:text-zinc-300">
                El sistema visual centraliza acciones, estados y foco accesible para evitar estilos aislados.
            </p>
        </div>
    </div>
</x-layouts.app>
