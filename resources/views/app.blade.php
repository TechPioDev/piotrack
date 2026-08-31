<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ $metaTitle ?? config('app.name', 'Laravel') }}</title>

        {{-- SEO meta for the public marketing pages (MSITE). The app has no SSR,
             so crawler-facing tags must render server-side via view data. --}}
        @isset($metaDescription)
            <meta name="description" content="{{ $metaDescription }}">
        @endisset
        @isset($canonical)
            <link rel="canonical" href="{{ $canonical }}">
        @endisset
        @isset($jsonLd)
            <script type="application/ld+json" nonce="{{ $cspNonce }}">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES) !!}</script>
        @endisset

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
