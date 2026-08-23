<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

@php
    $appName = config('app.name', 'Vera Time');
    $routeTitle = match (request()->route()?->getName()) {
        'home' => 'Inicio publico',
        'dashboard' => 'Inicio',
        'companies.index' => 'Empresas',
        'centers.index' => 'Centros',
        'workers.index' => 'Trabajadores',
        'schedules.index' => 'Horarios legacy',
        'schedule-assignments.index' => 'Asignaciones legacy',
        'mandatory-rest-days.index' => 'Descansos obligatorios',
        'organization.units' => 'Areas y departamentos',
        'organization.assignments' => 'Asignaciones organizacionales',
        'organization.scopes' => 'Responsables y supervisores',
        'organization.my-scope' => 'Mi alcance',
        'scheduling.shifts' => 'Catalogo de turnos',
        'scheduling.profiles' => 'Modelos de horario',
        'scheduling.profile-assignments' => 'Aplicacion de modelos',
        'scheduling.daily' => 'Programacion semanal',
        'time-clock.index' => 'Registro asistido',
        'time-events.manual' => 'Eventos',
        'attendance-incidents.index' => 'Incidencias y ausencias',
        'testing.quick-events' => 'Eventos rapidos de prueba',
        'work-days.index' => 'Jornadas',
        'kiosk.index' => 'Kiosco',
        'settings.profile' => 'Perfil',
        'settings.password' => 'Contrasena',
        'settings.appearance' => 'Apariencia',
        default => null,
    };
    $pageTitle = $title ?? ($routeTitle ? "{$routeTitle} | {$appName}" : $appName);
@endphp

<title>{{ $pageTitle }}</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
