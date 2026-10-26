@extends('layouts.authenticated')

@section('page', 'quiz')

@php
    $letters = ['a', 'b', 'c', 'd'];
    $hintNames = [
        'mcq' => 'Crosses out one wrong answer',
        'identification' => 'Shows the first letter and the length',
        'matching' => 'Locks in one correct pair',
        'sequence' => 'Pins the first event for you',
    ];
@endphp

@section('content')
<div class="max-w-3xl mx-auto px-4 py-6 space-y-4">

    <div class="flex items-center gap-3">
        <button
            type="button"
            data-modal-open="quiz-exit-modal"
            class="w-10 h-10 shrink-0 bg-white border-2 border-[#2D231E] text-[#2D231E] rounded-xl font-bold flex items-center justify-center shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-none transition-all"
            aria-label="Leave the quiz"
        >
            <x-icons.arrow-left class="w-4 h-4" />
        </button>
        <div class="min-w-0">
            <p class="font-pixel text-[8px] font-bold uppercase tracking-widest text-emerald-700">QUIZ</p>
            <h1 class="text-sm sm:text-base font-bold leading-tight truncate">{{ $story->title }}</h1>
        </div>
    </div>

    <x-modal id="quiz-hint-modal" title="USE A HINT?" pixel>
        <p class="text-sm font-semibold leading-relaxed text-[#2D231E]/80 text-center" data-hint-confirm-copy></p>
        <div class="flex justify-center">
            <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-emerald-100 border-2 border-[#2D231E] rounded-md text-emerald-700 text-xs font-bold [&>svg]:h-4 [&>svg]:w-4" title="Your keys">
                {!! \App\Support\LibraryPresentation::iconGlyph('ui', 'key') !!} <span data-slot="confirm-balance">0</span>
            </span>
        </div>
        <button type="button" data-hint-confirm class="hint-confirm-btn w-full min-h-[46px] inline-flex items-center justify-center gap-[7px] px-2.5 py-3 font-pixel text-[10px] tracking-[0.08em] font-bold uppercase text-white bg-emerald-600 hover:bg-emerald-700 border-2 border-[#2D231E] rounded-[10px] shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-none transition-all [&>svg]:w-[13px] [&>svg]:h-[13px]">SPEND {!! \App\Support\LibraryPresentation::iconGlyph('ui', 'key') !!}<span data-hint-confirm-cost>{{ $hintCost }}</span></button>
    </x-modal>

    <x-modal id="quiz-exit-modal" title="LEAVE THE QUIZ?" pixel>
        <p class="text-sm font-semibold text-[#2D231E]/70 text-center">Your answers are not saved yet, so leaving means starting over. Your question order and hints stay for next time.</p>
        <div class="flex items-center justify-center gap-3 pt-1">
            <button type="button" data-modal-close class="min-h-[44px] px-5 font-pixel text-[9px] font-bold uppercase bg-white border-2 border-[#2D231E] rounded-xl shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all">STAY</button>
            <a href="{{ route('story.preview', $story) }}" class="min-h-[44px] inline-flex items-center px-5 font-pixel text-[9px] font-bold uppercase bg-rose-600 text-white hover:bg-rose-700 border-2 border-[#2D231E] rounded-xl shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all">LEAVE</a>
        </div>
    </x-modal>

    <x-modal id="quiz-submit-modal" title="HAND IN QUIZ?" pixel>
        <p class="text-sm font-semibold text-[#2D231E]/70 text-center">This hands your quiz in for grading. You cannot change any answers after.</p>
        <div class="flex items-center justify-center gap-3 pt-1">
            <button type="button" data-modal-close class="min-h-[44px] px-5 font-pixel text-[9px] font-bold uppercase bg-white border-2 border-[#2D231E] rounded-xl shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all">NOT YET</button>
            <button type="button" id="quiz-submit-confirm" class="min-h-[44px] px-5 font-pixel text-[9px] font-bold uppercase text-white bg-emerald-600 hover:bg-emerald-700 border-2 border-[#2D231E] rounded-xl shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all">HAND IN</button>
        </div>
    </x-modal>

    <div
        id="quiz-main"
        data-attempt="{{ request()->session()->get("quiz_session.{$story->id}.uuid") }}"
        data-submit-url="{{ route('reader.quiz.submit', $story) }}"
        data-hint-url="{{ route('reader.quiz.hint', $story) }}"
        data-balance="{{ $balance }}"
        data-hints-used="{{ $hintsUsed }}"
        data-hints-max="{{ $hintsMax }}"
        data-hint-cost="{{ $hintCost }}"
        data-hints-applied="{{ json_encode((object) ($hintsApplied ?? [])) }}"
        class="space-y-4"
    >

        <div class="bg-white border-4 border-[#2D231E] rounded-3xl shadow-[5px_5px_0px_0px_rgba(45,35,30,1)] overflow-hidden">

            <div class="relative flex items-center justify-between gap-3 px-4 sm:px-6 py-3 bg-[#FAF6EE] border-b-2 border-dashed border-[#2D231E]/30">
                <div class="h-4 flex-1 bg-white border-2 border-[#2D231E] rounded-full overflow-hidden">
                    <div id="quiz-progress" class="h-full bg-emerald-500 border-r-2 border-[#2D231E] transition-all" style="width: {{ $total > 0 ? floor(100 / $total) : 0 }}%"></div>
                </div>
                <div class="flex items-center gap-2">
                    <span id="quiz-counter" class="font-pixel text-[9px] whitespace-nowrap">1/{{ $total }}</span>
                    <button
                        id="quiz-hint-btn"
                        type="button"
                        data-hint
                        aria-label="Use a hint"
                        disabled
                        class="w-10 h-10 inline-flex items-center justify-center bg-amber-100 border-2 border-[#2D231E] rounded-xl text-amber-700 shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] transition-all disabled:opacity-40 disabled:pointer-events-none"
                    >
                        <span class="w-4 h-4 text-amber-700 [&>svg]:h-full [&>svg]:w-full">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'light-bulb') !!}</span>
                    </button>
                </div>

                <button
                    id="quiz-hint-chip"
                    type="button"
                    hidden
                    class="absolute right-4 top-[calc(100%+8px)] z-10 inline-flex items-center gap-1.5 min-h-[40px] px-3 font-pixel text-[8px] font-bold uppercase text-amber-700 bg-amber-100 border-2 border-[#2D231E] rounded-xl shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all"
                >
                    <span class="w-3.5 h-3.5 text-amber-700 [&>svg]:h-full [&>svg]:w-full">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'light-bulb') !!}</span>
                    Need help?
                </button>
            </div>

            <div id="quiz-paper" class="px-5 sm:px-10 py-8 min-h-[46vh] bg-[#FFFDF7]">

                @foreach ($panels as $i => $panel)
                    <article
                        data-qpanel
                        data-qid="{{ $panel['id'] }}"
                        data-type="{{ $panel['type'] }}"
                        @if ($panel['type'] !== 'true_false')
                            data-hintable
                            data-hint-name="{{ $hintNames[$panel['type']] ?? 'Reveal help for this question.' }}"
                        @endif
                        @if ($i !== 0) hidden @endif
                    >
                        <p class="font-pixel text-[8px] font-bold uppercase tracking-widest text-emerald-700">{{ $panel['section'] }}</p>
                        <h2 class="text-xl sm:text-2xl font-bold leading-snug mt-2 mb-6">{{ $panel['text'] }}</h2>

                        @if ($panel['type'] === 'mcq')
                            <div class="grid gap-3">
                                @foreach ($panel['options'] as $option)
                                    @php
                                        // Badge reads A/B/C/D top-to-bottom (screen position),
                                        // while data-qoption keeps the true stored letter that
                                        // drives submit/hint-strike/grading (content re-orders
                                        // on shuffle, the grading letter travels with it).
                                        $badge = strtoupper(chr(ord('a') + $loop->index));
                                    @endphp
                                    <button type="button" data-qoption="{{ $option['letter'] }}" class="q-option group text-left">
                                        <span class="q-letter">{{ $badge }}</span>
                                        <span class="flex-1 font-semibold leading-snug">{{ $option['text'] }}</span>
                                        <span class="q-strike-mark" hidden>✕</span>
                                    </button>
                                @endforeach
                            </div>
                        @elseif ($panel['type'] === 'true_false')
                            <div class="grid grid-cols-2 gap-4">
                                <button type="button" data-tf="a" class="q-big">TRUE</button>
                                <button type="button" data-tf="b" class="q-big">FALSE</button>
                            </div>
                        @elseif ($panel['type'] === 'identification')
                            <p
                                data-hint-clue
                                hidden
                                class="mx-auto max-w-sm px-4 py-2.5 text-sm font-bold text-amber-800 bg-amber-100 border-2 border-amber-300 rounded-xl inline-flex items-center justify-center gap-2"
                            >
                                <span class="w-4 h-4 text-amber-700 [&>svg]:h-full [&>svg]:w-full">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'light-bulb') !!}</span>
                                <span data-hint-clue-text></span>
                            </p>
                            <input
                                type="text"
                                data-ident
                                autocomplete="off"
                                autocapitalize="none"
                                spellcheck="false"
                                maxlength="120"
                                placeholder="Type your answer"
                                class="mt-3 w-full min-h-[56px] px-4 text-xl font-bold text-center bg-white border-2 border-[#2D231E] rounded-2xl focus:outline-none focus:border-emerald-600"
                            >
                            <p class="text-center text-xs italic text-[#2D231E]/60 mt-4">Spelling counts. Capitals don't.</p>
                        @elseif ($panel['type'] === 'matching')
                            <div data-match>
                                <div class="grid grid-cols-2 gap-4 sm:gap-8">
                                    <ul class="space-y-3">
                                        @foreach ($panel['lefts'] as $li => $left)
                                            <li><button type="button" data-chip data-side="left" data-idx="{{ $li }}" class="q-chip w-full">{{ $left }}<span class="pair-badge" hidden></span></button></li>
                                        @endforeach
                                    </ul>
                                    <ul class="space-y-3">
                                        @foreach ($panel['rights'] as $ri => $right)
                                            <li><button type="button" data-chip data-side="right" data-idx="{{ $ri }}" class="q-chip w-full">{{ $right }}<span class="pair-badge" hidden></span></button></li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                            <p class="text-xs italic text-[#2D231E]/60 mt-4 text-center">Tap a card on the left, then its partner on the right. They lock in with a colour. Tap a locked card to undo.</p>
                        @elseif ($panel['type'] === 'sequence')
                            <div data-seq-pool class="grid gap-2.5">
                                @foreach ($panel['scrambled'] as $si => $item)
                                    <button type="button" data-seq-item data-idx="{{ $si }}" class="q-chip w-full">
                                        <span class="seq-num" hidden aria-hidden="true"></span>
                                        <span class="flex-1 font-semibold leading-snug">{{ $item }}</span>
                                    </button>
                                @endforeach
                            </div>
                            <ol data-seq-strip class="mt-5 space-y-2 empty:hidden" aria-label="Your answer, first event on top"></ol>
                            <p class="text-xs italic text-[#2D231E]/60 mt-4 text-center">Tap the events in the order they happened. Tap one in your list to send it back.</p>
                        @endif
                    </article>
                @endforeach
            </div>

            <!-- Footer: dots on top (mobile), action below; one row from sm up -->
            <div id="quiz-nav" class="px-4 sm:px-6 py-3 bg-[#FAF6EE] border-t-2 border-dashed border-[#2D231E]/30 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                <div id="quiz-dots" class="flex flex-wrap items-center justify-center gap-1.5 sm:order-1 sm:justify-start" aria-hidden="true">
                    @for ($i = 0; $i < $total; $i++)
                        <span class="w-2.5 h-2.5 rounded-full border-2 border-[#2D231E] {{ $i === 0 ? 'bg-amber-400' : 'bg-white' }}"></span>
                    @endfor
                </div>

                <div class="flex gap-2 sm:contents">
                    <button type="button" id="quiz-next" disabled class="w-full sm:w-auto min-h-[44px] px-4 inline-flex items-center justify-center gap-2 font-pixel text-[9px] font-bold uppercase text-white bg-emerald-600 hover:bg-emerald-700 border-2 border-[#2D231E] rounded-xl shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all disabled:opacity-40 disabled:pointer-events-none sm:order-2">
                        NEXT
                    </button>
                    <button type="button" id="quiz-submit" hidden class="w-full sm:w-auto min-h-[44px] px-5 inline-flex items-center justify-center gap-2 font-pixel text-[9px] font-bold uppercase text-white bg-emerald-600 hover:bg-emerald-700 border-2 border-[#2D231E] rounded-xl shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all disabled:opacity-50 sm:order-3">
                        SUBMIT
                    </button>
                </div>
            </div>
        </div>

        <p id="quiz-note" class="text-center text-xs font-semibold text-[#2D231E]/60 min-h-[1rem]"></p>
    </div>
</div>
@endsection
