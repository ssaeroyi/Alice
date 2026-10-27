@extends('layouts.authenticated')

@section('page', 'game-wordsearch')

@section('content')
<style>
    .ws-cell {
        display: flex; align-items: center; justify-content: center;
        width: 100%; aspect-ratio: 1;
        font-family: 'Press Start 2P', monospace; font-size: 12px; font-weight: 700;
        color: #2D231E; background: #FAF6EE;
        border: 1px solid #E6DCCF; border-radius: 0.25rem;
        cursor: pointer; user-select: none; -webkit-user-select: none;
        transition: background 0.15s ease, color 0.15s ease;
    }
    .ws-cell.selected { background: rgba(245,158,11,0.3); }
    .ws-cell.found { background: #D1FAE5; color: #10B981; }
    .ws-cell.missed { background: #FEE2E2; color: #F43F5E; }
    #ws-grid { touch-action: none; }

    /* Desktop word rows: full-width, flush, 44px touch targets (no ragged pills) */
    @media (min-width: 1024px) {
        #word-list > span {
            display: flex; align-items: center;
            width: 100%; min-height: 44px;
            border: 0; border-radius: 0;
            font-size: 13px; overflow-wrap: anywhere;
        }
        #word-list > span:hover { background: #FAF6EE; }
    }
</style>

<div class="max-w-2xl lg:max-w-5xl mx-auto px-4 py-4 space-y-4"
     data-game-words="{{ json_encode($words) }}"
     data-time-multiplier="{{ $timeMultiplier }}"
     data-base-time="{{ $baseTime }}"
     data-difficulty="{{ $difficulty }}"
     data-source="{{ $source }}">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <a href="{{ route('game.setup', ['game' => 'wordsearch']) }}" aria-label="Back to setup" id="btn-back"
           class="w-10 h-10 flex items-center justify-center bg-white border-2 border-[#2D231E] rounded-xl text-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all">
            <x-icons.arrow-left class="w-4 h-4" />
        </a>
        <div class="flex flex-col items-end gap-0.5">
            <span class="font-pixel text-[8px] font-bold uppercase tracking-wider text-[#2D231E]">Score</span>
            <span id="score-display" class="bg-amber-400 border-2 border-[#2D231E] rounded-full px-3 py-1 font-pixel text-xs font-bold text-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)]">0</span>
        </div>
    </div>

    @if($source === 'open' && ! empty($storyTitle))
    <div class="text-center -mt-1">
        <span class="text-[11px] font-semibold text-[#2D231E] italic">Words from: {{ $storyTitle }}</span>
    </div>
    @endif

    <!-- Timer Bar -->
    <div class="w-full bg-[#F3ECE0] rounded-full h-2 border border-[#2D231E]/20">
        <div id="timer-bar" class="game-timer-bar h-full rounded-full" style="width:100%"></div>
    </div>

    <!-- Timer + Progress -->
    <div class="flex items-center justify-between gap-2">
        <span id="timer-display" class="font-pixel text-xs text-[#2D231E]">2:00</span>
        <span id="word-progress" class="font-pixel text-xs text-[#2D231E]">0 / 10</span>
    </div>

    <!-- Combo + Hint + Help (own row so pips never collide on mobile) -->
    <div class="flex items-center justify-between gap-2 min-h-[44px]">
        <div class="flex items-center gap-2 min-w-0 flex-wrap">
            <span id="combo-pips" class="game-pips hidden"></span>
            <span data-combo-readout aria-live="polite" class="hidden items-center gap-0.5 font-pixel text-[9px] font-bold text-amber-600 whitespace-nowrap">COMBO <b>×2</b><span data-combo-max-tag class="text-rose-600" hidden>MAX ×2</span></span>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <button id="btn-hint" type="button" aria-label="Use a hint"
                    class="min-w-[44px] min-h-[44px] flex items-center justify-center bg-amber-100 border-2 border-[#2D231E] rounded-xl font-pixel text-[10px] font-bold text-amber-700 shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] transition-all uppercase">
                <span class="w-4 h-4 text-amber-700 [&>svg]:h-full [&>svg]:w-full">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'light-bulb') !!}</span>
            </button>
            <button id="btn-help" type="button" aria-label="How to play"
                    class="min-w-[44px] min-h-[44px] flex items-center justify-center bg-white border-2 border-[#2D231E] rounded-xl font-pixel text-[10px] font-bold text-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] transition-all uppercase">
                ?
            </button>
        </div>
    </div>

    <!-- Game Board Card + Words panel: side-by-side on desktop, stacked on phones -->
    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_18rem]">
        <!-- Game Board Card -->
        <div class="bg-white border-2 border-[#2D231E] rounded-2xl p-3 sm:p-4 shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]">
            <div class="relative">
                <div id="ws-grid" class="grid gap-[2px]" style="grid-template-columns: repeat(10, 1fr);">
                    <!-- Cells injected by JS -->
                </div>
                <svg id="ws-line-svg" class="absolute inset-0 w-full h-full pointer-events-none" style="z-index:10;"></svg>
            </div>
        </div>

        <!-- Word List Card -->
        <div class="bg-white border-2 border-[#2D231E] rounded-2xl p-4 shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] flex flex-col">
            <div class="pb-3 mb-3 border-b-2 border-[#2D231E]/10">
                <span class="font-pixel text-[11px] font-bold uppercase tracking-wider text-[#2D231E]">Words</span>
                @if($source === 'open' && ! empty($storyTitle))
                <span class="block mt-1.5 font-pixel text-[8px] leading-relaxed uppercase tracking-wider text-[#2D231E]">
                    Vocabulary from: {{ $storyTitle }}
                </span>
                @endif
            </div>

            <div id="word-list" class="flex flex-wrap justify-center gap-2 flex-1 lg:flex-col lg:items-center lg:gap-2">
                <!-- Word badges injected by JS -->
            </div>
        </div>
    </div>

    <!-- Status -->
    <!-- Status + Alice companion -->
    <div class="flex items-center justify-center gap-3 lg:pr-[19rem]">
        <div id="game-mascot" data-mascot data-skin='@json($equippedAlice)' aria-hidden="true"></div>
        <p id="game-status" class="text-center text-sm font-bold text-[#2D231E] uppercase">
            Swipe across letters to find words
        </p>
    </div>

    <!-- Help Modal -->
    <x-modal id="game-help-modal" title="How to Play" :pixel="true" :closeable="false">
        @include('partials.game-help-wordsearch')
        <div class="flex items-center justify-center pt-1">
            <button type="button" data-modal-close class="min-h-[44px] px-5 font-pixel text-[9px] font-bold uppercase bg-white border-2 border-[#2D231E] rounded-xl shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all">GOT IT</button>
        </div>
    </x-modal>

    <!-- Hint Modal -->
    <x-modal id="hint-modal" title="Use a hint?" :pixel="true" data-hint-cost="{{ $hintCost }}" data-hint-balance="{{ $keys }}">
        <p class="text-sm font-semibold leading-relaxed text-[#2D231E]/80 text-center">A hint highlights one word's location.</p>
        <div class="flex justify-center">
            <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-emerald-100 border-2 border-[#2D231E] rounded-md text-emerald-700 text-xs font-bold [&>svg]:h-4 [&>svg]:w-4" title="Your keys">
                {!! \App\Support\LibraryPresentation::iconGlyph('ui', 'key') !!} <span data-slot="hint-balance">{{ $keys }}</span>
            </span>
        </div>
        <button type="button" id="btn-hint-confirm" class="hint-confirm-btn w-full min-h-[46px] inline-flex items-center justify-center gap-[7px] px-2.5 py-3 font-pixel text-[10px] tracking-[0.08em] font-bold uppercase text-white bg-emerald-600 hover:bg-emerald-700 border-2 border-[#2D231E] rounded-[10px] shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-none transition-all [&>svg]:w-[13px] [&>svg]:h-[13px]">USE A HINT {!! \App\Support\LibraryPresentation::iconGlyph('ui', 'key') !!}<span>{{ $hintCost }}</span></button>
    </x-modal>

    <!-- Quit Modal -->
    <x-modal id="quit-modal" title="Quit game?" :pixel="true">
        <p class="text-sm text-[#2D231E] font-semibold text-center">Your progress this round will be lost.</p>
        <div class="flex gap-2">
            <button id="btn-quit-cancel" data-modal-close
                    class="flex-1 min-h-[44px] bg-white border-2 border-[#2D231E] text-[#2D231E] font-pixel text-xs px-4 py-3 rounded-2xl font-bold shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] transition-all uppercase cursor-pointer">
                Keep Playing
            </button>
            <a id="btn-quit-confirm" href="{{ route('game.setup', ['game' => 'wordsearch']) }}"
               class="flex-1 min-h-[44px] flex items-center justify-center bg-rose-400 border-2 border-[#2D231E] text-[#2D231E] font-pixel text-xs px-4 py-3 rounded-2xl font-bold shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] transition-all uppercase">
                Quit
            </a>
        </div>
    </x-modal>

    <!-- Result Screen (full-screen takeover) -->
    <div id="result-screen" class="hidden fixed inset-0 z-40 bg-[#FAF6EE] flex items-center justify-center p-4 overflow-y-auto">
        <div class="max-w-sm w-full bg-white border-4 border-[#2D231E] rounded-3xl p-6 text-center shadow-[6px_6px_0px_0px_rgba(45,35,30,1)] space-y-4">
            <div class="w-20 h-20 mx-auto rounded-2xl bg-amber-400 border-4 border-[#2D231E] flex items-center justify-center shadow-[4px_4px_0px_0px_rgba(45,35,30,1)]">
                <span class="text-[#2D231E] [&>svg]:h-9 [&>svg]:w-9">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'magnifying-glass') !!}</span>
            </div>
            <h2 id="result-title" class="font-pixel text-base font-bold text-[#2D231E] uppercase"></h2>
            <p id="result-subtitle" class="text-sm text-[#2D231E] font-semibold"></p>
            <div id="result-stats" class="grid grid-cols-2 gap-3 text-sm font-bold text-[#2D231E]"></div>

            <div id="result-missed" class="hidden bg-[#FEF2F2] border-2 border-rose-300 rounded-xl p-3">
                <p class="font-pixel text-[10px] font-bold text-rose-600 uppercase mb-2">Words still to find</p>
                <div id="result-missed-list" class="flex flex-wrap gap-2 justify-center"></div>
            </div>

            <div class="flex flex-col gap-2 pt-2">
                <button id="btn-replay"
                        class="w-full min-h-[44px] bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] font-pixel text-xs px-4 py-3 rounded-2xl font-bold shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all uppercase cursor-pointer">
                    Play Again
                </button>
                <a href="{{ route('mini-game') }}"
                   class="w-full min-h-[44px] flex items-center justify-center bg-white border-2 border-[#2D231E] text-[#2D231E] font-pixel text-xs px-4 py-3 rounded-2xl font-bold shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all uppercase">
                    Back to Menu
                </a>
            </div>
        </div>
    </div>

</div>
@endsection
