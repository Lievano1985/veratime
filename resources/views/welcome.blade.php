<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Vera Time') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-zinc-950 text-white antialiased">
        <main class="flex min-h-screen items-center justify-center px-6 py-12">
            <section class="w-full max-w-xl space-y-8 text-center">
                <div class="flex justify-center">
                    <x-app-logo class="h-24 w-72" />
                </div>

                <div class="space-y-3">
                    <h1 class="text-3xl font-semibold sm:text-4xl">Vera Time</h1>
                    <p class="text-base text-zinc-300 sm:text-lg">
                        Plataforma para administrar, registrar y evidenciar el tiempo laboral.
                    </p>
                </div>

                @if (Route::has('login'))
                    <div class="flex flex-col items-center justify-center gap-3 sm:flex-row">
                        @auth
                            <a href="{{ url('/dashboard') }}" class="inline-flex min-h-10 items-center justify-center rounded-md bg-white px-5 text-sm font-medium text-zinc-950 transition hover:bg-zinc-200">
                                Ir al inicio
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="inline-flex min-h-10 items-center justify-center rounded-md bg-white px-5 text-sm font-medium text-zinc-950 transition hover:bg-zinc-200">
                                Iniciar sesion
                            </a>
                        @endauth
                    </div>
                @endif
            </section>
        </main>
    </body>
</html>