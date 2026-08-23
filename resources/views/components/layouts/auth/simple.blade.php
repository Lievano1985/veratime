<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-surface-bg text-surface-text antialiased">
        <main class="min-h-svh bg-surface-bg">
            {{ $slot }}
        </main>
        @fluxScripts
    </body>
</html>