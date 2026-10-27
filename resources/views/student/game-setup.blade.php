@extends('layouts.authenticated')

@section('page', 'game-setup')

@section('content')
<style>
    .setup-help-btn { animation: setup-help-pulse 0.9s ease-in-out 6; }
    .setup-help-tag { animation: setup-help-tag 5.4s steps(1, end) forwards; }
    @keyframes setup-help-pulse {
        0%, 100% { box-shadow: 2px 2px 0 0 rgba(45, 35, 30, 1); transform: translateY(0); }
        50% { box-shadow: 0 0 0 5px rgba(251, 191, 36, 0.5), 2px 2px 0 0 rgba(45, 35, 30, 1); transform: translateY(-2px); }
    }
    @keyframes setup-help-tag {
        0%, 85% { opacity: 1; }
        100% { opacity: 0; visibility: hidden; }
    }
</style>
<div class="max-w-2xl mx-auto px-4 py-6 space-y-6" data-game-setup data-game="{{ $game }}">

    <!-- Header -->
    <div class="flex items-center gap-3">
        <a href="{{ route('mini-game') }}" aria-label="Back to games"
           class="shrink-0 w-10 h-10 flex items-center justify-center bg-white border-2 border-[#2D231E] rounded-xl text-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all">
            <x-icons.arrow-left class="w-4 h-4" />
        </a>
        <h1 class="flex-1 min-w-0 font-pixel text-[11px] sm:text-base font-bold text-[#2D231E] uppercase leading-relaxed">{{ $game === 'wordsearch' ? 'Word Search' : ($game === 'match' ? 'Memory' : 'Sentence Builder') }} Setup</h1>
        <div class="ml-auto flex items-center gap-2 shrink-0">
            <span class="setup-help-tag hidden sm:inline-flex font-pixel text-[8px] sm:text-[9px] font-bold text-[#2D231E] bg-white border-2 border-[#2D231E] rounded-lg px-2 py-1.5 shadow-[2px_2px_0px_0px_rgba(45,35,30,1)]">HOW TO PLAY</span>
            <button type="button" data-modal-open="game-help-modal" aria-label="How to play"
                    class="setup-help-btn min-w-[44px] min-h-[44px] flex items-center justify-center bg-amber-400 border-2 border-[#2D231E] rounded-xl font-pixel text-sm font-bold text-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] transition-all uppercase">
                ?
            </button>
        </div>
    </div>

    @if(session('error'))
        <div class="bg-rose-100 border-2 border-rose-400 text-rose-700 text-sm font-bold px-4 py-3 rounded-2xl text-center">
            {{ session('error') }}
        </div>
    @endif

    <!-- Word Source Selection -->
    @php $usesWordBank = in_array($game, ['match', 'sentence']); @endphp
    <div class="bg-white border-2 border-[#2D231E] rounded-2xl p-5 shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] {{ $usesWordBank ? 'opacity-50 pointer-events-none' : '' }}">
        <div class="flex items-center gap-2 mb-4">
            <h2 class="font-pixel text-[11px] font-bold text-[#2D231E] uppercase tracking-wider">Word Source</h2>
            @if(! $usesWordBank)
            <div data-info class="relative">
                <button type="button" data-info-toggle aria-label="What is word source?"
                        class="w-5 h-5 flex items-center justify-center rounded-full bg-[#F3ECE0] border border-[#2D231E]/30 text-[#2D231E] text-[11px] font-bold">?</button>
                <div data-info-popover class="hidden absolute z-50 w-56 bg-white border-2 border-[#2D231E] rounded-xl p-3 shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]">
                    <p class="text-xs text-[#2D231E] font-medium leading-relaxed">
                        <span class="font-bold">Pick a Story</span> uses words from the story you choose.
                        <span class="font-bold">Saved</span> uses words you saved while reading or studying.
                    </p>
                </div>
            </div>
            @else
            <div data-info class="relative">
                <button type="button" data-info-toggle aria-label="How does word source work here?"
                        class="w-5 h-5 flex items-center justify-center rounded-full bg-[#F3ECE0] border border-[#2D231E]/30 text-[#2D231E] text-[11px] font-bold">?</button>
                <div data-info-popover class="hidden absolute z-50 w-56 bg-white border-2 border-[#2D231E] rounded-xl p-3 shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]">
                    <p class="text-xs text-[#2D231E] font-medium leading-relaxed">
                        This game picks its own words. Just choose a time difficulty below.
                    </p>
                </div>
            </div>
            @endif
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <!-- Pick a Story (wordsearch only) -->
            <button data-source="open" @if(! $usesWordBank) data-open-picker @endif
                class="source-card group relative bg-white border-2 border-[#2D231E] rounded-2xl p-4 text-left transition-all shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] hover:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] hover:translate-x-[2px] hover:translate-y-[2px] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)]">
                <div class="flex items-center gap-3 mb-2">
                    <div class="shrink-0 w-10 h-10 rounded-xl bg-emerald-100 border-2 border-[#2D231E] flex items-center justify-center">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                    </div>
                    <div>
                        <h3 class="font-pixel text-xs font-bold text-[#2D231E] uppercase">{{ $usesWordBank ? 'Open' : 'Pick a Story' }}</h3>
                        <p class="text-[10px] text-[#2D231E] font-semibold">{{ $usesWordBank ? 'From stories' : 'Choose it yourself' }}</p>
                    </div>
                </div>
                <p class="text-xs text-[#2D231E] font-semibold">{{ $game === 'wordsearch' ? count($pickableStories).' stories to choose' : $openCounts['all'].' words available' }}</p>
            </button>

            <!-- Saved Words -->
            <button data-source="saved"
                class="source-card group relative bg-white border-2 border-[#2D231E] rounded-2xl p-4 text-left transition-all shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] hover:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] hover:translate-x-[2px] hover:translate-y-[2px] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] {{ $savedCount < 5 ? 'opacity-40 pointer-events-none' : '' }}">
                <div class="flex items-center gap-3 mb-2">
                    <div class="shrink-0 w-10 h-10 rounded-xl bg-amber-100 border-2 border-[#2D231E] flex items-center justify-center">
                        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                    </div>
                    <div>
                        <h3 class="font-pixel text-xs font-bold text-[#2D231E] uppercase">Saved</h3>
                        <p class="text-[10px] text-[#2D231E] font-semibold">Your words</p>
                    </div>
                </div>
                <p class="text-xs text-[#2D231E] font-semibold">
                    @if($savedCount >= 5)
                        {{ $savedCount }} words saved
                    @else
                        Need 5+ saved words
                    @endif
                </p>
            </button>
        </div>

        <!-- Chosen story chip -->
        <div data-story-chip class="mt-4 hidden items-center justify-between gap-3 bg-emerald-50 border-2 border-emerald-400 rounded-xl px-4 py-2.5">
            <p class="text-xs font-bold text-[#2D231E] truncate">
                <span class="text-emerald-700 font-pixel text-[9px] uppercase tracking-wider mr-2">Story</span>
                <span data-story-chip-title></span>
            </p>
            <button type="button" data-story-clear aria-label="Clear story choice"
                    class="min-w-[36px] min-h-[36px] shrink-0 flex items-center justify-center bg-white border-2 border-[#2D231E] rounded-lg font-pixel text-[10px] font-bold text-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all">[X]</button>
        </div>
    </div>

    <!-- Difficulty Selection -->
    <div class="bg-white border-2 border-[#2D231E] rounded-2xl p-5 shadow-[4px_4px_0px_0px_rgba(45,35,30,1)]">
        <h2 class="font-pixel text-[11px] font-bold text-[#2D231E] uppercase tracking-wider mb-4">Time Difficulty</h2>
        <div class="grid grid-cols-3 gap-3">
            <!-- Easy -->
            <button data-difficulty="easy"
                class="diff-card bg-white border-2 border-[#2D231E] rounded-2xl p-3 sm:p-4 text-center transition-all shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] hover:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] hover:translate-x-[2px] hover:translate-y-[2px] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)]">
                <div class="w-10 h-10 mx-auto rounded-xl bg-emerald-100 border-2 border-[#2D231E] flex items-center justify-center mb-2">
                    <span class="font-pixel text-xs font-bold text-emerald-600">E</span>
                </div>
                <h3 class="font-pixel text-[10px] font-bold text-[#2D231E] uppercase">Easy</h3>
                <p class="text-[10px] text-[#2D231E] font-semibold mt-1">More time</p>
            </button>

            <!-- Medium -->
            <button data-difficulty="medium"
                class="diff-card bg-white border-2 border-[#2D231E] rounded-2xl p-3 sm:p-4 text-center transition-all shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] hover:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] hover:translate-x-[2px] hover:translate-y-[2px] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)]">
                <div class="w-10 h-10 mx-auto rounded-xl bg-sky-100 border-2 border-[#2D231E] flex items-center justify-center mb-2">
                    <span class="font-pixel text-xs font-bold text-sky-600">M</span>
                </div>
                <h3 class="font-pixel text-[10px] font-bold text-[#2D231E] uppercase">Medium</h3>
                <p class="text-[10px] text-[#2D231E] font-semibold mt-1">Normal time</p>
            </button>

            <!-- Hard -->
            <button data-difficulty="hard"
                class="diff-card bg-white border-2 border-[#2D231E] rounded-2xl p-3 sm:p-4 text-center transition-all shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] hover:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] hover:translate-x-[2px] hover:translate-y-[2px] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)]">
                <div class="w-10 h-10 mx-auto rounded-xl bg-rose-100 border-2 border-[#2D231E] flex items-center justify-center mb-2">
                    <span class="font-pixel text-xs font-bold text-rose-600">H</span>
                </div>
                <h3 class="font-pixel text-[10px] font-bold text-[#2D231E] uppercase">Hard</h3>
                <p class="text-[10px] text-[#2D231E] font-semibold mt-1">Less time</p>
            </button>
        </div>
    </div>

    <!-- Continue Button -->
    <button id="btn-continue" disabled
        class="w-full min-h-[52px] font-pixel text-sm bg-[#E6DCCF] border-2 border-[#2D231E] text-[#8C7B70] px-6 py-3 rounded-2xl font-bold shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] transition-all uppercase cursor-not-allowed">
        Select options to continue
    </button>

    <!-- How to Play Modal -->
    <x-modal id="game-help-modal" title="How to Play" :pixel="true" :closeable="false">
        @if($game === 'match')
            @include('partials.game-help-match')
        @elseif($game === 'sentence')
            @include('partials.game-help-sentence')
        @else
            @include('partials.game-help-wordsearch')
        @endif
        <div class="flex items-center justify-center pt-1">
            <button type="button" data-modal-close class="min-h-[44px] px-5 font-pixel text-[9px] font-bold uppercase bg-white border-2 border-[#2D231E] rounded-xl shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all">GOT IT</button>
        </div>
    </x-modal>

    <!-- Story Picker Modal (wordsearch) -->
    <x-modal id="story-picker-modal" title="Choose a Story" :pixel="true" :wide="true">
        <div class="space-y-4">
            <input data-story-search type="search" placeholder="Search stories by title..."
                   class="w-full min-h-[44px] px-4 rounded-xl border-2 border-[#2D231E] bg-[#FAF6EE] text-sm font-semibold text-[#2D231E] placeholder-[#8C7B70] focus:outline-none focus:ring-2 focus:ring-amber-400" />
            <div data-story-grid class="grid grid-cols-2 sm:grid-cols-3 gap-3 max-h-[50vh] overflow-y-auto no-scrollbar pr-1">
                @forelse($pickableStories as $pick)
                    <button type="button" data-story-option data-story-id="{{ $pick['story']->id }}" data-story-title="{{ $pick['story']->title }}"
                            class="group text-left bg-white border-2 border-[#2D231E] rounded-2xl p-2.5 shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] hover:bg-[#FFF8E7] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all">
                        <div class="relative w-full aspect-[3/4] rounded-xl border-2 border-[#2D231E]/30 overflow-hidden bg-[#F3ECE0] flex items-center justify-center">
                            @if($pick['story']->cover_image)
                                <img src="{{ $pick['story']->coverUrl() }}" alt="" loading="lazy" decoding="async" class="absolute inset-0 w-full h-full object-cover" onerror="this.remove()">
                            @else
                                <x-icons.book-open class="font-semibold w-6 h-6 text-[#2D231E]" />
                            @endif
                            <span class="absolute bottom-1 right-1 bg-white/90 border border-[#2D231E] rounded px-1.5 py-0.5 font-pixel text-[7px] font-bold text-[#2D231E] uppercase">{{ $pick['word_count'] }} words</span>
                        </div>
                        <p class="mt-1.5 text-xs font-bold text-[#2D231E] leading-tight line-clamp-2">{{ $pick['story']->title }}</p>
                    </button>
                @empty
                    <p class="text-sm text-[#2D231E] font-semibold col-span-full text-center py-6">No pickable stories yet. Pick Saved words instead.</p>
                @endforelse
            </div>
        </div>
    </x-modal>

</div>
@endsection
