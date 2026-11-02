<div class="contents">
    <button type="button" class="btn-outline cursor-pointer" wire:click="openPanel">Importar Excel</button>

    <x-side-panel wire:model="showPanel" title="Importar trabajadores" subheading="Carga un archivo XLS o XLSX y revisa los datos antes de guardarlos." labelledby="worker-workbook-title" max-width="max-w-5xl">
        <div class="space-y-5 p-6">
            <div class="rounded-2xl border border-surface-line bg-surface-card p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <x-ui.panel-heading title="Prepara tu archivo" tone="blue" />
                    <a href="{{ route('workers.xlsx.template') }}" class="btn-outline cursor-pointer">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3v12m-5-5 5 5 5-5M5 16v5h14v-5" /></svg>
                        Descargar plantilla
                    </a>
                </div>
                <p class="mt-3 text-sm text-surface-muted">Completa clave, nombre, código de centro, fecha de ingreso y correo electrónico obligatorio. El RFC es opcional. Se creará la cuenta vinculada de cada trabajador.</p>
                <p class="mt-2 text-xs text-surface-muted">Se crean trabajadores activos con su relación laboral. Las claves existentes se señalan como error. Las credenciales y asignaciones a áreas se configuran después.</p>
            </div>

            <form wire:submit="preview" class="space-y-3">
                <label class="block space-y-3 rounded-2xl border-2 border-dashed border-brand-blue/30 bg-white p-4">
                    <span class="form-label">Archivo XLS o XLSX · hasta 5 MB y 500 trabajadores</span>
                    <input type="file" wire:model="file" accept=".xls,.xlsx" class="worker-workbook-file block w-full cursor-pointer text-sm text-brand-navy" />
                    <span class="block text-xs text-surface-muted">Pulsa Seleccionar archivo para adjuntar tu Excel.</span>
                </label>
                @error('file')<p class="form-error" role="alert">{{ $message }}</p>@enderror
                <button type="submit" class="btn-primary cursor-pointer" wire:loading.attr="disabled" wire:target="file,preview,apply">Revisar archivo</button>
                <span wire:loading wire:target="file,preview,apply" class="text-sm text-surface-muted">Procesando…</span>
            </form>

            @if ($rows)
                @php($invalid = collect($rows)->filter(fn ($row) => $row['errors'] !== [])->count())
                <div class="rounded-xl border border-surface-line bg-surface-bg p-4 text-sm">
                    <strong>{{ count($rows) }} trabajadores</strong> · {{ $invalid }} filas con errores
                    <p class="mt-1 text-surface-muted">{{ $invalid ? 'Corrige las filas indicadas y vuelve a subir el archivo. No se guardará ningún trabajador hasta que todo esté correcto.' : 'Revisa los datos. Todavía no se ha guardado ningún trabajador.' }}</p>
                </div>
                <div class="max-h-[50vh] overflow-auto rounded-xl border border-surface-line">
                    <table class="w-full text-left text-xs">
                        <thead class="sticky top-0 bg-surface-bg"><tr>@foreach (['Fila', 'Clave', 'Nombre', 'Centro', 'Ingreso', 'RFC', 'Correo', 'Revisión'] as $label)<th class="px-3 py-3">{{ $label }}</th>@endforeach</tr></thead>
                        <tbody class="divide-y divide-surface-line">
                            @foreach ($rows as $row)
                                <tr class="{{ $row['errors'] ? 'bg-red-50' : 'bg-white' }}">
                                    <td class="px-3 py-3">{{ $row['line'] }}</td><td class="px-3 py-3 font-mono">{{ $row['data']['employee_code'] }}</td>
                                    <td class="px-3 py-3">{{ $row['data']['full_name'] }}</td><td class="px-3 py-3">{{ $row['center_code'] }} · {{ $row['center_name'] }}</td>
                                    <td class="whitespace-nowrap px-3 py-3">{{ $row['data']['started_at'] ?? 'Fecha inválida' }}</td><td class="px-3 py-3">{{ $row['data']['rfc'] }}</td>
                                    <td class="px-3 py-3">{{ $row['data']['email'] }}</td>
                                    <td class="min-w-44 px-3 py-3">@forelse ($row['errors'] as $error)<p class="text-red-700">{{ $error }}</p>@empty<span class="text-emerald-700">Listo para importar</span>@endforelse</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-wrap justify-end gap-3 border-t border-surface-line pt-4">
                    <button type="button" class="btn-outline cursor-pointer" wire:click="$set('showPanel', false)">Cancelar</button>
                    <button type="button" class="btn-primary" wire:click="apply" wire:loading.attr="disabled" wire:target="file,preview,apply" @disabled($invalid > 0)>Importar {{ count($rows) }} trabajadores</button>
                </div>
            @endif
        </div>
    </x-side-panel>
</div>
