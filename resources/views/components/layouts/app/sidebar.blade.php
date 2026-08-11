<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body
        class="min-h-screen bg-white dark:bg-zinc-800"
        x-data="{
            sidebarVisible: window.innerWidth >= 1024,
            sidebarCollapsed: window.innerWidth < 1024,
            sidebarCollapseTimer: null,
            init() {
                window.addEventListener('resize', () => {
                    if (window.innerWidth < 1024) {
                        this.sidebarVisible = true;
                        this.sidebarCollapsed = false;
                    }
                });
            },
            toggleSidebar() {
                clearTimeout(this.sidebarCollapseTimer);

                if (this.sidebarVisible) {
                    this.sidebarVisible = false;
                    this.sidebarCollapseTimer = setTimeout(() => { this.sidebarCollapsed = true }, 300);
                    return;
                }

                this.sidebarCollapsed = false;
                this.$nextTick(() => {
                    this.sidebarVisible = true;
                });
            },
        }"
    >
        <div
            class="app-shell-toggle-position fixed top-1/2 z-50 hidden -translate-y-1/2 transition-[left] duration-300 ease-in-out lg:flex"
            x-bind:class="sidebarVisible ? 'app-shell-toggle-position-open' : 'app-shell-toggle-position-closed'"
        >
            <button
                type="button"
                class="app-shell-open-toggle app-shell-open-tab flex h-[80px] w-[18px] items-center justify-center"
                x-bind:aria-label="sidebarVisible ? 'Ocultar menu lateral' : 'Mostrar menu lateral'"
                @click="toggleSidebar()"
            >
                <span class="app-shell-open-symbol" x-show="sidebarVisible">&#8249;</span>
                <span class="app-shell-open-symbol" x-show="! sidebarVisible">&#8250;</span>
            </button>
        </div>

        <flux:sidebar
            sticky
            stashable
            class="vera-app-sidebar border-r border-vera-sidebar-border bg-vera-sidebar text-zinc-100 transition-[width,min-width,transform,padding] duration-300 ease-in-out"
            x-bind:class="{
                'lg:!-translate-x-full': ! sidebarVisible,
                'lg:!w-0 lg:!min-w-0 lg:!overflow-hidden lg:!border-r-0 lg:!p-0': sidebarCollapsed,
            }"
        >
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ route('dashboard') }}" class="vera-sidebar-logo mb-4 flex w-full justify-center rounded-lg px-2 py-2" wire:navigate>
                <x-app-logo class="h-14 w-36" href="#"></x-app-logo>
            </a>

            <livewire:companies.company-switcher />

            @php($activeCompany = app(\App\Domains\Tenancy\Support\CurrentCompany::class)->get())

            <flux:navlist variant="outline">
                <flux:navlist.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>Inicio</flux:navlist.item>

                <flux:navlist.group heading="Organización" class="grid">
                    <flux:navlist.item icon="building-office" :href="route('companies.index')" :current="request()->routeIs('companies.*')" wire:navigate>Empresas</flux:navlist.item>
                    <flux:navlist.item icon="map-pin" :href="route('centers.index')" :current="request()->routeIs('centers.*')" wire:navigate>Centros</flux:navlist.item>

                    @if ($activeCompany && auth()->user()->can('viewAny', [\App\Models\OrganizationalUnit::class, $activeCompany]))
                        <flux:navlist.item icon="building-office-2" :href="route('organization.units')" :current="request()->routeIs('organization.units')" wire:navigate>Áreas y departamentos</flux:navlist.item>
                        <flux:navlist.item icon="users" :href="route('workers.index')" :current="request()->routeIs('workers.*')" wire:navigate>Trabajadores</flux:navlist.item>
                        <flux:navlist.item icon="user-group" :href="route('organization.assignments')" :current="request()->routeIs('organization.assignments')" wire:navigate>Asignaciones organizacionales</flux:navlist.item>
                        <flux:navlist.item icon="shield-check" :href="route('organization.scopes')" :current="request()->routeIs('organization.scopes')" wire:navigate>Responsables y supervisores</flux:navlist.item>
                    @endif

                    @if ($activeCompany && auth()->user()->roleKeyForCompany($activeCompany) === \App\Support\RoleKey::SUPERVISOR)
                        <flux:navlist.item icon="eye" :href="route('organization.my-scope')" :current="request()->routeIs('organization.my-scope')" wire:navigate>Mi alcance</flux:navlist.item>
                    @endif
                </flux:navlist.group>

                <flux:navlist.group heading="Horarios" class="grid">
                    @if ($activeCompany && auth()->user()->can('viewAny', [\App\Models\ShiftTemplate::class, $activeCompany]))
                        <flux:navlist.item icon="calendar-days" :href="route('scheduling.shifts')" :current="request()->routeIs('scheduling.shifts')" wire:navigate>Catálogo de turnos</flux:navlist.item>
                    @endif

                    @if ($activeCompany && auth()->user()->can('viewAny', [\App\Models\ScheduleProfile::class, $activeCompany]))
                        <flux:navlist.item icon="calendar" :href="route('scheduling.profiles')" :current="request()->routeIs('scheduling.profiles')" wire:navigate>Modelos de horario</flux:navlist.item>
                        <flux:navlist.item icon="queue-list" :href="route('scheduling.profile-assignments')" :current="request()->routeIs('scheduling.profile-assignments')" wire:navigate>Aplicacion de modelos</flux:navlist.item>
                    @endif

                    @if ($activeCompany && auth()->user()->can('viewAny', [\App\Models\ScheduleBatch::class, $activeCompany]))
                        <flux:navlist.item icon="calendar-days" :href="route('scheduling.daily')" :current="request()->routeIs('scheduling.daily')" wire:navigate>Programacion semanal</flux:navlist.item>
                    @endif

                    <flux:navlist.item icon="calendar-days" :href="route('mandatory-rest-days.index')" :current="request()->routeIs('mandatory-rest-days.*')" wire:navigate>Descansos obligatorios</flux:navlist.item>
                </flux:navlist.group>

                @if ($activeCompany && auth()->user()->can('viewAny', [\App\Models\TimeEvent::class, $activeCompany]))
                    <flux:navlist.group heading="Registro de jornada" class="grid">
                        <flux:navlist.item icon="computer-desktop" :href="route('kiosk.index')" :current="request()->routeIs('kiosk.*')" wire:navigate>Kiosco</flux:navlist.item>
                        <flux:navlist.item icon="clock" :href="route('time-events.manual')" :current="request()->routeIs('time-events.*') || request()->routeIs('time-clock.*')" wire:navigate>Eventos</flux:navlist.item>
                        @can('viewAny', [\App\Models\AttendanceIncident::class, $activeCompany])
                            <flux:navlist.item icon="clipboard-document-check" :href="route('attendance-incidents.index')" :current="request()->routeIs('attendance-incidents.*')" wire:navigate>Incidencias y ausencias</flux:navlist.item>
                        @endcan
                        @can('viewAny', [\App\Models\WorkDay::class, $activeCompany])
                            <flux:navlist.item icon="calendar-days" :href="route('work-days.index')" :current="request()->routeIs('work-days.*')" wire:navigate>Jornadas</flux:navlist.item>
                        @endcan
                        @can('viewAny', [\App\Models\AttendancePeriod::class, $activeCompany])
                            <flux:navlist.item icon="document-check" :href="route('attendance-periods.index')" :current="request()->routeIs('attendance-periods.*')" wire:navigate>Periodos de asistencia</flux:navlist.item>
                        @endcan
                        @if (in_array(auth()->user()->roleKeyForCompany($activeCompany), [...\App\Support\RoleKey::companyManagers(), \App\Support\RoleKey::SUPER_ADMIN], true))
                            <flux:navlist.item icon="beaker" :href="route('testing.quick-events')" :current="request()->routeIs('testing.quick-events')" wire:navigate>Eventos rapidos</flux:navlist.item>
                        @endif
                    </flux:navlist.group>
                @endif
            </flux:navlist>

            <flux:spacer />

      {{--       <flux:navlist variant="outline">
                <flux:navlist.item icon="folder-git-2" href="https://github.com/Lievano1985/jornada360" target="_blank">
                    Repositorio
                </flux:navlist.item>

                <flux:navlist.item icon="book-open-text" href="https://github.com/Lievano1985/jornada360/tree/main/docs" target="_blank">
                    Documentacion
                </flux:navlist.item>
            </flux:navlist> --}}

            <!-- Menu de usuario de escritorio -->
            <flux:dropdown position="bottom" align="start">
                <flux:profile
                    :name="auth()->user()->name"
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevrons-up-down"
                />

                <flux:menu class="w-[220px]">
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-left text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item href="/settings/profile" icon="cog" wire:navigate>Configuracion</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            {{ __('Cerrar sesion') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:sidebar>

        <!-- Menu de usuario movil -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-left text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item href="/settings/profile" icon="cog" wire:navigate>Configuracion</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            {{ __('Cerrar sesion') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @fluxScripts
    </body>
</html>
