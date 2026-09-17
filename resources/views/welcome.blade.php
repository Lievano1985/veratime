<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Vera Time') }} — Registro y evidencia de jornadas laborales</title>
    <meta name="description"
        content="Vera Time convierte cada entrada y salida en jornadas calculadas, incidencias revisables y reportes listos para nómina.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&family=Inter:wght@400;500;600&family=Space+Mono&display=swap"
        rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        .hero-gradient {
            background:
                radial-gradient(circle at 12% 15%, color-mix(in oklab, var(--color-brand-sky), transparent 84%), transparent 42%),
                radial-gradient(circle at 88% 65%, color-mix(in oklab, var(--color-brand-blue), transparent 86%), transparent 48%);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(16px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-in {
            animation: fadeInUp .6s ease-out both;
        }

        .animate-in-delay {
            animation: fadeInUp .6s ease-out .15s both;
        }

        .screenshot-frame {
            box-shadow:
                0 28px 54px -34px rgba(0, 103, 228, .55),
                0 10px 24px -18px rgba(2, 25, 57, .28);
        }

        .screenshot-frame::before {
            content: "";
            position: absolute;
            inset: 0 0 auto;
            height: 3px;
            background: linear-gradient(90deg, var(--color-brand-sky), var(--color-brand-blue));
            z-index: 1;
        }

        .feature-carousel {
            scrollbar-width: none;
        }

        .feature-carousel::-webkit-scrollbar {
            display: none;
        }
    </style>
</head>

<body class="min-h-screen bg-surface-bg font-sans text-surface-text antialiased">

    {{-- Nav --}}
    <header class="sticky top-0 z-10 border-b border-surface-line bg-white/80 backdrop-blur">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-3">
            <a href="{{ url('/') }}" class="flex items-center gap-2">
                <img src="{{ asset('images/logo vera time.png') }}" alt="Vera Time" class="h-14 w-auto">
            </a>

            <nav class="hidden items-center gap-8 text-sm font-medium text-surface-muted md:flex">
                <a href="#producto" class="transition hover:text-brand-navy">Producto</a>
                <a href="#reforma" class="transition hover:text-brand-navy">Reforma 2027</a>
                <a href="#kiosco" class="transition hover:text-brand-navy">Registro de asistencia</a>
                <a href="#suite" class="transition hover:text-brand-navy">La suite VERA</a>
            </nav>

            @if (Route::has('login'))
                <div class="flex items-center gap-3">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="btn-primary">Ir al inicio</a>
                    @else
                        <a href="{{ route('login') }}" class="btn-outline">Iniciar sesión</a>
                    @endauth
                </div>
            @endif
        </div>
    </header>

    {{-- Hero --}}
    <section id="producto" class="hero-gradient relative overflow-hidden">
        <div class="mx-auto max-w-7xl px-6 py-20 sm:py-28 lg:px-10">
            <div class="grid items-center gap-12 lg:grid-cols-2 lg:gap-8">
                <div class="animate-in max-w-2xl">
                    <p class="mb-4 text-sm font-semibold italic tracking-wide text-brand-blue">
                        Preparado para la Reforma Laboral 2027
                    </p>
                    <h1 class="font-display text-4xl font-bold leading-[1.1] tracking-tight text-brand-navy sm:text-5xl">
                        El tiempo de tu equipo, con <span class="text-brand-blue">evidencia real</span>.
                    </h1>
                    <p class="mt-6 max-w-md text-base leading-relaxed text-surface-muted sm:text-lg">
                        Vera Time convierte cada entrada y salida en jornadas calculadas, incidencias revisables y
                        reportes listos para nómina.
                    </p>

                    @if (Route::has('login'))
                        <div class="mt-8 flex flex-wrap items-center gap-3">
                            @guest
                                <a href="{{ route('login') }}" class="btn-primary btn-lg">Iniciar sesión</a>
                            @else
                                <a href="{{ url('/dashboard') }}" class="btn-primary btn-lg">Ir al inicio</a>
                            @endguest
                            <a href="#kiosco" class="btn-ghost btn-lg">Ver cómo funciona</a>
                        </div>
                    @endif

                    <div class="mt-10 hidden flex-wrap gap-0">
                        <div class="mr-6 border-r border-surface-line pr-6">
                            <span class="font-display block text-xl text-brand-navy">13</span>
                            <span class="text-xs text-surface-muted">tipos de incidencia detectados</span>
                        </div>
                        <div class="mr-6 border-r border-surface-line pr-6">
                            <span class="font-display block text-xl text-brand-navy">3</span>
                            <span class="text-xs text-surface-muted">formas de registrar asistencia</span>
                        </div>
                        <div>
                            <span class="font-display block text-xl text-brand-navy">4</span>
                            <span class="text-xs text-surface-muted">pasos: de horario a nómina</span>
                        </div>
                    </div>
                </div>

                <div class="animate-in-delay relative mx-auto w-full">
                    <img src="{{ asset('images/marketing/hero_img.png') }}" alt="Panel de Vera Time"
                        class="block w-full object-contain">
                </div>
            </div>
        </div>
    </section>

    {{-- Cómo funciona y funcionalidades --}}
    <section id="como-funciona" class="border-t border-surface-line bg-white">
        <div class="mx-auto max-w-6xl px-6 py-20">
            <h2 class="font-display max-w-lg text-2xl font-bold leading-snug text-brand-navy sm:text-3xl">Cómo funciona</h2>

            <div class="mt-10 grid gap-10 sm:grid-cols-2 lg:grid-cols-4 lg:gap-8">
                @foreach ([['01', 'Programación esperada', 'Defines turnos, horarios y descansos por trabajador, centro o unidad.'], ['02', 'Eventos reales', 'Kiosco, captura manual o importación CSV — y pronto biométricos y app móvil.'], ['03', 'Cálculo automático', 'Vera Time compara lo programado con lo ocurrido y genera jornadas e incidencias.'], ['04', 'Dictamen y exportación', 'RH revisa, dictamina y exporta el periodo listo para nómina.']] as [$n, $title, $body])
                    <div class="border-t-2 border-brand-blue pt-4">
                        <span class="font-display text-sm font-semibold text-brand-blue">{{ $n }}</span>
                        <h3 class="mt-2 text-base font-bold text-brand-navy">{{ $title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-surface-muted">{{ $body }}</p>
                    </div>
                @endforeach
            </div>

            <div class="relative left-1/2 mt-20 w-screen -translate-x-1/2 border-t border-surface-line"></div>
            <h2 class="mt-16 font-display text-2xl font-bold leading-snug text-brand-navy sm:max-w-none sm:whitespace-nowrap sm:text-3xl">
                Todo lo que necesitas para controlar el tiempo de tu equipo.
            </h2>

            <div class="feature-carousel-shell relative mt-10">
                <div id="feature-carousel" class="feature-carousel flex snap-x snap-mandatory gap-4 overflow-x-auto scroll-smooth pb-3">
                @foreach ([
        ['Organización y equipo', ['Directorio de trabajadores', 'Centros, áreas y departamentos', 'Multiempresa y multiusuario', 'Roles y alcances por centro o unidad', 'Administración de supervisores y responsables'], true, '<circle cx="9" cy="8" r="3"/><path d="M3.5 20c0-3 2.5-5 5.5-5s5.5 2 5.5 5" stroke-linecap="round"/><circle cx="17" cy="8.5" r="2.3"/><path d="M15.5 12.5c2.4.3 4 2 4 4.5" stroke-linecap="round"/>'],
        ['Registro de asistencia', ['Kiosco de asistencia', 'Registro de entrada, salida y pausas', 'Captura manual justificada'], true, '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2" stroke-linecap="round" stroke-linejoin="round"/>'],
        ['Horarios y turnos', ['Programación semanal de horarios', 'Turnos fijos, nocturnos, mixtos, flexibles, rotativos y guardias', 'Descansos programados y obligatorios', 'Importación CSV de programación'], true, '<rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 9.5h17M8 3v4M16 3v4" stroke-linecap="round"/>'],
        ['Cálculo de jornadas', ['Horas ordinarias, extra dobles y extra triples', 'Domingos, descansos y festivos trabajados', 'Recálculo automático de jornadas', 'Cierre de periodos de asistencia'], true, '<rect x="4.5" y="3.5" width="15" height="17" rx="2"/><path d="M8 8h8M8 12h3M8 16h3M14 12h2M14 16h2" stroke-linecap="round"/>'],
        ['Incidencias', ['Faltas, retardos y salidas anticipadas', 'Vacaciones, incapacidades y permisos', 'Gestión de incidencias y ausencias', 'Dictamen y corrección de jornadas', 'Alertas operativas'], true, '<path d="M12 3.5l8 4.5v8l-8 4.5-8-4.5v-8L12 3.5z"/><path d="M12 9v4.5M12 16.5h.01" stroke-linecap="round"/>'],
        ['Nómina y evidencia', ['Exportación CSV para nómina', 'Evidencia histórica y trazabilidad', 'Seguridad por empresa, rol y alcance'], true, '<path d="M5 4.5h11l3 3V19a1 1 0 01-1 1H5a1 1 0 01-1-1V5.5a1 1 0 011-1z"/><path d="M8 9h6M8 13h6M8 17h4" stroke-linecap="round"/>'],
        ['Próximamente', ['Conexión con dispositivos biométricos', 'App Android para asistencia', 'Flujo avanzado de aprobación de incidencias', 'API e integraciones'], false, '<path d="M12 2.5c2.5 2 4 5.5 4 9 0 2-1 4-1 4h-6s-1-2-1-4c0-3.5 1.5-7 4-9z"/><path d="M9.5 15.5L8 20l4-2 4 2-1.5-4.5" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="10.5" r="1.6"/>'],
    ] as [$category, $items, $available, $icon])
                    @if (str_starts_with($category, 'Pr'))
                        @continue
                    @endif
                    <div
                        class="feature-carousel-card w-[min(20rem,calc(100vw-3rem))] flex-none snap-start rounded-2xl border {{ $available ? 'border-[#cfe2fb] bg-[#f2f7fe]' : 'border-dashed border-surface-line bg-surface-bg' }} p-5 sm:w-[21rem] lg:w-[22rem]">
                        <div
                            class="mb-3 flex h-9 w-9 items-center justify-center rounded-[10px] {{ $available ? 'bg-[#d8eafe] text-status-shift-text' : 'bg-surface-bg text-surface-muted' }}">
                            <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.6">{!! $icon !!}</svg>
                        </div>
                        <h3
                            class="text-xs font-bold uppercase tracking-wide {{ $available ? 'text-brand-blue' : 'text-surface-muted' }}">
                            {{ $category }}</h3>
                        <ul class="mt-3 space-y-1.5">
                            @foreach ($items as $item)
                                <li
                                    class="flex gap-2 text-[13.5px] leading-snug {{ $available ? 'text-surface-text' : 'text-surface-muted' }}">
                                    <span
                                        class="mt-[7px] h-[5px] w-[5px] flex-shrink-0 rounded-full {{ $available ? 'bg-status-shift-text' : 'bg-surface-muted' }}"></span>
                                    {{ $item }}
                                </li>
                            @endforeach
                            @if ($category === 'Registro de asistencia')
                                @foreach (['App Android para asistencia', 'Asistencia mediante WhatsApp', 'Autenticacion facial'] as $item)
                                    <li class="flex gap-2 text-[13.5px] leading-snug text-surface-muted">
                                        <span class="mt-[7px] h-[5px] w-[5px] flex-shrink-0 rounded-full bg-surface-muted"></span>
                                        <span>{{ $item }} <span class="ml-1 rounded-full bg-white px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-surface-muted">Proximamente</span></span>
                                    </li>
                                @endforeach
                            @endif
                            @if ($category === 'Incidencias')
                                <li class="flex gap-2 text-[13.5px] leading-snug text-surface-muted">
                                    <span class="mt-[7px] h-[5px] w-[5px] flex-shrink-0 rounded-full bg-surface-muted"></span>
                                    <span>Flujo avanzado de aprobacion <span class="ml-1 rounded-full bg-white px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-surface-muted">Proximamente</span></span>
                                </li>
                            @endif
                            @if (str_contains($category, 'evidencia'))
                                @foreach (['API e integraciones', 'Conexion con dispositivos biometricos', 'Geolocalizacion y geofencing'] as $item)
                                    <li class="flex gap-2 text-[13.5px] leading-snug text-surface-muted">
                                        <span class="mt-[7px] h-[5px] w-[5px] flex-shrink-0 rounded-full bg-surface-muted"></span>
                                        <span>{{ $item }} <span class="ml-1 rounded-full bg-white px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-surface-muted">Proximamente</span></span>
                                    </li>
                                @endforeach
                            @endif
                        </ul>
                    </div>
                @endforeach
                </div>
                <button type="button" data-carousel-direction="previous" aria-label="Ver tarjetas anteriores"
                    class="absolute left-3 top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full border border-surface-line bg-white text-brand-navy shadow-md transition hover:border-brand-blue hover:text-brand-blue focus:outline-none focus:ring-2 focus:ring-brand-blue focus:ring-offset-2 sm:-left-5">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
                <button type="button" data-carousel-direction="next" aria-label="Ver siguientes tarjetas"
                    class="absolute right-3 top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full border border-surface-line bg-white text-brand-navy shadow-md transition hover:border-brand-blue hover:text-brand-blue focus:outline-none focus:ring-2 focus:ring-brand-blue focus:ring-offset-2 sm:-right-5">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
            </div>
        </div>
    </section>

    {{-- Reforma laboral 2027 --}}
    <section id="reforma" class="bg-brand-navy text-white">
        <div class="mx-auto max-w-6xl px-6 py-16">
            <span class="text-xs font-bold uppercase tracking-wide text-brand-sky">Reforma laboral 2027</span>
            <div class="mt-16 grid items-start gap-12 lg:grid-cols-[minmax(0,1fr)_minmax(320px,0.9fr)] lg:gap-20">
                <div>
                    <h2 class="font-display mt-0 max-w-xl text-2xl font-bold leading-snug text-white sm:text-3xl">
                        No vendemos un reloj checador. Vendemos tranquilidad legal.
                    </h2>
                    <p class="mt-5 max-w-xl text-sm leading-relaxed text-white/85">
                        La reforma a la Ley Federal del Trabajo (DOF, mayo 2026) obliga a registrar electrónicamente la jornada
                        de cada trabajador —hora de entrada y salida— y a entregar ese registro a la autoridad si lo pide. Los
                        lineamientos de la STPS sobre cómo debe verse ese registro entran en vigor el 1 de enero de 2027, junto
                        con la reducción gradual de la jornada semanal.
                    </p>

                    <div class="mt-9 flex flex-wrap gap-2">
                    @foreach ([['48h', '2026', false], ['46h', '2027', true], ['44h', '2028', false], ['42h', '2029', false], ['40h', '2030', false]] as [$hours, $year, $active])
                        <div
                            class="w-20 rounded-lg border {{ $active ? 'border-brand-sky bg-brand-sky/10' : 'border-white/15' }} px-3 py-3 text-center">
                            <span class="font-display block text-lg">{{ $hours }}</span>
                            <span class="block text-xs text-white/55">{{ $year }}</span>
                        </div>
                    @endforeach
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="screenshot-frame relative overflow-hidden rounded-2xl border border-white/15 bg-white/5">
                        <img src="{{ asset('images/marketing/sectionimg.png') }}" alt="Evidencia laboral en Vera Time"
                            class="block w-full">
                    </div>

                    <ul class="space-y-3">
                    @foreach (['Hora de entrada y salida registradas', 'Tiempo efectivamente trabajado, calculado', 'Horas extraordinarias identificadas', 'Registro exportable para mostrar a la autoridad', 'No importa el dispositivo: kiosco, biométrico o app, todo cuenta como registro electrónico válido'] as $item)
                        <li class="flex items-start gap-2.5 text-sm text-white/90">
                            <span
                                class="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-brand-sky text-xs font-bold text-brand-navy">✓</span>
                            {{ $item }}
                        </li>
                    @endforeach
                    </ul>
                </div>
            </div>

            <p class="mx-auto mt-16 max-w-2xl text-center text-xs leading-relaxed text-white/55">
                La ley no exige una marca ni un tipo de dispositivo específico — exige poder demostrar el tiempo
                trabajado de forma confiable. Vera Time ya captura lo que la reforma pide desde hoy. Este contenido es
                informativo, no constituye asesoría legal; los lineamientos técnicos definitivos de la STPS aún están
                pendientes de publicación.
            </p>
        </div>
    </section>

    {{-- Registro de asistencia / kiosco --}}
    <section id="kiosco" class="border-t border-surface-line bg-white">
        <div class="mx-auto max-w-6xl px-6 py-20">
            <div class="grid items-center gap-10 lg:grid-cols-2 lg:gap-14">
                <div>
                    <h2 class="font-display max-w-lg text-2xl font-bold leading-snug text-brand-navy sm:text-3xl">
                        Registra asistencia desde donde tu equipo esté.
                    </h2>
                    <p class="mt-4 max-w-md text-sm leading-relaxed text-surface-muted sm:text-base">
                        El kiosco se coloca en un dispositivo dentro del centro de trabajo. Cada colaborador registra su
                        entrada, salida o pausa con su código y NIP — sin apps que instalar, sin fricción.
                    </p>
                    <p class="mt-4 max-w-md text-sm leading-relaxed text-surface-muted sm:text-base">
                        También puedes capturar eventos manualmente o importarlos por CSV. Próximamente: checado desde
                        dispositivos biométricos y desde app móvil.
                    </p>

                    <div class="mt-5 flex flex-wrap gap-2">
                        <span
                            class="inline-flex items-center rounded-full border border-surface-line bg-white px-3 py-1.5 text-xs font-medium">Kiosco</span>
                        <span
                            class="inline-flex items-center rounded-full border border-surface-line bg-white px-3 py-1.5 text-xs font-medium">Captura
                            manual</span>
                        <span
                            class="inline-flex items-center rounded-full border border-surface-line bg-white px-3 py-1.5 text-xs font-medium">Importación
                            CSV</span>
                        <span class="badge-muted">Biométrico · próximamente</span>
                        <span class="badge-muted">App móvil · próximamente</span>
                    </div>
                </div>

                <div
                    class="screenshot-frame relative mx-auto w-full overflow-hidden rounded-2xl border border-[#cfe2fb] bg-surface-card">
                    <img src="{{ asset('images/marketing/shot-kiosco.png') }}" alt="Pantalla de kiosco de Vera Time"
                        class="block w-full">
                </div>
            </div>

            <div class="relative left-1/2 mt-16 w-screen -translate-x-1/2 bg-brand-navy py-8 text-white shadow-[0_24px_48px_-32px_rgba(2,25,57,0.45)]">
                <div class="mx-auto max-w-6xl px-6 sm:px-8">
                <h3 class="font-display text-base font-bold text-white">Cada periodo cerrado exporta, listo para tu
                    proveedor de nómina</h3>
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach (['RFC', 'CURP', 'NSS', 'Horas normales', 'Extra dobles', 'Extra triples', 'Retardos', 'Domingos trabajados', 'Vacaciones e incapacidades'] as $field)
                        <span
                            class="inline-flex items-center rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-xs font-medium text-white">{{ $field }}</span>
                    @endforeach
                </div>
                </div>
            </div>
        </div>
    </section>

    {{-- La suite VERA --}}
    <section id="suite" class="border-t border-surface-line bg-white">
        <div class="mx-auto max-w-6xl px-6 py-20">
            <h2 class="font-display max-w-lg text-2xl font-bold leading-snug text-brand-navy sm:text-3xl">Una suite. Soluciones
                para cada necesidad.</h2>
            <p class="mt-4 max-w-md text-sm leading-relaxed text-surface-muted">
                Cada producto VERA se contrata por separado y comparte la misma cuenta. Cuando actives otro, ya tiene
                los datos que necesita.
            </p>

            <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-xl border-2 border-brand-blue bg-[#eaf3fe] p-5">
                    <div class="flex items-center justify-between">
                        <h3 class="font-display text-base font-bold text-brand-navy">Vera Time</h3>
                        <span class="badge-success">Disponible</span>
                    </div>
                    <p class="mt-2 text-sm leading-relaxed text-surface-muted">Registro de jornada laboral.</p>
                </div>

                @foreach ([['Vera HR', 'Recursos humanos.']] as [$name, $desc])
                    <div class="rounded-xl border border-surface-line p-5">
                        <div class="flex items-center justify-between">
                            <h3 class="font-display text-base font-bold text-surface-muted">{{ $name }}
                            </h3>
                            <span class="badge-muted">Próximamente</span>
                        </div>
                        <p class="mt-2 text-sm leading-relaxed text-surface-muted">{{ $desc }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Footer --}}
    <footer class="border-t border-surface-line">
        <div
            class="mx-auto flex max-w-6xl flex-col items-start justify-between gap-6 px-6 py-12 sm:flex-row sm:items-center">
            <div>
                <x-app-logo class="h-7 w-auto" />
                <p class="mt-3 text-sm text-surface-muted">Orden y evidencia para el tiempo laboral de tu equipo.</p>
            </div>

            @if (Route::has('login'))
                <a href="{{ auth()->check() ? url('/dashboard') : route('login') }}" class="btn-outline">
                    @auth Ir al inicio
                    @else
                    Iniciar sesión @endauth
                </a>
            @endif        </div>
    </footer>

    <script>
        (() => {
            const carousel = document.getElementById('feature-carousel');

            if (!carousel) {
                return;
            }

            const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            const card = () => carousel.querySelector('.feature-carousel-card');
            const step = () => card().offsetWidth + parseFloat(getComputedStyle(carousel).gap || 0);
            const move = (direction) => {
                const isAtStart = carousel.scrollLeft <= 4;
                const isAtEnd = carousel.scrollLeft + carousel.clientWidth >= carousel.scrollWidth - 4;

                if (direction === 'next' && isAtEnd) {
                    carousel.scrollTo({ left: 0, behavior: 'smooth' });
                    return;
                }

                if (direction === 'previous' && isAtStart) {
                    carousel.scrollTo({ left: carousel.scrollWidth, behavior: 'smooth' });
                    return;
                }

                carousel.scrollBy({ left: direction === 'next' ? step() : -step(), behavior: 'smooth' });
            };

            let autoplay = prefersReducedMotion ? null : setInterval(() => move('next'), 5000);
            const restartAutoplay = () => {
                clearInterval(autoplay);
                autoplay = prefersReducedMotion ? null : setInterval(() => move('next'), 5000);
            };

            document.querySelectorAll('[data-carousel-direction]').forEach((button) => {
                button.addEventListener('click', () => {
                    move(button.dataset.carouselDirection);
                    restartAutoplay();
                });
            });

            carousel.addEventListener('mouseenter', () => clearInterval(autoplay));
            carousel.addEventListener('mouseleave', restartAutoplay);
            carousel.addEventListener('focusin', () => clearInterval(autoplay));
            carousel.addEventListener('focusout', restartAutoplay);
        })();
    </script>

</body>

</html>
