@extends('layouts.authenticated')

@section('page', 'library')

@php
    $rowTitles = [
        'all' => 'All Stories',
        'recommended' => 'Recommended for Grade '.$grade,
        'quick-reads' => 'Quick Reads: Under 5 Minutes',
    ];
    $heading = $row === 'genre' ? $genre->name : ($rowTitles[$row] ?? 'Stories');
    $difficultyKey = match (($filters['grade'] ?? null)) {
        7 => 'easy',
        8 => 'intermediate',
        9 => 'hard',
        10 => 'very-hard',
        default => null,
    };
@endphp

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8 space-y-6">

    <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-2.5">
            <a
                href="{{ route('library') }}"
                class="w-8 h-8 shrink-0 bg-white border-2 border-[#2D231E] text-[#2D231E] rounded-xl font-bold flex items-center justify-center shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all"
                aria-label="Back to the Library"
            >
                <x-icons.arrow-left class="w-3.5 h-3.5" />
            </a>
            <h1 class="font-pixel text-[11px] sm:text-sm font-bold text-[#2D231E] uppercase tracking-wider">{{ $heading }}</h1>
        </div>

        <span class="shrink-0 font-pixel text-[9px] sm:text-[10px] bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] px-2 py-1 rounded-lg font-bold uppercase shadow-[2px_2px_0px_0px_rgba(45,35,30,1)]">
            {{ $stories->total() }} {{ $stories->total() === 1 ? 'STORY' : 'STORIES' }}
        </span>
    </div>

    <!-- Search + Filter Drawer -->
    <div class="flex items-center gap-2">
        <form method="GET" action="{{ $row === 'genre' ? route('library.genre', \App\Support\LibraryPresentation::genreSlug($genre->name)) : route('library.all', $row) }}" data-auto-filter class="flex-1 relative">
            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-[#8C7B70] pointer-events-none">
                <x-icons.search class="w-4 h-4" />
            </span>
            <input
                type="search"
                name="q"
                value="{{ $filters['q'] ?? '' }}"
                placeholder="Search titles, authors or topics..."
                class="w-full bg-white border-2 border-[#2D231E] rounded-2xl pl-11 pr-4 py-3 font-bold text-xs sm:text-sm text-[#2D231E] focus:outline-none focus:ring-2 focus:ring-amber-400 placeholder-[#8C7B70] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)]"
            >
        </form>

        <x-filter-drawer
            :action="$row === 'genre' ? route('library.genre', \App\Support\LibraryPresentation::genreSlug($genre->name)) : route('library.all', $row)"
            :currentDifficulty="$difficultyKey"
            :genres="$genres"
            :activeGenre="$filters['genre'] ?? null"
            :showGenre="$row === 'all' || $row === 'genre'"
            :searchValue="$filters['q'] ?? ''"
            :currentSort="$filters['sort'] ?? 'title'"
        />
    </div>

    <div data-search-results>
    @if ($stories->isEmpty())
        <div class="bg-white border-2 border-dashed border-[#2D231E]/40 rounded-3xl p-10 text-center space-y-3">
            <div class="w-12 h-12 mx-auto rounded-full bg-[#FAF6EE] border-2 border-[#2D231E] flex items-center justify-center shadow-[2px_2px_0px_0px_rgba(45,35,30,1)]">
                <x-icons.book-open class="w-6 h-6 text-[#2D231E]" />
            </div>
            <p class="font-pixel text-xs font-bold text-[#2D231E] uppercase">No stories found</p>
            <p class="text-xs font-bold text-[#8C7B70]">Try a different search or reset the filters.</p>
        </div>
    @else
        {{-- Phones: scannable rows. sm+: card grids. --}}
        <div class="space-y-3 md:hidden">
            @foreach ($stories as $story)
                <x-story-list-item
                    :story="$story"
                    :meta="['percent' => \App\Support\LibraryPresentation::percentFromRow($story), 'favorite' => (bool) ($story->is_favorite ?? false)]"
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
    </div>

</div>
@endsection
