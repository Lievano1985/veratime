@props(['name' => '', 'code' => '', 'center' => '', 'unit' => null])
@php
    $initials = collect(preg_split('/\s+/u', trim($name ?: 'VT')))->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    $context = $center.($unit ? ' · '.$unit : '');
@endphp
<div {{ $attributes->class('flex items-center gap-2.5') }}>
    <span aria-hidden="true" class="emp-avatar bg-gradient-to-br from-brand-blue-bright to-brand-deep">{{ $initials }}</span>
    <div class="min-w-0 flex-1">
        <span class="emp-name block truncate" title="{{ $name }}">{{ $name }}</span>
        <span class="emp-code block truncate">{{ $code }}</span>
        <span class="emp-role block truncate" title="{{ $context }}">{{ $context }}</span>
        {{ $slot }}
    </div>
</div>
