<section class="contents text-sm">
    <button type="button" class="btn" wire:click="openPanel">Importar CSV</button>

    <x-side-panel
        wire:model="showPanel"
        title="Importación CSV"
        subheading="Carga programación para este borrador y revisa la vista previa antes de aplicar."
        labelledby="daily-schedule-csv-import-title"
        maxWidth="max-w-none"
        widthStyle="width: max(22rem, calc(100vw - 18rem)); max-width: 100vw;"
    >
        <div class="space-y-4 p-6">
            @if (session('csvImportMessage'))
                <p class="rounded-xl border border-status-rest-line bg-status-rest-bg p-3 text-status-rest-text">{{ session('csvImportMessage') }}</p>
            @endif

            @error('csvImport')
                <p class="rounded-xl border border-status-pending-line bg-status-pending-bg p-3 text-status-pending-text">{{ $message }}</p>
            @enderror

            <div class="space-y-4 rounded-2xl border border-surface-line bg-surface-card p-5 shadow-[0_20px_50px_-34px_rgba(2,25,57,0.22)]">
                <div class="flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="font-display font-bold text-brand-navy">Nuevo archivo</p>
                        <p class="mt-1 text-xs text-surface-muted">Usa solo CSV UTF-8 con los encabezados de la plantilla. No se aceptan Excel ni archivos comprimidos.</p>
                    </div>
                    <a href="{{ $templateUrl }}" class="btn-outline btn-sm">
                        Descargar plantilla
                    </a>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <label class="space-y-1 md:col-span-2">
                        <span class="form-label">Archivo CSV</span>
                        <input type="file" wire:model="file" accept=".csv,text/csv" class="form-input">
                        @error('file')<span class="form-error">{{ $message }}</span>@enderror
                    </label>

                    <label class="space-y-1">
                        <span class="form-label">Si ya existe programación</span>
                        <select wire:model="existingAssignmentPolicy" class="form-select">
                            <option value="replace_existing">Usar datos del CSV</option>
                            <option value="preserve_existing">Conservar lo existente</option>
                        </select>
                        @error('existingAssignmentPolicy')<span class="form-error">{{ $message }}</span>@enderror
                    </label>

                    <label class="space-y-1">
                        <span class="form-label">Lote destino</span>
                        <input type="text" value="{{ $batch->center?->name }} | {{ $batch->period_start->toDateString() }} a {{ $batch->period_end->toDateString() }}" disabled class="form-input bg-surface-bg text-surface-muted">
                    </label>
                </div>

                <button type="button" class="btn-primary btn-sm" wire:click="uploadAndValidate" wire:loading.attr="disabled" wire:target="uploadAndValidate,file">
                    <span wire:loading wire:target="uploadAndValidate,file" class="btn-spinner"></span>
                    <span wire:loading.remove wire:target="uploadAndValidate,file">Cargar y validar</span>
                    <span wire:loading wire:target="uploadAndValidate,file">Validando</span>
                </button>
            </div>

            @if ($activeImport)
                @php($canApplyImport = $canUpdate && $activeImport->status === 'validated' && (int) $activeImport->invalid_rows === 0)
                <div class="space-y-4 rounded-2xl border border-surface-line bg-surface-card p-5 shadow-[0_20px_50px_-34px_rgba(2,25,57,0.22)]">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <p class="font-display font-bold text-brand-navy">Vista previa: {{ $activeImport->original_filename }}</p>
                            <p class="mt-1 flex flex-wrap items-center gap-2 text-xs text-surface-muted">
                                <span>Estado:</span>
                                <x-ui.badge variant="{{ $activeImport->status === 'validated' || $activeImport->status === 'applied' ? 'success' : ($activeImport->status === 'invalid' ? 'danger' : ($activeImport->status === 'cancelled' ? 'neutral' : 'warning')) }}">
                                    {{ $this->statusLabel($activeImport->status) }}
                                </x-ui.badge>
                                <span>|</span>
                                Hash de validación: {{ $activeImport->validation_sha256 ? \Illuminate\Support\Str::limit($activeImport->validation_sha256, 16, '') : 'No disponible' }}
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if (in_array($activeImport->status, ['uploaded', 'invalid', 'validated'], true))
                                <button type="button" class="btn-ghost btn-sm" wire:click="validateImport" wire:loading.attr="disabled" wire:target="validateImport">
                                    <span wire:loading wire:target="validateImport" class="btn-spinner"></span>
                                    <span wire:loading.remove wire:target="validateImport">Revalidar</span>
                                    <span wire:loading wire:target="validateImport">Revalidando</span>
                                </button>
                            @endif
                            @if ($activeImport->invalid_rows > 0 || $activeImport->warning_rows > 0)
                                <a href="{{ route('scheduling.daily.imports.errors', $activeImport) }}" class="btn-outline btn-sm">
                                    Descargar errores
                                </a>
                            @endif
                        </div>
                    </div>

                    @if ($canApplyImport)
                        <div class="rounded-xl border border-brand-blue/20 bg-brand-blue/5 p-4 text-sm text-brand-navy">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                <div>
                                    <p class="font-semibold">Archivo listo para enviar</p>
                                    <p class="mt-1 text-xs text-surface-muted">Revisa la vista previa y envía estos horarios al lote borrador.</p>
                                    @error('confirmApply')<p class="form-error mt-2">{{ $message }}</p>@enderror
                                </div>
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" wire:model="confirmApply" class="rounded border-surface-line text-brand-blue focus:ring-brand-blue">
                                        <span>Vista previa revisada</span>
                                    </label>
                                    <button type="button" class="btn-primary btn-sm" wire:click="applyImport" wire:loading.attr="disabled" wire:target="applyImport">
                                        <span wire:loading wire:target="applyImport" class="btn-spinner"></span>
                                        <span wire:loading.remove wire:target="applyImport">Enviar horarios al borrador</span>
                                        <span wire:loading wire:target="applyImport">Enviando</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @elseif ($canUpdate && in_array($activeImport->status, ['uploaded', 'invalid'], true))
                        <div class="rounded-xl border border-status-warn-line bg-status-warn-bg p-4 text-sm text-status-warn-text">
                            Corrige el archivo o revalida la carga. El botón para enviar horarios aparece cuando la vista previa queda validada sin errores.
                        </div>
                    @endif

                    <div class="grid gap-3 md:grid-cols-4">
                        <div class="rounded-xl bg-surface-bg p-3"><p class="text-xs text-surface-muted">Filas</p><p class="font-display font-bold text-brand-navy">{{ $activeImport->total_rows }}</p></div>
                        <div class="rounded-xl bg-surface-bg p-3"><p class="text-xs text-surface-muted">Válidas</p><p class="font-display font-bold text-brand-navy">{{ $activeImport->valid_rows }}</p></div>
                        <div class="rounded-xl bg-surface-bg p-3"><p class="text-xs text-surface-muted">Errores</p><p class="font-display font-bold text-brand-navy">{{ $activeImport->invalid_rows }}</p></div>
                        <div class="rounded-xl bg-surface-bg p-3"><p class="text-xs text-surface-muted">Advertencias</p><p class="font-display font-bold text-brand-navy">{{ $activeImport->warning_rows }}</p></div>
                    </div>

                    <div class="table-wrap">
                        <table class="min-w-max border-collapse text-sm">
                            <thead>
                                <tr>
                                    <th class="table-head-cell sticky left-0 z-20 min-w-32 bg-surface-bg">Código</th>
                                    <th class="table-head-cell sticky left-32 z-20 min-w-56 bg-surface-bg">Trabajador</th>
                                    @foreach ($previewDates as $date)
                                        <th class="table-head-cell min-w-44 text-center">{{ $date }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($previewWorkers as $worker)
                                    <tr class="table-row">
                                        <td class="table-cell sticky left-0 z-10 bg-inherit font-semibold text-brand-navy">{{ $worker['code'] }}</td>
                                        <td class="table-cell sticky left-32 z-10 bg-inherit">{{ $worker['name'] }}</td>
                                        @foreach ($previewDates as $date)
                                            @php($cell = $worker['cells'][$date] ?? null)
                                            <td class="table-cell min-w-44 align-top {{ ($cell['day_type'] ?? null) === 'Descanso' ? 'bg-status-rest-bg text-status-rest-text' : '' }}">
                                                @if ($cell)
                                                    <div class="space-y-1">
                                                        <p class="font-medium">{{ $cell['shift_code'] ?: $cell['day_type'] }}</p>
                                                        <p class="text-xs text-surface-muted">{{ $cell['day_type'] }}</p>
                                                        @foreach ($cell['messages'] as $message)
                                                            <p class="text-xs text-status-warn-text">{{ $message }}</p>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <span class="text-xs text-surface-muted">Sin captura</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ count($previewDates) + 2 }}" class="table-cell py-6 text-center text-surface-muted">No hay trabajadores para mostrar.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($previewWorkers)
                        {{ $previewWorkers->links(data: ['scrollTo' => false]) }}
                    @endif
                </div>
            @endif
        </div>
    </x-side-panel>
</section>
