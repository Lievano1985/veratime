@php
    $trialCompany = app(\App\Domains\Tenancy\Support\CurrentCompany::class)->get();
    $trial = $trialCompany && auth()->user()?->can('update', $trialCompany)
        ? \App\Domains\Companies\Support\CompanyTrialStatus::forCompany($trialCompany) : null;
@endphp
@if ($trial)
    <aside role="status" @class([
        'mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border p-4 text-sm',
        'border-amber-300 bg-amber-50 text-amber-950' => $trial['urgent'],
        'border-blue-200 bg-blue-50 text-brand-navy' => ! $trial['urgent'],
    ])>
        <div class="flex items-start gap-3">
            <svg class="mt-0.5 h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
            <div>
                <p class="font-semibold">{{ $trial['expired'] ? 'Tu periodo de prueba ha vencido' : 'Periodo de prueba: quedan '.$trial['days'].' '.($trial['days'] === 1 ? 'día' : 'días') }}</p>
                <p class="mt-1 text-xs">{{ $trialCompany->name }} · Vence el {{ $trial['endsAt']->format('d/m/Y H:i') }} ({{ $trialCompany->timezone }}).</p>
            </div>
        </div>
        <p class="text-xs">{{ $trial['urgent'] ? 'Contacta a Vera Time para activar tu empresa y continuar usando el servicio.' : 'Explora el sistema durante los 15 días de prueba.' }}</p>
    </aside>
@endif
