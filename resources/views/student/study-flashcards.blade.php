@extends('layouts.authenticated')

@section('title', 'Flashcards')

@section('page', 'study-flashcards')

@section('content')
<div class="min-h-[100dvh] max-w-md mx-auto w-full px-4 py-6 flex flex-col gap-4">

    <!-- Session header: back · Anki-style piles -->
    <div class="flex items-center justify-between gap-3">
        <a href="{{ route('study') }}" aria-label="Back to Study"
           class="w-10 h-10 flex items-center justify-center bg-white hover:bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl text-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all">
            <x-icons.arrow-left class="w-4 h-4" />
        </a>
        <div class="flex items-center gap-2" data-piles role="status" aria-live="polite" aria-label="Cards still to pass this session">
            <span data-pile-hard title="Hard this session"
                  class="min-w-[42px] px-2 py-1.5 rounded-xl bg-sky-500 border-2 border-[#2D231E] text-white font-pixel text-sm font-bold text-center shadow-[2px_2px_0px_0px_rgba(45,35,30,1)]">0</span>
            <span data-pile-forgot title="Forgot this session"
                  class="min-w-[42px] px-2 py-1.5 rounded-xl bg-rose-500 border-2 border-[#2D231E] text-white font-pixel text-sm font-bold text-center shadow-[2px_2px_0px_0px_rgba(45,35,30,1)]">0</span>
            <span data-pile-green title="Words left to pass"
                  class="min-w-[42px] px-2 py-1.5 rounded-xl bg-emerald-400 border-2 border-[#2D231E] text-[#2D231E] font-pixel text-sm font-bold text-center shadow-[2px_2px_0px_0px_rgba(45,35,30,1)]">{{ $cards->count() }}</span>
        </div>
        <span class="w-10"></span>
    </div>

    <noscript>
        <div class="bg-white border-2 border-[#2D231E] rounded-2xl p-6 text-center mb-4">
            <p class="text-sm font-bold text-[#2D231E]">Flashcards need JavaScript to run.</p>
        </div>
    </noscript>

    <!-- Card stage -->
    <div data-stage data-cards="{{ $cards->toJson() }}" class="hidden flex-1 flex flex-col justify-center">
        <section class="bg-white border-2 border-[#2D231E] rounded-2xl shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] p-4 sm:p-6">

            <!-- Tactile 3D flip card: meaning front, word back -->
            <div data-flip class="study-card relative h-[380px] select-none">
                <div class="study-card-inner relative h-full">

                    <!-- Front: meaning, guess the word -->
                    <div class="study-card-front bg-white rounded-xl border-2 border-[#E6DCCF] flex flex-col p-6 sm:p-8">
                        <div class="flex-1 flex flex-col items-center justify-center text-center">
                            <p class="font-pixel text-[9px] font-bold text-[#2D231E] uppercase tracking-wider">Meaning</p>
                            <p class="mt-3 inline-flex px-2.5 py-0.5 rounded-full font-pixel text-[8px] font-bold uppercase bg-sky-100 text-sky-700 border-2 border-sky-300" data-pos>&nbsp;</p>
                            <p class="mt-4 text-lg sm:text-xl font-extrabold text-[#2D231E] leading-relaxed break-words" data-definition>&nbsp;</p>
                        </div>
                        <div class="text-center">
                            <p class="text-xs font-semibold text-[#2D231E] italic leading-relaxed break-words" data-example>&nbsp;</p>
                            <p class="mt-4 text-[11px] font-bold text-[#2D231E]">Tap the card to reveal the word</p>
                        </div>
                    </div>

                    <!-- Back: the word + listen / practice -->
                    <div class="study-card-back bg-white rounded-xl border-2 border-[#2D231E] flex flex-col items-center justify-center gap-5 p-6 sm:p-8 text-center">
                        <div class="flex items-center gap-2.5">
                            <button type="button" data-say-prompt aria-label="Listen to the word"
                                    class="inline-flex items-center justify-center w-11 h-11 rounded-full bg-[#FAF6EE] hover:bg-[#F3ECE0] border-2 border-[#2D231E] text-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all cursor-pointer">
                                <x-icons.speaker class="w-5 h-5" />
                            </button>
                            <button type="button" data-say-modal-open aria-label="Practice saying the word"
                                    class="inline-flex items-center justify-center w-11 h-11 rounded-full bg-[#FAF6EE] hover:bg-[#F3ECE0] border-2 border-[#2D231E] text-emerald-700 shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all cursor-pointer">
                                <x-icons.microphone class="w-5 h-5" />
                            </button>
                        </div>
                        <h1 class="font-pixel text-3xl sm:text-4xl font-bold text-[#2D231E] break-words leading-tight" data-word>&nbsp;</h1>
                    </div>

                </div>
            </div>

            <!-- Reveal button -->
            <div class="mt-5">
                <button type="button" data-reveal
                        class="w-full min-h-[48px] inline-flex items-center justify-center gap-1.5 bg-amber-400 hover:bg-amber-300 border-2 border-[#2D231E] rounded-2xl font-pixel text-xs font-bold text-[#2D231E] uppercase shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all cursor-pointer">
                    <x-icons.eye class="w-4 h-4" /> Show answer
                </button>
            </div>

            <!-- Grading row (hidden until reveal) -->
            <div class="hidden grid grid-cols-4 gap-1.5 mt-3" data-grade-row>
                <button type="button" data-grade="again"
                        class="min-h-[56px] flex flex-col items-center justify-center gap-0.5 bg-rose-500 hover:bg-rose-600 border-2 border-[#2D231E] rounded-xl text-white font-pixel text-[8px] font-bold uppercase shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all cursor-pointer">
                    Forgot
                </button>
                <button type="button" data-grade="hard"
                        class="min-h-[56px] flex flex-col items-center justify-center gap-0.5 bg-sky-500 hover:bg-sky-600 border-2 border-[#2D231E] rounded-xl text-white font-pixel text-[8px] font-bold uppercase shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all cursor-pointer">
                    Hard
                </button>
                <button type="button" data-grade="good"
                        class="min-h-[56px] flex flex-col items-center justify-center gap-0.5 bg-emerald-500 hover:bg-emerald-600 border-2 border-[#2D231E] rounded-xl text-white font-pixel text-[8px] font-bold uppercase shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all cursor-pointer">
                    Good
                </button>
                <button type="button" data-grade="easy"
                        class="min-h-[56px] flex flex-col items-center justify-center gap-0.5 bg-amber-400 hover:bg-amber-300 border-2 border-[#2D231E] rounded-xl text-[#2D231E] font-pixel text-[8px] font-bold uppercase shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all cursor-pointer">
                    Easy
                </button>
            </div>

            <p class="hidden text-xs font-bold text-rose-600 text-center mt-2" data-grade-error>Couldn't save that - check your connection and try again.</p>
        </section>
    </div>

    <!-- Session end -->
    <section data-session-end class="hidden bg-white border-2 border-[#2D231E] rounded-2xl shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] p-8 text-center space-y-5">
        <x-icons.face-happy class="w-12 h-12 mx-auto text-emerald-500" />
        <h1 class="font-pixel text-xl font-bold text-[#2D231E] uppercase">Session complete!</h1>
        <div class="text-center space-y-1">
            <p class="font-pixel text-4xl font-bold text-emerald-600" data-end-remembered>0</p>
            <p class="font-pixel text-[9px] font-bold text-[#2D231E]/70 uppercase tracking-wider">words remembered</p>
        </div>
        <div class="flex justify-center gap-3">
            <div class="bg-sky-400 border-2 border-[#2D231E] rounded-xl px-5 py-3">
                <span class="block font-pixel text-lg font-bold text-[#2D231E]" data-end-hard>0</span>
                <span class="font-pixel text-[8px] font-bold text-[#2D231E]/70 uppercase">Hard</span>
            </div>
            <div class="bg-rose-400 border-2 border-[#2D231E] rounded-xl px-5 py-3">
                <span class="block font-pixel text-lg font-bold text-[#2D231E]" data-end-forgot>0</span>
                <span class="font-pixel text-[8px] font-bold text-[#2D231E]/70 uppercase">Forgot</span>
            </div>
        </div>
        <div data-tricky class="hidden space-y-2">
            <p class="font-pixel text-[9px] font-bold text-[#2D231E] uppercase tracking-wider">Trickiest words</p>
            <ul data-tricky-list class="flex flex-wrap justify-center gap-2"></ul>
        </div>
        <p class="text-xs font-semibold text-[#2D231E]">Words you found tricky today will return soon to lock them in.</p>
        <a href="{{ route('study') }}"
           class="inline-flex items-center justify-center w-full min-h-[48px] bg-amber-400 hover:bg-amber-300 border-2 border-[#2D231E] rounded-2xl font-pixel text-xs font-bold text-[#2D231E] shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all uppercase">
            Back to study
        </a>
    </section>

</div>

<!-- Say-it-out-loud popup: hosts the pronunciation check for the current card -->
<x-modal id="say-check-modal" title="SAY IT OUT LOUD" :closeable="true" pixel>
    <div class="space-y-3 text-center">
        <p class="font-pixel text-2xl font-bold text-[#2D231E] break-words" data-say-modal-word>&nbsp;</p>
        <p class="text-xs font-semibold text-[#2D231E]">Say the word out loud, then check how close you got.</p>
        <div class="flex justify-center py-1">
            @php
                // A saved card word that the dictionary cannot resolve - or that
                // is detected as a proper noun - is out-of-vocabulary for the
                // small offline speech model. Grade it gently instead of making
                // the student retry a name that can never pass.
                $cardWord = $cards->first()['word'] ?? '';
                $cardLookup = $cardWord !== '' ? \App\Support\Dictionary::lookup($cardWord) : [];
                $cardOov = (bool) ($cardLookup['found'] ?? true) === false || (bool) ($cardLookup['looks_like_name'] ?? false);
            @endphp
            <x-pronunciation-check :target="$cardWord" :oov="$cardOov" />
        </div>
    </div>
</x-modal>
@endsection