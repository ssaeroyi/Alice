@extends('layouts.placement')

@section('js')
    @vite(['resources/js/app.js'])
@endsection

@section('content')
<!-- Background Canvas -->
<div class="max-w-6xl mx-auto px-4 py-12 min-h-[75vh] flex items-center justify-center relative">

    <!-- Centered Pitch Container -->
    <div class="w-full max-w-lg bg-white border-4 border-[#2D231E] rounded-3xl p-6 sm:p-8 shadow-[6px_6px_0px_0px_rgba(45,35,30,1)] space-y-6">

        <!-- Reading Level Test Badge (centered) -->
        <div class="flex justify-center">
            <div class="inline-flex items-center gap-2 font-pixel text-[10px] sm:text-xs bg-emerald-400 border-2 border-[#2D231E] text-[#2D231E] px-4 py-1.5 rounded-full font-bold shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] uppercase tracking-wider">
                <span>READING LEVEL TEST</span>
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
                <div class="absolute -left-3 top-8 -translate-y-1/2 w-5 h-5 bg-[#FAF6EE] border-l-2 border-b-2 border-[#2D231E] rotate-45"></div>

                <!-- Speech Content -->
                <p class="text-xs sm:text-sm font-bold text-[#2D231E] leading-relaxed text-left">
                    Hey there! This short reading test finds your level and only takes about 5 minutes. No pass or fail, and when it's done I'll pick a shelf of books that fit you just right.
                </p>
            </div>

        </div>

        <!-- What To Expect Chips -->
        <div>
            <p class="font-pixel text-[10px] font-bold text-[#8C7B70] uppercase tracking-wider mb-3">WHAT'S INSIDE</p>
            <div class="grid grid-cols-3 gap-2">
                <div class="min-h-[44px] flex flex-col items-center justify-center bg-white border-2 border-[#2D231E] rounded-xl shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] px-1">
                    <span class="font-pixel text-xs font-bold text-[#2D231E]">10</span>
                    <span class="text-[9px] font-bold text-[#8C7B70] uppercase tracking-wide leading-tight text-center">word meanings</span>
                </div>
                <div class="min-h-[44px] flex flex-col items-center justify-center bg-white border-2 border-[#2D231E] rounded-xl shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] px-1">
                    <span class="font-pixel text-xs font-bold text-[#2D231E]">10</span>
                    <span class="text-[9px] font-bold text-[#8C7B70] uppercase tracking-wide leading-tight text-center">missing words</span>
                </div>
                <div class="min-h-[44px] flex flex-col items-center justify-center bg-white border-2 border-[#2D231E] rounded-xl shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] px-1">
                    <span class="font-pixel text-xs font-bold text-[#2D231E]">5</span>
                    <span class="text-[9px] font-bold text-[#8C7B70] uppercase tracking-wide leading-tight text-center">story questions</span>
                </div>
            </div>
        </div>

        <!-- Action Button (Tactile CTA) -->
        <div class="pt-2 border-t-2 border-dashed border-[#E6DCCF]">
            <a
                href="{{ route('placement.step2') }}"
                class="w-full min-h-[44px] font-pixel text-xs sm:text-sm px-6 py-3.5 rounded-2xl bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] font-bold flex items-center justify-center shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all cursor-pointer uppercase"
            >
                <span>START THE CHECK</span>
            </a>
        </div>

    </div>

</div>
@endsection
