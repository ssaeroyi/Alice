<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ALICE Admin - @yield('title', 'Administration')</title>
    <meta name="description" content="{{ $metaDescription ?? 'ALICE Administration - platform control and content management.' }}">

    <!-- Self-hosted web fonts -->
    <!-- Tailwind CSS -->
    @vite(['resources/css/app.css', 'resources/css/web-fonts.css', 'resources/js/app.js'])

    <style>
        body {
            font-family: 'Quicksand', sans-serif;
        }
        /* Brand headers are the one pixel exception on staff pages (eyebrows +
           ALICE wordmark). Plain .font-pixel stays undefined so shared
           components (modals, avatars) render in the normal face. */
        .font-pixel-brand {
            font-family: 'Press Start 2P', monospace;
        }
    </style>
</head>
<body class="bg-[#FAF6EE] text-[#2D231E] min-h-screen antialiased selection:bg-amber-300" data-area="admin" data-page="@yield('page', '')">
@php
    $viewAsActor = \App\Support\ViewAs::actor();
@endphp
@if ($viewAsActor)
    <div data-viewing-as class="sticky top-0 z-50 bg-[#2D231E] border-b-2 border-amber-400">
        <div class="px-4 py-1.5 flex items-center justify-between gap-3">
            <p class="text-[12px] font-semibold text-amber-300 truncate">
                VIEWING AS {{ strtoupper(auth()->user()->nickname ?? 'USER') }} ({{ auth()->user()->role->value }})
            </p>
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
        $adminUnread = (int) auth()->user()->notifications()->unread()->count();

        $navGroups = [
            ['items' => [
                ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'home'],
            ]],
            ['label' => 'Manage', 'items' => [
                ['label' => 'Accounts', 'route' => 'admin.accounts.index', 'icon' => 'users'],
                ['label' => 'Inbox', 'route' => 'admin.inbox', 'badge' => $adminUnread, 'icon' => 'mail'],
                ['label' => 'Student reports', 'route' => 'admin.reports.index', 'badge' => $adminPendingReports ?? 0, 'icon' => 'chart-bar'],
            ]],
            ['label' => 'System', 'items' => [
                ['label' => 'Status', 'route' => 'admin.status', 'icon' => 'heart', 'dot' => \App\Support\Monitor::errorLogDot()],
                ['label' => 'Control', 'route' => 'admin.control', 'icon' => 'sliders'],
                ['label' => 'Logs', 'route' => 'admin.logs.index', 'icon' => 'magnifier'],
            ]],
        ];

        $active = fn (array $item) => match ($item['route']) {
            'admin.logs.index' => request()->routeIs('admin.logs.*'),
            'admin.reports.index' => request()->routeIs('admin.reports.*'),
            'admin.accounts.index' => request()->routeIs('admin.accounts.*'),
            'admin.inbox' => request()->routeIs('admin.inbox*'),
            default => request()->routeIs($item['route']) || request()->routeIs($item['route'].'.*'),
        };
    @endphp

    <!-- SIDEBAR (off-canvas on mobile, fixed on lg+) -->
    <aside
        id="admin-sidebar"
        data-sidebar
        class="fixed inset-y-0 left-0 z-50 w-64 bg-white border-r border-[#E6DCCF] -translate-x-full lg:translate-x-0 transition-transform duration-200 flex flex-col"
    >
        <!-- Brand -->
        <div class="px-6 pt-6 pb-5 border-b border-[#E6DCCF]">
            <p class="font-pixel-brand text-[9px] text-amber-600 uppercase tracking-wider">Administration</p>
            <p class="font-pixel-brand text-xl tracking-widest text-[#2D231E] mt-1">ALICE</p>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
            @foreach ($navGroups as $group)
                @if ($group['label'] ?? null)
                    <p class="px-3 pt-4 pb-1 text-[10px] font-extrabold uppercase tracking-wider text-[#8C7B70]">{{ $group['label'] }}</p>
                @endif
                @foreach ($group['items'] as $item)
                    <a
                        href="{{ route($item['route']) }}"
                        aria-current="{{ $active($item) ? 'page' : 'false' }}"
                        class="flex items-center justify-between gap-2 px-3 py-2.5 rounded-lg text-sm font-bold transition-colors {{ $active($item) ? 'bg-amber-100 text-[#2D231E]' : 'text-[#2D231E] hover:bg-[#FAF6EE]' }}"
                    >
                        <span class="flex items-center gap-2.5 min-w-0">
                            <x-dynamic-component :component="'icons.core.'.$item['icon']" class="w-4 h-4 shrink-0 {{ $active($item) ? 'text-amber-600' : 'text-[#2D231E]' }}" />
                            <span class="truncate">{{ $item['label'] }}</span>
                        </span>
                        @isset($item['badge'])
                            @if ($item['badge'] > 0)
                                <span {{ ($item['route'] ?? '') === 'admin.inbox' ? 'data-unread-badge' : '' }} class="inline-flex items-center px-1.5 py-0.5 rounded-full bg-amber-200 text-[10px] font-extrabold text-[#2D231E]">{{ $item['badge'] > 99 ? '99+' : $item['badge'] }}</span>
                            @endif
                        @endisset
                        @if (! ($item['badge'] ?? 0) && ($item['dot'] ?? false))
                            <span class="inline-block w-2 h-2 shrink-0 rounded-full bg-rose-500" title="Recent errors in the log"></span>
                        @endif
                    </a>
                @endforeach
            @endforeach
        </nav>
    </aside>

    <!-- Mobile backdrop -->
    <div
        id="admin-sidebar-backdrop"
        data-sidebar-backdrop
        class="hidden fixed inset-0 z-40 bg-[#2D231E]/40 lg:hidden"
    ></div>

    <!-- MAIN AREA -->
    <div class="lg:pl-64 min-h-screen flex flex-col">

        <!-- TOP BAR -->
        <script>
        (function () {
            var root = document.documentElement;

            var measure = function () {
                var banner = document.querySelector('[data-viewing-as]');
                var bannerH = banner ? banner.offsetHeight : 0;
                root.style.setProperty('--banner-h', bannerH + 'px');
                root.style.setProperty('--topbar-h', (bannerH + 56) + 'px');
            };

            measure();
            window.addEventListener('resize', measure);
        })();
        </script>

        <header class="sticky top-[var(--banner-h,0px)] z-30 bg-[#FAF6EE]/95 backdrop-blur-md border-b border-[#E6DCCF]">
            <div class="flex items-center justify-between gap-4 px-4 sm:px-6 h-14">

                <div class="flex items-center gap-3 min-w-0">
                    <!-- Back button (only on pages that yield a back target) -->
                    @hasSection('back')
                        <a
                            href="@yield('back')"
                            aria-label="Back"
                            class="w-10 h-10 shrink-0 bg-white border border-[#E6DCCF] rounded-lg flex items-center justify-center text-[#2D231E] hover:border-[#2D231E] transition-colors"
                        >
                            <x-icons.arrow-left class="w-5 h-5" />
                        </a>
                    @endif

                    <!-- Hamburger (mobile only) -->
                    <button
                        type="button"
                        data-sidebar-toggle
                        aria-label="Open navigation"
                        class="lg:hidden w-10 h-10 shrink-0 bg-white border border-[#E6DCCF] rounded-lg flex items-center justify-center text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>

                    <div class="min-w-0">
                        <p class="font-pixel-brand text-[9px] text-amber-600 uppercase tracking-wider">@yield('eyebrow', 'Administration')</p>
                        <h1 class="text-sm sm:text-base font-extrabold text-[#2D231E] truncate">@yield('title', 'Administration')</h1>
                    </div>
                </div>

                <!-- Inbox bell + actor dropdown -->
                <div class="flex items-center gap-1">
                <!-- Page freshness stamp. Only pages that @section('freshness')
                     show it, so the timestamp sits where it is always visible
                     instead of floating between panels. -->
                @hasSection('freshness')
                    <p
                        data-freshness-stamp
                        data-loaded-at="{{ now()->toIso8601String() }}"
                        class="hidden sm:flex items-center mr-1 px-2 py-1 rounded-lg bg-white border border-[#E6DCCF] text-[11px] font-bold text-[#2D231E] whitespace-nowrap"
                    >
                        @yield('freshness')
                        <span data-freshness-age class="font-normal"></span>
                    </p>
                @endif
                <!-- Inbox bell. A real <a href="/admin/inbox"> first: with JS off
                     it simply navigates. bell-panel.js prevents the default and
                     opens the preview instead. -->
                <a
                    href="{{ route('admin.inbox') }}"
                    data-admin-inbox-bell
                    data-bell-toggle
                    data-url="{{ route('admin.inbox.preview') }}"
                    data-badge-url="{{ route('admin.inbox.unread') }}"
                    data-dropdown-toggle="admin-bell-dropdown"
                    class="relative shrink-0 w-11 h-11 inline-flex items-center justify-center text-[#2D231E] hover:bg-[#2D231E]/5 rounded-lg transition-colors"
                    title="Inbox"
                    aria-label="Inbox, {{ $adminUnread }} unread"
                >
                    <span class="relative">
                        <svg class="w-[22px] h-[22px]" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75v-.7V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/>
                        </svg>
                        <span data-admin-inbox-badge data-unread-badge class="hidden absolute -top-1 -right-2.5 min-w-[18px] h-[18px] px-1 rounded-full bg-rose-500 border border-[#FAF6EE] text-white text-[10px] font-bold flex items-center justify-center">
                            0
                        </span>
                    </span>
                </a>

                <!-- Bell preview panel. Header is static; [data-bell-body] is
                     filled by components/bell-panel.js on first open so the
                     row query never runs on page load. -->
                <div
                    id="admin-bell-dropdown"
                    data-dropdown
                    class="hidden right-0 w-[22rem] max-w-[calc(100vw-2rem)] bg-white border border-[#E6DCCF] rounded-xl shadow-[0_8px_24px_rgba(45,35,30,0.18)] z-50"
                    aria-label="Inbox"
                >
                    <div class="flex items-center justify-between gap-2 px-3 py-2.5 border-b border-[#E6DCCF]">
                        <h2 class="text-sm font-extrabold text-[#2D231E]">Inbox</h2>
                        <a href="{{ route('admin.inbox') }}" class="text-xs font-bold text-amber-700 hover:text-amber-800 transition-colors">See all</a>
                    </div>
                    <div data-bell-body class="p-2 max-h-[60vh] overflow-y-auto">
                        <div class="space-y-3" aria-hidden="true">
                            <span class="alice-skeleton block w-full h-14 rounded-xl"></span>
                            <span class="alice-skeleton block w-full h-14 rounded-xl"></span>
                            <span class="alice-skeleton block w-full h-14 rounded-xl"></span>
                        </div>
                    </div>
                </div>

                <!-- Actor dropdown -->
                <div class="relative shrink-0">
                    <button
                        type="button"
                        data-dropdown-toggle="admin-actor-dropdown"
                        class="flex items-center gap-2 px-3 py-1.5 bg-white border border-[#E6DCCF] rounded-lg hover:border-[#2D231E] transition-colors cursor-pointer"
                    >
                        <x-avatar :user="auth()->user()" alt="Your avatar" :size="28" />
                        <span class="text-xs font-bold text-[#2D231E] hidden sm:inline">
                            {{ auth()->user()->nickname ?? strtok(auth()->user()->email, '@') }}
                        </span>
                    </button>

                    <div
                        id="admin-actor-dropdown"
                        data-dropdown
                        class="hidden absolute right-0 mt-2 w-60 bg-white border border-[#E6DCCF] rounded-xl p-2 z-50 space-y-1 shadow-[0_4px_12px_rgba(45,35,30,0.08)]"
                    >
                        <div class="flex items-center gap-2.5 px-3 py-2.5">
                            <x-avatar :user="auth()->user()" alt="Your avatar" :size="36" />
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-bold text-[#2D231E] truncate">{{ auth()->user()->nickname ?? 'Admin' }}</p>
                                <p class="text-[11px] font-semibold text-[#2D231E] truncate">{{ auth()->user()->email }}</p>
                            </div>
                            <span class="shrink-0 text-[9px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-800">Admin</span>
                        </div>
                        <div class="border-t border-[#E6DCCF]"></div>

                        @if (config('devtools.enabled'))
                        <!-- One-click admin "view as": switches to a disposable scratch
                             Student / Teacher, never a real learner. Escape via the
                             "Viewing as ... Return to Admin" banner in the preview. -->
                        <p class="text-[10px] font-bold uppercase tracking-wider text-[#2D231E] px-3 pt-1.5 pb-0.5">View as</p>
                        <form method="POST" action="{{ route('admin.preview.switch', 'student') }}" data-submit-guard>
                            @csrf
                            <button type="submit" class="w-full text-left flex items-center gap-2.5 px-3 py-2 text-xs font-bold text-[#2D231E] hover:bg-amber-50 rounded-lg transition-colors cursor-pointer">
                                <x-icons.core.monitor class="w-4 h-4 shrink-0 text-[#2D231E]" />
                                <span data-submit-idle>View as Student</span>
                                <span data-submit-active class="hidden"><x-spinner label="Switching..." /></span>
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.preview.switch', 'teacher') }}" data-submit-guard>
                            @csrf
                            <button type="submit" class="w-full text-left flex items-center gap-2.5 px-3 py-2 text-xs font-bold text-[#2D231E] hover:bg-amber-50 rounded-lg transition-colors cursor-pointer">
                                <x-icons.core.monitor class="w-4 h-4 shrink-0 text-[#2D231E]" />
                                <span data-submit-idle>View as Teacher</span>
                                <span data-submit-active class="hidden"><x-spinner label="Switching..." /></span>
                            </button>
                        </form>
                        @endif

                        <div class="border-t border-[#E6DCCF] my-1"></div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-[#2D231E] px-3 pt-1.5 pb-0.5">Account</p>
                        <a
                            href="{{ route('admin.settings') }}"
                            class="flex items-center gap-2.5 px-3 py-2 text-xs font-bold text-[#2D231E] hover:bg-amber-50 rounded-lg transition-colors"
                        >
                            <x-icons.core.sliders class="w-4 h-4 shrink-0 text-[#2D231E]" />
                            Settings
                        </a>

                        <div class="border-t border-[#E6DCCF] my-1"></div>
                        <form method="POST" action="{{ route('logout') }}" data-submit-guard>
                            @csrf
                            <button
                                type="submit"
                                class="w-full text-left flex items-center gap-2.5 px-3 py-2 text-xs font-bold text-rose-600 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer"
                            >
                                <x-icons.core.sign-out class="w-4 h-4 shrink-0" />
                                <span data-submit-idle>Logout</span>
                                <span data-submit-active class="hidden"><x-spinner label="Signing out..." /></span>
                            </button>
                        </form>
                    </div>
                </div>
                </div>

            </div>
        </header>

        <!-- CONTENT -->
        <main class="flex-1 w-full max-w-7xl mx-auto px-4 sm:px-6 py-6 overflow-x-clip">
            @yield('content')
        </main>

        <!-- FOOTER -->
        <footer class="border-t border-[#E6DCCF] py-4">
            <p class="text-center text-[11px] font-semibold text-[#2D231E]">
                ALICE &middot; Gamified English Learning Platform &middot; Administration
            </p>
        </footer>

    </div>

    @include('partials.toast')

    @stack('scripts')

</body>
</html>