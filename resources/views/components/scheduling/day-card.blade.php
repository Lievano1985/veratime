@props(['assignment' => null, 'valid' => true, 'editable' => false, 'historical' => false, 'dateLabel' => null, 'summary' => null])
@php
    $theme = match ($assignment?->day_type) {
        'shift', 'flexible', 'on_call' => 'shift-turno',
        'rest' => 'shift-descanso',
        default => 'shift-pendiente',
    };
    $work = $assignment?->segments->where('segment_type', 'work');
    $first = $work?->sortBy(fn ($segment) => sprintf('%02d|%s', $segment->start_day_offset, $segment->start_local_time))->first();
    $last = $work?->sortBy(fn ($segment) => sprintf('%02d|%s', $segment->end_day_offset, $segment->end_local_time))->last();
    $description = trim((string) ($assignment?->shiftTemplate?->description ?: $assignment?->shiftTemplate?->name));
    if ($first && $last) {
        $start = preg_quote(substr($first->start_local_time, 0, 5), '/');
        $end = preg_quote(substr($last->end_local_time, 0, 5), '/');
        $description = trim(preg_replace('/\s*'.$start.'\s*(?:a|al|hasta|[-–—])\s*'.$end.'(?:\s*\(\+1(?:\s*día)?\))?\s*$/iu', '', $description));
    }
    $label = match ($assignment?->day_type) { 'rest' => 'Descanso', 'flexible' => 'Flexible', 'on_call' => 'Guardia', default => 'Pendiente' };
@endphp
<button type="button" @disabled(! $editable || ! $valid)
    {{ $attributes->class([
        $valid ? $theme : 'rounded-xl border border-dashed border-surface-line bg-surface-bg p-2.5 text-surface-muted',
        'flex min-h-[82px] w-full cursor-pointer flex-col items-center justify-center text-center transition hover:-translate-y-0.5 hover:shadow-[0_12px_24px_-18px_rgba(2,25,57,0.35)] disabled:cursor-default disabled:hover:translate-y-0 disabled:hover:shadow-none',
    ]) }}>
    @if ($dateLabel)<span class="mb-1 block font-mono text-[10.5px] text-surface-muted">{{ $dateLabel }}</span>@endif
    @if (! $valid)
        <span class="text-xs">Fuera de vigencia</span>
    @elseif ($assignment?->day_type === 'shift')
        <span class="shift-label justify-center"><span class="code-tag !mr-0 break-words">{{ $assignment->shiftTemplate?->code ?? 'Horario' }}</span></span>
        @if ($description !== '')<span class="shift-detail block">{{ $description }}</span>@endif
        @if ($first && $last)<span class="shift-time block">{{ substr($first->start_local_time, 0, 5) }}–{{ substr($last->end_local_time, 0, 5) }}{{ $last->end_day_offset ? ' (+1)' : '' }}</span>@endif
    @else
        <span class="shift-label justify-center">{{ $label }}</span>
        @if ($summary && $summary !== $label)<span class="shift-detail block">{{ $summary }}</span>@endif
    @endif
    @if ($historical)<span class="mt-1 rounded-full bg-white/70 px-1.5 py-0.5 text-[9px] font-semibold text-surface-muted">Baja</span>@endif
</button>
