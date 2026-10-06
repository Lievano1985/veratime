<?php

use App\Domains\TimeRecords\Actions\RegisterKioskTimeEventAction;
use App\Domains\TimeRecords\Actions\ResolveCurrentTimeRecordStateAction;
use App\Domains\TimeRecords\Actions\ResolveKioskCredentialAction;
use App\Domains\Companies\Actions\ResolveCompanyBrandingImageUrlAction;
use App\Models\Company;
use App\Models\KioskDevice;
use App\Models\WorkerCredential;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    private const TOKEN_TTL_MINUTES = 5;

    private const DEVICE_COOKIE = 'vera_kiosk_device';

    public ?int $kioskCompanyId = null;

    public ?string $kioskCompanyName = null;

    public ?string $kioskCompanyBrandImageUrl = null;

    public ?int $kioskDeviceId = null;

    public ?string $kioskDeviceName = null;

    public string $accessCode = '';

    public string $pin = '';

    public ?string $credentialToken = null;

    public ?string $workerName = null;

    public ?string $localDate = null;

    public ?string $timezone = null;

    public array $allowedActions = [];

    public ?string $confirmationMessage = null;

    public ?string $confirmationTime = null;

    public function mount(): void
    {
        if (! $this->loadKioskCompanyFromDevice()) {
            $this->redirectRoute('kiosk.authorize');

            return;
        }

        $toast = session()->pull('kiosk_toast');

        if (is_array($toast)) {
            \Flux\Flux::toast(
                $toast['text'] ?? '',
                $toast['heading'] ?? null,
                5000,
                $toast['variant'] ?? 'info',
                'top end',
            );
        }
    }

    public function refreshAuthorizedTerminal(): void
    {
        if (! $this->loadKioskCompanyFromDevice()) {
            $this->resetKioskState();
            $this->redirectRoute('kiosk.authorize');
        }
    }

    public function identify(ResolveKioskCredentialAction $resolveCredential, ResolveCurrentTimeRecordStateAction $resolveState): void
    {
        $company = $this->kioskCompanyOrFail();
        $device = $this->authorizedDeviceOrNull();

        if (! $device) {
            throw ValidationException::withMessages([
                'accessCode' => 'Esta terminal ya no esta autorizada. Solicita un codigo de autorizacion al administrador.',
            ]);
        }

        $this->validate([
            'accessCode' => ['required', 'string', 'max:50'],
            'pin' => ['required', 'string', 'max:20'],
        ]);

        try {
            $credential = $resolveCredential->handle($company, $this->accessCode, $this->pin, $device);
        } catch (\InvalidArgumentException) {
            $this->pin = '';

            throw ValidationException::withMessages([
                'accessCode' => 'No se pudo validar la credencial.',
            ]);
        }

        $state = $this->stateForCredential($credential, $resolveState);

        $this->credentialToken = Crypt::encryptString(json_encode([
            'company_id' => $credential->company_id,
            'credential_id' => $credential->id,
            'worker_id' => $credential->worker_id,
            'kiosk_device_id' => $device?->id,
            'issued_at' => now()->timestamp,
        ], JSON_THROW_ON_ERROR));
        $this->workerName = $credential->worker->full_name;
        $this->localDate = $state['local_date'];
        $this->timezone = $state['timezone'];
        $this->allowedActions = $state['allowed_actions'];
        $this->confirmationMessage = null;
        $this->confirmationTime = null;
        $this->pin = '';
    }

    public function record(string $eventType, RegisterKioskTimeEventAction $register, ResolveCurrentTimeRecordStateAction $resolveState): void
    {
        $credential = $this->credentialFromToken();
        $device = $this->authorizedDeviceOrNull();

        if (! $device) {
            $this->resetKioskState();

            throw ValidationException::withMessages([
                'accessCode' => 'La terminal ya no esta autorizada. Vuelve a solicitar autorizacion al administrador.',
            ]);
        }

        if ((int) ($this->credentialTokenPayload()['kiosk_device_id'] ?? 0) !== (int) ($device?->id ?? 0)) {
            $this->resetKioskState();

            throw ValidationException::withMessages([
                'accessCode' => 'Vuelve a identificarte para continuar.',
            ]);
        }

        try {
            $event = $register->handle($credential, $eventType, $device);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'accessCode' => $exception->getMessage(),
            ]);
        }

        $this->confirmationMessage = $this->eventMessage($event->event_type);
        $this->confirmationTime = $event->occurred_local_date->toDateString().' '.$event->occurred_local_time;
        Session::flash('status', $this->confirmationMessage);

        $this->resetKioskState(keepConfirmation: true);
    }

    public function resetKiosk(): void
    {
        $this->resetKioskState(keepConfirmation: false);
    }

    private function loadKioskCompanyFromDevice(): bool
    {
        $device = $this->authorizedDeviceOrNull();

        if (! $device) {
            return false;
        }

        $this->kioskCompanyId = $device->company_id;
        $this->kioskCompanyName = $device->company->name;
        $this->kioskCompanyBrandImageUrl = app(ResolveCompanyBrandingImageUrlAction::class)->handle($device->company);
        $this->kioskDeviceId = $device->id;
        $this->kioskDeviceName = $device->name;

        return true;
    }

    private function authorizedDeviceOrNull(): ?KioskDevice
    {
        $token = request()->cookie(self::DEVICE_COOKIE);

        if (blank($token)) {
            $this->kioskCompanyId = null;
            $this->kioskCompanyName = null;
            $this->kioskCompanyBrandImageUrl = null;
            $this->kioskDeviceId = null;
            $this->kioskDeviceName = null;

            return null;
        }

        $device = app(\App\Domains\TimeRecords\Actions\ResolveKioskDeviceAction::class)
            ->handle($token, request()->ip(), request()->userAgent());

        if (! $device) {
            $this->kioskCompanyId = null;
            $this->kioskCompanyName = null;
            $this->kioskCompanyBrandImageUrl = null;
            $this->kioskDeviceId = null;
            $this->kioskDeviceName = null;

            return null;
        }

        // Livewire updates use a route outside /time/kiosk. Reissue the
        // terminal cookie at the application root so polling keeps receiving it.
        Cookie::queue(Cookie::make(
            self::DEVICE_COOKIE,
            $token,
            60 * 24 * 365,
            '/',
            null,
            request()->isSecure() || app()->environment('production'),
            true,
            false,
            'strict',
        ));
        Cookie::queue(Cookie::forget(self::DEVICE_COOKIE, '/time/kiosk'));

        return $device;
    }

    private function kioskCompanyOrFail(): Company
    {
        if ($this->loadKioskCompanyFromDevice()) {
            return Company::query()
                ->whereKey($this->kioskCompanyId)
                ->where('status', 'active')
                ->firstOrFail();
        }

        $device = $this->authorizedDeviceOrNull();

        if (! $device) {
            throw ValidationException::withMessages([
                'accessCode' => 'Esta terminal no esta autorizada.',
            ]);
        }

        return Company::query()
            ->whereKey($device->company_id)
            ->where('status', 'active')
            ->firstOrFail();
    }

    private function credentialFromToken(): WorkerCredential
    {
        if (! $this->credentialToken) {
            throw ValidationException::withMessages([
                'accessCode' => 'Vuelve a identificarte para continuar.',
            ]);
        }

        try {
            $payload = $this->credentialTokenPayload();
        } catch (\Throwable) {
            $this->resetKioskState();

            throw ValidationException::withMessages([
                'accessCode' => 'Vuelve a identificarte para continuar.',
            ]);
        }

        $issuedAt = (int) ($payload['issued_at'] ?? 0);

        if ($issuedAt <= 0 || now()->timestamp - $issuedAt > self::TOKEN_TTL_MINUTES * 60) {
            $this->resetKioskState();

            throw ValidationException::withMessages([
                'accessCode' => 'Vuelve a identificarte para continuar.',
            ]);
        }

        $company = $this->kioskCompanyOrFail();

        if ((int) ($payload['company_id'] ?? 0) !== $company->id) {
            $this->resetKioskState();

            throw ValidationException::withMessages([
                'accessCode' => 'Vuelve a identificarte para continuar.',
            ]);
        }

        $credential = WorkerCredential::query()
            ->with(['company', 'worker'])
            ->where('company_id', $company->id)
            ->find((int) ($payload['credential_id'] ?? 0));

        if (! $credential || $credential->worker_id !== (int) ($payload['worker_id'] ?? 0)) {
            $this->resetKioskState();

            throw ValidationException::withMessages([
                'accessCode' => 'Vuelve a identificarte para continuar.',
            ]);
        }

        return $credential;
    }

    private function credentialTokenPayload(): array
    {
        if (! $this->credentialToken) {
            return [];
        }

        return json_decode(Crypt::decryptString($this->credentialToken), true, 512, JSON_THROW_ON_ERROR);
    }

    private function stateForCredential(WorkerCredential $credential, ResolveCurrentTimeRecordStateAction $resolveState): array
    {
        $relationship = $credential->worker?->activeEmploymentRelationship()->with('center')->first();

        return $resolveState->handle($credential->company, $credential->worker, null, $relationship?->center);
    }

    private function resetKioskState(bool $keepConfirmation = false): void
    {
        $this->accessCode = '';
        $this->pin = '';
        $this->credentialToken = null;
        $this->workerName = null;
        $this->localDate = null;
        $this->timezone = null;
        $this->allowedActions = [];

        if (! $keepConfirmation) {
            $this->confirmationMessage = null;
            $this->confirmationTime = null;
        }
    }

    private function eventMessage(string $eventType): string
    {
        return match ($eventType) {
            'clock_in' => 'Entrada registrada.',
            'clock_out' => 'Salida registrada.',
            'break_start' => 'Inicio de pausa registrado.',
            'break_end' => 'Fin de pausa registrado.',
            default => 'Registro guardado.',
        };
    }
}; ?>
<div class="min-h-screen" @if ($kioskDeviceId) wire:poll.60s="refreshAuthorizedTerminal" @endif>
    <div class="fixed left-0 right-0 top-0 h-1.5 bg-gradient-to-r from-brand-blue-bright via-brand-sky to-brand-deep"></div>

    <div class="flex min-h-screen items-center justify-center bg-[radial-gradient(140%_100%_at_50%_-10%,#DCEEFF_0%,#EAF3FC_45%,#F4F9FF_100%)] p-5 font-sans text-surface-text sm:p-7">
        <div class="relative grid w-full max-w-[900px] grid-cols-1 gap-8 overflow-hidden rounded-[32px] bg-white px-7 py-9 shadow-[0_40px_90px_-30px_rgba(2,25,57,0.28)] md:grid-cols-2 md:gap-12 md:px-14 md:py-12">
            <div class="absolute bottom-0 left-1/2 top-0 hidden w-px bg-surface-line md:block"></div>

            <section class="flex flex-col items-center border-b border-surface-line pb-6 text-center md:border-b-0 md:pr-6">
                <img src="{{ asset('images/logo vera time.png') }}" alt="Vera Time" class="mb-4 h-16 w-auto">

                <div class="font-display text-[26px] font-extrabold text-brand-navy">Kiosco</div>
                <p class="mt-2 max-w-[280px] text-[13.5px] leading-relaxed text-surface-muted">
                    Registra entrada, salida y pausas con código y NIP.
                </p>

                <div class="mt-8 w-full max-w-[260px] rounded-[20px] bg-gradient-to-br from-brand-blue-bright via-brand-blue to-brand-deep px-6 py-5 text-white">
                    <div data-kiosk-date class="font-mono text-[11.5px] uppercase tracking-[0.08em] text-white/75">--</div>
                    <div data-kiosk-clock class="mt-1.5 font-display text-[36px] font-bold tracking-wide tabular-nums">--:--:--</div>
                </div>

                <div @class([
                    'mt-6 inline-flex items-center gap-2 rounded-full border px-3.5 py-2 text-[12.5px] font-semibold',
                    'border-status-rest-line bg-status-rest-bg text-status-rest-text' => $kioskCompanyId,
                    'border-status-warn-line bg-status-warn-bg text-status-warn-text' => ! $kioskCompanyId,
                ])>
                    <span @class([
                        'h-1.5 w-1.5 rounded-full',
                        'bg-status-good shadow-[0_0_0_3px_rgba(22,178,106,0.18)]' => $kioskCompanyId,
                        'bg-status-warn-text shadow-[0_0_0_3px_rgba(147,101,11,0.18)]' => ! $kioskCompanyId,
                    ])></span>
                    {{ $kioskCompanyId ? 'Kiosco activo' : 'Pendiente de activación' }}
                </div>

                @if ($kioskCompanyName)
                    <p class="mt-3 text-xs font-semibold uppercase tracking-[0.12em] text-surface-muted">{{ $kioskCompanyName }}</p>
                @endif

                @if ($kioskCompanyBrandImageUrl)
                    <img src="{{ $kioskCompanyBrandImageUrl }}" alt="Imagen de {{ $kioskCompanyName }}" class="mt-3 h-16 max-w-[180px] rounded-lg object-contain">
                @endif
            </section>

            <section class="flex flex-col justify-center md:pl-6">
                @if ($confirmationMessage)
                    <div class="rounded-[24px] border border-status-rest-line bg-status-rest-bg px-6 py-7 text-center text-status-rest-text">
                        <p class="font-display text-2xl font-bold">{{ $confirmationMessage }}</p>
                        <p class="mt-2 text-sm">{{ $confirmationTime }}</p>
                        <button type="button" class="btn-primary mt-5 w-full justify-center" wire:click="resetKiosk">Volver al inicio</button>
                    </div>
                @endif

                @if (! $confirmationMessage && ! $kioskCompanyId)
                    <div class="rounded-[24px] border border-status-warn-line bg-status-warn-bg px-6 py-7 text-center text-status-warn-text">
                        <p class="font-display text-2xl font-bold">Terminal no autorizada</p>
                        <p class="mt-2 text-sm">Solicita un codigo de autorizacion al administrador para vincular este equipo.</p>
                        <a href="{{ route('kiosk.authorize') }}" class="btn-primary mt-5 w-full justify-center">Solicitar autorizacion</a>
                    </div>
                @elseif (! $confirmationMessage && ! $credentialToken)
                    <form wire:submit="identify" autocomplete="off" data-form-type="other" class="space-y-4">
                        <div>
                            <h1 class="font-display text-[24px] font-bold text-brand-navy">Identificación</h1>
                            <p class="mt-1 text-[13.5px] text-surface-muted">Captura tu código y NIP para continuar.</p>
                        </div>

                        @if ($errors->any())
                            <div class="rounded-xl border border-status-pending-line bg-status-pending-bg px-4 py-3 text-[13px] text-status-pending-text">
                                {{ $errors->first() }}
                            </div>
                        @endif

                        <div>
                            <label for="kiosk-access-code" class="mb-1.5 block text-[12.5px] font-semibold text-brand-navy">Código de acceso o número de empleado</label>
                            <input id="kiosk-access-code" wire:model="accessCode" type="text" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" autofocus placeholder="Ej. 00457" class="w-full rounded-2xl border-[1.5px] border-surface-line bg-[#FBFCFE] px-4 py-3.5 font-mono text-base tracking-wider outline-none transition placeholder:font-sans placeholder:tracking-normal placeholder:text-[#9AA8BB] focus:border-brand-blue focus:bg-white focus:ring-4 focus:ring-brand-blue/10">
                        </div>

                        <div>
                            <label for="kiosk-pin" class="mb-1.5 block text-[12.5px] font-semibold text-brand-navy">NIP</label>
                            <input id="kiosk-pin" wire:model="pin" type="password" autocomplete="new-password" autocapitalize="off" autocorrect="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" inputmode="numeric" maxlength="20" class="w-full rounded-2xl border-[1.5px] border-surface-line bg-[#FBFCFE] px-4 py-3.5 font-mono text-base tracking-wider outline-none transition placeholder:text-[#9AA8BB] focus:border-brand-blue focus:bg-white focus:ring-4 focus:ring-brand-blue/10">
                        </div>

                        <div class="my-3.5 grid grid-cols-3 gap-2.5">
                            @foreach ([1,2,3,4,5,6,7,8,9] as $n)
                                <button type="button" data-pin-key="{{ $n }}" class="kiosk-key">{{ $n }}</button>
                            @endforeach
                            <button type="button" data-kiosk-clear class="kiosk-key kiosk-key-action text-[15px]">Borrar</button>
                            <button type="button" data-pin-key="0" class="kiosk-key">0</button>
                            <button type="button" data-kiosk-backspace class="kiosk-key kiosk-key-action">⌫</button>
                        </div>

                        <button type="submit" class="w-full rounded-2xl bg-gradient-to-r from-brand-blue-bright via-brand-blue to-brand-deep py-4 font-display text-base font-bold tracking-wide text-white shadow-[0_14px_26px_-10px_rgba(0,103,228,0.55)] transition hover:-translate-y-0.5 active:translate-y-0">
                            Continuar
                        </button>
                    </form>
                @elseif (! $confirmationMessage)
                    <div class="space-y-5">
                        <div class="text-center">
                            <p class="font-mono text-xs uppercase tracking-[0.12em] text-surface-muted">{{ $kioskCompanyName }}</p>
                            <h1 class="mt-1 font-display text-[26px] font-bold text-brand-navy">{{ $workerName }}</h1>
                            <p class="mt-1 text-sm text-surface-muted">{{ $localDate }} · {{ $timezone }}</p>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            @if (in_array('clock_in', $allowedActions, true))
                                <button type="button" class="btn-primary justify-center" wire:click="record('clock_in')">Registrar entrada</button>
                            @endif
                            @if (in_array('break_start', $allowedActions, true))
                                <button type="button" class="btn-primary justify-center" wire:click="record('break_start')">Iniciar pausa</button>
                            @endif
                            @if (in_array('break_end', $allowedActions, true))
                                <button type="button" class="btn-primary justify-center" wire:click="record('break_end')">Terminar pausa</button>
                            @endif
                            @if (in_array('clock_out', $allowedActions, true))
                                <button type="button" class="btn-primary justify-center" wire:click="record('clock_out')">Registrar salida</button>
                            @endif
                        </div>

                        @if (empty($allowedActions))
                            <p class="rounded-xl border border-status-warn-line bg-status-warn-bg px-4 py-3 text-center text-sm text-status-warn-text">No hay acciones disponibles.</p>
                        @endif

                        <button type="button" class="btn-ghost w-full justify-center" wire:click="resetKiosk">Cancelar</button>
                    </div>
                @endif

                <div class="mt-5 text-center text-[11.5px] text-[#A6B3C4]">
                    Vera Time · Terminal de registro
                </div>
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
                const days = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                const months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

                clock.textContent = `${hh}:${mm}:${ss}`;
                date.textContent = `${days[now.getDay()]}, ${now.getDate()} ${months[now.getMonth()]}`;
            };

            const setPinValue = (value) => {
                const pin = document.getElementById('kiosk-pin');
                if (!pin) return;
                pin.value = value;
                pin.dispatchEvent(new Event('input', { bubbles: true }));
            };

            document.addEventListener('click', (event) => {
                const key = event.target.closest('[data-pin-key]');
                const clear = event.target.closest('[data-kiosk-clear]');
                const backspace = event.target.closest('[data-kiosk-backspace]');
                const pin = document.getElementById('kiosk-pin');

                if (!pin) return;

                if (key) {
                    setPinValue((pin.value + key.dataset.pinKey).slice(0, 20));
                }

                if (clear) {
                    setPinValue('');
                }

                if (backspace) {
                    setPinValue(pin.value.slice(0, -1));
                }
            });

            tick();
            setInterval(tick, 1000);
        })();
    </script>
</div>
