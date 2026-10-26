@extends('layouts.guest')

@section('title', 'ALICE - Learn English Like It\'s an Adventure')

@section('head')
    @php
        $ogUrl = config('app.url', url('/'));
        $ogImage = asset('image/Alice/Light%20mode/alice-lt-casual.png');
    @endphp
    <meta property="og:title" content="ALICE - Learn English Like It's an Adventure">
    <meta property="og:description" content="Read classic stories, play word games, and level up your reading one day at a time. Free for students of Dasmariñas East Integrated High School.">
    <meta property="og:url" content="{{ $ogUrl }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta name="twitter:title" content="ALICE - Learn English Like It's an Adventure">
    <meta name="twitter:description" content="Read classic stories, play word games, and level up your reading one day at a time. Free for students of Dasmariñas East Integrated High School.">
    <meta name="twitter:image" content="{{ $ogImage }}">
@endsection

@section('js')
    @vite(['resources/js/landing.js'])
@endsection

@section('content')
<div class="max-w-6xl mx-auto px-4 py-10 sm:py-14 space-y-16 sm:space-y-24 relative">

    <!-- HERO SECTION -->
    <section class="max-w-3xl mx-auto pt-2">
        <div class="bg-white border-4 border-[#2D231E] rounded-3xl p-6 sm:p-10 shadow-[6px_6px_0px_0px_rgba(45,35,30,1)] space-y-6 sm:space-y-8 text-center">

            <!-- Headline -->
            <div class="space-y-3">
                <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight text-[#2D231E] leading-tight">
                    Learn English like it's an adventure.
                </h1>
                <p class="text-sm sm:text-base font-semibold text-[#2D231E] leading-relaxed max-w-xl mx-auto">
                    Read classic stories, play word games, and level up your reading one day at a time.
                </p>
            </div>

            <!-- ALICE Companion -->
            <div class="flex flex-row items-center justify-center gap-3 sm:gap-4 max-w-xl mx-auto">
                <div class="shrink-0 flex items-center justify-center relative">
                    <div class="absolute w-16 h-16 sm:w-20 sm:h-20 rounded-full bg-[#2D231E]/20 blur-md"></div>
                    <img
                        id="landing-alice"
                        src="{{ asset('image/Alice/Light%20mode/alice-lt-casual.png') }}"
                        width="1280" height="1280"
                        alt="ALICE, your reading guide"
                        fetchpriority="high"
                        decoding="async"
                        class="relative z-10 w-20 h-20 sm:w-24 sm:h-24 object-contain scale-110 drop-shadow-[3px_3px_0px_rgba(45,35,30,1)] landing-alice-float"
                    >
                </div>

                <div class="relative flex-1 min-w-0 bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-3 sm:p-5 shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]">
                    <div class="absolute -left-2.5 sm:-left-3 top-1/2 -translate-y-1/2 w-4 h-4 sm:w-5 sm:h-5 bg-[#FAF6EE] border-l-2 border-b-2 border-[#2D231E] rotate-45"></div>

                    <p class="text-xs sm:text-sm font-bold text-[#2D231E] leading-relaxed text-left">
                        Hi! I'm ALICE, your reading guide. I find you stories just right for you, then we play, learn, and grow together.
                    </p>
                </div>
            </div>

            <!-- CTA -->
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-center gap-3 pt-2">
                <a href="{{ route('register') }}" data-cue="sparkle" class="w-full sm:w-auto min-h-[48px] font-pixel text-xs sm:text-sm px-8 py-4 rounded-2xl bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] font-bold flex items-center justify-center shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all">
                    START LEARNING
                </a>
                <a href="{{ route('login') }}" data-cue="nav" class="w-full sm:w-auto min-h-[48px] font-pixel text-xs sm:text-sm px-8 py-4 rounded-2xl bg-white border-2 border-[#2D231E] text-[#2D231E] font-bold flex items-center justify-center shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] hover:bg-amber-400 active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all">
                    SIGN IN
                </a>
            </div>

        </div>
    </section>

    <!-- FEATURES GRID -->
    <section class="space-y-8">

        <!-- Section Header -->
        <div class="text-center space-y-2" data-reveal>
            <div class="inline-block font-pixel text-xs text-amber-700 bg-amber-100 border-2 border-[#2D231E] px-3 py-1 rounded-xl shadow-[2px_2px_0px_0px_rgba(45,35,30,1)]">
                WHAT YOU CAN DO
            </div>
            <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-[#2D231E]">
                Everything You Need to Level Up
            </h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">

            <!-- 1. Story Library -->
            <div class="bg-white border-4 border-[#2D231E] rounded-3xl p-5 sm:p-6 shadow-[5px_5px_0px_0px_rgba(45,35,30,1)] hover:-translate-y-1 transition-transform space-y-3 relative overflow-hidden" data-reveal data-cue="pop">
                <div class="flex items-center justify-between">
                    <span class="font-pixel text-[10px] bg-amber-100 text-amber-700 border border-amber-300 px-2 py-1 rounded-md font-bold">
                        MODE 01
                    </span>
                    <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                        {!! \App\Support\LibraryPresentation::iconGlyph('ui', 'open-book') !!}
                    </div>
                </div>
                <h3 class="text-base sm:text-lg font-bold text-[#2D231E]">Story Library</h3>
                <p class="text-xs sm:text-sm font-semibold text-[#2D231E] leading-relaxed">
                    Over 100 stories picked for your level. Tap any word to see its meaning, or listen as it's read aloud.
                </p>
            </div>

            <!-- 2. Vocabulary Games -->
            <div class="bg-white border-4 border-[#2D231E] rounded-3xl p-5 sm:p-6 shadow-[5px_5px_0px_0px_rgba(45,35,30,1)] hover:-translate-y-1 transition-transform space-y-3 relative overflow-hidden" data-reveal data-cue="pop">
                <div class="flex items-center justify-between">
                    <span class="font-pixel text-[10px] bg-emerald-100 text-emerald-700 border border-emerald-300 px-2 py-1 rounded-md font-bold">
                        MODE 02
                    </span>
                    <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                        {!! \App\Support\LibraryPresentation::iconGlyph('ui', 'cards') !!}
                    </div>
                </div>
                <h3 class="text-base sm:text-lg font-bold text-[#2D231E]">Minigames</h3>
                <p class="text-xs sm:text-sm font-semibold text-[#2D231E] leading-relaxed">
                    Play word games with the words you're learning.
                </p>
            </div>

            <!-- 3. Quizzes & Tests -->
            <div class="bg-white border-4 border-[#2D231E] rounded-3xl p-5 sm:p-6 shadow-[5px_5px_0px_0px_rgba(45,35,30,1)] hover:-translate-y-1 transition-transform space-y-3 relative overflow-hidden" data-reveal data-cue="pop">
                <div class="flex items-center justify-between">
                    <span class="font-pixel text-[10px] bg-violet-100 text-violet-700 border border-violet-300 px-2 py-1 rounded-md font-bold">
                        MODE 03
                    </span>
                    <div class="w-8 h-8 rounded-xl bg-violet-50 text-violet-600 flex items-center justify-center">
                        {!! \App\Support\LibraryPresentation::iconGlyph('ui', 'scroll-quill') !!}
                    </div>
                </div>
                <h3 class="text-base sm:text-lg font-bold text-[#2D231E]">Quizzes</h3>
                <p class="text-xs sm:text-sm font-semibold text-[#2D231E] leading-relaxed">
                    Answer questions after each story to check what you understood.
                </p>
            </div>

            <!-- 4. Study & Flashcards -->
            <div class="bg-white border-4 border-[#2D231E] rounded-3xl p-5 sm:p-6 shadow-[5px_5px_0px_0px_rgba(45,35,30,1)] hover:-translate-y-1 transition-transform space-y-3 relative overflow-hidden" data-reveal data-cue="pop">
                <div class="flex items-center justify-between">
                    <span class="font-pixel text-[10px] bg-sky-100 text-sky-700 border border-sky-300 px-2 py-1 rounded-md font-bold">
                        MODE 04
                    </span>
                    <div class="w-8 h-8 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center">
                        {!! \App\Support\LibraryPresentation::iconGlyph('ui', 'magnifying-glass') !!}
                    </div>
                </div>
                <h3 class="text-base sm:text-lg font-bold text-[#2D231E]">Flashcards</h3>
                <p class="text-xs sm:text-sm font-semibold text-[#2D231E] leading-relaxed">
                    Review the words you saved, a few at a time.
                </p>
            </div>

            <!-- 5. Daily Tasks -->
            <div class="bg-white border-4 border-[#2D231E] rounded-3xl p-5 sm:p-6 shadow-[5px_5px_0px_0px_rgba(45,35,30,1)] hover:-translate-y-1 transition-transform space-y-3 relative overflow-hidden" data-reveal data-cue="pop">
                <div class="flex items-center justify-between">
                    <span class="font-pixel text-[10px] bg-rose-100 text-rose-700 border border-rose-300 px-2 py-1 rounded-md font-bold">
                        MODE 05
                    </span>
                    <div class="w-8 h-8 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center">
                        {!! \App\Support\LibraryPresentation::iconGlyph('ui', 'key') !!}
                    </div>
                </div>
                <h3 class="text-base sm:text-lg font-bold text-[#2D231E]">Daily Tasks</h3>
                <p class="text-xs sm:text-sm font-semibold text-[#2D231E] leading-relaxed">
                    Finish small tasks each day and earn keys. Unlock skins with them.
                </p>
            </div>

            <!-- 6. Badges & Streaks -->
            <div class="bg-white border-4 border-[#2D231E] rounded-3xl p-5 sm:p-6 shadow-[5px_5px_0px_0px_rgba(45,35,30,1)] hover:-translate-y-1 transition-transform space-y-3 relative overflow-hidden" data-reveal data-cue="pop">
                <div class="flex items-center justify-between">
                    <span class="font-pixel text-[10px] bg-orange-100 text-orange-700 border border-orange-300 px-2 py-1 rounded-md font-bold">
                        MODE 06
                    </span>
                    <div class="w-8 h-8 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center">
                        {!! \App\Support\LibraryPresentation::iconGlyph('ui', 'trophy') !!}
                    </div>
                </div>
                <h3 class="text-base sm:text-lg font-bold text-[#2D231E]">Badges & Streaks</h3>
                <p class="text-xs sm:text-sm font-semibold text-[#2D231E] leading-relaxed">
                    {{ count(config('badges.badges')) }} badges to collect, daily stamps, and streaks that track your reading.
                </p>
            </div>

        </div>
    </section>

</div>

<style>
    /* ALICE floating idle animation */
    @keyframes landing-float {
        0%, 100% { transform: translateY(0) scale(1.1); }
        50% { transform: translateY(-6px) scale(1.1); }
    }
    .landing-alice-float {
        animation: landing-float 3s ease-in-out infinite;
    }
    @media (prefers-reduced-motion: reduce) {
        .landing-alice-float { animation: none; }
    }

    /* Scroll reveal */
    [data-reveal] {
        opacity: 0;
        transform: translateY(12px);
        transition: opacity 0.45s ease, transform 0.45s ease;
    }
    [data-reveal].revealed {
        opacity: 1;
        transform: translateY(0);
    }
    @media (prefers-reduced-motion: reduce) {
        [data-reveal] {
            opacity: 1;
            transform: none;
            transition: none;
        }
    }
</style>
@endsection
