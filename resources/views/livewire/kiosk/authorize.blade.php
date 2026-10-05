<?php

use App\Domains\TimeRecords\Actions\ClaimKioskTerminalAccessRequestAction;
use App\Domains\TimeRecords\Actions\RequestKioskTerminalAccessAction;
use Flux\Flux;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    private const DEVICE_COOKIE = 'vera_kiosk_device';

    private const REQUEST_COOKIE = 'vera_kiosk_terminal_request';

    public string $companyIdentifier = '';

    public string $enrollmentKey = '';

    public string $terminalName = '';

    public bool $waitingForApproval = false;

    public function mount(): void
    {
        $requestSecret = request()->cookie(self::REQUEST_COOKIE);
        $this->waitingForApproval = filled($requestSecret);

        if ($this->waitingForApproval) {
            $this->queueRequestCookie($requestSecret);
        }
    }

    public function requestAuthorization(RequestKioskTerminalAccessAction $action): void
    {
        $identifier = trim($this->companyIdentifier);
        $key = $this->enrollmentKey;
        $name = trim($this->terminalName);

        if ($name === '' || mb_strlen($name) > 120) {
            $this->showRequestError('Escribe un nombre valido para esta terminal.');

            return;
        }

        if ($identifier === '' || mb_strlen($identifier) > 32) {
            $this->showRequestError('Escribe un codigo de empresa valido.');

            return;
        }

        if (mb_strlen($key) < 8 || mb_strlen($key) > 120) {
            $this->showRequestError('La clave de solicitud debe tener entre 8 y 120 caracteres.');

            return;
        }

        $throttleKey = 'kiosk-terminal-request:'.request()->ip().'|'.mb_strtoupper($identifier);

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $this->showRequestError('Espera un minuto antes de solicitar otra autorizacion.');

            return;
        }

        try {
            $request = $action->handle(
                $identifier,
                $key,
                $name,
                request()->ip(),
                request()->userAgent(),
            );
        } catch (\InvalidArgumentException $exception) {
            RateLimiter::hit($throttleKey, 60);
            $this->showRequestError($exception->getMessage());

            return;
        }

        RateLimiter::clear($throttleKey);

        Cookie::queue(Cookie::make(
            self::REQUEST_COOKIE,
            $request['request_secret'],
            15,
            '/',
            null,
            request()->isSecure() || app()->environment('production'),
            true,
            false,
            'strict',
        ));
        Cookie::queue(Cookie::forget(self::REQUEST_COOKIE, '/time/kiosk'));

        $this->enrollmentKey = '';
        $this->waitingForApproval = true;

        Flux::toast('La solicitud fue enviada. Espera la aprobacion de un administrador.', 'Solicitud enviada', 6000, 'success', 'top end');
    }

    public function checkRequestStatus(ClaimKioskTerminalAccessRequestAction $action)
    {
        $requestSecret = request()->cookie(self::REQUEST_COOKIE);

        if (blank($requestSecret)) {
            $this->waitingForApproval = false;

            return;
        }

        $result = $action->handle($requestSecret, request()->ip(), request()->userAgent());

        if ($result['status'] === 'claimed') {
            $this->queueDeviceCookie($result['device_token']);
            $this->forgetRequestCookie();

            session()->flash('kiosk_toast', [
                'text' => 'Esta terminal fue autorizada y ya esta lista para registrar asistencias.',
                'heading' => 'Terminal autorizada',
                'variant' => 'success',
            ]);

            return redirect()->route('kiosk.index');
        }

        if ($result['status'] === 'pending') {
            return;
        }

        $this->forgetRequestCookie();
        $this->waitingForApproval = false;

        $message = match ($result['status']) {
            'rejected' => 'La solicitud de esta terminal fue rechazada por un administrador.',
            'expired' => 'La solicitud vencio antes de ser autorizada. Puedes enviar una nueva.',
            'claimed' => 'La solicitud ya fue utilizada por esta terminal.',
            default => 'La solicitud ya no esta disponible. Puedes enviar una nueva.',
        };

        Flux::toast($message, 'Solicitud de terminal', 6000, 'warning', 'top end');
    }

    private function showRequestError(string $message): void
    {
        $this->addError('enrollmentKey', $message);

        Flux::toast($message, 'No fue posible solicitar la terminal', 6000, 'danger', 'top end');
    }

    private function queueRequestCookie(string $requestSecret): void
    {
        Cookie::queue(Cookie::make(
            self::REQUEST_COOKIE,
            $requestSecret,
            15,
            '/',
            null,
            request()->isSecure() || app()->environment('production'),
            true,
            false,
            'strict',
        ));
        Cookie::queue(Cookie::forget(self::REQUEST_COOKIE, '/time/kiosk'));
    }

    private function queueDeviceCookie(string $deviceToken): void
    {
        Cookie::queue(Cookie::make(
            self::DEVICE_COOKIE,
            $deviceToken,
            60 * 24 * 365,
            '/',
            null,
            request()->isSecure() || app()->environment('production'),
            true,
            false,
            'strict',
        ));
        Cookie::queue(Cookie::forget(self::DEVICE_COOKIE, '/time/kiosk'));
    }

    private function forgetRequestCookie(): void
    {
        Cookie::queue(Cookie::forget(self::REQUEST_COOKIE, '/'));
        Cookie::queue(Cookie::forget(self::REQUEST_COOKIE, '/time/kiosk'));
    }
}; ?>

<div class="min-h-screen" @if ($waitingForApproval) wire:poll.5s="checkRequestStatus" @endif>
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
                    {{ $waitingForApproval ? 'Solicitud pendiente' : 'Terminal sin autorizar' }}
                </div>
            </section>

            <section class="flex flex-col justify-center md:pl-6">
                @if ($waitingForApproval)
                    <div class="rounded-[24px] border border-status-pending-line bg-status-pending-bg px-6 py-7 text-center text-status-pending-text">
                        <p class="font-display text-2xl font-bold">Solicitud enviada</p>
                        <p class="mt-3 text-sm leading-relaxed">Un administrador debe aceptar esta terminal desde Configuracion de empresa. Esta pantalla permanecera lista y continuara automaticamente al aprobarse.</p>
                    </div>
                @else
                    <form wire:submit="requestAuthorization" autocomplete="off" data-form-type="other" class="space-y-5">
                        <div>
                            <h1 class="font-display text-[24px] font-bold text-brand-navy">Solicitar terminal</h1>
                            <p class="mt-2 text-[13.5px] leading-relaxed text-surface-muted">Captura el codigo de empresa y la clave de solicitud. Un administrador debe aprobar este equipo antes de registrar asistencias.</p>
                        </div>

                        <div>
                            <label for="terminal-name" class="mb-1.5 block text-[12.5px] font-semibold text-brand-navy">Nombre de la terminal</label>
                            <input id="terminal-name" wire:model="terminalName" type="text" maxlength="120" autocomplete="off" autocapitalize="words" data-lpignore="true" data-1p-ignore="true" autofocus placeholder="Ej. Recepcion planta norte" class="w-full rounded-2xl border-[1.5px] border-surface-line bg-[#FBFCFE] px-4 py-3 text-sm outline-none transition placeholder:text-[#9AA8BB] focus:border-brand-blue focus:bg-white focus:ring-4 focus:ring-brand-blue/10">
                        </div>

                        <div>
                            <label for="company-identifier" class="mb-1.5 block text-[12.5px] font-semibold text-brand-navy">Codigo de empresa</label>
                            <input id="company-identifier" wire:model="companyIdentifier" type="text" maxlength="32" autocomplete="off" autocapitalize="characters" autocorrect="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" placeholder="Ej. VT-AB12CD" class="w-full rounded-2xl border-[1.5px] border-surface-line bg-[#FBFCFE] px-4 py-3 font-mono text-sm uppercase outline-none transition placeholder:font-sans placeholder:normal-case placeholder:text-[#9AA8BB] focus:border-brand-blue focus:bg-white focus:ring-4 focus:ring-brand-blue/10">
                        </div>

                        <div>
                            <label for="enrollment-key" class="mb-1.5 block text-[12.5px] font-semibold text-brand-navy">Clave de solicitud</label>
                            <input id="enrollment-key" wire:model="enrollmentKey" type="password" maxlength="120" autocomplete="new-password" autocapitalize="off" autocorrect="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" class="w-full rounded-2xl border-[1.5px] border-surface-line bg-[#FBFCFE] px-4 py-3 font-mono text-sm outline-none transition focus:border-brand-blue focus:bg-white focus:ring-4 focus:ring-brand-blue/10">
                        </div>

                        <button type="submit" wire:loading.attr="disabled" wire:target="requestAuthorization" class="w-full rounded-2xl bg-gradient-to-r from-brand-blue-bright via-brand-blue to-brand-deep py-4 font-display text-base font-bold tracking-wide text-white shadow-[0_14px_26px_-10px_rgba(0,103,228,0.55)] transition hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-70 active:translate-y-0">Enviar solicitud</button>
                    </form>
                @endif

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
