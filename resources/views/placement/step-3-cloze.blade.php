@extends('layouts.placement')

@section('js')
    @vite(['resources/js/app.js'])
@endsection

@section('content')
@php
    $blanks = collect($cloze['blanks'])->keyBy('key');
    $parts = preg_split('/\{\{(\d+)\}\}/', $cloze['text'], -1, PREG_SPLIT_DELIM_CAPTURE);
@endphp
<!-- Background Canvas -->
<div class="max-w-6xl mx-auto px-4 py-12 min-h-[75vh] flex items-center justify-center relative">

    <!-- Centered Setup Container -->
    <div class="w-full max-w-2xl bg-white border-4 border-[#2D231E] rounded-3xl p-6 sm:p-8 shadow-[6px_6px_0px_0px_rgba(45,35,30,1)] space-y-6">
        
        <!-- Placement Progress (above ALICE, step 1 style) -->
        <div class="border-b-2 border-dashed border-[#E6DCCF] pb-4 space-y-3">
            <div class="flex items-center justify-between gap-3">
                <div class="inline-flex items-center gap-2 font-pixel text-[10px] sm:text-xs bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] px-3.5 py-1.5 rounded-full font-bold shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] uppercase tracking-wider">
                    <span>STEP 3 OF 4</span>
                </div>
                <a
                    href="{{ route('placement.step2') }}"
                    class="shrink-0 min-h-[40px] px-4 inline-flex items-center gap-1 font-pixel text-[9px] sm:text-[10px] font-bold uppercase text-[#2D231E] bg-white border-2 border-[#2D231E] rounded-xl shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all"
                >
                    BACK
                </a>
            </div>
            <div class="h-2 rounded-full bg-[#F3ECE0] overflow-hidden">
                <div class="h-full w-3/4 rounded-full bg-amber-400"></div>
            </div>
        </div>

        <!-- Conversational Dialogue Box with ALICE Radial Shadow -->
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
                    class="relative z-10 w-14 h-14 sm:w-24 sm:h-24 object-contain scale-110 drop-shadow-[3px_3px_0px_rgba(45,35,30,1)]"
                >
            </div>

            <!-- Hovering Speech Bubble -->
            <div class="relative flex-1 w-full min-w-0 bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-4 sm:p-5 shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]">
                
                <!-- Left Speech Tail (Always points left to ALICE) -->
                <div class="absolute -left-3 top-1/2 -translate-y-1/2 w-5 h-5 bg-[#FAF6EE] border-l-2 border-b-2 border-[#2D231E] rotate-45"></div>

                <!-- Speech Content -->
                <p class="text-xs sm:text-sm font-bold text-[#2D231E] leading-relaxed text-left">
                    Here we go! This is a <span class="text-amber-600 underline decoration-2">cloze test</span> where some words are missing from the story. Pick the best word for each blank.
                </p>
            </div>

        </div>

        <!-- Cloze Passage Form -->
        <form action="{{ route('placement.step3.submit') }}" method="POST" data-submit-guard class="space-y-6">
            @csrf

            @if ($errors->any())
                <div class="bg-rose-50 border-2 border-rose-400 text-rose-700 text-xs font-bold rounded-xl p-3">
                    {{ $errors->first() }}
                </div>
            @endif

            <!-- Passage Box -->
            <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-5 space-y-4 text-justify leading-[2.25] text-sm font-medium text-[#2D231E]">
                <p>
                    @foreach ($parts as $part)
                        @if (is_numeric($part))
                            @php $blank = $blanks[(int) $part]; @endphp
                            <select name="cloze_{{ $part }}" required class="inline-block px-2 py-1 mx-1 bg-white border-2 border-[#2D231E] rounded-lg font-bold text-amber-700 text-xs focus:outline-none focus:ring-2 focus:ring-amber-400">
                                <option value="" disabled @selected(old("cloze_".$part, $saved[(int) $part] ?? null) === null)>Choose</option>
                                @foreach ($blank['options'] as $option)
                                    <option value="{{ $option }}" @selected(old("cloze_".$part, $saved[(int) $part] ?? null) === $option)>{{ $option }}</option>
                                @endforeach
                            </select>
                        @else
                            {{ $part }}
                        @endif
                    @endforeach
                </p>
            </div>

            <x-source-attribution :title="$set->title" :author="$set->author" :url="$set->source_url" />

            <!-- Footer: one full-width submit (BACK lives in the step header) -->
            <div class="pt-2 border-t-2 border-dashed border-[#E6DCCF]">
                <button 
                    type="submit"
                    class="w-full min-h-[44px] font-pixel text-xs sm:text-sm px-6 py-3.5 rounded-2xl bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] font-bold flex items-center justify-center gap-2 shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all cursor-pointer uppercase"
                >
                    <span data-submit-idle>CONTINUE TO STORY QUESTIONS</span>
                    <span data-submit-active class="hidden"><x-spinner label="Saving..." /></span>
                </button>
            </div>
        </form>

    </div>

</div>
@endsection
