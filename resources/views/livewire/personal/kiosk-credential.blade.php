<section id="mi-clave-kiosco" class="rounded-2xl border border-surface-line bg-white p-5"
    x-data="{ pin: null, code: null }"
    x-on:personal-kiosk-pin-generated.window="pin = $event.detail.pin; code = $event.detail.code"
    x-on:personal-kiosk-pin-clear.window="pin = null; code = null"
    x-on:visibilitychange.document="if (document.hidden) { pin = null; code = null }">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0 flex-1">
            <h3 class="font-display font-semibold text-brand-navy">Mi clave de kiosco</h3>
            <p class="mt-1 text-sm text-surface-muted">Usa tu número de empleado y un NIP personal en la terminal autorizada de tu empresa.</p>
            <p class="mt-4 text-sm text-surface-muted">Número de empleado: <strong class="font-mono text-brand-navy">{{ $worker->employee_code }}</strong></p>
            <p class="mt-2 text-xs text-surface-muted">{{ match ($credential?->status) { 'active' => 'Tu credencial está activa. Puedes generar un NIP nuevo si lo necesitas.', 'reset_required' => 'Tu credencial necesita un NIP nuevo.', 'blocked', 'inactive' => 'Tu credencial está bloqueada o inactiva. Contacta a RH.', default => 'Todavía no has generado tu clave de kiosco.' } }}</p>
        </div>
        <button type="button" class="btn-outline cursor-pointer" wire:click="openPanel" @disabled($credential && ! in_array($credential->status, ['active', 'reset_required'], true))>
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="8" cy="15" r="5"/><path d="m11.5 11.5 8-8M16 7l3 3M19 4l2 2"/></svg>
            {{ $credential ? 'Renovar mi NIP' : 'Generar mi NIP' }}
        </button>
    </div>

    <x-side-panel wire:model="showPanel" title="Mi clave de kiosco" subheading="La clave corresponde únicamente a tu trabajador y empresa." labelledby="personal-kiosk-pin-title" max-width="max-w-lg" close-method="closePanel">
        <div class="space-y-5 p-5 sm:p-6">
            <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                <p class="text-sm font-semibold text-brand-navy">Tu código de acceso es {{ $worker->employee_code }}</p>
                <p class="mt-2 text-sm text-surface-muted">Confirmaremos la contraseña con la que entras a tu cuenta y generaremos un NIP de seis dígitos.</p>
            </div>
            <form wire:submit="generatePin" class="space-y-4">
                <flux:input label="Contraseña de tu cuenta" type="password" autocomplete="current-password" wire:model="currentPassword" required />
                <p class="text-xs leading-relaxed text-surface-muted">Al renovar, el NIP anterior deja de funcionar. Memoriza el nuevo: no podremos consultarlo después.</p>
                <button type="submit" class="btn-primary cursor-pointer" wire:loading.attr="disabled" wire:target="generatePin">
                    <span wire:loading.remove wire:target="generatePin">Generar NIP de kiosco</span>
                    <span wire:loading wire:target="generatePin">Generando…</span>
                </button>
            </form>
            <div x-show="pin !== null" x-cloak role="status" class="space-y-3 rounded-xl border border-emerald-200 bg-emerald-50 p-5">
                <p class="text-sm font-semibold text-emerald-900">Tu nueva clave está lista</p>
                <p class="text-sm">Número de empleado: <strong x-text="code"></strong></p>
                <p class="font-mono text-3xl font-bold tracking-widest text-brand-navy" x-text="pin"></p>
                <p class="text-xs text-emerald-900">Se muestra una sola vez. Se ocultará al cerrar este panel o cambiar de pestaña. No la compartas.</p>
                <button type="button" class="btn-outline cursor-pointer" x-on:click="pin = null; code = null">Ocultar NIP</button>
            </div>
        </div>
    </x-side-panel>
</section>
