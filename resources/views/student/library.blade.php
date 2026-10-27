@extends('layouts.authenticated')

@section('page', 'library')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8 space-y-8">

    <!-- Search + Filter Drawer -->
    <div class="flex items-center gap-2">
        <form method="GET" action="{{ route('library.all', 'all') }}" data-auto-filter class="flex-1 relative">
            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-[#8C7B70] pointer-events-none">
                <x-icons.search class="w-4 h-4" />
            </span>
            <input
                type="search"
                name="q"
                value=""
                placeholder="Search titles, authors or topics..."
                class="w-full bg-white border-2 border-[#2D231E] rounded-2xl pl-11 pr-4 py-3 font-bold text-xs sm:text-sm text-[#2D231E] focus:outline-none focus:ring-2 focus:ring-amber-400 placeholder-[#8C7B70] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)]"
            >
        </form>

        <x-filter-drawer
            :action="route('library.all', 'all')"
            :genres="$genres"
            :activeGenre="null"
            :showGenre="true"
            :showSort="true"
            searchName="q"
            searchValue=""
            :currentSort="'title'"
        />
    </div>

    <!-- HERO CAROUSEL: featured picks -->
    @if ($hero->isNotEmpty())
        <section class="space-y-4" data-carousel>
            <div class="flex items-center justify-between">
                <h2 class="font-pixel text-xs sm:text-sm font-bold text-[#2D231E] uppercase tracking-wider flex items-center gap-2">
                    Featured Picks
                </h2>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        data-carousel-prev
                        aria-label="Scroll back"
                        class="w-9 h-9 bg-white border-2 border-[#2D231E] text-[#2D231E] rounded-xl font-bold flex items-center justify-center shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all cursor-pointer"
                    >
                        <x-icons.arrow-right class="w-4 h-4 -scale-x-100" />
                    </button>
                    <button
                        type="button"
                        data-carousel-next
                        aria-label="Scroll forward"
                        class="w-9 h-9 bg-white border-2 border-[#2D231E] text-[#2D231E] rounded-xl font-bold flex items-center justify-center shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all cursor-pointer"
                    >
                        <x-icons.arrow-right class="w-4 h-4" />
                    </button>
                </div>
            </div>

            <div class="relative">
                <div data-carousel-track class="flex gap-4 overflow-x-auto pb-4 pt-1 snap-x snap-mandatory scrollbar-none">
                    @foreach ($hero as $story)
                        <x-story-card :story="$story" />
                    @endforeach
                </div>

                {{-- Right-edge fade hints that the strip scrolls sideways. --}}
                <div aria-hidden="true" class="pointer-events-none absolute inset-y-0 right-0 w-10 bg-gradient-to-l from-[#FAF6EE] to-transparent"></div>
            </div>
        </section>
    @endif

    <!-- ROW 1: In Progress & Saved (hidden entirely until the student has shelf activity) -->
    @if ($inProgress->isNotEmpty())
        <x-library-row
            title="In Progress and Saved"
            :viewAllUrl="route('collection')"
        >
            @foreach ($inProgress as $story)
                <x-story-card
                    :story="$story"
                    :meta="['percent' => \App\Support\LibraryPresentation::percentFromRow($story), 'favorite' => (bool) ($story->is_favorite ?? false)]"
                />
            @endforeach
        </x-library-row>
    @endif

    @if ($recommended->isNotEmpty())
        <x-library-row title="Recommended for Grade {{ $grade }}" :viewAllUrl="route('library.all', 'recommended')">
            @foreach ($recommended as $story)
                <x-story-card :story="$story" />
            @endforeach
        </x-library-row>
    @endif

    <!-- EXPLORE BY GENRE: large tinted tiles spanning every published genre. -->
    @if ($genres->isNotEmpty())
        <section class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-pixel text-[9px] sm:text-[11px] font-bold text-[#2D231E] uppercase tracking-wider flex items-center gap-2 leading-relaxed">
                    Explore by Genre
                </h2>
            </div>

            <div class="flex gap-4 overflow-x-auto pb-4 pt-1 snap-x snap-mandatory scrollbar-none">
                @foreach ($genres as $genre)
                    <x-genre-tile :genre="$genre" />
                @endforeach
            </div>
        </section>
    @endif

    <!-- ROW 3: Quick Reads -->
    @if ($quickReads->isNotEmpty())
        <x-library-row title="Quick Reads (Under 5 Mins)" :viewAllUrl="route('library.all', 'quick-reads')">
            @foreach ($quickReads as $story)
                <x-story-card :story="$story" />
            @endforeach
        </x-library-row>
    @endif

</div>
@endsection
