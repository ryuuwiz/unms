<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head', ['title' => 'Papan Antrean Teknisi'])
    </head>
    <body class="min-h-screen bg-zinc-950 text-white antialiased">
        {{ $slot }}

        @fluxScripts
    </body>
</html>
