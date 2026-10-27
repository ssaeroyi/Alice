@extends('layouts.authenticated')

@section('page', 'collection')

@php
    $tabs = [
        'favorites' => ['label' => 'FAVORITES', 'accent' => 'bg-rose-400'],
        'progress' => ['label' => 'IN PROGRESS', 'accent' => 'bg-amber-400'],
        'finished' => ['label' => 'FINISHED', 'accent' => 'bg-emerald-400'],
    ];
    $emptyMessages = [
        'favorites' => 'No favourites yet. Tap the heart on any story cover to keep it here.',
        'progress' => 'Nothing in progress. Open a story and your place is saved automatically.',
        'finished' => 'No finished stories yet. Read a story to the end, or pass its quiz, to shelve it here.',
    ];
    $difficultyKey = match (($filters['grade'] ?? null)) {
        7 => 'easy',
        8 => 'intermediate',
        9 => 'hard',
        10 => 'very-hard',
        default => null,
    };
@endphp

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6 space-y-5">

    <!-- Page header -->
    <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-2.5">
            <a
                href="{{ route('library') }}"
                class="w-8 h-8 shrink-0 bg-white border-2 border-[#2D231E] text-[#2D231E] rounded-xl font-bold flex items-center justify-center shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all"
                aria-label="Back to the Library"
            >
                <x-icons.arrow-left class="w-3.5 h-3.5" />
            </a>
            <h1 class="font-pixel text-[11px] sm:text-sm font-bold text-[#2D231E] uppercase tracking-wider">MY COLLECTION</h1>
        </div>

        <span class="shrink-0 font-pixel text-[9px] sm:text-[10px] bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] px-2 py-1 rounded-lg font-bold uppercase shadow-[2px_2px_0px_0px_rgba(45,35,30,1)]">
            {{ $stories->count() }} {{ $stories->count() === 1 ? 'STORY' : 'STORIES' }}
        </span>
    </div>

    <!-- Search + Filter Drawer (universal filtering on the collection too) -->
    <div class="flex items-center gap-2">
        <form method="GET" action="{{ route('collection') }}" data-auto-filter data-search-clear-url="{{ route('collection', ['tab' => $tab]) }}" class="flex-1 relative">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-[#8C7B70] pointer-events-none">
                <x-icons.search class="w-4 h-4" />
            </span>
            <input
                type="search"
                name="q"
                value="{{ $filters['q'] ?? '' }}"
                placeholder="Search my collection..."
                class="w-full bg-white border-2 border-[#2D231E] rounded-2xl pl-11 pr-10 py-3 font-bold text-xs sm:text-sm text-[#2D231E] focus:outline-none focus:ring-2 focus:ring-amber-400 placeholder-[#8C7B70] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)]"
            >
            <button type="button" data-search-clear aria-label="Clear search"
                class="hidden absolute right-3 top-1/2 -translate-y-1/2 min-w-[28px] h-7 items-center justify-center rounded-md text-[#8C7B70] hover:text-[#2D231E] hover:bg-[#F3ECE0] transition-colors cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"></path></svg>
            </button>
        </form>

        <x-filter-drawer
            :action="route('collection')"
            :currentDifficulty="$difficultyKey"
            :genres="$genres"
            :activeGenre="$filters['genre'] ?? null"
            :showGenre="true"
            :searchValue="$filters['q'] ?? ''"
            :currentSort="$filters['sort'] ?? 'title'"
            tabName="tab"
            :tabValue="$tab"
        />
    </div>

    <!-- Shelf tabs -->
    <nav class="flex items-center gap-1.5 overflow-x-auto -mx-1 px-1 pt-1 pb-1.5 scrollbar-none" aria-label="Collection shelves">
        @foreach ($tabs as $key => $pill)
            <a
                href="{{ route('collection', ['tab' => $key]) }}"
                aria-current="{{ $tab === $key ? 'page' : 'false' }}"
                class="shrink-0 inline-flex items-center gap-1.5 font-pixel text-[9px] sm:text-[10px] font-bold px-3 py-2 rounded-xl border-2 border-[#2D231E] uppercase shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] transition-all {{ $tab === $key ? $pill['accent'].' text-[#2D231E]' : 'bg-white text-[#8C7B70] hover:bg-amber-100' }}"
            >
                {{ $pill['label'] }}
                <span class="font-pixel text-[8px] bg-white border border-[#2D231E] rounded-md px-1 py-0.5 text-[#2D231E]">{{ $counts[$key] }}</span>
            </a>
        @endforeach
    </nav>

    <!-- Active shelf -->
    @if ($stories->isEmpty())
        <div class="bg-white border-2 border-dashed border-[#2D231E]/40 rounded-3xl p-10 text-center space-y-3">
            <div class="w-12 h-12 mx-auto rounded-full bg-[#F3ECE0] border-2 border-[#2D231E] flex items-center justify-center shadow-[2px_2px_0px_0px_rgba(45,35,30,1)]">
                <x-icons.book-open class="w-6 h-6 text-[#2D231E]" />
            </div>
            <p class="text-xs font-bold text-[#8C7B70]">{{ $emptyMessages[$tab] }}</p>
            <a
                href="{{ route('library') }}"
                class="inline-block font-pixel text-xs px-5 py-2.5 rounded-xl bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] uppercase shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] transition-all"
            >
                GO TO LIBRARY
            </a>
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
                <x-story-card
                    :story="$story"
                    :fit="'grid'"
                    :meta="['percent' => \App\Support\LibraryPresentation::percentFromRow($story), 'favorite' => (bool) ($story->is_favorite ?? false)]"
                />
            @endforeach
        </div>
    @endif

</div>
@endsection
