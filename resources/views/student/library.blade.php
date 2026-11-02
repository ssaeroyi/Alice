@extends('layouts.authenticated')

@section('page', 'library')

@php
    use App\Support\LibraryPresentation;

    // Browse-strip chrome, shared by every chip (native inputs + peer styles,
    // so state survives a plain GET round trip with no JavaScript at all).
    $chip = 'inline-flex items-center justify-center whitespace-nowrap px-3 py-2 min-h-[36px] rounded-xl border-2 border-[#2D231E] font-pixel text-[9px] font-bold uppercase tracking-wider bg-white text-[#8C7B70] cursor-pointer select-none active:translate-y-[1px] transition-all peer-checked:bg-amber-400 peer-checked:text-[#2D231E] peer-checked:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)]';
    $stripLabel = 'font-pixel text-[8px] font-bold text-[#8C7B70] uppercase tracking-wider';
@endphp

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8">

    {{-- Search + browse strip: mounted once; library-browse.js swaps only
         the [data-library-root] body below, so focus and scroll survive. --}}
    <form id="library-browse" method="GET" action="{{ route('library') }}" data-browse data-search-clear-url="{{ route('library') }}" autocomplete="off" class="space-y-5">
        <!-- Search -->
        <div class="relative">
            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-[#8C7B70] pointer-events-none">
                <x-icons.search class="w-4 h-4" />
            </span>
            <input
                type="search"
                name="q"
                autocomplete="off"
                data-combobox="{{ route('library.suggest') }}"
                value="{{ $filters['q'] ?? '' }}"
                placeholder="Search titles or authors..."
                class="w-full bg-white border-2 border-[#2D231E] rounded-2xl pl-11 pr-10 py-3 font-bold text-xs sm:text-sm text-[#2D231E] focus:outline-none focus:ring-2 focus:ring-amber-400 placeholder-[#8C7B70] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)]"
            >
            <button type="button" data-search-clear aria-label="Clear search and back to the library"
                class="hidden absolute right-3 top-1/2 -translate-y-1/2 min-w-[28px] h-7 items-center justify-center rounded-md text-[#8C7B70] hover:text-[#2D231E] hover:bg-[#F3ECE0] transition-colors cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        <!-- Level scope: MY GRADE is the standing rule, ALL LEVELS is the tap -->
        <div class="space-y-1">
            <span class="{{ $stripLabel }}">Level</span>
            <div class="flex gap-1.5">
                <label class="relative">
                    <input type="radio" name="levels" value="mine" class="peer sr-only" @checked($filters['levels'] !== 'all')>
                    <span class="{{ $chip }}">Grade {{ $grade }}</span>
                </label>
                <label class="relative">
                    <input type="radio" name="levels" value="all" class="peer sr-only" @checked($filters['levels'] === 'all')>
                    <span class="{{ $chip }}">All levels</span>
                </label>
            </div>
        </div>

        <!-- Genre + hidden genre (a genre can be include or hide, never both) -->
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:items-start">
            <div class="space-y-1">
                <span class="{{ $stripLabel }}">Genre</span>
                <div class="flex gap-1.5 overflow-x-auto pb-1 scrollbar-none">
                    @foreach ($genres as $genre)
                        @php($slug = LibraryPresentation::genreSlug($genre->name))
                        <label class="relative">
                            <input type="checkbox" name="genre[]" value="{{ $slug }}" class="peer sr-only" @checked(in_array($slug, $filters['genre']))>
                            <span class="{{ $chip }}">{{ $genre->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="space-y-1">
                <span class="{{ $stripLabel }}">Hide genre</span>
                <div class="flex gap-1.5 overflow-x-auto pb-1 scrollbar-none">
                    @foreach ($genres as $genre)
                        @php($slug = LibraryPresentation::genreSlug($genre->name))
                        <label class="relative">
                            <input type="checkbox" name="exclude[]" value="{{ $slug }}" class="peer sr-only" @checked(in_array($slug, $filters['exclude']))>
                            <span class="{{ $chip }} peer-checked:bg-rose-400">{{ $genre->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <!-- Length + difficulty -->
            <div class="space-y-1">
                <span class="{{ $stripLabel }}">Length</span>
                <div class="flex gap-1.5 overflow-x-auto pb-1 scrollbar-none">
                    <label class="relative">
                        <input type="radio" name="length" value="quick" class="peer sr-only" @checked($filters['length'] === 'quick')>
                        <span class="{{ $chip }}">Under 5 mins</span>
                    </label>
                    <label class="relative">
                        <input type="radio" name="length" value="medium" class="peer sr-only" @checked($filters['length'] === 'medium')>
                        <span class="{{ $chip }}">5 to 13 mins</span>
                    </label>
                    <label class="relative">
                        <input type="radio" name="length" value="long" class="peer sr-only" @checked($filters['length'] === 'long')>
                        <span class="{{ $chip }}">Long read</span>
                    </label>
                    <label class="relative">
                        <input type="radio" name="length" value="" class="peer sr-only" @checked($filters['length'] === null)>
                        <span class="{{ $chip }}">Any</span>
                    </label>
                </div>
            </div>

            <div class="space-y-1">
                <span class="{{ $stripLabel }}">Difficulty</span>
                <div class="flex gap-1.5 overflow-x-auto pb-1 scrollbar-none">
                    @foreach (['easy' => 7, 'intermediate' => 8, 'hard' => 9, 'very-hard' => 10] as $value => $bandGrade)
                        <label class="relative">
                            <input type="radio" name="difficulty" value="{{ $value }}" class="peer sr-only" @checked($filters['grade'] === $bandGrade)>
                            <span class="{{ $chip }}">{{ ['7' => 'Easy', '8' => 'Intermediate', '9' => 'Hard', '10' => 'Very hard'][$bandGrade] }}</span>
                        </label>
                    @endforeach
                    <label class="relative">
                        <input type="radio" name="difficulty" value="" class="peer sr-only" @checked($filters['grade'] === null)>
                        <span class="{{ $chip }}">Any</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Sort -->
        <div class="space-y-1">
            <span class="{{ $stripLabel }}">Sort</span>
            <div>
                <select name="sort" data-browse-sort
                    class="min-h-[36px] bg-white border-2 border-[#2D231E] rounded-xl px-3 py-2 font-pixel text-[9px] font-bold uppercase tracking-wider text-[#2D231E] cursor-pointer focus:outline-none focus:ring-2 focus:ring-amber-400">
                    @foreach (['title' => 'A to Z', 'newest' => 'Newest', 'length' => 'Shortest', 'grade' => 'Easiest first'] as $value => $text)
                        <option value="{{ $value }}" @selected($filters['sort'] === $value)>{{ $text }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </form>

    {{-- One body: idle shelves when nothing is set, the filtered grid when
         any browse state is active. library-browse.js swaps exactly here. --}}
    <div data-library-root aria-live="polite" class="pt-7 space-y-8">

        @if ($browsing)
            <!-- RESULTS: count + clean reset + the frozen story-card look -->
            <section class="space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-pixel text-xs sm:text-sm font-bold text-[#2D231E] uppercase tracking-wider">
                        {{ number_format($stories->total()) }} {{ Str::plural('story', $stories->total()) }}
                    </h2>

                    <a
                        href="{{ route('library') }}"
                        data-browse-reset
                        class="shrink-0 font-pixel text-[9px] sm:text-xs font-bold text-[#2D231E] uppercase inline-flex items-center px-1.5 py-1.5 min-h-[32px] transition-colors hover:underline decoration-2 underline-offset-2"
                    >
                        Clear all
                    </a>
                </div>

                @if ($stories->isEmpty())
                    <div class="bg-white border-2 border-dashed border-[#2D231E]/40 rounded-3xl p-10 text-center space-y-3">
                        <div class="w-12 h-12 mx-auto rounded-full bg-[#FAF6EE] border-2 border-[#2D231E] flex items-center justify-center shadow-[2px_2px_0px_0px_rgba(45,35,30,1)]">
                            <x-icons.book-open class="w-6 h-6 text-[#2D231E]" />
                        </div>
                        <p class="font-pixel text-xs font-bold text-[#2D231E] uppercase">No stories found</p>
                        <p class="text-xs font-bold text-[#8C7B70]">Try All levels or a different search.</p>
                        <a
                            href="{{ route('library') }}"
                            data-browse-reset
                            class="inline-block min-h-[40px] font-pixel text-[10px] font-bold px-5 py-2.5 rounded-xl bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] uppercase shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all"
                        >
                            Reset filters
                        </a>
                    </div>
                @else
                    {{-- Phones: scannable rows. sm+: card grids. --}}
                    <div class="space-y-3 md:hidden">
                        @foreach ($stories as $story)
                            <x-story-list-item
                                :story="$story"
                                :meta="['percent' => LibraryPresentation::percentFromRow($story), 'favorite' => (bool) ($story->is_favorite ?? false)]"
                            />
                        @endforeach
                    </div>

                    <div class="hidden md:grid grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-4">
                        @foreach ($stories as $story)
                            <x-story-card :story="$story" :fit="'grid'" />
                        @endforeach
                    </div>

                    <div class="pt-2">
                        {{ $stories->links('pagination::library') }}
                    </div>
                @endif
            </section>
        @else
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
                            :meta="['percent' => LibraryPresentation::percentFromRow($story), 'favorite' => (bool) ($story->is_favorite ?? false)]"
                        />
                    @endforeach
                </x-library-row>
            @endif

            @if ($recommended->isNotEmpty())
                <x-library-row title="Recommended for Grade {{ $grade }}">
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
                <x-library-row title="Quick Reads (Under 5 Mins)">
                    @foreach ($quickReads as $story)
                        <x-story-card :story="$story" />
                    @endforeach
                </x-library-row>
            @endif
        @endif
    </div>

</div>
@endsection
