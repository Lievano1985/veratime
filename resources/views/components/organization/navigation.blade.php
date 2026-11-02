@props(['group' => 'structure', 'active'])

@php
    $company = app(\App\Domains\Tenancy\Support\CurrentCompany::class)->get();
    $user = auth()->user();
    $isSupervisor = $company && $user->roleKeyForCompany($company) === \App\Support\RoleKey::SUPERVISOR;
    $tabs = $group === 'workers'
        ? [
            ['workers', 'Trabajadores', 'workers.index', $company && $user->can('viewAny', [\App\Models\Worker::class, $company])],
            ['assignments', 'Asignación organizacional', 'organization.assignments', $company && $user->can('viewAny', [\App\Models\EmploymentUnitAssignment::class, $company])],
        ]
        : [
            ['companies', 'Empresa', 'companies.index', ! $isSupervisor],
            ['centers', 'Centros', 'centers.index', ! $isSupervisor && $company && $user->can('viewAny', [\App\Models\Center::class, $company])],
            ['units', 'Áreas y departamentos', 'organization.units', ! $isSupervisor && $company && $user->can('viewAny', [\App\Models\OrganizationalUnit::class, $company])],
        ];
@endphp

<nav aria-label="{{ $group === 'workers' ? 'Gestión de trabajadores' : 'Estructura de organización' }}" class="flex flex-wrap gap-2 border-b border-zinc-200 pb-3 dark:border-zinc-700">
    @foreach ($tabs as [$key, $label, $route, $allowed])
        @if ($allowed)
            <a href="{{ route($route) }}" wire:navigate aria-current="{{ $active === $key ? 'page' : 'false' }}"
                @class(['rounded-lg px-4 py-2 text-sm font-semibold transition', 'bg-blue-600 text-white' => $active === $key, 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-200' => $active !== $key])>{{ $label }}</a>
        @endif
    @endforeach
</nav>
