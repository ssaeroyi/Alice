@extends('layouts.placement')

@section('js')
    @vite(['resources/js/app.js'])
@endsection

@section('content')
<!-- Background Canvas -->
<div class="max-w-6xl mx-auto px-4 py-12 min-h-[75vh] flex items-center justify-center relative">

    <!-- Centered Setup Container -->
    <div class="w-full max-w-2xl bg-white border-4 border-[#2D231E] rounded-3xl p-6 sm:p-8 shadow-[6px_6px_0px_0px_rgba(45,35,30,1)] space-y-6">

        <!-- Placement Progress (above ALICE, step 1 of 4 style) -->
        <div class="border-b-2 border-dashed border-[#E6DCCF] pb-4 space-y-3">
            <div class="flex items-center justify-between">
                <div class="inline-flex items-center gap-2 font-pixel text-[10px] sm:text-xs bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] px-3.5 py-1.5 rounded-full font-bold shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] uppercase tracking-wider">
                    <span>STEP 2 OF 4</span>
                </div>
            </div>
            <div class="h-2 rounded-full bg-[#F3ECE0] overflow-hidden">
                <div class="h-full w-2/4 rounded-full bg-amber-400"></div>
            </div>
        </div>

        <!-- Conversational Dialogue Box with ALICE Radial Shadow (static guide) -->
        <div class="flex items-center gap-3 sm:gap-4 text-left">

            <!-- ALICE Mascot with Radial Shadow -->
            <div class="shrink-0 flex items-center justify-center relative">
                <!-- Dark Soft Radial Glow -->
                <div class="absolute w-12 h-12 sm:w-20 sm:h-20 rounded-full bg-[#2D231E]/20 blur-md"></div>

                <!-- Character Sprite -->
                <img
                    src="{{ asset('image/Alice/Light mode/alice-lt-talking.png') }}"
                    width="1280" height="1280"
                    alt="ALICE"
                    class="relative z-10 w-14 h-14 sm:w-24 sm:h-24 object-contain scale-110 drop-shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]"
                >
            </div>

            <!-- Hovering Speech Bubble -->
            <div class="relative flex-1 w-full min-w-0 bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-4 sm:p-5 shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]">

                <!-- Left Speech Tail (Always points left to ALICE) -->
                <div class="absolute -left-3 top-1/2 -translate-y-1/2 w-5 h-5 bg-[#FAF6EE] border-l-2 border-b-2 border-[#2D231E] rotate-45"></div>

                <!-- Speech Content -->
                <p class="text-xs sm:text-sm font-bold text-[#2D231E] leading-relaxed text-left">
                    Here we go! This is a <span class="text-amber-600 underline decoration-2">vocabulary test</span> with 10 questions. Pick the answer that matches each word best.
                </p>
            </div>

        </div>

        <!-- Vocabulary Check Form (one word per screen, quiz pager) -->
        <form
            id="vocab-quiz"
            action="{{ route('placement.step2.submit') }}"
            method="POST"
            data-submit-guard
            class="bg-white border-4 border-[#2D231E] rounded-3xl shadow-[5px_5px_0px_0px_rgba(45,35,30,1)] overflow-hidden"
        >
            @csrf

            @if ($errors->any())
                <div class="bg-rose-50 border-b-2 border-rose-400 text-rose-700 text-xs font-bold px-5 sm:px-6 py-3">
                    {{ $errors->first() }}
                </div>
            @endif

            <!-- Paper: the current word -->
            <div id="vocab-paper" class="px-5 sm:px-8 py-6 sm:py-7 min-h-[44vh] bg-[#FFFDF7]">
                @foreach ($vocab as $index => $item)
                    <article class="space-y-4" data-vpanel @unless($loop->first) hidden @endunless>
                        <p class="font-pixel text-[10px] sm:text-xs font-bold text-[#8C7B70] uppercase tracking-wider">
                            WORD {{ $index + 1 }} OF {{ count($vocab) }}
                        </p>
                        <p class="text-sm sm:text-base font-extrabold text-[#2D231E] leading-snug">
                            What is the closest meaning of <span class="text-amber-600 underline decoration-2">{{ $item['word'] }}</span>?
                        </p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-1">
                            @foreach ($item['options'] as $optionIndex => $option)
                                @php $letter = chr(65 + $optionIndex); @endphp
                                <label class="flex items-center gap-3 p-3 bg-white border-2 border-[#2D231E] rounded-xl cursor-pointer hover:bg-amber-50 transition-colors min-h-[44px]">
                                    <input type="radio" name="vocab_{{ $index + 1 }}" value="{{ $option }}" @checked(old("vocab_".($index + 1), $saved[$index + 1] ?? null) === $option) class="accent-amber-500 w-4 h-4">
                                    <span class="text-xs font-bold text-[#2D231E]">{{ $letter }}) {{ $option }}</span>
                                </label>
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </div>

            <!-- Footer: dots on top (mobile), BACK + action below; one row from sm up -->
            <div class="px-4 sm:px-6 py-3 bg-[#FAF6EE] border-t-2 border-dashed border-[#2D231E]/30 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                <div id="vocab-dots" class="flex items-center justify-center gap-1.5 sm:order-2" aria-hidden="true">
                    @for ($i = 0; $i < count($vocab); $i++)
                        <span class="w-2.5 h-2.5 rounded-full border-2 border-[#2D231E] {{ $i === 0 ? 'bg-amber-400' : 'bg-white' }}"></span>
                    @endfor
                </div>
                <div class="flex items-center justify-between gap-2 sm:contents">
                    <button
                        type="button"
                        id="vocab-back"
                        data-back-url="{{ route('placement.step1') }}"
                        class="shrink-0 min-h-[44px] px-4 inline-flex items-center gap-1 font-pixel text-[9px] sm:text-[10px] font-bold uppercase text-[#2D231E] bg-white border-2 border-[#2D231E] rounded-xl shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all sm:order-1"
                    >
                        BACK
                    </button>
                    <div class="flex items-center gap-2 sm:order-3">
                        <button
                            type="button"
                            id="vocab-next"
                            disabled
                            class="min-h-[44px] px-5 inline-flex items-center gap-2 font-pixel text-[9px] sm:text-[10px] font-bold uppercase text-[#2D231E] bg-amber-400 border-2 border-[#2D231E] rounded-xl shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all disabled:opacity-40 disabled:pointer-events-none"
                        >
                            NEXT
                        </button>
                        <button
                            type="submit"
                            id="vocab-submit"
                            hidden
                            disabled
                            class="min-h-[44px] px-5 inline-flex items-center gap-2 font-pixel text-[9px] sm:text-[10px] font-bold uppercase text-[#2D231E] bg-amber-400 border-2 border-[#2D231E] rounded-xl shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all disabled:opacity-40 disabled:pointer-events-none"
                        >
                            <span data-submit-idle><span class="sm:hidden">SUBMIT</span><span class="hidden sm:inline">SUBMIT WORDS</span></span>
                            <span data-submit-active class="hidden"><x-spinner label="Saving..." /></span>
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <x-source-attribution :title="$set->title" :author="$set->author" :url="$set->source_url" />

    </div>

</div>
@endsection