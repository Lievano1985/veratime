<section class="contents text-sm">
    @if (! $showPanel)
        <flux:button size="xs" variant="ghost" wire:click="openPanel">Importar CSV</flux:button>
    @else
        <div class="basis-full rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800/70">
            <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="font-medium text-zinc-900 dark:text-zinc-100">Importacion CSV</p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Descarga plantilla o importa programacion para este borrador.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ $templateUrl }}" class="inline-flex items-center rounded-md border border-zinc-300 bg-white px-3 py-1.5 text-xs font-medium text-zinc-800 hover:border-sky-400 hover:text-sky-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                        Descargar plantilla
                    </a>
                    <flux:button size="xs" variant="ghost" wire:click="closePanel">Cerrar</flux:button>
                </div>
            </div>
        </div>
    @endif

    @if (session('csvImportMessage'))
        <p class="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-100">{{ session('csvImportMessage') }}</p>
    @endif

    @error('csvImport')
        <p class="rounded-md border border-red-200 bg-red-50 p-3 text-red-900 dark:border-red-900 dark:bg-red-950 dark:text-red-100">{{ $message }}</p>
    @enderror

    @if ($showPanel)
        <div class="basis-full">
            <div class="space-y-4 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <div>
                    <p class="font-medium">Nuevo archivo</p>
                    <p class="text-xs text-zinc-500">Usa solo CSV UTF-8 con los encabezados de la plantilla. No se aceptan Excel ni archivos comprimidos.</p>
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
                            <option value="replace_existing">Reemplazar con el CSV</option>
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
        </div>

        @if ($activeImport)
            <div class="space-y-4 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="font-medium">Vista previa: {{ $activeImport->original_filename }}</p>
                        <p class="text-xs text-zinc-500">
                            Estado: {{ $this->statusLabel($activeImport->status) }} |
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

                <div class="grid gap-3 md:grid-cols-4 xl:grid-cols-8">
                    <div class="rounded-md bg-zinc-50 p-3 dark:bg-zinc-800"><p class="text-xs text-zinc-500">Filas</p><p class="font-semibold">{{ $activeImport->total_rows }}</p></div>
                    <div class="rounded-md bg-zinc-50 p-3 dark:bg-zinc-800"><p class="text-xs text-zinc-500">Validas</p><p class="font-semibold">{{ $activeImport->valid_rows }}</p></div>
                    <div class="rounded-md bg-zinc-50 p-3 dark:bg-zinc-800"><p class="text-xs text-zinc-500">Errores</p><p class="font-semibold">{{ $activeImport->invalid_rows }}</p></div>
                    <div class="rounded-md bg-zinc-50 p-3 dark:bg-zinc-800"><p class="text-xs text-zinc-500">Advertencias</p><p class="font-semibold">{{ $activeImport->warning_rows }}</p></div>
                    <div class="rounded-md bg-zinc-50 p-3 dark:bg-zinc-800"><p class="text-xs text-zinc-500">Crear</p><p class="font-semibold">{{ $summary['create'] ?? 0 }}</p></div>
                    <div class="rounded-md bg-zinc-50 p-3 dark:bg-zinc-800"><p class="text-xs text-zinc-500">Reemplazar</p><p class="font-semibold">{{ $summary['replace'] ?? 0 }}</p></div>
                    <div class="rounded-md bg-zinc-50 p-3 dark:bg-zinc-800"><p class="text-xs text-zinc-500">Conservar</p><p class="font-semibold">{{ $summary['preserve'] ?? 0 }}</p></div>
                    <div class="rounded-md bg-zinc-50 p-3 dark:bg-zinc-800"><p class="text-xs text-zinc-500">Sin cambio</p><p class="font-semibold">{{ $summary['no_change'] ?? 0 }}</p></div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left">Fila</th>
                                <th class="px-3 py-2 text-left">Trabajador</th>
                                <th class="px-3 py-2 text-left">Fecha</th>
                                <th class="px-3 py-2 text-left">Tipo</th>
                                <th class="px-3 py-2 text-left">Accion prevista</th>
                                <th class="px-3 py-2 text-left">Mensajes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @forelse ($rows as $row)
                                <tr>
                                    <td class="px-3 py-2">{{ $row->row_number }}</td>
                                    <td class="px-3 py-2">
                                        {{ $row->employmentRelationship?->worker?->employee_code ?? ($row->raw_data['clave_empleado'] ?? 'Sin resolver') }}
                                    </td>
                                    <td class="px-3 py-2">{{ $row->work_date?->toDateString() ?? ($row->raw_data['fecha'] ?? '-') }}</td>
                                    <td class="px-3 py-2">{{ $this->dayTypeLabel($row->normalized_data['assignment']['day_type'] ?? null) }}</td>
                                    <td class="px-3 py-2">{{ $this->rowActionLabel($row) }}</td>
                                    <td class="px-3 py-2">
                                        @foreach (($row->errors ?? []) as $error)
                                            <p class="text-red-600 dark:text-red-400">{{ $error }}</p>
                                        @endforeach
                                        @foreach (($row->warnings ?? []) as $warning)
                                            <p class="text-amber-700 dark:text-amber-300">{{ $warning }}</p>
                                        @endforeach
                                        @if (($row->errors ?? []) === [] && ($row->warnings ?? []) === [])
                                            <span class="text-zinc-500">Sin observaciones</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-3 py-6 text-center text-zinc-500">No hay filas para mostrar.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($rows)
                    {{ $rows->links(data: ['scrollTo' => false]) }}
                @endif

                @if ($canUpdate && in_array($activeImport->status, ['validated', 'invalid', 'uploaded'], true))
                    <div class="rounded-md border border-zinc-200 p-4 dark:border-zinc-700">
                        <p class="font-medium">Aplicar importacion</p>
                        <p class="text-xs text-zinc-500">Solo se puede aplicar una importacion validada sin errores. La Action vuelve a comparar el hash antes de modificar el calendario.</p>
                        <label class="mt-3 flex items-center gap-2">
                            <input type="checkbox" wire:model="confirmApply" class="rounded border-zinc-300">
                            <span>Confirmo que revise la vista previa.</span>
                        </label>
                        @error('confirmApply')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
                        <flux:button class="mt-3" size="sm" variant="primary" wire:click="applyImport">
                            Aplicar al borrador
                        </flux:button>
                    </div>
                @endif
            </div>
        @endif
    @endif
</section>
