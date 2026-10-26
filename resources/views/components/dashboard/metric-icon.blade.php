@props(['icon', 'tone' => null])
@php
    $tone ??= match ($icon) {
        'users' => 'violet', 'user-plus' => 'sky', 'clock' => 'amber',
        'check-circle' => 'emerald', 'exclamation-circle', 'exclamation-triangle' => 'orange',
        default => 'indigo',
    };
    $colors = match ($tone) {
        'violet' => 'bg-violet-50 text-violet-600 dark:bg-violet-950 dark:text-violet-300',
        'sky' => 'bg-sky-50 text-sky-600 dark:bg-sky-950 dark:text-sky-300',
        'amber' => 'bg-amber-50 text-amber-600 dark:bg-amber-950 dark:text-amber-300',
        'emerald' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-300',
        'orange' => 'bg-orange-50 text-orange-600 dark:bg-orange-950 dark:text-orange-300',
        'rose' => 'bg-rose-50 text-rose-600 dark:bg-rose-950 dark:text-rose-300',
        default => 'bg-indigo-50 text-indigo-600 dark:bg-indigo-950 dark:text-indigo-300',
    };
@endphp
<span class="flex size-10 shrink-0 items-center justify-center rounded-xl {{ $colors }}"><flux:icon :icon="$icon" variant="outline" class="size-5" /></span>
