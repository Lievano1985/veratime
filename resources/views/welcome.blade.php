<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Vera Time') }} — Registro y evidencia de jornadas laborales</title>
    <meta name="description"
        content="Planea horarios, registra asistencia desde kioscos autorizados y app móvil con georreferenciación, revisa jornadas y exporta resultados con Vera Time.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&family=Inter:wght@400;500;600&family=Space+Mono&display=swap"
        rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        .hero-gradient {
            background:
                radial-gradient(ellipse at 88% 22%, #c4e2ff 0, transparent 48%),
                radial-gradient(ellipse at 0 0, #dcefff 0, transparent 42%),
                linear-gradient(115deg, #f2f9ff 0%, #f8fbff 44%, #e2f0ff 100%);
        }

        .hero-glass { background: rgba(255,255,255,.88); box-shadow: 0 10px 28px -14px rgba(0,103,228,.28); backdrop-filter: blur(12px); }
        .hero-illustration {
            -webkit-mask-image: linear-gradient(to right, transparent, #000 7%, #000 93%, transparent), linear-gradient(to bottom, transparent, #000 9%, #000 89%, transparent);
            -webkit-mask-composite: source-in;
            mask-image: linear-gradient(to right, transparent, #000 7%, #000 93%, transparent), linear-gradient(to bottom, transparent, #000 9%, #000 89%, transparent);
            mask-composite: intersect;
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

            <nav class="hidden items-center gap-5 text-sm font-medium text-surface-muted xl:flex">
                <a href="#producto" class="transition hover:text-brand-navy">Producto</a>
                <a href="#como-funciona" class="transition hover:text-brand-navy">Cómo funciona</a>
                <a href="#reforma" class="transition hover:text-brand-navy">Reforma 2027</a>
                <a href="#kiosco" class="transition hover:text-brand-navy">Registro de asistencia</a>
                <a href="#seguridad" class="transition hover:text-brand-navy">Seguridad</a>
                <a href="#demo" class="transition hover:text-brand-navy">Agenda una demo</a>
            </nav>

            @if (Route::has('login'))
                <div class="flex items-center gap-3">
                    @auth
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn-outline">Cerrar sesión</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="btn-outline">Iniciar sesión</a>
                    @endauth
                </div>
            @endif
        </div>
    </header>

    {{-- Hero --}}
    @php
        $heroIcons = [
            'shield' => '<path d="m12 3 8 3v5c0 5-4 8-8 10-4-2-8-5-8-10V6l8-3Z"/><path d="m8 12 3 3 5-6"/>',
            'pin' => '<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
            'phone' => '<rect x="6" y="2" width="12" height="20" rx="2"/><path d="M10 5h4M11 19h2"/>',
            'kiosk' => '<rect x="5" y="2" width="14" height="12" rx="2"/><path d="M9 14v6M15 14v6M6 22h12"/><circle cx="12" cy="8" r="1.5"/>',
            'file' => '<path d="M6 2h8l5 5v15H6V2Zm8 0v6h5M9 12h7M9 16h7"/>',
            'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 6v6l5 3"/>',
            'users' => '<circle cx="9" cy="7" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 4a3 3 0 0 1 0 6M18 14a5 5 0 0 1 3 5v2"/>',
        ];
    @endphp
    <section id="producto" class="hero-gradient relative isolate overflow-hidden">
        <div aria-hidden="true" class="pointer-events-none absolute -left-40 -top-56 h-[26rem] w-[30rem] rounded-full border-[65px] border-blue-100/50"></div>
        <div aria-hidden="true" class="pointer-events-none absolute -bottom-40 -left-28 h-80 w-[70%] rotate-12 rounded-[50%] bg-white/35"></div>
        <div aria-hidden="true" class="pointer-events-none absolute -right-40 top-24 h-[38rem] w-[48rem] -rotate-12 rounded-[50%] bg-blue-200/20"></div>
        <div class="relative mx-auto max-w-[1640px] px-6 pb-7 pt-12 sm:px-8 sm:pt-16 lg:px-10 lg:pt-16">
            <div class="grid items-center gap-6 lg:grid-cols-[1fr_1.08fr] lg:gap-0">
                <div class="animate-in relative z-20 min-w-0 lg:pb-5">
                    <p class="mb-5 text-xs font-bold uppercase tracking-[.19em] text-brand-blue sm:text-sm">
                        Horarios y asistencia con controles de seguridad
                    </p>
                    <h1 class="font-display max-w-[760px] text-[2.5rem] font-bold leading-[1.04] tracking-[-.045em] text-brand-navy sm:text-5xl xl:text-[3.1rem] 2xl:text-[4.25rem]">
                        El tiempo de tu equipo,<br class="hidden xl:block"> con <span class="text-brand-blue">registros<br class="hidden xl:block"> verificables.</span>
                    </h1>
                    <p class="mt-6 max-w-[620px] text-base leading-relaxed text-surface-muted sm:text-lg 2xl:text-[1.3rem]">
                        Planea horarios, registra asistencia desde kioscos autorizados o la app móvil con
                        georreferenciación y revisa jornadas con información exportable para RH y nómina.
                    </p>

                    @if (Route::has('login'))
                        <div class="mt-7 flex flex-wrap items-center gap-3 2xl:gap-4">
                            <button type="button" data-demo-modal-open class="btn-primary btn-lg">Solicitar una demo <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 5 7 7-7 7" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                            @guest
                                <a href="{{ route('login') }}" class="btn-outline btn-lg">Iniciar sesión</a>
                            @else
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="btn-outline btn-lg">Cerrar sesión</button>
                                </form>
                            @endguest
                            <div class="flex max-w-[250px] items-center gap-3 text-xs font-medium leading-relaxed text-surface-muted sm:border-l sm:border-blue-100 sm:pl-4 2xl:text-sm">
                                <svg class="h-8 w-8 shrink-0 text-brand-blue" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $heroIcons['shield'] !!}</svg>
                                <span>Información segura,<br>trazable y lista para revisión.</span>
                            </div>
                        </div>
                    @endif

                    <ul aria-label="Capacidades de Vera Time" class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4 2xl:flex">
                        @foreach ([['kiosk', 'Kioscos autorizados'], ['phone', 'App móvil'], ['pin', 'Geolocalización'], ['file', 'Evidencia verificable']] as [$icon, $label])
                            <li class="hero-glass flex items-center gap-2 rounded-2xl border border-blue-100 px-3 py-3 text-xs font-semibold text-brand-navy 2xl:text-[13px]">
                                <svg class="h-6 w-6 shrink-0 text-brand-blue" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $heroIcons[$icon] !!}</svg>
                                {{ $label }}
                            </li>
                        @endforeach
                    </ul>
                </div>

                <figure class="animate-in-delay relative mx-auto w-full max-w-[820px]">
                    <img src="{{ asset('images/marketing/hero_img.png') }}" alt="Ilustración de un kiosco de asistencia y una app con ubicación." width="1448" height="1086" class="hero-illustration block h-auto w-full" fetchpriority="high">
                </figure>
            </div>

            <div class="hero-glass relative z-30 mt-6 grid grid-cols-2 gap-y-6 rounded-[28px] border border-white/80 px-4 py-6 md:grid-cols-4 md:gap-y-0 sm:px-6 lg:mt-2 lg:px-8">
                @foreach ([['users', 'Acceso por roles', 'y alcances autorizados', ''], ['clock', 'Control de jornadas', 'en tiempo real', ''], ['file', 'Reportes exportables', 'para RH y nómina', ''], ['shield', 'Control preventivo LFT', 'y Reforma 2027', '']] as [$icon, $title, $description, $note])
                    <div class="flex items-center gap-3 px-2 sm:gap-4 sm:px-4 md:border-r md:border-blue-100 md:last:border-0">
                        <svg class="h-8 w-8 shrink-0 text-brand-blue sm:h-10 sm:w-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $heroIcons[$icon] !!}</svg>
                        <div><p class="text-sm font-bold text-brand-navy sm:text-base 2xl:text-lg">{{ $title }}</p><p class="mt-0.5 text-xs text-surface-muted sm:text-sm">{{ $description }}</p>@if ($note)<p class="mt-0.5 text-[9px] text-surface-muted">{{ $note }}</p>@endif</div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Cómo funciona y funcionalidades --}}
    <section id="como-funciona" class="border-t border-surface-line bg-white">
        <div class="mx-auto max-w-6xl px-6 py-20">
            <h2 class="font-display max-w-lg text-2xl font-bold leading-snug text-brand-navy sm:text-3xl">Cómo funciona</h2>

            <div class="mt-10 grid gap-10 sm:grid-cols-2 lg:grid-cols-4 lg:gap-8">
                @foreach ([['01', 'Organiza tu operación', 'Configura centros y áreas, asigna responsables y carga trabajadores desde Excel.'], ['02', 'Planea la semana', 'Asigna horarios a varias personas y días a la vez. Reutiliza semanas y publica tu programación.'], ['03', 'Registra y revisa', 'Recibe marcajes desde kioscos autorizados o la app con georreferenciación. Compara horarios y registros y atiende las incidencias.'], ['04', 'Obtén resultados', 'Revisa jornadas, consulta proyecciones y cierra períodos para exportar resultados en Excel o CSV.']] as [$n, $title, $body])
                    <div class="border-t-2 border-brand-blue pt-4">
                        <span class="font-display text-sm font-semibold text-brand-blue">{{ $n }}</span>
                        <h3 class="mt-2 text-base font-bold text-brand-navy">{{ $title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-surface-muted">{{ $body }}</p>
                    </div>
                @endforeach
            </div>

            <div class="relative left-1/2 mt-20 w-screen -translate-x-1/2 border-t border-surface-line"></div>
            <h2 class="mt-16 font-display text-2xl font-bold leading-snug text-brand-navy sm:text-3xl">
                Organiza horarios, revisa asistencia y conserva resultados.
            </h2>

            <div class="relative mt-10 grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div class="relative aspect-video min-w-0 overflow-hidden rounded-2xl border border-[#cfe2fb] bg-surface-card lg:aspect-auto">
                    <img data-feature-preview src="{{ asset('images/marketing/shot-kiosco.png') }}"
                        alt="Kiosco de Vera Time"
                        class="absolute inset-0 h-full w-full object-contain transition-opacity duration-700"
                        style="object-fit: contain; object-position: center;">
                    <img data-feature-preview src="{{ asset('images/marketing/shot-trabajadores.png') }}"
                        alt="Trabajadores en Vera Time"
                        class="absolute inset-0 h-full w-full object-contain opacity-0 transition-opacity duration-700"
                        style="object-fit: contain; object-position: center;">
                    <img data-feature-preview src="{{ asset('images/marketing/shot-portal.png') }}"
                        alt="Portal de colaboradores en Vera Time"
                        class="absolute inset-0 h-full w-full object-contain opacity-0 transition-opacity duration-700"
                        style="object-fit: contain; object-position: center;">
                </div>
                <button type="button" data-carousel-direction="previous" aria-label="Ver tarjetas anteriores"
                    class="absolute -left-5 top-1/2 hidden h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full border border-surface-line bg-white text-brand-navy shadow-md transition hover:border-brand-blue hover:text-brand-blue focus:outline-none focus:ring-2 focus:ring-brand-blue focus:ring-offset-2 lg:flex">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>

                <div class="feature-carousel-shell relative min-w-0 lg:col-span-2">
                <div class="mb-3 flex items-center justify-between lg:hidden">
                    <button type="button" data-carousel-direction="previous" aria-label="Ver tarjetas anteriores"
                        class="flex h-10 w-10 items-center justify-center rounded-full border border-surface-line bg-white text-brand-navy shadow-sm transition hover:border-brand-blue hover:text-brand-blue focus:outline-none focus:ring-2 focus:ring-brand-blue focus:ring-offset-2">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                    <span class="text-xs font-medium text-surface-muted">Desliza para ver más</span>
                    <button type="button" data-carousel-direction="next" aria-label="Ver siguientes tarjetas"
                        class="flex h-10 w-10 items-center justify-center rounded-full border border-surface-line bg-white text-brand-navy shadow-sm transition hover:border-brand-blue hover:text-brand-blue focus:outline-none focus:ring-2 focus:ring-brand-blue focus:ring-offset-2">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                </div>
                <div id="feature-carousel" class="feature-carousel flex snap-x snap-mandatory gap-4 overflow-x-auto scroll-smooth pb-3">
                @foreach ([
        ['Organización y equipo', ['Directorio e importación de trabajadores desde XLS y XLSX', 'Centros, áreas y departamentos', 'Multiempresa y multiusuario', 'Roles y alcances por centro o unidad', 'Consulta personal de horarios y marcajes'], true, '<circle cx="9" cy="8" r="3"/><path d="M3.5 20c0-3 2.5-5 5.5-5s5.5 2 5.5 5" stroke-linecap="round"/><circle cx="17" cy="8.5" r="2.3"/><path d="M15.5 12.5c2.4.3 4 2 4 4.5" stroke-linecap="round"/>'],
        ['Registro de asistencia', ['Kioscos autorizados por la empresa', 'App móvil con georreferenciación para marcar', 'Entrada, salida y pausas', 'Ubicación y área autorizada según la política activa', 'Captura manual con motivo y responsable'], true, '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2" stroke-linecap="round" stroke-linejoin="round"/>'],
        ['Horarios y turnos', ['Asignación por grupos, filas y días', 'Turnos fijos, nocturnos, mixtos, flexibles, rotativos y guardias', 'Borradores, publicación y correcciones', 'Clonación de semanas y horarios habituales', 'Importación de programación desde Excel y CSV'], true, '<rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 9.5h17M8 3v4M16 3v4" stroke-linecap="round"/>'],
        ['Jornadas y prevención', ['Tiempo ordinario y extra doble y triple', 'Pausas, puntualidad, domingos y descansos', 'Comparación de horarios y marcajes', 'Acumulados y proyecciones semanales', 'Tablero operativo y alertas preventivas'], true, '<rect x="4.5" y="3.5" width="15" height="17" rx="2"/><path d="M8 8h8M8 12h3M8 16h3M14 12h2M14 16h2" stroke-linecap="round"/>'],
        ['Incidencias', ['Vacaciones, incapacidades y permisos', 'Ausencias documentadas con o sin goce', 'Revisión de registros incompletos y diferencias', 'Seguimiento por aviso con decisión y comentario', 'Correcciones y recálculo con antecedentes'], true, '<path d="M12 3.5l8 4.5v8l-8 4.5-8-4.5v-8L12 3.5z"/><path d="M12 9v4.5M12 16.5h.01" stroke-linecap="round"/>'],
        ['Resultados e integración', ['Cierre de períodos con información histórica', 'Exportación en Excel y CSV', 'Layouts con conceptos, unidades y nomenclaturas', 'API empresarial y personal con permisos', 'Trazabilidad y separación de datos por empresa'], true, '<path d="M5 4.5h11l3 3V19a1 1 0 01-1 1H5a1 1 0 01-1-1V5.5a1 1 0 011-1z"/><path d="M8 9h6M8 13h6M8 17h4" stroke-linecap="round"/>'],
    ] as [$category, $items, $available, $icon])
                    <div
                        class="feature-carousel-card w-full flex-none snap-start rounded-2xl border {{ $available ? 'border-[#cfe2fb] bg-[#f2f7fe]' : 'border-dashed border-surface-line bg-surface-bg' }} p-5 lg:w-[calc((100%-1rem)/2)]">
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
                        </ul>
                    </div>
                @endforeach
                </div>
                <button type="button" data-carousel-direction="next" aria-label="Ver siguientes tarjetas"
                    class="absolute -right-5 top-1/2 hidden h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full border border-surface-line bg-white text-brand-navy shadow-md transition hover:border-brand-blue hover:text-brand-blue focus:outline-none focus:ring-2 focus:ring-brand-blue focus:ring-offset-2 lg:flex">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
                </div>
            </div>
        </div>
    </section>

    {{-- Seguridad de marcaje --}}
    <section id="seguridad" class="border-t border-surface-line bg-[#f2f7fe]">
        <div class="mx-auto max-w-6xl px-6 py-20">
            <p class="text-xs font-bold uppercase tracking-wide text-brand-blue">Seguridad de marcaje</p>
            <h2 class="font-display mt-3 max-w-3xl text-2xl font-bold leading-snug text-brand-navy sm:text-3xl">
                Más controles para reducir el riesgo de marcajes falsos.
            </h2>
            <p class="mt-4 max-w-3xl text-sm leading-relaxed text-surface-muted sm:text-base">
                Define desde qué terminal, dispositivo y ubicación se permite registrar asistencia.
                Los controles se aplican según el canal y la política de seguridad activada por tu empresa.
            </p>
            <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['Terminales aprobadas', 'Cada equipo de kiosco requiere autorización. Puedes limitarlo a un centro y revocar su acceso. El código y NIP del trabajador se utilizan desde una terminal habilitada.'],
                    ['Marcaje georreferenciado', 'La app incorpora ubicación al marcaje. Con una política de área autorizada, el servidor comprueba la distancia, la precisión y la vigencia de la ubicación según sus parámetros.'],
                    ['Dispositivo y firma', 'Las políticas móviles permiten exigir un dispositivo vinculado y validar la firma del marcaje. La empresa puede revocar el dispositivo autorizado.'],
                    ['Cambios con responsable', 'Las capturas manuales requieren motivo y usuario autorizado. Los registros y sus revisiones conservan referencias para explicar el resultado de la jornada.'],
                ] as [$title, $description])
                    <article class="rounded-2xl border border-[#cfe2fb] bg-white p-5">
                        <svg class="mb-4 h-7 w-7 text-brand-blue" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="m12 3 8 3v5c0 5-4 8-8 10-4-2-8-5-8-10V6l8-3Z" stroke-linejoin="round"/><path d="m8.5 12 2.5 2.5 4.5-5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <h3 class="text-base font-bold text-brand-navy">{{ $title }}</h3>
                        <p class="mt-3 text-sm leading-relaxed text-surface-muted">{{ $description }}</p>
                    </article>
                @endforeach
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
                        Información organizada para revisar el tiempo laboral.
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
                    <p class="mt-8 max-w-xl text-xs leading-relaxed text-white/55">
                        Vera Time conserva marcajes, cálculos y resultados exportables para apoyar la revisión del tiempo
                        laboral. Los requisitos aplicables y las disposiciones de la STPS deben revisarse para cada operación;
                        el uso de la plataforma no constituye una certificación de cumplimiento.
                        <a href="https://dof.gob.mx/nota_detalle_popup.php?codigo=5786537" target="_blank" rel="noopener noreferrer" class="underline underline-offset-2 hover:text-white">Consultar el decreto en el DOF</a>.
                    </p>
                </div>

                <div class="space-y-6 lg:-mt-8">
                    <div class="screenshot-frame relative overflow-hidden rounded-2xl border border-white/15 bg-white/5">
                        <img src="{{ asset('images/marketing/lft.png') }}" alt="Reforma laboral 2027"
                            class="block w-full">
                    </div>

                    <ul class="space-y-3">
                    @foreach (['Entrada, salida y pausas identificadas', 'Tiempo trabajado calculado desde registros válidos', 'Tiempo ordinario y extraordinario desglosado', 'Información histórica de los períodos cerrados', 'Registros con origen y contexto para su revisión'] as $item)
                        <li class="flex items-start gap-2.5 text-sm text-white/90">
                            <span
                                class="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-brand-sky text-xs font-bold text-brand-navy">✓</span>
                            {{ $item }}
                        </li>
                    @endforeach
                    </ul>
                </div>
            </div>

        </div>
    </section>

    {{-- Registro de asistencia / kiosco --}}
    <section id="kiosco" class="border-t border-surface-line bg-white">
        <div class="mx-auto max-w-6xl px-6 py-20">
            <div class="grid items-center gap-10 lg:grid-cols-2 lg:gap-14">
                <div>
                    <h2 class="font-display max-w-lg text-2xl font-bold leading-snug text-brand-navy sm:text-3xl">
                        Kiosco autorizado y app con marcaje georreferenciado.
                    </h2>
                    <p class="mt-4 max-w-md text-sm leading-relaxed text-surface-muted sm:text-base">
                        El kiosco se coloca en un dispositivo dentro del centro de trabajo. Cada colaborador registra su
                        entrada, salida o pausa con su código y NIP desde una terminal previamente autorizada por la empresa.
                    </p>
                    <p class="mt-4 max-w-md text-sm leading-relaxed text-surface-muted sm:text-base">
                        La app móvil permite marcar con georreferenciación. Configura las políticas de ubicación y
                        dispositivo para validar los registros según tu operación. Las excepciones se pueden capturar
                        manualmente con motivo y responsable.
                    </p>

                    <div class="mt-5 flex flex-wrap gap-2">
                        <span
                            class="inline-flex items-center rounded-full border border-surface-line bg-white px-3 py-1.5 text-xs font-medium">Kiosco</span>
                        <span
                            class="inline-flex items-center rounded-full border border-surface-line bg-white px-3 py-1.5 text-xs font-medium">Captura
                            manual</span>
                        <span class="inline-flex items-center rounded-full border border-surface-line bg-white px-3 py-1.5 text-xs font-medium">App móvil disponible</span>
                        <span class="inline-flex items-center rounded-full border border-surface-line bg-white px-3 py-1.5 text-xs font-medium">Georreferenciación</span>
                        <span class="inline-flex items-center rounded-full border border-surface-line bg-white px-3 py-1.5 text-xs font-medium">Área autorizada según política</span>
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
                <h3 class="font-display text-base font-bold text-white">Del período revisado a resultados exportables para RH y nómina</h3>
                <p class="mt-3 max-w-3xl text-sm leading-relaxed text-white/80">Descarga Excel o CSV y configura layouts con conceptos, unidades y nomenclaturas. El formato del sistema receptor se valida durante la integración.</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach (['Datos del trabajador disponibles', 'Tiempo ordinario', 'Extra dobles', 'Extra triples', 'Retardos', 'Domingos trabajados', 'Vacaciones e incapacidades'] as $field)
                        <span
                            class="inline-flex items-center rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-xs font-medium text-white">{{ $field }}</span>
                    @endforeach
                </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Solicita un demo --}}
    <section id="demo" class="bg-white">
        <div class="mx-auto max-w-6xl px-6 py-20">
            <div class="relative overflow-hidden rounded-[28px] bg-brand-navy px-8 py-16 sm:px-14">
                <div class="relative grid items-center gap-12 lg:grid-cols-2">
                    <div>
                        <h2 class="font-display text-3xl font-bold leading-[1.15] text-white sm:text-4xl">
                            Solicita un demo — en vivo o a tu ritmo
                        </h2>
                        <p class="mt-5 max-w-md text-[15.5px] leading-relaxed text-white/75">
                            Te mostramos cómo Vera Time organiza turnos, registra asistencia y prepara a tu empresa
                            para la Reforma Laboral 2027 — con la plataforma real, no una versión genérica.
                        </p>

                        <div class="mt-8 flex flex-wrap gap-3">
                            <button type="button" data-demo-modal-open class="btn-primary btn-lg">Agendar demo</button>
                            <a href="#como-funciona"
                                class="inline-flex items-center gap-2 rounded-xl border border-white/20 bg-white/10 px-5 py-3.5 text-[14.5px] font-semibold text-white transition hover:bg-white/15">
                                <svg viewBox="0 0 24 24" fill="currentColor" class="h-3.5 w-3.5"><path d="M8 5v14l11-7z"/></svg>
                                Ver cómo funciona
                            </a>
                        </div>
                    </div>

                    <div class="relative mx-auto flex w-full max-w-[520px] flex-col items-center">
                        <div class="w-full rounded-2xl bg-[#0c1220] p-2.5 shadow-[0_30px_60px_-20px_rgba(0,0,0,0.55)] ring-1 ring-white/5">
                            <div class="mx-auto mb-2 h-1.5 w-1.5 rounded-full bg-[#2a3446]"></div>
                            <div class="overflow-hidden rounded-md bg-white leading-none">
                                <img src="{{ asset('images/marketing/slide 1.png') }}"
                                    alt="Panel de Vera Time" class="block w-full">
                            </div>
                        </div>
                        <div class="-mt-0.5 h-4 w-28"
                            style="background: linear-gradient(180deg, #1c2434, #0c1220); clip-path: polygon(38% 0, 62% 0, 78% 100%, 22% 100%);">
                        </div>
                        <div class="mt-0.5 h-2.5 w-48 rounded-md bg-[#0c1220] shadow-[0_6px_16px_-4px_rgba(0,0,0,0.4)]"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @if (session('demo_request_success'))
        <div class="mx-auto max-w-6xl px-6 pb-6" role="status">
            <div class="rounded-xl border border-brand-sky/30 bg-[#edf7ff] px-4 py-3 text-sm text-brand-navy">
                {{ session('demo_request_success') }}
            </div>
        </div>
    @endif

    <div data-demo-modal data-demo-modal-open-on-load="{{ $errors->getBag('demoRequest')->any() ? 'true' : 'false' }}"
        class="fixed inset-0 z-50 hidden items-center justify-center p-4" role="dialog" aria-modal="true"
        aria-labelledby="demo-modal-title">
        <div data-demo-modal-close class="absolute inset-0 bg-brand-navy/70 backdrop-blur-sm"></div>
        <div class="relative w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl">
            <button type="button" data-demo-modal-close aria-label="Cerrar formulario de demo"
                class="absolute right-4 top-4 z-10 flex h-9 w-9 items-center justify-center rounded-full text-surface-muted transition hover:bg-surface-bg hover:text-brand-navy focus:outline-none focus:ring-2 focus:ring-brand-blue">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 6 12 12M18 6 6 18" stroke-linecap="round"/></svg>
            </button>

            <div class="max-h-[90vh] overflow-y-auto p-6 sm:p-8">
            <h2 id="demo-modal-title" class="font-display pr-10 text-2xl font-bold text-brand-navy">Agenda tu demo</h2>
            <p class="mt-2 max-w-xl text-sm leading-relaxed text-surface-muted">
                Cuéntanos un poco sobre tu empresa y te contactaremos para elegir el mejor horario.
            </p>

            <form method="POST" action="{{ route('demo-requests.store') }}" class="mt-7 space-y-5">
                @csrf
                <div class="hidden" aria-hidden="true">
                    <label for="website">Sitio web</label>
                    <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="contact_name" class="form-label">Nombre completo *</label>
                        <input id="contact_name" name="contact_name" type="text" value="{{ old('contact_name') }}" required autocomplete="name"
                            class="w-full rounded-xl border border-surface-line px-3.5 py-2.5 text-sm text-brand-navy outline-none transition focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/20">
                        @error('contact_name', 'demoRequest')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="company_name" class="form-label">Empresa</label>
                        <input id="company_name" name="company_name" type="text" value="{{ old('company_name') }}" autocomplete="organization"
                            class="w-full rounded-xl border border-surface-line px-3.5 py-2.5 text-sm text-brand-navy outline-none transition focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/20">
                        @error('company_name', 'demoRequest')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="email" class="form-label">Correo de trabajo *</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email"
                            class="w-full rounded-xl border border-surface-line px-3.5 py-2.5 text-sm text-brand-navy outline-none transition focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/20">
                        @error('email', 'demoRequest')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="phone" class="form-label">Teléfono *</label>
                        <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" required autocomplete="tel"
                            class="w-full rounded-xl border border-surface-line px-3.5 py-2.5 text-sm text-brand-navy outline-none transition focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/20">
                        @error('phone', 'demoRequest')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div>
                    <label for="team_size" class="form-label">Personas en tu equipo</label>
                    <select id="team_size" name="team_size"
                        class="w-full rounded-xl border border-surface-line bg-white px-3.5 py-2.5 text-sm text-brand-navy outline-none transition focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/20">
                        <option value="">Selecciona una opción</option>
                        @foreach ([10, 25, 50, 100, 250, 500, 1000] as $size)
                            <option value="{{ $size }}" @selected((string) old('team_size') === (string) $size)>{{ $size }}{{ $size === 1000 ? '+' : '' }}</option>
                        @endforeach
                    </select>
                    @error('team_size', 'demoRequest')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="message" class="form-label">¿Qué te gustaría revisar? <span class="font-normal text-surface-muted">(opcional)</span></label>
                    <textarea id="message" name="message" rows="3" maxlength="1000"
                        class="w-full resize-y rounded-xl border border-surface-line px-3.5 py-2.5 text-sm text-brand-navy outline-none transition focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/20">{{ old('message') }}</textarea>
                    @error('message', 'demoRequest')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <label class="flex items-start gap-3 text-xs leading-relaxed text-surface-muted">
                    <input name="consent" type="checkbox" value="1" @checked(old('consent')) required
                        class="mt-0.5 h-4 w-4 rounded border-surface-line text-brand-blue focus:ring-brand-blue">
                    <span>Acepto que Vera Time use estos datos para contactarme y agendar una demostración.</span>
                </label>
                @error('consent', 'demoRequest')<p class="-mt-3 text-xs text-red-600">{{ $message }}</p>@enderror

                <div class="flex flex-wrap justify-end gap-3 pt-2">
                    <button type="button" data-demo-modal-close class="btn-ghost">Cancelar</button>
                    <button type="submit" class="btn-primary btn-lg">Solicitar demo</button>
                </div>
            </form>
            </div>
        </div>
    </div>

    {{-- La suite VERA --}}
    <section id="suite" class="border-t border-surface-line bg-white">
        <div class="mx-auto max-w-6xl px-6 py-20">
            <h2 class="font-display max-w-lg text-2xl font-bold leading-snug text-brand-navy sm:text-3xl">Una suite. Soluciones
                para cada necesidad.</h2>
            <p class="mt-4 max-w-md text-sm leading-relaxed text-surface-muted">
                Vera Time reúne la operación de horarios y asistencia. La suite VERA contempla productos adicionales
                que se incorporarán con su propio alcance y disponibilidad.
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
    <footer id="contacto" class="border-t border-surface-line bg-white">
        <div class="mx-auto grid max-w-6xl gap-8 px-6 py-12 md:grid-cols-3">
            <div>
                <x-app-logo class="h-7 w-auto" />
                <p class="mt-3 max-w-xs text-sm leading-relaxed text-surface-muted">Orden y evidencia para el tiempo laboral de tu equipo.</p>
            </div>

            <div>
                <h2 class="font-display text-base font-semibold text-brand-navy">Contacto</h2>
                <address class="mt-4 space-y-3 text-sm not-italic leading-relaxed text-surface-muted">
                    <div class="flex items-start gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-brand-blue"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m4 3 4-.5 2 5-2.5 2a15 15 0 0 0 7 7l2-2.5 5 2-.5 4c-.1 1-1 1.7-2 1.5C9.5 20.5 3.5 14.5 2.5 5 2.3 4 3 3.1 4 3Z" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                        <p><span class="font-semibold text-brand-navy">Teléfono</span><br><a href="tel:9935901300" class="transition hover:text-brand-blue">993 590 1300</a></p>
                    </div>
                    <div class="flex items-start gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-brand-blue"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z" stroke-linejoin="round"/><circle cx="12" cy="10" r="2.5"/></svg></span>
                        <p><span class="font-semibold text-brand-navy">Dirección</span><br>Av. Paseo Tabasco núm. 1120, altos.<br>Colonia Jesús García, C.P. 86040.</p>
                    </div>
                </address>
            </div>

            <div>
                <h2 class="font-display text-base font-semibold text-brand-navy">Redes sociales</h2>
                <ul class="mt-4 space-y-3 text-sm text-surface-muted">
                    <li class="flex items-start gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-brand-blue"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M14 22v-9h3l.5-4H14V7c0-1.2.3-2 2-2h2V1.4A25 25 0 0 0 15 1c-3 0-5 1.8-5 5v3H7v4h3v9h4Z"/></svg></span>
                        <div><span class="block font-semibold text-brand-navy">Facebook</span><span>Vera Time</span></div>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-brand-blue"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg></span>
                        <div><span class="block font-semibold text-brand-navy">Instagram</span><a href="https://www.instagram.com/veratime_/" target="_blank" rel="noopener noreferrer" class="transition hover:text-brand-blue">@veratime_<span class="sr-only"> (abre en una pestaña nueva)</span></a></div>
                    </li>
                </ul>
            </div>
        </div>

        <div class="mx-auto flex max-w-6xl justify-end border-t border-surface-line px-6 py-5">
            @if (Route::has('login'))
                @auth
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn-outline">Cerrar sesión</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="btn-outline">Iniciar sesión</a>
                @endauth
            @endif
        </div>
    </footer>

    <script>
        (() => {
            const carousel = document.getElementById('feature-carousel');

            if (!carousel) {
                return;
            }

            const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const previews = [...document.querySelectorAll('[data-feature-preview]')];
            let previewIndex = 0;

            const card = () => carousel.querySelector('.feature-carousel-card');
            const step = () => card().offsetWidth + parseFloat(getComputedStyle(carousel).gap || 0);
            const rotatePreview = (direction = 'next') => {
                if (!previews.length) {
                    return;
                }

                previews[previewIndex].classList.add('opacity-0');
                previewIndex = (previewIndex + (direction === 'previous' ? -1 : 1) + previews.length) % previews.length;
                previews[previewIndex].classList.remove('opacity-0');
            };
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

            const advance = () => {
                move('next');
                rotatePreview();
            };
            let autoplay = prefersReducedMotion ? null : setInterval(advance, 5000);
            const restartAutoplay = () => {
                clearInterval(autoplay);
                autoplay = prefersReducedMotion ? null : setInterval(advance, 5000);
            };

            document.querySelectorAll('[data-carousel-direction]').forEach((button) => {
                button.addEventListener('click', () => {
                    move(button.dataset.carouselDirection);
                    rotatePreview(button.dataset.carouselDirection);
                    restartAutoplay();
                });
            });

            carousel.addEventListener('mouseenter', () => clearInterval(autoplay));
            carousel.addEventListener('mouseleave', restartAutoplay);
            carousel.addEventListener('focusin', () => clearInterval(autoplay));
            carousel.addEventListener('focusout', restartAutoplay);

            const demoModal = document.querySelector('[data-demo-modal]');
            const openDemoModal = () => {
                if (!demoModal) {
                    return;
                }

                demoModal.classList.remove('hidden');
                demoModal.classList.add('flex');
                document.body.classList.add('overflow-hidden');
                demoModal.querySelector('#contact_name')?.focus();
            };
            const closeDemoModal = () => {
                if (!demoModal) {
                    return;
                }

                demoModal.classList.add('hidden');
                demoModal.classList.remove('flex');
                document.body.classList.remove('overflow-hidden');
            };

            document.querySelectorAll('[data-demo-modal-open]').forEach((button) => {
                button.addEventListener('click', openDemoModal);
            });
            document.querySelectorAll('[data-demo-modal-close]').forEach((button) => {
                button.addEventListener('click', closeDemoModal);
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && demoModal && !demoModal.classList.contains('hidden')) {
                    closeDemoModal();
                }
            });

            if (demoModal?.dataset.demoModalOpenOnLoad === 'true') {
                openDemoModal();
            }
        })();
    </script>

</body>

</html>
