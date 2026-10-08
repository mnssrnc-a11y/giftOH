<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Gift of Hope')</title>
    <link rel="icon" type="image/png" sizes="64x64" href="{{ asset('images/favicon-64.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">

    {{-- Public Firebase web config, so pages do not need an extra request for it. --}}
    <script>window.giftOfHopeFirebaseConfig = @json(config('services.firebase.client'));</script>

    {{-- Sidebar pages load in the background while the pointer rests on a link, so the click
         opens them instantly (Chrome and Edge; other browsers navigate normally). --}}
    <script type="speculationrules">
    {
        "prerender": [{
            "where": { "and": [
                { "selector_matches": ".ws-nav a, .ws-top-avatar, [data-prerender]" },
                { "not": { "href_matches": "/logout" } }
            ] },
            "eagerness": "moderate"
        }]
    }
    </script>

    <!-- Styles (Tailwind via Vite) -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 font-sans">
    <div class="nav-progress" data-nav-progress aria-hidden="true"></div>
    @yield('body')
</body>
</html>
