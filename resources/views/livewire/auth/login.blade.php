<?php

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->ensureIsNotRateLimited();

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password, 'status' => 'active'], $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    /**
     * Ensure the authentication request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the authentication rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }
}; ?>
<div class="flex min-h-[calc(100svh-8rem)] items-center justify-center px-4 py-6 font-sans text-surface-text sm:px-6">
    <div class="grid min-h-[660px] w-full max-w-[1120px] grid-cols-1 overflow-hidden rounded-[28px] bg-surface-card shadow-[0_30px_80px_-20px_rgba(2,25,57,0.35)] md:grid-cols-[1.1fr_1fr]">
        <section class="relative hidden flex-col overflow-hidden bg-[radial-gradient(120%_140%_at_0%_0%,#0C86FF_0%,var(--color-brand-blue)_32%,var(--color-brand-deep)_62%,var(--color-brand-navy)_100%)] p-11 text-white md:flex">
            <div class="pointer-events-none absolute inset-0 bg-[repeating-linear-gradient(115deg,rgba(255,255,255,0.05)_0px,rgba(255,255,255,0.05)_1px,transparent_1px,transparent_64px)]"></div>
            <div class="pointer-events-none absolute -bottom-[200px] -right-[180px] h-[520px] w-[520px] rounded-full bg-[radial-gradient(circle_at_30%_30%,rgba(41,182,246,0.35),transparent_65%)]"></div>

            <div class="relative z-10 inline-flex w-fit items-center rounded-2xl bg-white px-4 py-2.5 shadow-[0_10px_24px_-8px_rgba(2,25,57,0.35)]">
                <img src="{{ asset('images/logo vera time.png') }}" alt="Vera Time" class="h-10 w-auto">
            </div>

            <div class="relative z-10 mt-14">
                <span class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1.5 font-mono text-xs uppercase tracking-[0.12em] text-white/75">
                    <span class="h-1.5 w-1.5 rounded-full bg-status-good-soft shadow-[0_0_0_3px_rgba(95,227,161,0.25)]"></span>
                    Portal de acceso
                </span>

                <h1 class="mt-4 font-display text-[42px] font-extrabold leading-[1.12] tracking-[-0.01em]">
                    Todo tu historial,<br>en un mismo lugar.
                </h1>

                <p class="mt-3.5 max-w-[380px] text-[15.5px] leading-relaxed text-white/80">
                    Consulta tus horarios, revisa el estado de tus incidencias y mantén tu historial laboral al día, todo desde un mismo lugar.
                </p>
            </div>

            <div class="relative z-10 mt-auto pt-10">
                <div class="flex items-center gap-5 rounded-[20px] border border-white/15 bg-white/10 px-6 py-5 backdrop-blur-md">
                    <div class="relative h-16 w-16 shrink-0">
                        <svg viewBox="0 0 64 64" class="h-full w-full -rotate-90">
                            <circle cx="32" cy="32" r="28" fill="none" stroke="rgba(255,255,255,0.18)" stroke-width="5"></circle>
                            <circle data-login-ring cx="32" cy="32" r="28" fill="none" stroke="#5FE3A1" stroke-width="5" stroke-linecap="round" stroke-dasharray="176" stroke-dashoffset="130"></circle>
                        </svg>
                        <div data-login-ring-label class="absolute inset-0 flex items-center justify-center font-mono text-[9px] tracking-wide">70%</div>
                    </div>
                    <div class="flex-1">
                        <div class="font-mono text-[11.5px] uppercase tracking-[0.08em] text-white/60">Hora actual</div>
                        <div data-login-clock class="mt-1 font-display text-[26px] font-bold tabular-nums">--:--:--</div>
                        <div class="mt-1 text-[12.5px] text-white/70">Sistema <b class="font-semibold text-status-good-soft">en línea</b></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="flex flex-col justify-center px-8 py-10 md:px-14">
            <div>
                <h2 class="font-display text-[28px] font-bold text-brand-navy">Bienvenido de nuevo</h2>
                <p class="mt-2 text-[14.5px] text-surface-muted">Inicia sesión para gestionar tus horarios e incidencias.</p>
            </div>

            <x-auth-session-status class="mt-5 rounded-xl border border-status-rest-line bg-status-rest-bg px-4 py-3 text-center text-[13px] text-status-rest-text" :status="session('status')" />

            <form wire:submit="login" class="mt-8 space-y-4">
                <div>
                    <label for="email" class="mb-1.5 block text-[12.5px] font-semibold text-brand-navy">Correo electrónico</label>
                    <input
                        wire:model="email"
                        id="email"
                        type="email"
                        name="email"
                        required
                        autofocus
                        autocomplete="email"
                        placeholder="correo@empresa.com"
                        class="w-full rounded-xl border-[1.5px] border-surface-line bg-[#FBFCFE] px-4 py-3 text-[14.5px] outline-none transition placeholder:text-[#9AA8BB] focus:border-brand-blue focus:bg-white focus:ring-4 focus:ring-brand-blue/10"
                    >
                    @error('email')
                        <p class="mt-1.5 text-[12px] text-status-pending-text">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <div class="mb-1.5 flex items-center justify-between gap-3">
                        <label for="password" class="block text-[12.5px] font-semibold text-brand-navy">Contraseña</label>
                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" class="text-[13.5px] font-semibold text-brand-blue hover:underline" wire:navigate>
                                ¿Olvidaste tu contraseña?
                            </a>
                        @endif
                    </div>
                    <input
                        wire:model="password"
                        id="password"
                        type="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        placeholder="••••••••"
                        class="w-full rounded-xl border-[1.5px] border-surface-line bg-[#FBFCFE] px-4 py-3 text-[14.5px] outline-none transition placeholder:text-[#9AA8BB] focus:border-brand-blue focus:bg-white focus:ring-4 focus:ring-brand-blue/10"
                    >
                    @error('password')
                        <p class="mt-1.5 text-[12px] text-status-pending-text">{{ $message }}</p>
                    @enderror
                </div>

                <label class="flex items-center gap-2 text-[13.5px] text-surface-muted">
                    <input wire:model="remember" type="checkbox" name="remember" class="h-[15px] w-[15px] accent-brand-blue">
                    Recordarme
                </label>

                <button type="submit" class="w-full rounded-xl bg-gradient-to-r from-brand-blue-bright via-brand-blue to-brand-deep py-3.5 font-display text-[15px] font-semibold tracking-wide text-white shadow-[0_12px_24px_-8px_rgba(0,103,228,0.55)] transition hover:-translate-y-0.5 hover:shadow-[0_16px_28px_-8px_rgba(0,103,228,0.6)] active:translate-y-0">
                    Iniciar sesión
                </button>
            </form>

            <div class="mt-6 flex items-center gap-2.5 rounded-xl border border-[#E1EEFC] bg-[#F2F7FE] px-3.5 py-3">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" class="shrink-0">
                    <path d="M12 9v4M12 16.5h.01M10.3 3.9 2.7 17.2c-.5.9.1 2 1.1 2h16.4c1 0 1.6-1.1 1.1-2L13.7 3.9c-.5-.9-1.7-.9-2.2 0Z" stroke="#3B5A82" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span class="text-[12.5px] leading-snug text-[#3B5A82]">
                    <b class="text-brand-navy">¿No puedes acceder?</b> Contacta con Recursos Humanos o tu administrador del sistema.
                </span>
            </div>

            <p class="mt-6 text-center text-[13.5px] text-surface-muted">
                ¿Primera vez aquí? <span class="font-semibold text-brand-blue">Contacta a RH para tu alta</span>
            </p>
        </section>
    </div>
</div>

<script>
    (() => {
        const tick = () => {
            const clock = document.querySelector('[data-login-clock]');
            const ring = document.querySelector('[data-login-ring]');
            const ringLabel = document.querySelector('[data-login-ring-label]');

            if (!clock || !ring || !ringLabel) return;

            const now = new Date();
            const hh = String(now.getHours()).padStart(2, '0');
            const mm = String(now.getMinutes()).padStart(2, '0');
            const ss = String(now.getSeconds()).padStart(2, '0');
            clock.textContent = `${hh}:${mm}:${ss}`;

            const pct = Math.round((now.getSeconds() / 59) * 100);
            const circumference = 2 * Math.PI * 28;
            ringLabel.textContent = `${pct}%`;
            ring.style.strokeDasharray = circumference;
            ring.style.strokeDashoffset = circumference - (pct / 100) * circumference;
        };

        tick();
        setInterval(tick, 1000);
    })();
</script>
