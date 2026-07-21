<section class="space-y-4 rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm dark:border-sky-900 dark:bg-sky-950">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <p class="font-semibold text-sky-950 dark:text-sky-100">Importacion CSV</p>
            <p class="text-sky-800 dark:text-sky-200">Carga programacion diaria en un lote borrador. La vista previa no modifica el calendario hasta que confirmes aplicar.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('scheduling.daily.csv.template') }}" class="inline-flex items-center rounded-md border border-sky-300 bg-white px-3 py-2 text-xs font-medium text-sky-900 hover:bg-sky-100 dark:border-sky-800 dark:bg-sky-950 dark:text-sky-100">
                Descargar plantilla
            </a>
            @if (! $showPanel)
                <flux:button size="sm" variant="primary" wire:click="openPanel">Importar CSV</flux:button>
            @else
                <flux:button size="sm" variant="ghost" wire:click="closePanel">Cerrar importacion</flux:button>
            @endif
        </div>
    </div>

    @if (session('csvImportMessage'))
        <p class="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-100">{{ session('csvImportMessage') }}</p>
    @endif

    @error('csvImport')
        <p class="rounded-md border border-red-200 bg-red-50 p-3 text-red-900 dark:border-red-900 dark:bg-red-950 dark:text-red-100">{{ $message }}</p>
    @enderror

    @if ($showPanel)
        <div class="grid gap-4 xl:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)]">
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

                    <label class="space-y-1 md:col-span-2">
                        <span class="block text-xs font-medium text-zinc-700 dark:text-zinc-300">Motivo</span>
                        <textarea wire:model="reason" rows="3" class="block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" placeholder="Ejemplo: Ajuste por archivo operativo autorizado para el periodo."></textarea>
                        @error('reason')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                    </label>
                </div>

                <flux:button size="sm" variant="primary" wire:click="uploadAndValidate" wire:loading.attr="disabled">
                    Cargar y validar
                </flux:button>
            </div>

            <div class="space-y-3 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <p class="font-medium">Historial de importaciones</p>
                <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($imports as $import)
                        <button type="button" wire:click="selectImport({{ $import->id }})" class="w-full py-3 text-left text-sm">
                            <span class="block font-medium {{ $activeImport?->id === $import->id ? 'text-sky-700 dark:text-sky-300' : '' }}">
                                {{ $import->original_filename }} - {{ $this->statusLabel($import->status) }}
                            </span>
                            <span class="block text-xs text-zinc-500">
                                {{ $import->total_rows }} filas | {{ $import->invalid_rows }} con error | {{ $import->warning_rows }} con advertencia
                            </span>
                            <span class="block text-xs text-zinc-400">Creada por {{ $import->creator?->name ?? 'Usuario' }} el {{ $import->created_at?->format('Y-m-d H:i') }}</span>
                        </button>
                    @empty
                        <p class="py-3 text-zinc-500">No hay importaciones para este lote.</p>
                    @endforelse
                </div>
                {{ $imports->links(data: ['scrollTo' => false]) }}
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
                    <div class="grid gap-4 rounded-md border border-zinc-200 p-4 dark:border-zinc-700 lg:grid-cols-2">
                        <div>
                            <p class="font-medium">Aplicar importacion</p>
                            <p class="text-xs text-zinc-500">Solo se puede aplicar una importacion validada sin errores. La Action vuelve a comparar el hash antes de modificar el calendario.</p>
                            <label class="mt-3 flex items-center gap-2">
                                <input type="checkbox" wire:model="confirmApply" class="rounded border-zinc-300">
                                <span>Confirmo que revise la vista previa.</span>
                            </label>
                            @error('confirmApply')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
                            <flux:button class="mt-3" size="sm" variant="primary" wire:click="applyImport" wire:confirm="Aplicar esta importacion modificara el lote borrador.">
                                Aplicar al borrador
                            </flux:button>
                        </div>
                        <div>
                            <p class="font-medium">Cancelar importacion</p>
                            <p class="text-xs text-zinc-500">Cancela esta carga sin borrar evidencia del historial.</p>
                            <textarea wire:model="cancelReason" rows="2" class="mt-3 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950" placeholder="Motivo de cancelacion"></textarea>
                            @error('cancelReason')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
                            <flux:button class="mt-3" size="sm" variant="ghost" wire:click="cancelImport">Cancelar importacion</flux:button>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    @endif
</section>
