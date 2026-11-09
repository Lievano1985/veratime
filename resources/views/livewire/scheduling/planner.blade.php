<section class="flex flex-col gap-5 p-4 lg:p-6" x-data="{ selectedCells: $wire.entangle('cells'), selectedPeople: $wire.entangle('people'), toggleCells(keys, select) { this.selectedCells = select ? [...new Set([...this.selectedCells, ...keys])] : this.selectedCells.filter(cell => !keys.includes(cell)); this.selectedPeople = [...new Set(this.selectedCells.map(cell => cell.split('|')[0]))]; } }">
    <div class="grid items-center gap-4 lg:grid-cols-[minmax(0,1fr)_auto]">
        <div>
            <flux:heading size="xl">Planificador de horarios</flux:heading>
            <flux:subheading>Prepara la semana, asigna a varias personas y guarda horarios que se repiten.</flux:subheading>
        </div>
        <div wire:key="planner-week-selector-{{ $centerId }}-{{ $week }}" class="relative" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false" x-on:planner-week-changed.window="open = false">
            <div class="flex items-center justify-center gap-2">
                <button type="button" class="btn-ghost" wire:click="navigateWeek(-1)" wire:loading.attr="disabled" aria-label="Semana anterior">←</button>
                <button type="button" class="rounded-lg border border-surface-line px-4 py-2 text-center text-sm hover:bg-slate-50" x-on:click="open = !open" x-bind:aria-expanded="open" aria-controls="planner-week-search">
                    <span class="block font-semibold">Semana {{ $dates->first()->isoWeek() }} · {{ $dates->first()->isoWeekYear() }} ▾</span>
                    <span class="block text-surface-muted">Del {{ $dates->first()->format('d/m/Y') }} al {{ $dates->last()->format('d/m/Y') }}</span>
                </button>
                <button type="button" class="btn-ghost" wire:click="navigateWeek(1)" wire:loading.attr="disabled" aria-label="Semana siguiente">→</button>
            </div>
            <div id="planner-week-search" x-show="open" x-cloak class="absolute left-0 right-0 z-20 mt-2 rounded-xl border border-surface-line bg-white p-4 shadow-lg">
                <form wire:submit="jumpToWeek" class="space-y-3">
                    <flux:input type="number" label="Buscar por año" min="1900" max="9998" wire:model.live.debounce.350ms="weekSearchYear" />
                    <flux:select label="Semana y rango de fechas" wire:model="weekSearchNumber">
                        @foreach ($searchWeeks as $searchWeek)
                            <flux:select.option value="{{ $searchWeek->isoWeek() }}">Semana {{ $searchWeek->isoWeek() }} · {{ $searchWeek->format('d/m/Y') }} al {{ $searchWeek->addDays(6)->format('d/m/Y') }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @error('weekSearchNumber')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                    <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled">Ir a esta semana</button>
                </form>
            </div>
        </div>
    </div>

    @if ($notice)<p role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">{{ $notice }}</p>@endif
    @if ($errors->any())
        <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-900">
            @foreach ($errors->all() as $message)<p>{{ $message }}</p>@endforeach
        </div>
    @endif

    <div class="grid items-end gap-3 rounded-xl border border-surface-line bg-white p-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto]">
        <flux:select label="Centro de trabajo" wire:model.live="centerId">
            <flux:select.option value="">Selecciona centro</flux:select.option>
            @foreach ($centers as $center)<flux:select.option value="{{ $center->id }}">{{ $center->name }}</flux:select.option>@endforeach
        </flux:select>
        <flux:input label="Buscar persona" placeholder="Nombre o clave" wire:model.live.debounce.350ms="search" :disabled="! $batch" />
        <flux:select label="Equipo o área" wire:model.live="unitId" :disabled="! $batch">
            <flux:select.option value="">Todos</flux:select.option>
            @foreach ($units as $unit)<flux:select.option value="{{ $unit->id }}">{{ $unit->name }}</flux:select.option>@endforeach
        </flux:select>
        <div class="text-sm">
            @if ($batch)
                <span class="rounded-full px-3 py-1 {{ $editable ? 'bg-amber-50 text-amber-900' : 'bg-emerald-50 text-emerald-900' }}">{{ $editable ? ($batch->previous_batch_id ? 'Corrección en borrador' : 'Borrador') : 'Publicado' }}</span>
            @else
                <span class="text-surface-muted">Semana sin preparar</span>
            @endif
        </div>
    </div>

    @if (! $centerId)
        <div class="rounded-xl border border-dashed border-surface-line p-8 text-center">No hay centros disponibles dentro de tu permiso para planificar horarios.</div>
    @elseif (! $batch)
        <div class="space-y-4 rounded-xl border border-dashed border-surface-line bg-white p-8 text-center">
            <h2 class="text-lg font-semibold">¿Cómo quieres empezar esta semana?</h2>
            <p class="text-sm text-surface-muted">Puedes recuperar los horarios habituales o asignar turnos directamente. Todo se prepara como borrador.</p>
            <div class="flex flex-wrap justify-center gap-3">
                <button type="button" class="btn-primary" wire:click="openWeek(true)" wire:loading.attr="disabled">Usar horarios habituales</button>
                <button type="button" class="btn-outline" wire:click="openWeek(false)" wire:loading.attr="disabled">Armar semana desde cero</button>
            </div>
        </div>
    @else
            @if ($editable)
                <div class="flex flex-wrap justify-end gap-2">
                    <button type="button" class="btn-outline" wire:click="refreshRoster" wire:loading.attr="disabled" title="Agregar al borrador los trabajadores vigentes que todavía no están incluidos">Actualizar plantilla</button>
                    @can('create', [\App\Models\ShiftTemplate::class, app(\App\Domains\Tenancy\Support\CurrentCompany::class)->get()])
                        <button type="button" class="btn-outline cursor-pointer" wire:click="openCreateShift">+ Crear turno</button>
                    @endcan
                    @can('deleteDraft', $batch)
                        <button type="button" class="btn-danger" wire:click="deleteDraft" wire:loading.attr="disabled" wire:confirm="¿Eliminar este borrador y sus asignaciones de la semana? Los turnos, horarios habituales y publicaciones anteriores se conservan. Esta acción no se puede deshacer.">Eliminar borrador</button>
                    @endcan
                    <livewire:scheduling.daily-schedule-csv-import :schedule-batch-id="$batch->id" :worker-search="$search" :organizational-unit-id="$unitId" :key="'planner-csv-'.$batch->id.'-'.md5($search.'|'.$unitId)" />
                    <button type="button" class="btn-primary" wire:click="reviewWeek" wire:loading.attr="disabled">Revisar y publicar</button>
                </div>
            @endif

        @if ($editable)
            <div class="flex flex-wrap items-center gap-3 rounded-xl border border-blue-100 bg-blue-50 p-3 text-sm">
                <button type="button" class="btn-ghost btn-sm" wire:click="selectVisible">Seleccionar esta página</button>
                <span><span x-text="selectedPeople.length">{{ count($people) }}</span> personas seleccionadas</span>
                <button type="button" class="btn-primary btn-sm" wire:click="openAssignment" x-bind:disabled="selectedPeople.length === 0">Asignar horario</button>
                <button type="button" class="btn-ghost btn-sm" wire:click="clearSelection">Limpiar selección</button>
            </div>
            <section class="overflow-hidden rounded-2xl border border-blue-200 bg-white shadow-sm" aria-labelledby="planner-apply-title">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-blue-100 bg-blue-50 px-5 py-4">
                    <div>
                        <h3 id="planner-apply-title" class="font-display text-sm font-bold text-brand-navy">Aplicar turnos al calendario</h3>
                        <p class="mt-1 text-xs text-surface-muted">Asigna un turno o descanso a varios días en tres pasos.</p>
                    </div>
                    <span class="rounded-full border border-blue-200 bg-white px-3 py-1.5 text-xs font-semibold text-brand-blue"><span x-text="selectedCells.length">{{ count($cells) }}</span> celdas seleccionadas</span>
                </div>
                <div class="grid gap-5 p-5 lg:grid-cols-3">
                    <div class="flex items-start gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-sm font-bold text-blue-600" aria-hidden="true">01</span>
                        <div>
                            <h4 class="text-sm font-semibold text-brand-navy">Selecciona los días</h4>
                            <p class="mt-1 text-xs leading-relaxed text-surface-muted">Pulsa los encabezados de días o elige celdas del calendario. Puedes seleccionar varios días.</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-sm font-bold text-indigo-600" aria-hidden="true">02</span>
                        <div class="min-w-0 flex-1">
                            <flux:select label="Elige el turno o descanso" wire:model="brush">
                                <flux:select.option value="rest">Descanso</flux:select.option>
                                @foreach ($turns as $turn)<flux:select.option value="{{ $turn->id }}">{{ $turn->name }}</flux:select.option>@endforeach
                            </flux:select>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-sm font-bold text-emerald-600" aria-hidden="true">03</span>
                        <div class="min-w-0 flex-1">
                            <h4 class="mb-2 text-sm font-semibold text-brand-navy">Aplica a la selección</h4>
                            <button type="button" class="btn-primary cursor-pointer" wire:click="applyCells" wire:loading.attr="disabled" wire:target="applyCells" x-bind:disabled="selectedCells.length === 0">
                                <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m5 12 4 4L19 6" /></svg>
                                Aplicar a <span x-text="selectedCells.length">{{ count($cells) }}</span> días
                            </button>
                        </div>
                    </div>
                </div>
                <p class="border-t border-surface-line bg-surface-bg px-5 py-3 text-xs leading-relaxed text-surface-muted">Seleccionar un encabezado incluye a todas las personas que coinciden con los filtros, también en otras páginas. Al aplicar se reemplazan los horarios de las celdas seleccionadas.</p>
            </section>
            @if ($copySource)
                <div class="flex flex-wrap items-center gap-3 rounded-xl border border-blue-100 bg-blue-50 p-3 text-sm">
                    <span>Fila origen elegida. Se copiará a <span x-text="selectedPeople.length">{{ count($people) }}</span> personas seleccionadas.</span>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="replaceCopiedDays" class="rounded border-slate-300" />Reemplazar días definidos</label>
                    <button type="button" class="btn-outline btn-sm" wire:click="copyRow" wire:loading.attr="disabled" x-bind:disabled="selectedPeople.length === 0">Copiar fila al grupo</button>
                    <button type="button" class="btn-ghost btn-sm" wire:click="$set('copySource', '')">Cancelar copia</button>
                </div>
            @endif
        @endif

        <div class="overflow-hidden rounded-[18px] border border-surface-line bg-surface-card">
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-surface-line px-5 py-3 text-xs text-surface-muted">
                <span><strong class="mr-1 text-brand-navy">{{ $calendarStats['workers'] }}</strong> trabajadores</span>
                <span><strong class="mr-1 text-brand-navy">{{ $calendarStats['days'] }}</strong> días</span>
                @foreach (['shift' => 'turnos', 'rest' => 'descansos', 'flexible' => 'flexibles', 'on_call' => 'guardias', 'unassigned' => 'pendientes'] as $type => $label)
                    <span class="border-l border-surface-line pl-4"><strong class="mr-1 text-brand-navy">{{ $calendarStats[$type] ?? 0 }}</strong> {{ $label }}</span>
                @endforeach
            </div>
        <div class="vt-planner-calendar overflow-x-auto px-5 pb-5 pt-5">
            <table class="w-full min-w-[1280px] table-fixed border-collapse overflow-hidden rounded-[14px] border border-surface-line text-sm">
                <thead class="text-left">
                    <tr><th class="cell-head sticky left-0 z-10 w-[232px]">Trabajador</th>
                    @foreach ($dates as $date)
                        @php
                            $dayKeys = $columnKeys[$date->toDateString()] ?? [];
                        @endphp
                        <th class="cell-head w-[150px] text-left transition-colors last:border-r-0 {{ $editable && $dayKeys !== [] ? 'cursor-pointer hover:bg-blue-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-500' : '' }}" x-bind:class="@js($dayKeys).some(key => selectedCells.includes(key)) ? 'bg-blue-100 text-blue-950' : 'bg-surface-bg'"
                            @if ($editable && $dayKeys !== [])
                                role="button" tabindex="0" aria-label="Seleccionar o deseleccionar columna del {{ $date->format('d/m/Y') }}"
                                x-bind:aria-pressed="@js($dayKeys).some(key => selectedCells.includes(key))"
                                x-on:click="toggleCells(@js($dayKeys), !@js($dayKeys).some(key => selectedCells.includes(key)))"
                                x-on:keydown.enter.prevent.self="toggleCells(@js($dayKeys), !@js($dayKeys).some(key => selectedCells.includes(key)))"
                                x-on:keydown.space.prevent.self="toggleCells(@js($dayKeys), !@js($dayKeys).some(key => selectedCells.includes(key)))"
                            @endif>
                            <x-scheduling.day-heading :date="$date" />
                        </th>
                    @endforeach</tr>
                </thead>
                <tbody>
                    @forelse ($relationships as $relationship)
                        @php
                            $rowKeys = $dates->filter(fn ($date) => $relationship->isEffectiveOn($date) || ($batch->previous_batch_id && $assignments->has($relationship->id.'|'.$date->toDateString())))
                                ->map(fn ($date) => $relationship->id.'|'.$date->toDateString())->values()->all();
                            $workerName = $relationship->worker?->full_name ?? '';
                            $workerUnit = $assignments->first(fn ($day) => $day->employment_relationship_id === $relationship->id)?->organizationalUnit?->name ?: $relationship->position_name;
                        @endphp
                        <tr wire:key="planner-row-{{ $batch->id }}-{{ $relationship->id }}" class="border-t border-surface-line">
                            <th class="sticky left-0 z-10 border-r border-surface-line px-3.5 py-4 text-left font-normal transition-colors {{ $editable && $rowKeys !== [] ? 'cursor-pointer hover:bg-blue-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-500' : '' }}" x-bind:class="@js($rowKeys).some(key => selectedCells.includes(key)) ? 'bg-blue-100 text-blue-950' : 'bg-white'"
                                @if ($editable && $rowKeys !== [])
                                    role="button" tabindex="0" aria-label="Seleccionar o deseleccionar fila de {{ $relationship->worker?->full_name }}"
                                    x-bind:aria-pressed="@js($rowKeys).some(key => selectedCells.includes(key))"
                                    x-on:click="toggleCells(@js($rowKeys), !@js($rowKeys).some(key => selectedCells.includes(key)))"
                                    x-on:keydown.enter.prevent.self="toggleCells(@js($rowKeys), !@js($rowKeys).some(key => selectedCells.includes(key)))"
                                    x-on:keydown.space.prevent.self="toggleCells(@js($rowKeys), !@js($rowKeys).some(key => selectedCells.includes(key)))"
                                @endif>
                                <x-scheduling.worker-heading :name="$workerName" :code="$relationship->worker?->employee_code" :center="$batch->center?->name" :unit="$workerUnit">
                                    @if ($editable)<button type="button" class="mt-1 cursor-pointer text-[10px] font-medium text-blue-700 hover:underline" x-on:click.stop wire:click="$set('copySource', '{{ $relationship->id }}')">{{ $copySource === (string) $relationship->id ? '✓ Fila origen' : 'Copiar fila' }}</button>@endif
                                </x-scheduling.worker-heading>
                            </th>
                            @foreach ($dates as $date)
                                @php
                                    $key = $relationship->id.'|'.$date->toDateString();
                                    $assignment = $assignments->get($key);
                                    $valid = $relationship->isEffectiveOn($date) || ($batch->previous_batch_id && $assignment);
                                @endphp
                                <td wire:key="planner-cell-{{ $batch->id }}-{{ $key }}" x-data="{ cellKey: @js($key) }" class="border-r border-surface-line p-2 align-top transition-colors last:border-r-0" x-bind:class="@js($rowKeys).some(key => selectedCells.includes(key)) ? 'bg-blue-50/50' : ''">
                                    <x-scheduling.day-card :assignment="$assignment" :valid="$valid" :editable="$editable" x-on:click="toggleCells([cellKey], !selectedCells.includes(cellKey))"
                                        x-bind:aria-pressed="selectedCells.includes(cellKey)" aria-label="{{ $relationship->worker?->full_name }}, {{ $date->format('d/m') }}"
                                        x-bind:class="selectedCells.includes(cellKey) ? 'ring-2 ring-blue-500 ring-offset-1' : ''"
                                    />
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="8" class="p-8 text-center text-surface-muted">No hay personas vigentes con estos filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        </div>
        {{ $relationships->links() }}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-surface-muted">La publicación revisa a todo el centro, incluyendo personas que no aparecen en esta página o en los filtros.</p>
            @if ($editable)
                <button type="button" wire:click="openResetConfirmation" wire:loading.attr="disabled"
                    class="ml-auto inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs text-slate-500 transition hover:bg-red-50 hover:text-red-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-500">
                    <span aria-hidden="true">↺</span> Restablecer horarios
                </button>
            @endif
        </div>

        @if ($batch->status === 'published')
            <details wire:key="publication-tools-{{ $batch->id }}" class="group rounded-xl border border-surface-line bg-white">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-xl px-5 py-4 text-brand-navy transition hover:bg-slate-50 [&::-webkit-details-marker]:hidden">
                    <span><span class="block text-sm font-semibold">Gestionar publicación</span><span class="mt-1 block text-xs font-normal text-surface-muted">Corregir esta semana o clonar sus horarios.</span></span>
                    <svg class="h-4 w-4 shrink-0 text-surface-muted transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m5 7.5 5 5 5-5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </summary>
            <div class="grid gap-4 border-t border-surface-line p-4 lg:grid-cols-2">
                @can('createCorrection', $batch)
                    <form wire:submit="createCorrection" class="flex flex-col gap-4 rounded-xl border border-surface-line bg-white p-5">
                        <div><h2 class="font-semibold text-brand-navy">Cambiar publicación</h2><p class="mt-1 text-sm text-surface-muted">Prepara una corrección de esta semana conservando la versión publicada.</p></div>
                        <flux:input label="Motivo de la corrección" wire:model="correctionReason" />
                        <div class="mt-auto pt-2"><button type="submit" class="btn-outline cursor-pointer" wire:loading.attr="disabled" wire:target="createCorrection">Crear corrección</button></div>
                    </form>
                @endcan
                @can('create', [\App\Models\ScheduleBatch::class, app(\App\Domains\Tenancy\Support\CurrentCompany::class)->get()])
                    <form wire:submit="cloneWeek" class="flex flex-col gap-4 rounded-xl border border-surface-line bg-white p-5">
                        <div><h2 class="font-semibold text-brand-navy">Clonar semana</h2><p class="mt-1 text-sm text-surface-muted">Repite los horarios de esta publicación en semanas consecutivas.</p></div>
                        <div class="grid gap-3">
                            <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false" x-on:planner-copy-week-changed.window="open = false">
                                <p class="mb-2 text-sm font-medium">Primera semana destino</p>
                                <div class="flex items-center gap-2">
                                    <button type="button" class="btn-ghost cursor-pointer" wire:click="navigateCopyWeek(-1)" wire:loading.attr="disabled" aria-label="Semana destino anterior">←</button>
                                    <button type="button" class="flex-1 cursor-pointer rounded-lg border border-surface-line px-3 py-2 text-center text-sm hover:bg-slate-50" x-on:click="open = !open" x-bind:aria-expanded="open" aria-controls="planner-copy-week-search">
                                        <span class="block font-semibold">Semana {{ $copyStart->isoWeek() }} · {{ $copyStart->isoWeekYear() }} ▾</span>
                                        <span class="block text-surface-muted">Del {{ $copyStart->format('d/m/Y') }} al {{ $copyStart->addDays(6)->format('d/m/Y') }}</span>
                                    </button>
                                    <button type="button" class="btn-ghost cursor-pointer" wire:click="navigateCopyWeek(1)" wire:loading.attr="disabled" aria-label="Semana destino siguiente">→</button>
                                </div>
                                <div id="planner-copy-week-search" x-show="open" x-cloak class="absolute left-0 right-0 z-20 mt-2 space-y-3 rounded-xl border border-surface-line bg-white p-4 shadow-lg">
                                    <flux:input type="number" label="Buscar por año" min="1900" max="9998" wire:model.live.debounce.350ms="copyWeekSearchYear" />
                                    <flux:select label="Semana y rango de fechas" wire:model="copyWeekSearchNumber">
                                        @foreach ($copySearchWeeks as $copySearchWeek)
                                            <flux:select.option value="{{ $copySearchWeek->isoWeek() }}">Semana {{ $copySearchWeek->isoWeek() }} · {{ $copySearchWeek->format('d/m/Y') }} al {{ $copySearchWeek->addDays(6)->format('d/m/Y') }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    @error('copyWeekSearchNumber')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                                    <button type="button" class="btn-primary w-full cursor-pointer justify-center" wire:click="chooseCopyWeek" wire:loading.attr="disabled">Elegir semana destino</button>
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-[minmax(0,1fr)_minmax(0,2fr)] items-end gap-3">
                            <flux:select label="Cantidad de semanas" wire:model="cloneWeeks">
                                @foreach (range(1, 12) as $number)<flux:select.option value="{{ $number }}">{{ $number }} {{ $number === 1 ? 'semana' : 'semanas' }}</flux:select.option>@endforeach
                            </flux:select>
                            <flux:select label="Resultado de la clonación" wire:model.live="cloneMode">
                            <flux:select.option value="draft">Crear como borrador</flux:select.option>
                            <flux:select.option value="publish">Clonar y publicar directamente</flux:select.option>
                            </flux:select>
                        </div>
                        @if ($cloneMode === 'publish')<p class="rounded-lg bg-blue-50 p-3 text-xs leading-relaxed text-blue-900">Al confirmar se publicarán todas las semanas. Si alguna tiene conflictos o pendientes, no se creará ninguna.</p>@endif
                        <div class="mt-auto pt-2"><button type="submit" class="btn-primary cursor-pointer" wire:loading.attr="disabled" wire:target="cloneWeek"><span wire:loading wire:target="cloneWeek" class="btn-spinner"></span>{{ $cloneMode === 'publish' ? 'Clonar y publicar' : 'Crear borradores' }}</button></div>
                    </form>
                @endcan
            </div>
            </details>
        @endif
    @endif

    @if ($editable)
    <flux:modal wire:model="showResetConfirmation" name="planner-reset-schedules" focusable class="w-full max-w-lg overflow-hidden rounded-2xl !p-0">
        <form wire:submit="resetDraftSchedules">
            <div class="space-y-5 px-6 pb-6 pt-7 sm:px-7">
                <div class="flex items-start gap-3.5 pr-6">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-xl border border-amber-200 bg-amber-50 text-amber-700">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 10a8 8 0 1 1 1.8 7.1M4 4v6h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <div class="min-w-0">
                        <p class="mb-1 text-[11px] font-semibold uppercase tracking-widest text-surface-muted">Programación semanal</p>
                        <flux:heading size="lg" class="leading-snug text-brand-navy">Restablecer horarios del borrador</flux:heading>
                    </div>
                </div>

                <div class="flex items-center gap-3 rounded-xl border border-surface-line bg-slate-50 px-4 py-3">
                    <svg class="size-5 shrink-0 text-blue-600" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2" stroke="currentColor" stroke-width="1.7"/><path d="M8 3v4m8-4v4M4 10h16" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                    <div>
                        <p class="text-sm font-semibold text-brand-navy">Semana {{ $dates->first()->isoWeek() }} · {{ $dates->first()->isoWeekYear() }}</p>
                        <p class="mt-0.5 text-xs text-surface-muted">{{ $dates->first()->format('d/m/Y') }} al {{ $dates->last()->format('d/m/Y') }}</p>
                    </div>
                </div>

                <div class="rounded-xl border border-amber-200 bg-amber-50/70 px-4 py-3.5">
                    <p class="text-sm font-semibold text-amber-950">Toda la semana volverá a quedar pendiente</p>
                    <p class="mt-1.5 text-sm leading-relaxed text-amber-900">Se quitarán los horarios asignados, incluyendo los de personas fuera de los filtros y de otras páginas.</p>
                </div>
                <div class="flex items-start gap-2.5 text-sm leading-relaxed text-surface-muted">
                    <svg class="mt-0.5 size-4 shrink-0 text-emerald-600" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m4 10 4 4 8-8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <p>Se conservan el borrador, los turnos, los horarios habituales y las publicaciones anteriores.</p>
                </div>
                @error('planner')<p role="alert" class="text-sm text-status-danger-text">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-2 items-center gap-3 border-t border-surface-line bg-slate-50/80 px-6 py-4 sm:flex sm:justify-end sm:px-7">
                <button type="button" wire:click="$set('showResetConfirmation', false)" wire:loading.attr="disabled" wire:target="resetDraftSchedules"
                    class="btn cursor-pointer justify-center">Cancelar</button>
                <button type="submit" wire:loading.attr="disabled" wire:target="resetDraftSchedules"
                    class="btn-danger cursor-pointer justify-center">
                    <span wire:loading wire:target="resetDraftSchedules" class="btn-spinner"></span>
                    <span wire:loading.remove wire:target="resetDraftSchedules">Restablecer horarios</span>
                    <span wire:loading wire:target="resetDraftSchedules">Restableciendo...</span>
                </button>
            </div>
        </form>
    </flux:modal>
    @endif

    <x-side-panel wire:model="showAssignment" title="Asignar horario" subheading="Selecciona una forma de trabajar y aplícala al grupo completo." max-width="max-w-4xl">
        <form wire:submit="applyAssignment" class="space-y-5 p-6">
            <p class="rounded-lg bg-blue-50 p-3 text-sm font-medium">{{ count($people) }} personas · semana {{ $week }} al {{ $dates->last()->format('Y-m-d') }}</p>
            @can('create', [\App\Models\ShiftTemplate::class, app(\App\Domains\Tenancy\Support\CurrentCompany::class)->get()])
                <button type="button" class="btn-outline btn-sm" wire:click="openCreateShift">+ Crear turno desde el catálogo</button>
            @endcan
            <flux:select label="¿Cómo será este horario?" wire:model.live="mode">
                <flux:select.option value="manual">Solo esta semana</flux:select.option>
                @if (! $batch?->previous_batch_id)
                    <flux:select.option value="weekly">Se repite cada semana</flux:select.option>
                    <flux:select.option value="cycle">Rotación por ciclo</flux:select.option>
                    <flux:select.option value="existing">Usar un horario habitual existente</flux:select.option>
                @endif
            </flux:select>

            @if ($mode === 'existing')
                <flux:select label="Horario habitual" wire:model="profileId">
                    <flux:select.option value="">Selecciona horario</flux:select.option>
                    @foreach ($profiles as $profile)<flux:select.option value="{{ $profile->id }}">{{ $profile->name }}</flux:select.option>@endforeach
                </flux:select>
            @else
                @if ($mode !== 'manual')<flux:input label="Nombre para reutilizar este horario" placeholder="Ej. Oficina de lunes a viernes" wire:model="name" />@endif
                @if ($mode !== 'cycle')
                    <div class="space-y-3 rounded-xl border border-surface-line p-4">
                        <h3 class="font-semibold">Asignar el mismo turno a varios días</h3>
                        <div class="flex flex-wrap gap-3">
                            @foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $i => $label)
                                <label class="flex items-center gap-1 text-sm"><input type="checkbox" wire:model="workdays" value="{{ $i + 1 }}" class="rounded border-slate-300" />{{ $label }}</label>
                            @endforeach
                        </div>
                        <div class="flex flex-wrap items-end gap-2">
                            <div class="min-w-48 flex-1"><flux:select label="Turno" wire:model="fillTurn">
                                <flux:select.option value="">Selecciona turno</flux:select.option>
                                <flux:select.option value="rest">Descanso</flux:select.option>
                                @foreach ($turns as $turn)<flux:select.option value="{{ $turn->id }}">{{ $turn->name }}</flux:select.option>@endforeach
                            </flux:select></div>
                            <button type="button" class="btn-outline" wire:click="fillDays">Aplicar a días elegidos</button>
                        </div>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach (['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'] as $i => $label)
                            <flux:select label="{{ $label }}" wire:model="rules.{{ $i + 1 }}">
                                <flux:select.option value="rest">Descanso</flux:select.option>
                                @foreach ($turns as $turn)<flux:select.option value="{{ $turn->id }}">{{ $turn->name }}</flux:select.option>@endforeach
                            </flux:select>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-surface-muted">Define los días en orden. El ciclo vuelve al Día 1 al terminar; la fecha de inicio será su Día 1.</p>
                    <div class="flex flex-wrap items-end gap-3 rounded-xl border border-surface-line p-3">
                        <div class="min-w-48 flex-1"><flux:select label="Agregar bloque de" wire:model="cycleTurn">
                            <flux:select.option value="rest">Descanso</flux:select.option>
                            @foreach ($turns as $turn)<flux:select.option value="{{ $turn->id }}">{{ $turn->name }}</flux:select.option>@endforeach
                        </flux:select></div>
                        <div class="w-24"><flux:input type="number" label="Días" min="1" max="366" wire:model="cycleDays" /></div>
                        <button type="button" class="btn-outline" wire:click="addCycleBlock">Agregar bloque</button>
                    </div>
                    <p class="text-xs text-surface-muted">{{ count($cycle) }} días en el ciclo. Ejemplo: agrega 2 de mañana, 2 de noche y 2 de descanso.</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($cycle as $i => $value)
                            <div wire:key="cycle-day-{{ $i }}" class="flex items-end gap-2">
                                <div class="flex-1"><flux:select label="Día {{ $i + 1 }}" wire:model="cycle.{{ $i }}">
                                    <flux:select.option value="rest">Descanso</flux:select.option>
                                    @foreach ($turns as $turn)<flux:select.option value="{{ $turn->id }}">{{ $turn->name }}</flux:select.option>@endforeach
                                </flux:select></div>
                                <button type="button" class="btn-ghost" wire:click="removeCycleDay({{ $i }})" aria-label="Quitar día {{ $i + 1 }}" @disabled(count($cycle) <= 2)>×</button>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" class="btn-ghost" wire:click="$set('cycle', [])">Reiniciar ciclo</button>
                @endif
            @endif

            @if ($mode !== 'manual')
                <div class="grid gap-3 sm:grid-cols-2">
                    <flux:input type="date" label="{{ $mode === 'cycle' ? 'Fecha del Día 1' : 'Aplicar desde' }}" wire:model="effectiveFrom" />
                    <flux:input type="date" label="Hasta (opcional)" wire:model="effectiveTo" />
                </div>
                <label class="flex items-start gap-2 rounded-lg bg-amber-50 p-3 text-sm"><input type="checkbox" wire:model="replaceHabitual" class="mt-1 rounded border-slate-300" /><span>Reemplazar los horarios habituales individuales existentes desde la nueva fecha. La fecha debe ser posterior al inicio del horario anterior; las publicaciones se conservan.</span></label>
                <p class="text-xs text-surface-muted">Se aplica a las personas seleccionadas. Un horario individual tiene prioridad sobre los horarios del equipo, centro o empresa. Las próximas semanas podrán generarse con esta base.</p>
            @endif
            <label class="flex items-start gap-2 rounded-lg border border-surface-line p-3 text-sm"><input type="checkbox" wire:model="replaceDays" class="mt-1 rounded border-slate-300" /><span>Reemplazar también los días ya definidos del borrador. Sin marcar, solo se completan días vacíos o pendientes.</span></label>
            <p class="text-sm text-surface-muted">Los cambios se guardan en borrador para revisión.</p>
            @if ($errors->any())<div class="text-sm text-red-700" role="alert">@foreach ($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif
            <div class="flex justify-end gap-2">
                <button type="button" class="btn-ghost" wire:click="$set('showAssignment', false)">Cancelar</button>
                <button type="submit" class="btn-primary" wire:loading.attr="disabled">Aplicar horario al grupo</button>
            </div>
        </form>
    </x-side-panel>

    @can('create', [\App\Models\ShiftTemplate::class, app(\App\Domains\Tenancy\Support\CurrentCompany::class)->get()])
        <livewire:scheduling.shifts :embedded="true" :key="'planner-shift-catalog'" />
    @endcan

    <x-side-panel wire:model="showReview" title="Revisar semana" subheading="Comprueba la cobertura de todo el centro antes de publicar." max-width="max-w-2xl">
        <div class="space-y-4 p-6">
            @if ($review)
                <div class="grid grid-cols-3 gap-3 rounded-xl bg-slate-50 p-4 text-sm">
                    <p>Esperados<strong class="block text-xl">{{ $review['assignments_expected'] ?? 0 }}</strong></p>
                    <p>Sin asignar<strong class="block text-xl">{{ $review['assignments_missing'] ?? 0 }}</strong></p>
                    <p>Pendientes<strong class="block text-xl">{{ $review['assignments_unassigned'] ?? 0 }}</strong></p>
                </div>
                @foreach ($review['errors'] ?? [] as $message)<p class="rounded-lg bg-red-50 p-3 text-sm text-red-900">{{ $message }}</p>@endforeach
                @foreach ($review['warnings'] ?? [] as $message)<p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">{{ $message }}</p>@endforeach
                @if ($review['valid'] ?? false)
                    <p class="text-sm">La semana está lista. Al publicar, este horario quedará vigente y sus cambios posteriores se harán mediante una corrección.</p>
                    <button type="button" class="btn-primary" wire:click="publish" wire:loading.attr="disabled">Confirmar publicación</button>
                @endif
            @endif
            @if ($errors->any())<div class="text-sm text-red-700" role="alert">@foreach ($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif
            <button type="button" class="btn-ghost" wire:click="$set('showReview', false)">Volver al calendario</button>
        </div>
    </x-side-panel>
</section>
