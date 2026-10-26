@extends('layouts.authenticated')

@section('page', 'game-sentence')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-4 space-y-4"
    data-game-words="{{ json_encode($words) }}"
    data-time-multiplier="{{ $timeMultiplier }}"
    data-base-time="{{ $baseTime }}"
    data-difficulty="{{ $difficulty }}"
    data-source="{{ $source }}"
    data-saved-words="{{ json_encode($savedWords ?? []) }}">

    <!-- Header -->
    <div class="flex items-center justify-between gap-2">
        <a href="{{ route('game.setup', ['game' => 'sentence']) }}" aria-label="Back to setup" id="btn-back"
            class="w-10 h-10 flex items-center justify-center bg-white border-2 border-[#2D231E] text-[#2D231E] rounded-xl shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all shrink-0">
<x-icons.arrow-left class="w-4 h-4" />
            </a>
            <div class="flex flex-col items-end gap-0.5">
                <span class="font-pixel text-[8px] font-bold uppercase tracking-wider text-[#2D231E]">Score</span>
                <span id="score-display" class="bg-amber-400 border-2 border-[#2D231E] rounded-full px-3 py-1 font-pixel text-xs font-bold text-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] whitespace-nowrap">0 points</span>
            </div>
        </div>

    <!-- Timer Bar -->
    <div class="w-full bg-[#F3ECE0] rounded-full h-2 border border-[#2D231E]/20">
        <div id="timer-bar" class="game-timer-bar h-full rounded-full" style="width:100%"></div>
    </div>

    <!-- Timer + Progress -->
    <div class="flex items-center justify-between gap-2">
        <span id="timer-display" class="font-pixel text-xs text-[#2D231E]">0:30</span>
        <span id="word-progress" class="font-pixel text-xs text-[#2D231E]">1 / 5</span>
    </div>

    <!-- Combo + Hint + Help (own row so pips never collide on mobile) -->
    <div class="flex items-center justify-between gap-2 min-h-[44px]">
        <div class="flex items-center gap-2 min-w-0 flex-wrap">
            <span id="combo-pips" class="game-pips hidden"></span>
            <span data-combo-readout aria-live="polite" class="hidden items-center gap-0.5 font-pixel text-[9px] font-bold text-amber-600 whitespace-nowrap">COMBO <b>×2</b><span data-combo-max-tag class="text-rose-600" hidden>MAX ×2</span></span>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <button id="btn-hint" type="button" aria-label="Use a hint"
                class="min-w-[44px] min-h-[44px] flex items-center justify-center gap-1 bg-amber-100 border-2 border-[#2D231E] rounded-xl font-pixel text-[10px] font-bold text-amber-700 shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] transition-all uppercase px-2">
                <span class="w-4 h-4 text-amber-700 [&>svg]:h-full [&>svg]:w-full">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'light-bulb') !!}</span>
            </button>
            <button id="btn-help" type="button" aria-label="How to play"
                class="min-w-[44px] min-h-[44px] flex items-center justify-center bg-white border-2 border-[#2D231E] rounded-xl font-pixel text-[10px] font-bold text-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] transition-all uppercase">
                ?
            </button>
        </div>
    </div>

    <!-- Hint -->
    <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-4 shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]">
        <p id="sentence-hint" class="text-sm font-bold text-[#2D231E] text-center leading-relaxed italic"></p>
    </div>

    <!-- Answer Slots (broken sentence) -->
    <div class="bg-white border-2 border-[#2D231E] rounded-2xl p-4 shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]">
        <p class="font-pixel text-[10px] text-[#2D231E] uppercase mb-3 text-center">Complete the sentence</p>
        <div id="answer-slots" class="flex items-center justify-center gap-2 flex-wrap min-h-[60px]"></div>
    </div>

    <!-- Word Blocks -->
    <div class="bg-white border-2 border-[#2D231E] rounded-2xl p-4 shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]">
        <p class="font-pixel text-[10px] text-[#2D231E] uppercase mb-3 text-center">Word Blocks</p>
        <div id="word-blocks" class="flex items-center justify-center gap-2 flex-wrap min-h-[60px]"></div>
    </div>

    <!-- Actions -->
    <div class="flex items-center justify-center gap-3">
        <button id="btn-clear"
            class="min-h-[44px] bg-white border-2 border-[#2D231E] text-[#2D231E] font-pixel text-xs px-5 py-2.5 rounded-2xl font-bold shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all uppercase cursor-pointer hidden">
            Clear
        </button>
        <button id="btn-submit"
            class="min-h-[44px] bg-emerald-400 border-2 border-[#2D231E] text-[#2D231E] font-pixel text-xs px-5 py-2.5 rounded-2xl font-bold shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all uppercase cursor-pointer hidden">
            Submit
        </button>
    </div>

    <!-- Status -->
    <p id="game-status" class="text-center text-sm font-bold text-[#2D231E] uppercase">
        Fill in the missing words
    </p>

    <!-- Help Modal -->
    <x-modal id="game-help-modal" title="Sentence Builder" :pixel="true" :closeable="false">
        @include('partials.game-help-sentence')
        <div class="flex items-center justify-center pt-1">
            <button type="button" data-modal-close class="min-h-[44px] px-5 font-pixel text-[9px] font-bold uppercase bg-white border-2 border-[#2D231E] rounded-xl shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all">GOT IT</button>
        </div>
    </x-modal>

    <!-- Hint Modal -->
    <x-modal id="hint-modal" title="Use a hint?" :pixel="true" data-hint-cost="{{ $hintCost }}" data-hint-balance="{{ $keys }}">
        <p class="text-sm font-semibold leading-relaxed text-[#2D231E]/80 text-center">A hint fills in one missing word.</p>
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
            <a id="btn-quit-confirm" href="{{ route('game.setup', ['game' => 'sentence']) }}"
               class="flex-1 min-h-[44px] flex items-center justify-center bg-rose-400 border-2 border-[#2D231E] text-[#2D231E] font-pixel text-xs px-4 py-3 rounded-2xl font-bold shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] transition-all uppercase">
                Quit
            </a>
        </div>
    </x-modal>

    <!-- Result Screen (full-screen takeover) -->
    <div id="result-screen" class="hidden fixed inset-0 z-40 bg-[#FAF6EE] flex items-center justify-center p-4 overflow-y-auto">
        <div class="max-w-sm w-full bg-white border-4 border-[#2D231E] rounded-3xl p-6 text-center shadow-[6px_6px_0px_0px_rgba(45,35,30,1)] space-y-4">
            <div class="w-20 h-20 mx-auto rounded-2xl bg-amber-400 border-4 border-[#2D231E] flex items-center justify-center shadow-[4px_4px_0px_0px_rgba(45,35,30,1)]">
                <span class="text-[#2D231E] [&>svg]:h-9 [&>svg]:w-9">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'book-pile') !!}</span>
            </div>
            <h2 id="result-title" class="font-pixel text-base font-bold text-[#2D231E] uppercase"></h2>
            <p id="result-subtitle" class="text-sm text-[#2D231E] font-semibold"></p>
            <div id="result-stats" class="grid grid-cols-2 gap-3 text-sm font-bold text-[#2D231E]"></div>

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

{{-- Word-definition pane --}}
<div id="word-popup" hidden class="fixed z-50 w-[19rem] max-w-[calc(100vw-2rem)] bg-white border-4 border-[#2D231E] rounded-2xl shadow-[5px_5px_0px_0px_rgba(45,35,30,1)] overflow-hidden">
    <div class="flex items-start justify-between gap-2 px-4 pt-3 pb-2">
        <div class="min-w-0">
            <p class="font-pixel text-[11px] font-bold truncate" data-slot="lemma"></p>
            <p class="text-xs italic text-[#2D231E]/60 truncate" data-slot="tapped"></p>
        </div>
        <span class="shrink-0 font-pixel text-[8px] font-bold px-1.5 py-0.5 rounded-md border-2 border-[#2D231E]" data-slot="badge" hidden></span>
    </div>
    <div class="px-4 pb-3 space-y-2 text-sm max-h-[40dvh] overflow-y-auto" data-slot="senses"></div>
    <div class="flex items-stretch gap-1.5 p-1.5 border-t-2 border-[#2D231E]">
        <button type="button" data-slot="say" class="flex-1 min-h-[38px] rounded-lg inline-flex items-center justify-center gap-1.5 font-pixel text-[9px] font-bold uppercase bg-[#FAF6EE] active:bg-amber-100 transition-colors">
            SAY IT
        </button>
        <button type="button" data-slot="save" class="flex-1 min-h-[38px] rounded-lg inline-flex items-center justify-center gap-1.5 font-pixel text-[9px] font-bold uppercase bg-emerald-600 text-white hover:bg-emerald-700 disabled:bg-[#2D231E]/20 disabled:text-[#2D231E]/50 transition-colors"></button>
    </div>
</div>
@endsection
