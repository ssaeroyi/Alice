@extends('layouts.placement')

@section('js')
    @vite(['resources/js/app.js'])
@endsection

@section('content')
<!-- Background Canvas (Bounded to max-w-6xl) -->
<div class="max-w-6xl mx-auto px-4 py-12 min-h-[75vh] flex items-center justify-center relative">

    <!-- Centered Setup Container -->
    <div class="w-full max-w-lg bg-white border-4 border-[#2D231E] rounded-3xl p-6 sm:p-8 shadow-[6px_6px_0px_0px_rgba(45,35,30,1)] space-y-7">
        
        <!-- Step Progress Badge -->
        <div class="border-b-2 border-dashed border-[#E6DCCF] pb-4 space-y-3">
            <div class="flex items-center justify-between">
                <div class="inline-flex items-center gap-2 font-pixel text-[10px] sm:text-xs bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] px-3.5 py-1.5 rounded-full font-bold shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] uppercase tracking-wider">
                    <span>STEP 1 OF 4</span>
                </div>
            </div>

            <!-- Progress Indicator -->
            <div class="h-2 rounded-full bg-[#F3ECE0] overflow-hidden">
                <div class="h-full w-1/4 rounded-full bg-amber-400"></div>
            </div>
        </div>

        <!-- Conversational Dialogue Box with ALICE Backdrop -->
        <div class="flex items-center gap-3 sm:gap-4 text-left">
            
            <!-- ALICE Mascot with Retro Backdrop -->
            <div class="shrink-0 flex items-center justify-center relative">
                <!-- Dark Soft Radial Glow -->
                <div class="absolute w-12 h-12 sm:w-20 sm:h-20 rounded-full bg-[#2D231E]/20 blur-md"></div>

                <!-- Character Sprite -->
                <img 
                    src="{{ asset('image/Alice/Light mode/alice-lt-talking.png') }}" 
                    width="1280" height="1280"
                    alt="ALICE" 
                    class="relative z-10 w-14 h-14 sm:w-24 sm:h-24 object-contain scale-110 drop-shadow-[2px_2px_0px_rgba(45,35,30,1)]"
                >
            </div>

            <!-- Hovering Speech Bubble -->
            <div class="relative flex-1 w-full min-w-0 bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-4 sm:p-5 shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]">
                
                <!-- Left Speech Tail (Always points left to ALICE) -->
                <div class="absolute -left-3 top-1/2 -translate-y-1/2 w-5 h-5 bg-[#FAF6EE] border-l-2 border-b-2 border-[#2D231E] rotate-45"></div>

                <!-- Speech Content -->
                <p class="text-xs sm:text-sm font-bold text-[#2D231E] leading-relaxed text-left">
                    Almost ready! A few quick choices, then a short reading check after this.
                </p>
            </div>

        </div>

        <!-- Name Input Form -->
        <form action="{{ route('placement.step1.submit') }}" method="POST" data-submit-guard class="space-y-6">
            @csrf

            <!-- Grade Level Field -->
            <div class="space-y-1.5">
                <div class="flex items-center gap-1.5">
                    <label for="grade_level" class="text-sm sm:text-base font-extrabold text-[#2D231E] tracking-tight">
                        What is your current grade level?
                    </label>
                    <x-info label="Why do you need my grade?">
                        Pick the grade you are in right now. ALICE uses it as your reading starting point.
                    </x-info>
                </div>
                <div class="relative">
                    <select
                        id="grade_level"
                        name="grade_level"
                        required
                        class="w-full min-h-[44px] px-4 pr-10 py-2.5 appearance-none bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl text-sm font-bold text-[#2D231E] focus:outline-none focus:ring-2 focus:ring-amber-400 transition-all cursor-pointer"
                    >
                        <option value="" disabled @selected(old('grade_level', $selectedGrade) === null)>Select your grade</option>
                        @foreach ($gradeLevels as $grade)
                            <option value="{{ $grade->value }}" @selected(old('grade_level', $selectedGrade) === $grade->value)>{{ $grade->label() }}</option>
                        @endforeach
                    </select>
                    <!-- Custom chevron (the native popup list itself can't be restyled) -->
                    <svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[#8C7B70]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </div>
                <x-input-error field="grade_level" />
            </div>

            <!-- Daily Reading Goal -->
            <div class="space-y-2">
                <div class="flex items-center gap-1.5">
                    <label class="text-sm sm:text-base font-extrabold text-[#2D231E] tracking-tight">
                        Reading Goal
                    </label>
                    <x-info label="What is my reading goal?">
                        Your daily reading target. Pick a time you can keep up with. You can change it anytime in your hub.
                    </x-info>
                </div>
                <p class="text-sm font-bold text-[#2D231E]">
                    Pick a time that works for you.
                </p>
                <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-4 space-y-3">
                    <div class="text-center">
                        <span data-reading-goal-output class="font-pixel text-3xl font-bold text-amber-600">{{ old('daily_reading_goal', $selectedGoal) ?? config('placement.reading_goal_default') }}</span>
                        <span class="font-pixel text-xs font-bold text-[#8C7B70] uppercase ml-1">min / day</span>
                    </div>
                    <input
                        type="range"
                        name="daily_reading_goal"
                        data-reading-goal-slider
                        min="{{ config('placement.reading_goal_min') }}"
                        max="{{ config('placement.reading_goal_max') }}"
                        step="{{ config('placement.reading_goal_step') }}"
                        value="{{ old('daily_reading_goal', $selectedGoal) ?? config('placement.reading_goal_default') }}"
                        class="w-full accent-amber-500 cursor-pointer"
                        aria-label="Daily reading goal in minutes"
                    >
                    <div class="flex justify-between">
                        <span class="font-pixel text-[9px] font-bold text-[#8C7B70] uppercase">Less</span>
                        <span class="font-pixel text-[9px] font-bold text-[#8C7B70] uppercase">More</span>
                    </div>
                </div>
                <x-input-error field="daily_reading_goal" />
            </div>

            <!-- Submit Button (Tactile 3D Button) -->
            <div class="pt-2">
                <button 
                    type="submit"
                    class="w-full min-h-[44px] font-pixel text-xs sm:text-sm px-6 py-3.5 rounded-2xl bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] font-bold flex items-center justify-center gap-2 shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all cursor-pointer uppercase"
                >
                    <span data-submit-idle>CONTINUE</span>
                    <span data-submit-active class="hidden"><x-spinner label="Saving..." /></span>
                </button>
            </div>
        </form>

    </div>

</div>
@endsection