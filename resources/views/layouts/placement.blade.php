<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ALICE - Gamified English Learning Platform')</title>
    <meta name="description" content="Read classic stories, play word games, and level up your reading one day at a time.">

    <!-- Open Graph / Social Share Preview -->
    @php
        $ogUrl = config('app.url', url('/'));
        $ogTitle = 'ALICE - Gamified English Learning Platform';
        $ogDesc  = 'Read classic stories, play word games, and level up your reading one day at a time.';
        $ogImage = asset('image/Alice/Light%20mode/alice-lt-talking.png');
    @endphp
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="ALICE">
    <meta property="og:url" content="{{ $ogUrl }}">
    <meta property="og:title" content="{{ $ogTitle }}">
    <meta property="og:description" content="{{ $ogDesc }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $ogTitle }}">
    <meta name="twitter:description" content="{{ $ogDesc }}">
    <meta name="twitter:image" content="{{ $ogImage }}">

    @yield('head')

    <!-- Self-hosted web fonts (Quicksand & Press Start 2P, pixel font) -->
    <!-- Tailwind CSS -->
    @vite(['resources/css/app.css', 'resources/css/web-fonts.css'])
    @yield('js')

    {{-- Dark-mode preference applied before first paint (see also the
         authenticated layout). Landing/guest shares the same palette. --}}
    <script>
        (function () {
            try {
                var stored = localStorage.getItem('alice-dark-mode');
                var dark = stored === '1';
                if (dark) document.documentElement.classList.add('dark');
            } catch (_) {}
        })();
    </script>

    <style>
        body {
            font-family: 'Quicksand', sans-serif;
        }
        .font-pixel {
            font-family: 'Press Start 2P', monospace;
            image-rendering: pixelated;
        }
    </style>
</head>
<body class="bg-[#FAF6EE] text-[#2D231E] min-h-screen flex flex-col antialiased selection:bg-amber-300" data-area="placement" style="background: radial-gradient(var(--c-ink) 0.75px, transparent 0.75px) 0 0 / 20px 20px, var(--c-page);">

    <!-- MAIN CONTENT (placement is a focused flow: no header or footer) -->
    <main class="flex-grow">
        @yield('content')
    </main>

    @include('partials.toast')

</body>
</html>