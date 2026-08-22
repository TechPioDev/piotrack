<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        {{-- Instrument Sans is self-hosted and bundled via resources/css/app.css
             (the app CSP blocks the fonts.bunny.net stylesheet). --}}

        {{-- The route table is the only inline script the app ships. The nonce is
             minted per request by the SecurityHeaders middleware; without it the
             production CSP blocks this tag and every page fails on `route()`. --}}
        @routes(nonce: $cspNonce)
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
