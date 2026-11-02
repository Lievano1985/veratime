@props(['date'])
@php
    $day = \Carbon\CarbonImmutable::parse($date);
@endphp
<span {{ $attributes }}>
    <span class="block font-semibold">{{ ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'][$day->dayOfWeekIso - 1] }}</span>
    <span class="block text-[11px] font-medium text-surface-muted">{{ $day->format('d/m') }}</span>
</span>
