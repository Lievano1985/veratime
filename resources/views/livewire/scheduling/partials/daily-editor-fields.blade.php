@php
    $dayType = data_get($this, $formRoot.'.day_type');
    $usesWindow = (bool) data_get($this, $formRoot.'.uses_window');
@endphp

@if ($dayType === 'shift')
    <div class="space-y-4">
        <flux:select label="Turno" wire:model.live="{{ $formRoot }}.shift_template_id">
            <flux:select.option value="">Selecciona una plantilla</flux:select.option>
            @foreach ($shiftTemplates as $template)
                <flux:select.option value="{{ $template->id }}">{{ $template->code }} - {{ $template->name }}</flux:select.option>
            @endforeach
        </flux:select>

        @if ($previewTemplate)
            <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                <p class="font-medium">{{ $previewTemplate['template']->name }}</p>
                <div class="mt-3 space-y-1">
                    @foreach ($previewTemplate['lines'] as $line)
                        <p>{{ $line }}</p>
                    @endforeach
                </div>
                <div class="mt-4 grid gap-2 text-xs text-zinc-600 md:grid-cols-4 dark:text-zinc-300">
                    <span>Trabajo bruto: {{ $this->formatMinutes($previewTemplate['metrics']['gross_work_minutes'] ?? 0) }}</span>
                    <span>Descanso fijo: {{ $this->formatMinutes(($previewTemplate['metrics']['fixed_paid_break_minutes'] ?? 0) + ($previewTemplate['metrics']['fixed_unpaid_break_minutes'] ?? 0)) }}</span>
                    <span>Trabajo efectivo: {{ $this->formatMinutes($previewTemplate['metrics']['effective_work_minutes'] ?? 0) }}</span>
                    <span>Cruza medianoche: {{ ($previewTemplate['metrics']['crosses_midnight'] ?? false) ? 'Si' : 'No' }}</span>
                </div>
            </div>
        @endif
    </div>
@elseif ($dayType === 'rest')
    <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
        Este dia quedara marcado como descanso dentro del borrador.
    </div>
@elseif ($dayType === 'flexible')
    <div class="space-y-4">
        <flux:input type="number" min="1" label="Minutos requeridos" wire:model="{{ $formRoot }}.required_minutes" />
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" wire:model.live="{{ $formRoot }}.uses_window" class="rounded border-zinc-300">
            <span>Usar ventana de cumplimiento</span>
        </label>
        @if ($usesWindow)
            <div class="grid gap-4 md:grid-cols-2">
                <flux:input type="time" label="Inicio de ventana" wire:model="{{ $formRoot }}.window_start_local_time" />
                <flux:select label="Dia inicial" wire:model="{{ $formRoot }}.window_start_day_offset">
                    <flux:select.option value="0">Mismo dia</flux:select.option>
                    <flux:select.option value="1">Dia siguiente</flux:select.option>
                </flux:select>
                <flux:input type="time" label="Fin de ventana" wire:model="{{ $formRoot }}.window_end_local_time" />
                <flux:select label="Dia final" wire:model="{{ $formRoot }}.window_end_day_offset">
                    <flux:select.option value="0">Mismo dia</flux:select.option>
                    <flux:select.option value="1">Dia siguiente</flux:select.option>
                </flux:select>
            </div>
        @endif
        <p class="text-sm text-zinc-500">La persona debe completar el tiempo requerido dentro de las condiciones indicadas.</p>
    </div>
@elseif ($dayType === 'on_call')
    <div class="space-y-4">
        <div class="grid gap-4 md:grid-cols-2">
            <flux:input type="time" label="Inicio de disponibilidad" wire:model="{{ $formRoot }}.availability_start_local_time" />
            <flux:select label="Dia inicial" wire:model="{{ $formRoot }}.availability_start_day_offset">
                <flux:select.option value="0">Mismo dia</flux:select.option>
                <flux:select.option value="1">Dia siguiente</flux:select.option>
            </flux:select>
            <flux:input type="time" label="Fin de disponibilidad" wire:model="{{ $formRoot }}.availability_end_local_time" />
            <flux:select label="Dia final" wire:model="{{ $formRoot }}.availability_end_day_offset">
                <flux:select.option value="0">Mismo dia</flux:select.option>
                <flux:select.option value="1">Dia siguiente</flux:select.option>
            </flux:select>
        </div>
        <flux:input type="number" min="1" label="Maximo de trabajo al activarse" wire:model="{{ $formRoot }}.max_work_minutes" />
        <p class="text-sm text-zinc-500">La disponibilidad se registra por separado del trabajo activado. No se contabiliza automaticamente como tiempo trabajado.</p>
    </div>
@elseif ($dayType === 'unassigned')
    <flux:select label="Razon del pendiente" wire:model="{{ $formRoot }}.pending_reason">
        <flux:select.option value="manual_definition_required">Requiere definicion manual</flux:select.option>
        <flux:select.option value="pending_shift_confirmation">Falta confirmar turno</flux:select.option>
        <flux:select.option value="no_applicable_profile">Sin perfil aplicable</flux:select.option>
        <flux:select.option value="other">Otro</flux:select.option>
    </flux:select>
@endif
