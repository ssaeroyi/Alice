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
<body class="bg-[#FAF6EE] text-[#2D231E] min-h-screen flex flex-col antialiased selection:bg-amber-300" data-area="guest" style="background: radial-gradient(var(--c-ink) 0.75px, transparent 0.75px) 0 0 / 20px 20px, var(--c-page);">

    <!-- HEADER / NAVIGATION -->
    <header class="sticky top-0 z-50 bg-[#FAF6EE]/90 backdrop-blur-md border-b-2 border-[#E6DCCF]">
        <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
            
            <!-- Brand Logo -->
            <a href="/" class="group flex items-center gap-1 transition-transform group-hover:scale-105">
                <span class="font-pixel text-xl sm:text-2xl font-bold tracking-wider text-[#2D231E]">
                    ALICE
                </span>
            </a>

            <!-- Sign in -->
            <a
                href="{{ route('login') }}"
                class="inline-flex items-center justify-center min-h-[44px] font-pixel text-[9px] sm:text-[10px] px-4 sm:px-5 py-2 rounded-xl bg-white border-2 border-[#2D231E] text-[#2D231E] font-bold uppercase tracking-wider shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] hover:bg-amber-400 active:translate-x-[1px] active:translate-y-[1px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] transition-all"
            >
                SIGN IN
            </a>

        </div>
    </header>

    <!-- MAIN CONTENT -->
    <main class="flex-grow">
        @yield('content')
    </main>

    <!-- FOOTER -->
    <footer class="bg-[#F3ECE0] border-t-2 border-[#E6DCCF] py-8 mt-12">
        <div class="max-w-6xl mx-auto px-4 flex flex-col md:flex-row items-center justify-between gap-4">
            
            <!-- Brand & Trademark Info -->
            <div class="text-center md:text-left space-y-1">
                <p class="font-pixel text-sm text-[#2D231E]">ALICE</p>
                <p class="text-xs font-semibold text-[#2D231E]">Gamified English Learning Platform</p>
            </div>

            <!-- Footer Navigation Links -->
            <div class="flex items-center gap-6 text-xs font-bold text-[#2D231E]">
                <a href="{{ route('about') }}" class="hover:text-[#2D231E] hover:underline transition-colors">About Us</a>
                <a href="{{ route('contact') }}" class="hover:text-[#2D231E] hover:underline transition-colors">Contact</a>
                <a href="{{ route('terms') }}" class="hover:text-[#2D231E] hover:underline transition-colors">Terms & Conditions</a>
            </div>

        </div>
    </footer>

    @include('partials.toast')

</body>
</html>