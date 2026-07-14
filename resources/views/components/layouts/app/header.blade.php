<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:header container class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <a href="{{ route('dashboard') }}" class="ml-2 mr-5 flex items-center space-x-2 lg:ml-0" wire:navigate>
                <x-app-logo class="size-8" href="#"></x-app-logo>
            </a>

            <flux:navbar class="-mb-px max-lg:hidden">
                <flux:navbar.item icon="layout-grid" href="{{ route('dashboard') }}" :current="request()->routeIs('dashboard')" wire:navigate>
                    Inicio
                </flux:navbar.item>

                <flux:navbar.item icon="building-office" href="{{ route('companies.index') }}" :current="request()->routeIs('companies.*')" wire:navigate>
                    Empresas
                </flux:navbar.item>

                <flux:navbar.item icon="map-pin" href="{{ route('centers.index') }}" :current="request()->routeIs('centers.*')" wire:navigate>
                    Centros
                </flux:navbar.item>

                <flux:navbar.item icon="users" href="{{ route('workers.index') }}" :current="request()->routeIs('workers.*')" wire:navigate>
                    Trabajadores
                </flux:navbar.item>

                <flux:navbar.item icon="calendar-days" href="{{ route('schedules.index') }}" :current="request()->routeIs('schedules.*')" wire:navigate>
                    Horarios
                </flux:navbar.item>
            </flux:navbar>

            <flux:spacer />

            <div class="hidden w-[320px] lg:block">
                <livewire:companies.company-switcher />
            </div>

            <flux:navbar class="mr-1.5 space-x-0.5 py-0!">
                <flux:tooltip content="Buscar" position="bottom">
                    <flux:navbar.item class="!h-10 [&>div>svg]:size-5" icon="magnifying-glass" href="#" label="Buscar" />
                </flux:tooltip>
                <flux:tooltip content="Repositorio" position="bottom">
                    <flux:navbar.item
                        class="h-10 max-lg:hidden [&>div>svg]:size-5"
                        icon="folder-git-2"
                        href="https://github.com/Lievano1985/jornada360"
                        target="_blank"
                        label="Repositorio"
                    />
                </flux:tooltip>
                <flux:tooltip content="Documentacion" position="bottom">
                    <flux:navbar.item
                        class="h-10 max-lg:hidden [&>div>svg]:size-5"
                        icon="book-open-text"
                        href="https://github.com/Lievano1985/jornada360/tree/main/docs"
                        target="_blank"
                        label="Documentacion"
                    />
                </flux:tooltip>
            </flux:navbar>

            <!-- Menu de usuario de escritorio -->
            <flux:dropdown position="top" align="end">
                <flux:profile
                    class="cursor-pointer"
                    :initials="auth()->user()->initials()"
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

        <!-- Menu movil -->
        <flux:sidebar stashable sticky class="lg:hidden border-r border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ route('dashboard') }}" class="mb-2 flex w-full justify-center" wire:navigate>
                <x-app-logo class="size-8" href="#"></x-app-logo>
            </a>

            <flux:navlist variant="outline">
                <flux:navlist.group heading="Plataforma">
                    <flux:navlist.item icon="layout-grid" href="{{ route('dashboard') }}" :current="request()->routeIs('dashboard')" wire:navigate>
                        Inicio
                    </flux:navlist.item>
                    <flux:navlist.item icon="building-office" href="{{ route('companies.index') }}" :current="request()->routeIs('companies.*')" wire:navigate>
                        Empresas
                    </flux:navlist.item>
                    <flux:navlist.item icon="map-pin" href="{{ route('centers.index') }}" :current="request()->routeIs('centers.*')" wire:navigate>
                        Centros
                    </flux:navlist.item>
                    <flux:navlist.item icon="users" href="{{ route('workers.index') }}" :current="request()->routeIs('workers.*')" wire:navigate>
                        Trabajadores
                    </flux:navlist.item>
                    <flux:navlist.item icon="calendar-days" href="{{ route('schedules.index') }}" :current="request()->routeIs('schedules.*')" wire:navigate>
                        Horarios
                    </flux:navlist.item>
                </flux:navlist.group>
            </flux:navlist>

            <flux:spacer />

            <flux:navlist variant="outline">
                <flux:navlist.item icon="folder-git-2" href="https://github.com/Lievano1985/jornada360" target="_blank">
                    Repositorio
                </flux:navlist.item>

                <flux:navlist.item icon="book-open-text" href="https://github.com/Lievano1985/jornada360/tree/main/docs" target="_blank">
                    Documentacion
                </flux:navlist.item>
            </flux:navlist>
        </flux:sidebar>

        {{ $slot }}

        @fluxScripts
    </body>
</html>
