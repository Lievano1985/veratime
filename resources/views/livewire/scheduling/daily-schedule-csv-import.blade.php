<section class="contents text-sm">
    <flux:button size="xs" variant="ghost" wire:click="openPanel">Importar CSV</flux:button>

    <x-side-panel
        wire:model="showPanel"
        title="Importacion CSV"
        subheading="Carga programacion para este borrador y revisa la vista previa antes de aplicar."
        labelledby="daily-schedule-csv-import-title"
        maxWidth="max-w-none"
        widthStyle="width: max(22rem, calc(100vw - 18rem)); max-width: 100vw;"
    >
        <div class="space-y-4 p-6">
            @if (session('csvImportMessage'))
                <p class="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-100">{{ session('csvImportMessage') }}</p>
            @endif

            @error('csvImport')
                <p class="rounded-md border border-red-200 bg-red-50 p-3 text-red-900 dark:border-red-900 dark:bg-red-950 dark:text-red-100">{{ $message }}</p>
            @enderror

            <div class="space-y-4 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="font-medium">Nuevo archivo</p>
                        <p class="text-xs text-zinc-500">Usa solo CSV UTF-8 con los encabezados de la plantilla. No se aceptan Excel ni archivos comprimidos.</p>
                    </div>
                    <a href="{{ $templateUrl }}" class="inline-flex items-center rounded-md border border-zinc-300 bg-white px-3 py-1.5 text-xs font-medium text-zinc-800 hover:border-sky-400 hover:text-sky-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                        Descargar plantilla
                    </a>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <label class="space-y-1 md:col-span-2">
                        <span class="block text-xs font-medium text-zinc-700 dark:text-zinc-300">Archivo CSV</span>
                        <input type="file" wire:model="file" accept=".csv,text/csv" class="block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                        @error('file')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                    </label>

                    <label class="space-y-1">
                        <span class="block text-xs font-medium text-zinc-700 dark:text-zinc-300">Si ya existe programacion</span>
                        <select wire:model="existingAssignmentPolicy" class="block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                            <option value="replace_existing">Usar datos del CSV</option>
                            <option value="preserve_existing">Conservar lo existente</option>
                        </select>
                        @error('existingAssignmentPolicy')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                    </label>

                    <label class="space-y-1">
                        <span class="block text-xs font-medium text-zinc-700 dark:text-zinc-300">Lote destino</span>
                        <input type="text" value="{{ $batch->center?->name }} | {{ $batch->period_start->toDateString() }} a {{ $batch->period_end->toDateString() }}" disabled class="block w-full rounded-md border border-zinc-300 bg-zinc-50 px-3 py-2 text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800">
                    </label>
                </div>

                <flux:button size="sm" variant="primary" wire:click="uploadAndValidate" wire:loading.attr="disabled">
                    Cargar y validar
                </flux:button>
            </div>

            @if ($activeImport)
                @php($canApplyImport = $canUpdate && $activeImport->status === 'validated' && (int) $activeImport->invalid_rows === 0)
                <div class="space-y-4 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <p class="font-medium">Vista previa: {{ $activeImport->original_filename }}</p>
                            <p class="flex flex-wrap items-center gap-2 text-xs text-zinc-500">
                                <span>Estado:</span>
                                <x-ui.badge variant="{{ $activeImport->status === 'validated' || $activeImport->status === 'applied' ? 'success' : ($activeImport->status === 'invalid' ? 'danger' : ($activeImport->status === 'cancelled' ? 'neutral' : 'warning')) }}">
                                    {{ $this->statusLabel($activeImport->status) }}
                                </x-ui.badge>
                                <span>|</span>
                                Hash de validacion: {{ $activeImport->validation_sha256 ? \Illuminate\Support\Str::limit($activeImport->validation_sha256, 16, '') : 'No disponible' }}
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if (in_array($activeImport->status, ['uploaded', 'invalid', 'validated'], true))
                                <flux:button size="xs" variant="ghost" wire:click="validateImport">Revalidar</flux:button>
                            @endif
                            @if ($activeImport->invalid_rows > 0 || $activeImport->warning_rows > 0)
                                <a href="{{ route('scheduling.daily.imports.errors', $activeImport) }}" class="inline-flex items-center rounded-md border border-zinc-300 px-3 py-1.5 text-xs font-medium hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                                    Descargar errores
                                </a>
                            @endif
                        </div>
                    </div>

                    @if ($canApplyImport)
                        <div class="rounded-md border border-sky-200 bg-sky-50 p-4 text-sm text-sky-950 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-100">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                <div>
                                    <p class="font-medium">Archivo listo para enviar</p>
                                    <p class="mt-1 text-xs">Revisa la vista previa y envia estos horarios al lote borrador.</p>
                                    @error('confirmApply')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" wire:model="confirmApply" class="rounded border-zinc-300">
                                        <span>Vista previa revisada</span>
                                    </label>
                                    <flux:button size="sm" variant="primary" wire:click="applyImport" wire:loading.attr="disabled">
                                        Enviar horarios al borrador
                                    </flux:button>
                                </div>
                            </div>
                        </div>
                    @elseif ($canUpdate && in_array($activeImport->status, ['uploaded', 'invalid'], true))
                        <div class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100">
                            Corrige el archivo o revalida la carga. El boton para enviar horarios aparece cuando la vista previa queda validada sin errores.
                        </div>
                    @endif

                    <div class="grid gap-3 md:grid-cols-4">
                        <div class="rounded-md bg-zinc-50 p-3 dark:bg-zinc-800"><p class="text-xs text-zinc-500">Filas</p><p class="font-semibold">{{ $activeImport->total_rows }}</p></div>
                        <div class="rounded-md bg-zinc-50 p-3 dark:bg-zinc-800"><p class="text-xs text-zinc-500">Validas</p><p class="font-semibold">{{ $activeImport->valid_rows }}</p></div>
                        <div class="rounded-md bg-zinc-50 p-3 dark:bg-zinc-800"><p class="text-xs text-zinc-500">Errores</p><p class="font-semibold">{{ $activeImport->invalid_rows }}</p></div>
                        <div class="rounded-md bg-zinc-50 p-3 dark:bg-zinc-800"><p class="text-xs text-zinc-500">Advertencias</p><p class="font-semibold">{{ $activeImport->warning_rows }}</p></div>
                    </div>

                    <div class="overflow-x-auto rounded-md border border-zinc-200 dark:border-zinc-700">
                        <table class="min-w-max divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                            <thead class="bg-zinc-50 text-left text-xs font-medium text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                                <tr>
                                    <th class="sticky left-0 z-20 min-w-32 bg-zinc-50 px-3 py-2 dark:bg-zinc-800">Codigo</th>
                                    <th class="sticky left-32 z-20 min-w-56 bg-zinc-50 px-3 py-2 dark:bg-zinc-800">Trabajador</th>
                                    @foreach ($previewDates as $date)
                                        <th class="min-w-44 px-3 py-2 text-center">{{ $date }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 [&>tr:nth-child(odd)]:bg-white [&>tr:nth-child(even)]:bg-zinc-50/60 dark:divide-zinc-800 dark:[&>tr:nth-child(odd)]:bg-zinc-900 dark:[&>tr:nth-child(even)]:bg-zinc-800/40">
                                @forelse ($previewWorkers as $worker)
                                    <tr>
                                        <td class="sticky left-0 z-10 bg-inherit px-3 py-2 font-medium">{{ $worker['code'] }}</td>
                                        <td class="sticky left-32 z-10 bg-inherit px-3 py-2">{{ $worker['name'] }}</td>
                                        @foreach ($previewDates as $date)
                                            @php($cell = $worker['cells'][$date] ?? null)
                                            <td class="min-w-44 px-3 py-2 align-top {{ ($cell['day_type'] ?? null) === 'Descanso' ? 'bg-emerald-50 text-emerald-950 dark:bg-emerald-950/40 dark:text-emerald-100' : '' }}">
                                                @if ($cell)
                                                    <div class="space-y-1">
                                                        <p class="font-medium">{{ $cell['shift_code'] ?: $cell['day_type'] }}</p>
                                                        <p class="text-xs text-zinc-500">{{ $cell['day_type'] }}</p>
                                                        @foreach ($cell['messages'] as $message)
                                                            <p class="text-xs text-amber-700 dark:text-amber-300">{{ $message }}</p>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <span class="text-xs text-zinc-400">Sin captura</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ count($previewDates) + 2 }}" class="px-3 py-6 text-center text-zinc-500">No hay trabajadores para mostrar.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($previewWorkers)
                        {{ $previewWorkers->links(data: ['scrollTo' => false]) }}
                    @endif

                    @if ($canApplyImport)
                        <div class="rounded-md border border-zinc-200 p-4 dark:border-zinc-700">
                            <p class="font-medium">Enviar horarios</p>
                            <p class="text-xs text-zinc-500">La Action vuelve a comparar el hash antes de modificar el calendario.</p>
                            <label class="mt-3 flex items-center gap-2">
                                <input type="checkbox" wire:model="confirmApply" class="rounded border-zinc-300">
                                <span>Confirmo que revise la vista previa.</span>
                            </label>
                            @error('confirmApply')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
                            <flux:button class="mt-3" size="sm" variant="primary" wire:click="applyImport">
                                Enviar horarios al borrador
                            </flux:button>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </x-side-panel>
</section>
