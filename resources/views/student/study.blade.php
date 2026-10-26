@extends('layouts.authenticated')

@section('title', 'Study')

@section('page', 'study')

@php
    // Donut geometry: three arcs on a shared circle.
    $radius = 40;
    $circumference = round(2 * pi() * $radius, 2);
    $total = max(1, $stats['total']);
    $segments = [
        'strong' => ['count' => $stats['strong'], 'color' => '#10B981'],
        'learning' => ['count' => $stats['learning'], 'color' => '#0EA5E9'],
        'new' => ['count' => $stats['not_studied'], 'color' => '#B5A89C'],
    ];
    $offset = 0;
@endphp

@section('content')
<div class="max-w-2xl mx-auto px-4 py-6 space-y-4">

    <!-- Study-local header row -->
    <div class="flex items-center justify-between gap-3">
        <h1 class="font-pixel text-lg sm:text-xl font-bold text-[#2D231E] uppercase">Study</h1>
        <a href="{{ route('study.dictionary') }}"
           class="inline-flex items-center gap-2 min-h-[44px] px-4 py-2 bg-white hover:bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl text-[#2D231E] shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] transition-all">
            <x-icons.book-open class="w-4 h-4" />
            <span class="font-pixel text-[10px] sm:text-xs font-bold uppercase">Dictionary</span>
        </a>
    </div>

    @if ($totalWords === 0)
        <!-- Empty state -->
        <div class="bg-white border-2 border-[#2D231E] rounded-2xl p-8 shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] text-center space-y-3">
            <x-icons.book-open class="w-10 h-10 mx-auto text-[#2D231E]" />
            <p class="font-pixel text-sm font-bold text-[#2D231E] uppercase">No words yet</p>
            <p class="text-xs sm:text-sm font-semibold text-[#2D231E] leading-relaxed">
                While reading, tap any word you don't know and hit SAVE. Your saved words will show up here as flashcards.
            </p>
            <a href="{{ route('library') }}"
               class="inline-block mt-2 min-h-[44px] leading-[44px] px-6 bg-amber-400 hover:bg-amber-300 border-2 border-[#2D231E] rounded-2xl font-pixel text-xs font-bold text-[#2D231E] shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all uppercase">
                Go to library
            </a>
        </div>
    @else

        <!-- MY WORDS: mastery breakdown -->
        <section class="bg-white border-2 border-[#2D231E] rounded-2xl p-5 shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]">
            <h2 class="font-pixel text-[11px] font-bold text-[#2D231E] uppercase tracking-wider mb-4">My words</h2>

            <div class="flex flex-col sm:flex-row items-center gap-5">
                <!-- Donut (tappable segments) -->
                <div class="relative w-32 h-32 shrink-0" data-donut-wrap>
                    <svg viewBox="0 0 100 100" class="w-full h-full -rotate-90" role="img" aria-label="Mastery breakdown">
                        <circle cx="50" cy="50" r="{{ $radius }}" fill="none" stroke="#F3ECE0" stroke-width="14" />
                        @php $offset = 0; @endphp
                        @foreach ($segments as $tier => $segment)
                            @if ($segment['count'] > 0)
                                @php
                                    $len = round($circumference * ($segment['count'] / $total), 2);
                                @endphp
                                <!-- Invisible hit area (wider stroke for mobile tap) -->
                                <circle cx="50" cy="50" r="{{ $radius }}" fill="none"
                                        stroke="transparent" stroke-width="28"
                                        stroke-dasharray="{{ $len }} {{ round($circumference - $len, 2) }}"
                                        stroke-dashoffset="{{ -$offset }}"
                                        data-tier="{{ $tier }}" class="cursor-pointer" />
                                <!-- Visible segment -->
                                <circle cx="50" cy="50" r="{{ $radius }}" fill="none"
                                        stroke="{{ $segment['color'] }}" stroke-width="14" stroke-linecap="butt"
                                        stroke-dasharray="{{ $len }} {{ round($circumference - $len, 2) }}"
                                        stroke-dashoffset="{{ -$offset }}"
                                        data-tier="{{ $tier }}" class="pointer-events-none" />
                                @php $offset += $len; @endphp
                            @endif
                        @endforeach
                        <text x="50" y="50" class="rotate-90 origin-center text-[#2D231E] pointer-events-none" text-anchor="middle" dominant-baseline="central"
                              style="font-family: var(--font-pixel, monospace); font-size: 17px; font-weight: 700; fill: currentColor;">
                            {{ $stats['total'] }}
                        </text>
                    </svg>
                </div>

                <!-- Tier badges (clickable links) -->
                <div class="flex-1 w-full space-y-2.5">
                    <a href="{{ route('study.words', ['filter' => 'strong']) }}"
                       class="flex items-center justify-between bg-[#FAF6EE] hover:bg-white border-2 border-[#2D231E] rounded-xl px-4 py-2.5 active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all">
                        <span class="inline-flex items-center gap-2 font-pixel text-[10px] font-bold text-emerald-600 uppercase">
                            <span class="w-3 h-3 rounded-full bg-emerald-500 border-2 border-[#2D231E]"></span> Strong
                        </span>
                        <span class="font-pixel text-sm font-bold text-[#2D231E]" data-tier-count="strong">{{ $stats['strong'] }}</span>
                    </a>
                    <a href="{{ route('study.words', ['filter' => 'learning']) }}"
                       class="flex items-center justify-between bg-[#FAF6EE] hover:bg-white border-2 border-[#2D231E] rounded-xl px-4 py-2.5 active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all">
                        <span class="inline-flex items-center gap-2 font-pixel text-[10px] font-bold text-sky-600 uppercase">
                            <span class="w-3 h-3 rounded-full bg-sky-500 border-2 border-[#2D231E]"></span> Learning
                        </span>
                        <span class="font-pixel text-sm font-bold text-[#2D231E]" data-tier-count="learning">{{ $stats['learning'] }}</span>
                    </a>
                    <a href="{{ route('study.words', ['filter' => 'not-studied']) }}"
                       class="flex items-center justify-between bg-[#FAF6EE] hover:bg-white border-2 border-[#2D231E] rounded-xl px-4 py-2.5 active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all">
                        <span class="inline-flex items-center gap-2 font-pixel text-[10px] font-bold text-[#2D231E] uppercase">
                            <span class="w-3 h-3 rounded-full bg-[#B5A89C] border-2 border-[#2D231E]"></span> Not studied
                        </span>
                        <span class="font-pixel text-sm font-bold text-[#2D231E]" data-tier-count="not-studied">{{ $stats['not_studied'] }}</span>
                    </a>
                </div>
            </div>
        </section>

        <!-- ALL SAVED WORDS banner -->
        <a href="{{ route('study.words') }}"
           class="card-hover flex items-center justify-between bg-white border-2 border-[#2D231E] rounded-2xl p-5 shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] transition-all group">
            <div>
                <p class="font-pixel text-sm font-bold text-[#2D231E] uppercase">All saved words</p>
                <p class="text-xs font-semibold text-[#2D231E] mt-1"><span data-total-words>{{ $totalWords }}</span> words in your collection</p>
            </div>
            <x-icons.arrow-right class="w-5 h-5 text-[#2D231E] group-hover:translate-x-0.5 transition-transform" />
        </a>

        <!-- FLASHCARDS FOR TODAY -->
        <section class="bg-white border-2 border-[#2D231E] rounded-2xl p-5 shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] space-y-4"
                 data-caught-up="{{ $allCaughtUp ? 'true' : 'false' }}">
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-pixel text-[11px] font-bold text-[#2D231E] uppercase tracking-wider">Flashcards for today</h2>
                <button type="button" data-modal-open="goal-modal"
                        class="min-h-[36px] px-3 bg-white hover:bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl font-pixel text-[9px] font-bold text-[#2D231E] uppercase shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all cursor-pointer">
                    Edit goal
                </button>
            </div>

            <!-- Daily progress bar -->
            <div>
                <div class="w-full bg-[#F3ECE0] border-2 border-[#2D231E] rounded-full h-4 overflow-hidden">
                    <div class="h-full bg-amber-400 transition-all duration-500" data-progress-bar
                         style="width: {{ min(100, (int) round(($reviewedToday / max(1, $goal)) * 100)) }}%"></div>
                </div>
                <p class="text-[10px] font-bold text-[#2D231E] mt-1.5 uppercase tracking-wide">
                    <span data-reviewed-count>{{ $reviewedToday }}</span> of <span data-goal-text>{{ $goal }}</span> reviewed today
                </p>
            </div>

            @if ($allCaughtUp)
                <div class="text-center py-3 space-y-2" data-state-caught-up>
                    <p class="font-pixel text-base font-bold text-emerald-600 uppercase">All caught up!</p>
                    <p class="text-xs font-semibold text-[#2D231E]">No flashcards are due right now. Come back tomorrow!</p>
                </div>
            @else
                <div class="space-y-4" data-state-ready>
                    <div class="text-center space-y-1">
                        <p class="font-pixel text-lg sm:text-xl font-bold text-[#2D231E] uppercase">
                            <span data-queue-size>{{ $queueSize }}</span> flashcards to study
                        </p>
                        <p class="text-xs font-bold text-[#2D231E]">{{ $goalComplete ? 'Goal complete! Want a bonus round?' : $encouragement }}</p>
                    </div>

                    <a href="{{ route('study.session') }}"
                       class="w-full min-h-[52px] flex items-center justify-center bg-amber-400 hover:bg-amber-300 border-2 border-[#2D231E] rounded-2xl font-pixel text-sm font-bold text-[#2D231E] shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all uppercase">
                        {{ $goalComplete ? 'Bonus round' : 'Study' }}
                    </a>
                </div>
            @endif
        </section>

        @if ($forgottenCount > 0)
            <!-- FORGOTTEN WORDS -->
            <a href="{{ route('study.words', ['filter' => 'forgotten']) }}"
               class="card-hover group block bg-white border-2 border-[#2D231E] rounded-2xl p-5 shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] transition-all">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-pixel text-sm font-bold text-[#2D231E] uppercase">
                            Forgotten words <span class="ml-1 text-rose-600">{{ $forgottenCount }}</span>
                        </p>
                        <p class="text-xs font-semibold text-[#2D231E] mt-1">
                            These tripped you up before. Reviewing them early locks them in faster.
                        </p>
                    </div>
                    <x-icons.arrow-right class="w-5 h-5 text-[#2D231E] shrink-0 group-hover:translate-x-0.5 transition-transform" />
                </div>
            </a>
        @endif

    @endif

</div>

<!-- Edit Daily Study Goal Modal -->
<x-modal id="goal-modal" title="EDIT DAILY STUDY GOAL" pixel>
    <div class="space-y-5 pt-2">
        <div class="text-center">
            <span class="font-pixel text-3xl font-bold text-amber-600" data-goal-output>{{ $goal }}</span>
            <span class="font-pixel text-xs font-bold text-[#2D231E] uppercase ml-1">words / day</span>
        </div>

        <div>
            <input type="range"
                   data-goal-slider
                   min="{{ $goalMin }}" max="{{ $goalMax }}" step="{{ $goalStep }}" value="{{ $goal }}"
                   class="w-full accent-amber-500 cursor-pointer" aria-label="Daily study goal">
            <div class="flex justify-between mt-1">
                <span class="font-pixel text-[9px] font-bold text-[#2D231E] uppercase">Less</span>
                <span class="font-pixel text-[9px] font-bold text-[#2D231E] uppercase">More</span>
            </div>
        </div>

        <p class="text-xs font-semibold text-[#2D231E] leading-relaxed">
            Set a study goal to limit how many words you review each day. We recommend around 20 words per day to avoid burnout. Slow and steady wins the race!
        </p>

        <button type="button" data-save-goal
                class="w-full min-h-[48px] bg-amber-400 hover:bg-amber-300 border-2 border-[#2D231E] rounded-2xl font-pixel text-xs font-bold text-[#2D231E] shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all cursor-pointer uppercase">
            Save
        </button>
    </div>
</x-modal>
@endsection
