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

<div class="min-h-screen">
    <div class="fixed left-0 right-0 top-0 h-1.5 bg-gradient-to-r from-brand-blue-bright via-brand-sky to-brand-deep"></div>

    <div class="flex min-h-screen items-center justify-center bg-[radial-gradient(140%_100%_at_50%_-10%,#DCEEFF_0%,#EAF3FC_45%,#F4F9FF_100%)] p-5 font-sans text-surface-text sm:p-7">
        <div class="relative grid w-full max-w-[900px] grid-cols-1 gap-8 overflow-hidden rounded-[32px] bg-white px-7 py-9 shadow-[0_40px_90px_-30px_rgba(2,25,57,0.28)] md:grid-cols-2 md:gap-12 md:px-14 md:py-12">
            <div class="absolute bottom-0 left-1/2 top-0 hidden w-px bg-surface-line md:block"></div>

            <section class="flex flex-col items-center border-b border-surface-line pb-6 text-center md:border-b-0 md:pr-6">
                <img src="{{ asset('images/logo vera time.png') }}" alt="Vera Time" class="mb-4 h-16 w-auto">
                <div class="font-display text-[26px] font-extrabold text-brand-navy">Kiosco</div>
                <p class="mt-2 max-w-[280px] text-[13.5px] leading-relaxed text-surface-muted">Autoriza este equipo antes de usarlo para registrar entradas, salidas y pausas.</p>

                <div class="mt-8 w-full max-w-[260px] rounded-[20px] bg-gradient-to-br from-brand-blue-bright via-brand-blue to-brand-deep px-6 py-5 text-white">
                    <div data-kiosk-date class="font-mono text-[11.5px] uppercase tracking-[0.08em] text-white/75">--</div>
                    <div data-kiosk-clock class="mt-1.5 font-display text-[36px] font-bold tracking-wide tabular-nums">--:--:--</div>
                </div>

                <div class="mt-6 inline-flex items-center gap-2 rounded-full border border-status-warn-line bg-status-warn-bg px-3.5 py-2 text-[12.5px] font-semibold text-status-warn-text">
                    <span class="h-1.5 w-1.5 rounded-full bg-status-warn-text shadow-[0_0_0_3px_rgba(147,101,11,0.18)]"></span>
                    Pendiente de autorizacion
                </div>
            </section>

            <section class="flex flex-col justify-center md:pl-6">
                <form wire:submit="authorizeDevice" autocomplete="off" data-form-type="other" class="space-y-5">
                    <div>
                        <h1 class="font-display text-[24px] font-bold text-brand-navy">Autorizar terminal</h1>
                        <p class="mt-2 text-[13.5px] leading-relaxed text-surface-muted">Escanea el QR o pega el codigo generado desde Configuracion de empresa. El codigo solo funciona durante una hora y una sola vez.</p>
                    </div>

                    @error('pairingCode')
                        <div class="rounded-xl border border-status-pending-line bg-status-pending-bg px-4 py-3 text-[13px] text-status-pending-text">{{ $message }}</div>
                    @enderror

                    <div>
                        <label for="pairing-code" class="mb-1.5 block text-[12.5px] font-semibold text-brand-navy">Codigo de autorizacion</label>
                        <textarea id="pairing-code" wire:model="pairingCode" rows="4" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" autofocus class="w-full rounded-2xl border-[1.5px] border-surface-line bg-[#FBFCFE] px-4 py-3 font-mono text-sm outline-none transition focus:border-brand-blue focus:bg-white focus:ring-4 focus:ring-brand-blue/10"></textarea>
                    </div>

                    <button type="submit" class="w-full rounded-2xl bg-gradient-to-r from-brand-blue-bright via-brand-blue to-brand-deep py-4 font-display text-base font-bold tracking-wide text-white shadow-[0_14px_26px_-10px_rgba(0,103,228,0.55)] transition hover:-translate-y-0.5 active:translate-y-0">Autorizar esta terminal</button>
                </form>

                <a href="{{ route('kiosk.index') }}" class="btn-ghost mt-5 flex w-full justify-center">Volver al kiosco</a>
                <div class="mt-5 text-center text-[11.5px] text-[#A6B3C4]">Vera Time · Terminal de registro</div>
            </section>
        </div>
    </div>

    <script>
        (() => {
            const tick = () => {
                const clock = document.querySelector('[data-kiosk-clock]');
                const date = document.querySelector('[data-kiosk-date]');
                if (!clock || !date) return;

                const now = new Date();
                const hh = String(now.getHours()).padStart(2, '0');
                const mm = String(now.getMinutes()).padStart(2, '0');
                const ss = String(now.getSeconds()).padStart(2, '0');
                const days = ['Domingo', 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado'];
                const months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

                clock.textContent = `${hh}:${mm}:${ss}`;
                date.textContent = `${days[now.getDay()]}, ${now.getDate()} ${months[now.getMonth()]}`;
            };

            tick();
            setInterval(tick, 1000);
        })();
    </script>
</div>
