<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky stashable class="border-r border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ route('dashboard') }}" class="mb-2 flex w-full justify-center" wire:navigate>
                <x-app-logo class="size-8" href="#"></x-app-logo>
            </a>

            <livewire:companies.company-switcher />

            <flux:navlist variant="outline">
                <flux:navlist.group heading="Plataforma" class="grid">
                    <flux:navlist.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>Inicio</flux:navlist.item>
                    <flux:navlist.item icon="building-office" :href="route('companies.index')" :current="request()->routeIs('companies.*')" wire:navigate>Empresas</flux:navlist.item>
                    <flux:navlist.item icon="map-pin" :href="route('centers.index')" :current="request()->routeIs('centers.*')" wire:navigate>Centros</flux:navlist.item>
                    <flux:navlist.item icon="users" :href="route('workers.index')" :current="request()->routeIs('workers.*')" wire:navigate>Trabajadores</flux:navlist.item>
                    <flux:navlist.item icon="calendar-days" :href="route('schedules.index')" :current="request()->routeIs('schedules.*')" wire:navigate>Horarios</flux:navlist.item>
                    <flux:navlist.item icon="clock" :href="route('schedule-assignments.index')" :current="request()->routeIs('schedule-assignments.*')" wire:navigate>Asignacion de Horarios</flux:navlist.item>
                    <flux:navlist.item icon="calendar-days" :href="route('mandatory-rest-days.index')" :current="request()->routeIs('mandatory-rest-days.*')" wire:navigate>Descansos obligatorios</flux:navlist.item>
                    @php($activeCompanyForOrganization = app(\App\Domains\Tenancy\Support\CurrentCompany::class)->get())
                    @if ($activeCompanyForOrganization && auth()->user()->can('viewAny', [\App\Models\OrganizationalUnit::class, $activeCompanyForOrganization]))
                        <flux:navlist.item icon="building-office-2" :href="route('organization.units')" :current="request()->routeIs('organization.units')" wire:navigate>Areas y departamentos</flux:navlist.item>
                        <flux:navlist.item icon="user-group" :href="route('organization.assignments')" :current="request()->routeIs('organization.assignments')" wire:navigate>Asignaciones organizacionales</flux:navlist.item>
                        <flux:navlist.item icon="shield-check" :href="route('organization.scopes')" :current="request()->routeIs('organization.scopes')" wire:navigate>Responsables y supervisores</flux:navlist.item>
                    @endif
                    @if ($activeCompanyForOrganization && auth()->user()->roleKeyForCompany($activeCompanyForOrganization) === \App\Support\RoleKey::SUPERVISOR)
                        <flux:navlist.item icon="eye" :href="route('organization.my-scope')" :current="request()->routeIs('organization.my-scope')" wire:navigate>Mi alcance</flux:navlist.item>
                    @endif
                    @php($activeCompanyForTimeClock = app(\App\Domains\Tenancy\Support\CurrentCompany::class)->get())
                    @if ($activeCompanyForTimeClock && auth()->user()->can('viewAny', [\App\Models\TimeEvent::class, $activeCompanyForTimeClock]))
                        <flux:navlist.item icon="clock" :href="route('time-clock.index')" :current="request()->routeIs('time-clock.*')" wire:navigate>Registro asistido</flux:navlist.item>
                        <flux:navlist.item icon="computer-desktop" :href="route('kiosk.index')" :current="request()->routeIs('kiosk.*')" wire:navigate>Kiosco</flux:navlist.item>
                        <flux:navlist.item icon="clock" :href="route('time-events.manual')" :current="request()->routeIs('time-events.*')" wire:navigate>Captura justificada</flux:navlist.item>
                    @endif
                </flux:navlist.group>
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
