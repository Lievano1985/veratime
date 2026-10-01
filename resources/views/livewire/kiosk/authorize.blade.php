<?php

use App\Domains\TimeRecords\Actions\PairKioskDeviceAction;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    private const DEVICE_COOKIE = 'vera_kiosk_device';

    public string $pairingCode = '';

    public function mount(): void
    {
        $this->pairingCode = trim((string) request()->query('code', ''));
    }

    public function authorizeDevice(PairKioskDeviceAction $action)
    {
        $this->validate([
            'pairingCode' => ['required', 'string', 'max:100'],
        ]);

        $throttleKey = 'kiosk-pairing:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'pairingCode' => 'Espera un minuto antes de intentar otro codigo.',
            ]);
        }

        try {
            $pairing = $action->handle($this->pairingCode, request()->ip(), request()->userAgent());
        } catch (\InvalidArgumentException) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'pairingCode' => 'El codigo de autorizacion no es valido o ya vencio.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        Cookie::queue(Cookie::make(
            self::DEVICE_COOKIE,
            $pairing['device_token'],
            60 * 24 * 365,
            '/time/kiosk',
            null,
            request()->isSecure() || app()->environment('production'),
            true,
            false,
            'strict',
        ));

        session()->flash('status', 'Terminal autorizada para '.$pairing['device']->company->name.'.');

        return redirect()->route('kiosk.index');
    }
}; ?>

<div class="min-h-screen bg-[radial-gradient(140%_100%_at_50%_-10%,#DCEEFF_0%,#EAF3FC_45%,#F4F9FF_100%)] p-5 font-sans text-surface-text sm:p-7">
    <div class="mx-auto flex min-h-screen max-w-xl items-center justify-center">
        <section class="w-full rounded-[28px] bg-white px-7 py-9 shadow-[0_40px_90px_-30px_rgba(2,25,57,0.28)] sm:px-10">
            <img src="{{ asset('images/logo vera time.png') }}" alt="Vera Time" class="mx-auto h-14 w-auto">
            <h1 class="mt-6 text-center font-display text-2xl font-bold text-brand-navy">Autorizar terminal</h1>
            <p class="mt-2 text-center text-sm leading-relaxed text-surface-muted">Escanea el QR o pega el codigo generado desde Configuracion de empresa. El codigo solo funciona durante una hora y una sola vez.</p>

            <form wire:submit="authorizeDevice" autocomplete="off" class="mt-7 space-y-5">
                <div>
                    <label for="pairing-code" class="mb-1.5 block text-sm font-semibold text-brand-navy">Codigo de autorizacion</label>
                    <textarea id="pairing-code" wire:model="pairingCode" rows="4" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" autofocus class="w-full rounded-2xl border-[1.5px] border-surface-line bg-[#FBFCFE] px-4 py-3 font-mono text-sm outline-none transition focus:border-brand-blue focus:bg-white focus:ring-4 focus:ring-brand-blue/10"></textarea>
                    @error('pairingCode')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="w-full rounded-2xl bg-gradient-to-r from-brand-blue-bright via-brand-blue to-brand-deep py-4 font-display text-base font-bold tracking-wide text-white shadow-[0_14px_26px_-10px_rgba(0,103,228,0.55)]">Autorizar esta terminal</button>
            </form>

            <a href="{{ route('kiosk.index') }}" class="btn-ghost mt-5 flex w-full justify-center">Volver al kiosco</a>
        </section>
    </div>
</div>
