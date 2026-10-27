<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="asset-base" content="{{ url('/') }}">
    <title>ALICE - Gamified English Learning Platform</title>
    <meta name="description" content="{{ $metaDescription ?? 'ALICE - Read classic stories, play word games, and level up your reading one day at a time.' }}">

    <!-- Self-hosted web fonts -->
    <!-- Tailwind CSS -->
    @vite(['resources/css/app.css', 'resources/css/web-fonts.css', 'resources/js/app.js'])

    {{-- Dark-mode preference is applied synchronously on <html> before first
         paint to avoid a flash of the wrong theme. The stored choice wins; the
         OS scheme is only a fallback the first time. Matches alice-sound-mute. --}}
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
@php
    // Reader wallpaper: full URLs via asset() so they resolve against the app
    // origin even when the CSS itself is served by the Vite dev server (:5173),
    // which never serves public/ files.
    $isReader = trim((string) $__env->yieldContent('page')) === 'reader';
@endphp
@php
    // Reversible admin "view as" preview: while active show a slim escape banner
    // so the admin can return to their console from anywhere — including the
    // immersive reader (which hides the normal header). The banner is styled as
    // plainly dark admin tooling; the previewed page itself keeps its normal
    // gamified look (View As shows the REAL student/teacher experience).
    $viewAsActor = \App\Support\ViewAs::actor();
@endphp
<body class="bg-[#FAF6EE] text-[#2D231E] min-h-screen flex flex-col antialiased selection:bg-amber-300" data-area="student" data-page="@yield('page')" data-debug-actor="{{ $viewAsActor?->role->value ?? '' }}" data-debug="{{ app()->hasDebugModeEnabled() ? '1' : '0' }}" data-stt-debug="{{ session('alice.stt_debug') ? '1' : '0' }}"
    @if ($isReader) style="--reader-bg-light: url('{{ asset('image/reading/bg-light.jpg') }}'); --reader-bg-dark: url('{{ asset('image/reading/bg-dark.jpg') }}');" @endif>
@if ($viewAsActor)
    <div class="sticky top-0 z-50 bg-[#2D231E] border-b-2 border-amber-400">
        <div class="max-w-7xl mx-auto px-3 sm:px-4 py-1.5 flex items-center justify-between gap-3">
            <p class="text-[12px] font-semibold text-amber-300 truncate">
                VIEWING AS {{ strtoupper(auth()->user()->nickname ?? 'USER') }} ({{ auth()->user()->role->value }})
            </p>
            @include('partials.dev-tools-overlay')
            <form method="POST" action="{{ route('preview.return') }}">
                @csrf
                <button
                    type="submit"
                    class="shrink-0 text-xs font-bold px-3 py-1.5 rounded-lg bg-amber-400 text-[#2D231E] hover:bg-amber-300 transition-colors cursor-pointer"
                >RETURN TO ADMIN</button>
            </form>
        </div>
    </div>
@endif
@php
    // B3 immersive shell: reader, quiz & mini-games hide the app chrome so
    // the activity fills the viewport. Flashcard sessions are immersive too
    // (Anki-style full-screen review with no header/footer chrome).
    $immersive = in_array(trim((string) $__env->yieldContent('page')), ['reader', 'quiz', 'game-wordsearch', 'game-match', 'game-sentence', 'study-flashcards']);

    // S4: gamification visible indicators. If the controller already supplied
    // $gamer (e.g. HubController which computes rank+tasks once), reuse it;
    // otherwise compute the full set so non-hub pages (library, badges, etc.)
    // still render the nav chips + MY STATS panel correctly.
    if (!$immersive && !isset($gamer) && auth()->check()) {
        $u = auth()->user();
        $rank = \App\Support\PointsRank::for($u);
        $ladder = config('gamification.points.titles', []);
        $tierIcon = $ladder[$rank['tier']]['icon'] ?? 'book-cover';
        $gamer = [
            'rank' => $rank,
            'tier_icon' => $tierIcon,
            'keys' => (int) ($u->total_keys ?? 0),
            'streak' => (int) ($u->current_streak ?? 0),
            'longest_streak' => (int) ($u->longest_streak ?? 0),
            'freezes' => (int) ($u->streak_freezes ?? 0),
            'freeze_cost' => (int) config('gamification.sinks.streak_freeze'),
            'freeze_cap' => (int) config('gamification.freeze.hold_cap'),
            'claimable_tasks' => (int) app(\App\Services\DailyTaskService::class)->claimableCount($u, \Illuminate\Support\Carbon::now()),
            'in_progress_tasks' => (int) app(\App\Services\DailyTaskService::class)->state($u, \Illuminate\Support\Carbon::now())['in_progress'],
        ];
    }
    if ($immersive) {
        $gamer = null;
    }

    // Nav-chip badge: active assignments visible to this student (cheap count;
    // the hub page hydrates the full classwork data separately).
    $classworkCount = (!$immersive && auth()->check()) ? (int) auth()->user()->activeAssignments()->count() : 0;

    // In-app inbox: one unread count shared by the header bell, the profile
    // dropdown entry and the mobile HUB chip so the student never misses a
    // teacher notice. Skipped on immersive screens (no chrome to badge).
    $unreadNotifications = 0;
    if (!$immersive && auth()->check()) {
        $unreadNotifications = (int) auth()->user()->notifications()->unread()->count();
    }
@endphp

    @unless ($immersive)
    <!-- TOP HEADER / NAVIGATION -->
    <header class="sticky top-0 z-40 bg-[#FAF6EE]/95 backdrop-blur-md border-b-4 border-[#2D231E]">
        <div class="max-w-7xl mx-auto px-4 h-16 md:h-20 flex items-center justify-between gap-4 md:grid md:grid-cols-[1fr_auto_1fr]">
            
            <!-- Left: Brand Logo -->
            <a href="/hub" class="group flex items-center gap-1 transition-transform active:scale-95 shrink-0 justify-self-start">
                <span class="font-pixel text-xl sm:text-2xl font-bold tracking-wider text-[#2D231E]">
                    ALICE
                </span>
            </a>

            <!-- Center: Desktop Navigation Links (Hidden on Mobile) -->
            <nav class="hidden md:flex items-center gap-3 md:gap-4 lg:gap-5 justify-self-center">
                <a 
                    href="{{ route('hub') . (($gamer['claimable_tasks'] ?? 0) > 0 ? '#tasks' : '') }}" 
                    class="relative font-pixel text-xs font-bold px-4 lg:px-5 py-2.5 rounded-2xl border-2 border-[#2D231E] {{ request()->routeIs('hub*', 'tasks*', 'badges') ? 'bg-amber-400' : 'bg-white hover:bg-amber-100' }} text-[#2D231E] shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] transition-all uppercase"
                >
                    HUB
                    <span data-hub-claimable-badge class="{{ ($gamer['claimable_tasks'] ?? 0) > 0 ? '' : 'hidden' }} absolute -top-2 -right-2 min-w-[22px] h-[22px] px-1.5 rounded-full bg-rose-500 border-2 border-[#2D231E] text-white font-pixel text-[9px] font-bold flex items-center justify-center shadow-[2px_2px_0px_0px_rgba(45,35,30,0.9)]">
                        {{ $gamer['claimable_tasks'] }}
                    </span>
                    @if (($gamer['claimable_tasks'] ?? 0) <= 0 && ($gamer['in_progress_tasks'] ?? 0) > 0)
                        <span class="absolute -top-2 -right-2 w-[14px] h-[14px] rounded-full bg-amber-400 border-2 border-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,0.9)]" title="A daily task is in progress" aria-label="A daily task is in progress"></span>
                    @endif
                </a>

                <a
                    href="{{ route('student.classwork') }}"
                    class="relative font-pixel text-xs font-bold px-4 lg:px-5 py-2.5 rounded-2xl border-2 border-[#2D231E] {{ request()->routeIs('student.classwork*') ? 'bg-amber-400' : 'bg-white hover:bg-amber-100' }} text-[#2D231E] shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] transition-all uppercase"
                >
                    CLASSWORK
                    @if ($classworkCount > 0)
                        <span data-nav-classwork-badge class="absolute -top-2 -right-2 min-w-[22px] h-[22px] px-1.5 rounded-full bg-rose-500 border-2 border-[#2D231E] text-white font-pixel text-[9px] font-bold flex items-center justify-center shadow-[2px_2px_0px_0px_rgba(45,35,30,0.9)]">
                            {{ $classworkCount }}
                        </span>
                    @endif
                </a>

                <a
                    href="{{ route('library') }}" 
                    class="font-pixel text-xs font-bold px-4 lg:px-5 py-2.5 rounded-2xl border-2 border-[#2D231E] {{ request()->routeIs('library*', 'reader*', 'quiz*', 'story.preview', 'collection') ? 'bg-amber-400' : 'bg-white hover:bg-amber-100' }} text-[#2D231E] shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] transition-all uppercase"
                >
                    LIBRARY
                </a>

                <a
                    href="{{ route('study') }}"
                    class="font-pixel text-xs font-bold px-4 lg:px-5 py-2.5 rounded-2xl border-2 border-[#2D231E] {{ request()->routeIs('study*') ? 'bg-amber-400' : 'bg-white hover:bg-amber-100' }} text-[#2D231E] shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] transition-all uppercase"
                >
                    STUDY
                </a>

                <a 
                    href="{{ route('mini-game') }}" 
                    class="font-pixel text-xs font-bold px-4 lg:px-5 py-2.5 rounded-2xl border-2 border-[#2D231E] {{ request()->routeIs('mini-game', 'game*') ? 'bg-amber-400' : 'bg-white hover:bg-amber-100' }} text-[#2D231E] shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] transition-all uppercase"
                >
                    MINI-GAMES
                </a>

            </nav>

            <!-- Right: Help + Profile -->
            <div class="flex items-center gap-3 shrink-0 justify-self-end">

                <!-- Help Button Trigger (?) -->
                <button 
                    type="button"
                    data-modal-open="help-modal"
                    class="w-9 h-9 sm:w-10 sm:h-10 font-pixel text-xs sm:text-sm bg-white border-2 border-[#2D231E] text-[#2D231E] rounded-full font-bold flex items-center justify-center shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] transition-all cursor-pointer {{ auth()->user()?->created_at?->gt(now()->subDays(7)) ? 'alice-nav-help-pulse' : '' }}"
                    title="How ALICE Works"
                >
                    ?
                </button>

                <!-- Inbox bell. A real <a href="/inbox"> first: with JS off it
                     simply navigates. bell-panel.js prevents the default and
                     opens the preview instead. -->
                <a
                    href="{{ route('inbox') }}"
                    data-bell-toggle
                    data-url="{{ route('inbox.preview') }}"
                    data-dropdown-toggle="bell-dropdown"
                    class="relative w-9 h-9 sm:w-10 sm:h-10 bg-white border-2 border-[#2D231E] text-[#2D231E] rounded-full flex items-center justify-center shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] transition-all"
                    title="Inbox"
                    aria-label="Inbox"
                >
                    <span class="w-4 h-4 shrink-0 text-[#2D231E] [&>svg]:h-full [&>svg]:w-full">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'bell') !!}</span>
                    <span data-student-unread class="{{ $unreadNotifications > 0 ? '' : 'hidden' }} absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-rose-500 border-2 border-[#2D231E] text-white font-pixel text-[7px] font-bold flex items-center justify-center shadow-[1px_1px_0px_0px_rgba(45,35,30,0.9)]">
                        {{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}
                    </span>
                </a>

                <!-- Bell preview panel. Header is static; [data-bell-body] is
                     filled by components/bell-panel.js on first open so the
                     row query never runs on page load. -->
                <div
                    id="bell-dropdown"
                    data-dropdown
                    class="hidden right-0 w-[22rem] max-w-[calc(100vw-2rem)] bg-white border-2 border-[#2D231E] rounded-2xl shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] z-50"
                    aria-label="Inbox"
                >
                    <div class="flex items-center justify-between gap-2 px-3 py-2.5 border-b-2 border-[#E6DCCF]">
                        <h2 class="text-sm font-extrabold text-[#2D231E]">Inbox</h2>
                        <a href="{{ route('inbox') }}" class="text-xs font-bold text-sky-700 hover:text-sky-800 transition-colors">See all</a>
                    </div>
                    <div data-bell-body class="p-2 max-h-[60vh] overflow-y-auto">
                        <div class="space-y-3" aria-hidden="true">
                            <span class="alice-skeleton block w-full h-14 rounded-xl"></span>
                            <span class="alice-skeleton block w-full h-14 rounded-xl"></span>
                            <span class="alice-skeleton block w-full h-14 rounded-xl"></span>
                        </div>
                    </div>
                </div>

                <!-- User Profile Dropdown Menu -->
                <div class="relative">
                    <button 
                        type="button"
                        id="profile-button"
                        data-dropdown-toggle="profile-dropdown"
                        class="flex items-center gap-2 px-3 py-1.5 bg-white border-2 border-[#2D231E] rounded-2xl shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] transition-all cursor-pointer"
                    >
                        <x-avatar :user="auth()->user()" alt="Your avatar" :size="32" />
                        <span class="text-xs font-bold text-[#2D231E] hidden sm:inline">
                            {{ auth()->user()->nickname ?? strtok(auth()->user()->email, '@') }}
                        </span>
                    </button>

                    <!-- Profile Dropdown Content -->
                    <div 
                        id="profile-dropdown" 
                        data-dropdown
                        class="hidden absolute right-0 mt-2 w-64 bg-white border-2 border-[#2D231E] rounded-2xl p-2 shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] z-50 space-y-1"
                        role="menu"
                        aria-label="Profile menu"
                    >
                        @if ($gamer)
                        <!-- MY PROGRESS -->
                        <section aria-labelledby="dropdown-progress-heading">
                            <h3 id="dropdown-progress-heading" class="font-pixel text-[8px] font-bold uppercase tracking-wider text-[#2D231E] px-2 pt-1 pb-0.5">
                                My Progress
                            </h3>
                                <div class="space-y-1">
                                    <!-- Title (violet) — opens the TITLE LADDER roadmap -->
                                    <button
                                        type="button"
                                        data-rank-roadmap-open
                                        role="menuitem"
                                        class="flex items-center gap-1.5 w-full px-2 py-1.5 rounded-xl bg-violet-50 hover:bg-violet-100 border border-[#2D231E] text-violet-700 transition-colors cursor-pointer"
                                        title="{{ $gamer['rank']['title'] }}"
                                    >
                                        <span class="w-3.5 h-3.5 shrink-0 text-violet-700 [&>svg]:h-full [&>svg]:w-full">{!! \App\Support\LibraryPresentation::iconGlyph('ui', $gamer['tier_icon']) !!}</span>
                                        <span class="min-w-0 font-pixel text-[8px] font-bold uppercase truncate">{{ $gamer['rank']['title'] }}</span>
                                    </button>
                                    <!-- Streak (rose) — the streak tab (battle, calendar, freezes) -->
                                    <a
                                        href="{{ route('activity', ['tab' => 'streak']) }}"
                                        role="menuitem"
                                        class="flex items-center gap-1.5 w-full px-2 py-1.5 rounded-xl bg-rose-50 hover:bg-rose-100 border border-[#2D231E] text-rose-600 transition-colors"
                                        title="{{ $gamer['streak'] }} day streak"
                                    >
                                        <span class="w-3.5 h-3.5 shrink-0 text-rose-600 [&>svg]:h-full [&>svg]:w-full">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'flame') !!}</span>
                                        <span class="min-w-0 font-pixel text-[8px] font-bold truncate">{{ $gamer['streak'] }} days</span>
                                    </a>
                                    <!-- Keys (amber) — the keys ledger tab -->
                                    <a
                                        href="{{ route('activity', ['tab' => 'keys']) }}"
                                        role="menuitem"
                                        class="flex items-center gap-1.5 w-full px-2 py-1.5 rounded-xl bg-amber-50 hover:bg-amber-100 border border-[#2D231E] text-amber-600 transition-colors"
                                        title="{{ number_format($gamer['keys']) }} keys"
                                    >
                                        <span class="w-3.5 h-3.5 shrink-0 text-amber-600 [&>svg]:h-full [&>svg]:w-full">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'key') !!}</span>
                                        <span class="min-w-0 font-pixel text-[8px] font-bold truncate" data-keys-total>{{ number_format($gamer['keys']) }}</span>
                                    </a>
                                </div>
                        </section>
                        <hr role="separator" class="border-dashed border-[#E6DCCF] my-1">
                        @endif

                        <!-- APP -->
                        <section aria-label="App">
                            <h3 class="font-pixel text-[8px] font-bold uppercase tracking-wider text-[#2D231E] px-2 pt-1 pb-0.5">
                                App
                            </h3>
                            <button
                                type="button"
                                data-theme-toggle
                                role="switch"
                                aria-checked="false"
                                class="w-full text-left px-3 py-2 text-xs font-bold text-[#2D231E] hover:bg-[#FAF6EE] rounded-xl transition-colors cursor-pointer flex items-center justify-between gap-2"
                            >
                                <span class="flex items-center gap-2 min-w-0">
                                    <span class="w-3.5 h-3.5 shrink-0 text-[#2D231E] [&>svg]:h-full [&>svg]:w-full">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                                        </svg>
                                    </span>
                                    <span>Dark mode</span>
                                </span>
                                <span class="theme-switch shrink-0" aria-hidden="true">
                                    <span class="theme-switch-thumb">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path data-switch-icon="sun" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                                            <path data-switch-icon="moon" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                                        </svg>
                                    </span>
                                </span>
                            </button>
                            @if ($gamer)
                            <button
                                type="button"
                                data-modal-open="rewards-modal"
                                role="menuitem"
                                class="w-full text-left px-3 py-2 text-xs font-bold text-violet-700 hover:bg-violet-50 rounded-xl transition-colors cursor-pointer flex items-center gap-2"
                            >
                                <span class="w-3.5 h-3.5 shrink-0 text-violet-700 [&>svg]:h-full [&>svg]:w-full">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'laurel-crown') !!}</span>
                                <span>My Stats</span>
                            </button>
                            <a
                                href="{{ route('shop') }}"
                                role="menuitem"
                                class="flex items-center gap-2 px-3 py-2 text-xs font-bold text-amber-600 hover:bg-amber-50 rounded-xl transition-colors"
                            >
                                <span class="w-3.5 h-3.5 shrink-0 text-amber-600" aria-hidden="true">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="h-full w-full">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.3 4.6a1 1 0 00.9 1.4H19M9 21a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z" />
                                    </svg>
                                </span>
                                <span>Shop</span>
                            </a>
                            @endif
                            <a
                                href="{{ route('collection') }}"
                                role="menuitem"
                                class="flex items-center gap-2 px-3 py-2 text-xs font-bold text-[#2D231E] hover:bg-[#FAF6EE] rounded-xl transition-colors"
                            >
                                <span class="w-3.5 h-3.5 shrink-0 text-[#2D231E] [&>svg]:h-full [&>svg]:w-full">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'bookshelf') !!}</span>
                                <span>My Collection</span>
                            </a>
                            <a
                                href="{{ route('badges') }}"
                                role="menuitem"
                                class="flex items-center gap-2 px-3 py-2 text-xs font-bold text-[#2D231E] hover:bg-[#FAF6EE] rounded-xl transition-colors"
                            >
                                <span class="w-3.5 h-3.5 shrink-0 text-amber-600 [&>svg]:h-full [&>svg]:w-full">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'trophy') !!}</span>
                                <span>My Badges</span>
                            </a>
                            <a
                                href="{{ route('activity') }}"
                                role="menuitem"
                                class="flex items-center gap-2 px-3 py-2 text-xs font-bold text-[#2D231E] hover:bg-[#FAF6EE] rounded-xl transition-colors"
                            >
                                <span class="w-3.5 h-3.5 shrink-0 text-[#2D231E] [&>svg]:h-full [&>svg]:w-full">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'compass') !!}</span>
                                <span>Activity</span>
                            </a>
                            <a
                                href="{{ route('inbox') }}"
                                role="menuitem"
                                class="relative flex items-center gap-2 px-3 py-2 text-xs font-bold text-[#2D231E] hover:bg-[#FAF6EE] rounded-xl transition-colors"
                            >
                                <span class="w-3.5 h-3.5 shrink-0 text-[#2D231E] [&>svg]:h-full [&>svg]:w-full">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'bell') !!}</span>
                                <span>Inbox</span>
                                <span data-student-unread class="{{ $unreadNotifications > 0 ? '' : 'hidden' }} ml-auto min-w-[20px] h-[20px] px-1 rounded-full bg-rose-500 border border-[#2D231E] text-white font-pixel text-[8px] font-bold flex items-center justify-center">
                                    {{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}
                                </span>
                            </a>
                            <a
                                href="/settings"
                                role="menuitem"
                                class="flex items-center gap-2 px-3 py-2 text-xs font-bold text-[#2D231E] hover:bg-[#FAF6EE] rounded-xl transition-colors"
                            >
                                <span class="w-3.5 h-3.5 shrink-0 text-[#2D231E] [&>svg]:h-full [&>svg]:w-full">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'cog') !!}</span>
                                <span>Settings</span>
                            </a>
                        </section>
                        <hr role="separator" class="border-dashed border-[#E6DCCF] my-1">

                        <!-- SESSION -->
                        <section aria-label="Session">
                            <h3 class="font-pixel text-[8px] font-bold uppercase tracking-wider text-[#2D231E] px-2 pt-1 pb-0.5">
                                Session
                            </h3>
                            <form method="POST" action="{{ route('logout') }}" data-submit-guard>
                                @csrf
                                <button 
                                    type="submit" 
                                    role="menuitem"
                                    class="w-full text-left px-3 py-2 text-xs font-bold text-rose-600 hover:bg-rose-50 rounded-xl transition-colors cursor-pointer"
                                >
                                    <span data-submit-idle>Logout</span>
                                    <span data-submit-active class="hidden"><x-spinner label="Signing out..." /></span>
                                </button>
                            </form>
                        </section>
                    </div>
                </div>

            </div>

        </div>
    </header>
    @endunless

    <!-- MAIN VIEW AREA -->
    <main class="{{ $immersive ? 'flex-grow' : 'flex-grow max-w-7xl mx-auto w-full px-4 py-6 sm:px-6 sm:py-6 lg:py-8 pb-24 md:pb-8' }}">
        @yield('content')
    </main>

    @unless ($immersive)
    <!-- MOBILE FIXED BOTTOM TAB BAR WITH SVG ICONS (Hidden on Desktop) -->
    <nav class="md:hidden fixed bottom-0 left-0 right-0 z-40 bg-[#FAF6EE] border-t-4 border-[#2D231E] px-2 pt-2 pb-[calc(0.5rem+env(safe-area-inset-bottom))] shadow-[0px_-4px_0px_0px_rgba(45,35,30,0.15)]">
        <div class="grid grid-cols-5 gap-1.5 max-w-md mx-auto">
            
            <a href="{{ route('hub') . (($gamer['claimable_tasks'] ?? 0) > 0 ? '#tasks' : '') }}" class="relative flex flex-col items-center justify-center min-h-[44px] py-1 rounded-xl {{ request()->routeIs('hub*', 'tasks*', 'badges', 'collection') ? 'bg-amber-400' : 'bg-white' }} border-2 border-[#2D231E] text-[#2D231E] font-pixel text-[9px] font-bold shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] uppercase">
                <svg class="w-4 h-4 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                <span>HUB</span>
                <span data-hub-claimable-badge class="{{ ($gamer['claimable_tasks'] ?? 0) > 0 ? '' : 'hidden' }} absolute -top-1 -right-1 min-w-[20px] h-[20px] px-1 rounded-full bg-rose-500 border-2 border-[#2D231E] text-white font-pixel text-[8px] font-bold flex items-center justify-center shadow-[2px_2px_0px_0px_rgba(45,35,30,0.9)]">
                    {{ $gamer['claimable_tasks'] }}
                </span>
                @if (($gamer['claimable_tasks'] ?? 0) <= 0 && ($gamer['in_progress_tasks'] ?? 0) > 0)
                    <span class="absolute -top-1 -right-1 w-[12px] h-[12px] rounded-full bg-amber-400 border-2 border-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,0.9)]" title="A daily task is in progress" aria-label="A daily task is in progress"></span>
                @else
                    <span data-student-unread class="{{ $unreadNotifications > 0 ? '' : 'hidden' }} absolute -top-1 -right-1 min-w-[20px] h-[20px] px-1 rounded-full bg-rose-500 border-2 border-[#2D231E] text-white font-pixel text-[8px] font-bold flex items-center justify-center shadow-[2px_2px_0px_0px_rgba(45,35,30,0.9)]" title="{{ $unreadNotifications }} unread in your inbox" aria-label="{{ $unreadNotifications }} unread in your inbox">
                        {{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}
                    </span>
                @endif
            </a>

            <a href="{{ route('student.classwork') }}" class="relative flex flex-col items-center justify-center min-h-[44px] py-1 rounded-xl {{ request()->routeIs('student.classwork*') ? 'bg-amber-400' : 'bg-white' }} border-2 border-[#2D231E] text-[#2D231E] font-pixel text-[9px] font-bold shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] uppercase">
                <svg class="w-4 h-4 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                </svg>
                <span>Work</span>
                @if ($classworkCount > 0)
                    <span data-nav-classwork-badge class="absolute -top-1 -right-1 min-w-[20px] h-[20px] px-1 rounded-full bg-rose-500 border-2 border-[#2D231E] text-white font-pixel text-[8px] font-bold flex items-center justify-center shadow-[2px_2px_0px_0px_rgba(45,35,30,0.9)]">
                        {{ $classworkCount > 9 ? '9+' : $classworkCount }}
                    </span>
                @endif
            </a>

            <a href="{{ route('library') }}" class="flex flex-col items-center justify-center min-h-[44px] py-1 rounded-xl {{ request()->routeIs('library*', 'reader*', 'quiz*', 'story.preview') ? 'bg-amber-400' : 'bg-white' }} border-2 border-[#2D231E] text-[#2D231E] font-pixel text-[9px] font-bold shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] uppercase">
                <svg class="w-4 h-4 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
                <span>Library</span>
            </a>

            <a href="{{ route('study') }}" class="flex flex-col items-center justify-center min-h-[44px] py-1 rounded-xl {{ request()->routeIs('study*') ? 'bg-amber-400' : 'bg-white' }} border-2 border-[#2D231E] text-[#2D231E] font-pixel text-[9px] font-bold shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] uppercase">
                <svg class="w-4 h-4 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222" />
                </svg>
                <span>Study</span>
            </a>

            <a href="{{ route('mini-game') }}" class="flex flex-col items-center justify-center min-h-[44px] py-1 rounded-xl {{ request()->routeIs('mini-game', 'game*') ? 'bg-amber-400' : 'bg-white' }} border-2 border-[#2D231E] text-[#2D231E] font-pixel text-[9px] font-bold shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] uppercase">
                <svg class="w-4 h-4 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Games</span>
            </a>

        </div>
    </nav>
    @endunless

    @unless ($immersive)
    <!-- FOOTER -->
    <footer class="bg-[#F3ECE0] border-t-2 border-[#E6DCCF] py-8 mt-auto pb-28 md:pb-8">
        <div class="max-w-7xl mx-auto px-4 flex flex-col md:flex-row items-center justify-between gap-4">
            
            <!-- Brand Info -->
            <div class="text-center md:text-left space-y-1">
                <p class="font-pixel text-sm text-[#2D231E]">ALICE</p>
                <p class="text-xs font-semibold text-[#2D231E]">Gamified English Learning Platform</p>
            </div>

            <!-- Links -->
            <div class="flex items-center gap-6 text-xs font-bold text-[#2D231E]">
                <a href="{{ route('about') }}" class="hover:text-[#2D231E] hover:underline transition-colors">About Us</a>
                <a href="{{ route('contact') }}" class="hover:text-[#2D231E] hover:underline transition-colors">Contact</a>
                <a href="{{ route('terms') }}" class="hover:text-[#2D231E] hover:underline transition-colors">Terms and Conditions</a>
            </div>

        </div>
    </footer>
    @endunless

    @if ($gamer)
    <!-- MY STATS PANEL (S4) — full gamification breakdown -->
    <x-modal id="rewards-modal" title="MY STATS" :closeable="true" pixel>
        <div class="space-y-4 text-xs font-bold text-[#2D231E]">
            <!-- Current reading title + tier icon (tap to open the rank roadmap) -->
            <button
                type="button"
                data-rank-roadmap-open
                aria-haspopup="dialog"
                aria-controls="rank-roadmap-modal"
                class="w-full text-left flex items-center gap-3 bg-violet-500 border-2 border-[#2D231E] rounded-2xl p-3 text-white shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] hover:bg-violet-600 active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all cursor-pointer"
            >
                <span class="w-10 h-10 bg-white border-2 border-[#2D231E] rounded-xl flex items-center justify-center text-violet-700 [&>svg]:h-6 [&>svg]:w-6 shrink-0">
                    {!! \App\Support\LibraryPresentation::iconGlyph('ui', $gamer['tier_icon']) !!}
                </span>
                <span class="flex-1 min-w-0">
                    <span class="block font-pixel text-[10px] uppercase tracking-wider">Your title</span>
                    <span class="block font-pixel text-sm uppercase truncate" title="{{ $gamer['rank']['title'] }}">{{ $gamer['rank']['title'] }}</span>
                </span>
                <span class="font-pixel text-xl font-bold shrink-0">{{ number_format($gamer['rank']['points']) }}</span>
            </button>

            <!-- Progress to next title -->
            <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-3 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="font-pixel text-[10px] uppercase">Progress to next</span>
                    @if ($gamer['rank']['next_title'])
                        <span class="text-violet-700 font-bold">{{ $gamer['rank']['next_title'] }}</span>
                    @else
                        <span class="text-violet-700 font-bold">MAX</span>
                    @endif
                </div>
                <div class="h-4 w-full bg-[#E6DCCF] border-2 border-[#2D231E] rounded-full overflow-hidden">
                    <div class="h-full bg-violet-500 rounded-full transition-all" style="width: {{ (int) round($gamer['rank']['progress'] * 100) }}%"></div>
                </div>
                @if ($gamer['rank']['next_title'])
                    <p class="text-[10px] text-[#2D231E] font-semibold">{{ $gamer['rank']['points_to_next'] }} more points to {{ $gamer['rank']['next_title'] }}</p>
                @else
                    <p class="text-[10px] text-[#2D231E] font-semibold">You reached the top of the ladder!</p>
                @endif
            </div>

            <!-- Keys -->
            <div class="flex items-center gap-3 bg-white border-2 border-[#2D231E] rounded-2xl p-3 shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]">
                <span class="w-8 h-8 bg-amber-100 border-2 border-[#2D231E] rounded-xl flex items-center justify-center text-amber-600 [&>svg]:h-5 [&>svg]:w-5">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'key') !!}</span>
                <span class="font-pixel text-[10px] uppercase flex-1">Keys</span>
                <span class="font-pixel text-sm" data-keys-total>{{ number_format($gamer['keys']) }}</span>
            </div>

            <!-- Streak (current + longest) -->
            <div class="flex items-center gap-3 bg-white border-2 border-[#2D231E] rounded-2xl p-3 shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]">
                <span class="w-8 h-8 bg-rose-100 border-2 border-[#2D231E] rounded-xl flex items-center justify-center text-rose-600 [&>svg]:h-5 [&>svg]:w-5">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'flame') !!}</span>
                <span class="font-pixel text-[10px] uppercase flex-1">Streak</span>
                <span class="font-pixel text-sm">
                    {{ $gamer['streak'] }}
                    @if ($gamer['longest_streak'] > $gamer['streak'])
                        <span class="text-[#2D231E] font-semibold">/ best {{ $gamer['longest_streak'] }}</span>
                    @endif
                </span>
            </div>

            <!-- Freezes held -->
            <div class="bg-white border-2 border-[#2D231E] rounded-2xl p-3 space-y-1 shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]">
                <div class="flex items-center gap-3">
                    <span class="w-8 h-8 bg-sky-100 border-2 border-[#2D231E] rounded-xl flex items-center justify-center text-sky-600 [&>svg]:h-5 [&>svg]:w-5">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'snowflake') !!}</span>
                    <span class="font-pixel text-[10px] uppercase flex-1">Streak Restores</span>
                    <span class="font-pixel text-sm">{{ $gamer['freezes'] }}</span>
                </div>
                <p class="text-[10px] text-[#2D231E] font-semibold leading-relaxed pl-11">
                    A streak grows when you read at least once each day. A Streak Restore brings your streak back after a missed day, so it does not reset.
                </p>
            </div>

            @if ($gamer['freezes'] < $gamer['freeze_cap'])
            <!-- Buy a streak freeze (S6 sink) -->
            <form method="POST" action="{{ route('rewards.freeze.buy') }}" class="flex items-center gap-3 bg-sky-50 border-2 border-[#2D231E] rounded-2xl p-3">
                @csrf
                <span class="w-8 h-8 bg-sky-100 border-2 border-[#2D231E] rounded-xl flex items-center justify-center text-sky-600 [&>svg]:h-5 [&>svg]:w-5">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'key') !!}</span>
                <div class="flex-1 min-w-0">
                    <p class="font-pixel text-[10px] uppercase leading-tight">Buy a Streak Restore</p>
                    <p class="text-[10px] text-[#2D231E] font-semibold">{{ number_format($gamer['freeze_cost']) }} keys each, keep max {{ $gamer['freeze_cap'] }}</p>
                </div>
                <button type="submit" class="min-w-[44px] min-h-[44px] px-3 bg-sky-500 text-white font-pixel text-[11px] font-bold rounded-xl border-2 border-[#2D231E] shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all uppercase">
                    Buy
                </button>
            </form>
            @endif

            <p class="text-[10px] text-[#2D231E] font-semibold">Read, take quizzes and play word games to earn points. Tap your title to see the full rank roadmap.</p>
        </div>
    </x-modal>

    @include('partials.rank-roadmap', ['points' => $gamer['rank']['points']])
    @endif

    <!-- HOW ALICE WORKS (?) - paginated tour of today's student app -->
    <x-modal id="help-modal" title="HOW ALICE WORKS" pixel>
        <div data-help-pager class="space-y-4">

            <!-- Pager head: dots -->
            <div class="flex items-center gap-1.5" role="group" aria-label="Help pages">
                <button type="button" class="help-pager-dot is-active" data-help-dot="0" aria-label="Page 1: Welcome" aria-current="true"></button>
                <button type="button" class="help-pager-dot" data-help-dot="1" aria-label="Page 2: Hub"></button>
                <button type="button" class="help-pager-dot" data-help-dot="2" aria-label="Page 3: Library"></button>
                <button type="button" class="help-pager-dot" data-help-dot="3" aria-label="Page 4: Study"></button>
                <button type="button" class="help-pager-dot" data-help-dot="4" aria-label="Page 5: Mini Games"></button>
                <button type="button" class="help-pager-dot" data-help-dot="5" aria-label="Page 6: Classwork"></button>
            </div>

            <!-- Page 1 · Welcome -->
            <div data-help-page data-label="Welcome" class="space-y-2.5">
                <button
                    type="button"
                    data-tour-start
                    class="w-full min-h-[46px] font-pixel text-[10px] sm:text-xs px-6 py-3 rounded-2xl bg-emerald-400 border-2 border-[#2D231E] text-[#2D231E] font-bold flex items-center justify-center gap-2 shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all cursor-pointer uppercase"
                >
                    <span>GET STARTED</span>
                </button>
                <p class="text-[10px] text-[#2D231E] font-semibold text-center">Alice will show you around. Ready when you are.</p>
            </div>

            <!-- Page 2 · Hub -->
            <div data-help-page data-label="1. Hub" class="hidden space-y-2.5 text-xs font-medium text-[#2D231E] leading-relaxed">
                <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-3 space-y-1">
                    <p class="font-pixel text-[10px] font-bold text-amber-600 uppercase">HUB</p>
                    <p>Your main dashboard. Check your stats and progress, look at your tasks, and claim your rewards.</p>
                </div>
            </div>

            <!-- Page 3 · Library -->
            <div data-help-page data-label="2. Library" class="hidden space-y-2.5 text-xs font-medium text-[#2D231E] leading-relaxed">
                <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-3 space-y-1">
                    <p class="font-pixel text-[10px] font-bold text-amber-600 uppercase">LIBRARY</p>
                    <p>This is where you read and take your quizzes. The library has 100+ stories you can choose from.</p>
                </div>
            </div>

            <!-- Page 4 · Study -->
            <div data-help-page data-label="3. Study" class="hidden space-y-2.5 text-xs font-medium text-[#2D231E] leading-relaxed">
                <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-3 space-y-1">
                    <p class="font-pixel text-[10px] font-bold text-amber-600 uppercase">STUDY</p>
                    <p>This is where you test your vocabulary. Search up words in our dictionary and study them with flashcards.</p>
                </div>
            </div>

            <!-- Page 5 · Mini Games -->
            <div data-help-page data-label="4. Mini Games" class="hidden space-y-2.5 text-xs font-medium text-[#2D231E] leading-relaxed">
                <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-3 space-y-1">
                    <p class="font-pixel text-[10px] font-bold text-amber-600 uppercase">MINI GAMES</p>
                    <p>A fun little hustle. This is the arcade section where you set high scores depending on what game you play. There's a weekly leaderboard where you can see other students' scores.</p>
                </div>
            </div>

            <!-- Page 6 · Classwork -->
            <div data-help-page data-label="5. Classwork" class="hidden space-y-2.5 text-xs font-medium text-[#2D231E] leading-relaxed">
                <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-3 space-y-1">
                    <p class="font-pixel text-[10px] font-bold text-amber-600 uppercase">CLASSWORK</p>
                    <p>This section is for doing the assignments your teacher gives you. You first need to be in a class group to receive them.</p>
                </div>
            </div>

            <!-- Prev / Next nav -->
            <div class="flex items-center justify-between gap-3">
                <button
                    type="button"
                    data-help-prev
                    class="hidden min-h-[40px] px-4 font-pixel text-[9px] font-bold uppercase bg-white border-2 border-[#2D231E] text-[#2D231E] rounded-xl shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all cursor-pointer"
                >
                    Previous
                </button>
                <button
                    type="button"
                    data-help-next
                    class="ml-auto min-h-[40px] px-4 font-pixel text-[9px] font-bold uppercase bg-white border-2 border-[#2D231E] text-[#2D231E] rounded-xl shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all cursor-pointer"
                >
                    Next
                </button>
            </div>

            <!-- Final action (last page only) -->
            <button
                type="button"
                data-help-done
                data-modal-close
                class="hidden w-full min-h-[44px] font-pixel text-xs px-6 py-3 rounded-2xl bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] font-bold flex items-center justify-center shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all cursor-pointer uppercase"
            >
                <span>Done</span>
            </button>
        </div>
    </x-modal>

    @include('partials.streak-restore-prompt')
    @include('partials.toast')
    @include('partials.game-banner')
    @include('partials.tour-dialogue')

</body>
</html>