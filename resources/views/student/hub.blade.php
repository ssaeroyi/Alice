@extends('layouts.authenticated')

@section('page', 'hub')

@section('content')
@php
    // Real snapshot values. $gamer is computed by the layout (title/points/
    // streak/keys); $snapshot holds saved-words + today's stamp status.
    $g = $gamer ?? [
        'rank' => ['title' => 'New Reader', 'points' => 0],
        'tier_icon' => 'open-book',
        'streak' => 0,
        'keys' => 0,
    ];
    $points = (int) ($g['rank']['points'] ?? 0);
    $streak = (int) ($g['streak'] ?? 0);
    $keys = (int) ($g['keys'] ?? 0);
@endphp

<div class="space-y-6">

    <!-- ================= ALICE GREETING ================= -->
    <section class="flex items-center gap-3 sm:gap-6 pt-2">
        <div class="shrink-0 flex items-center justify-center relative">
            <img
                src="{{ asset('image/Alice/Light mode/alice-lt-casual.png') }}"
                width="1280" height="1280"
                alt="ALICE"
                class="relative z-10 w-16 h-16 sm:w-32 sm:h-32 object-contain drop-shadow-[3px_3px_0px_rgba(45,35,30,1)]"
            >
        </div>

        <div class="relative flex-1 min-w-0 bg-white border-4 border-[#2D231E] rounded-3xl p-4 sm:p-6 shadow-[6px_6px_0px_0px_rgba(45,35,30,1)]">
            <div class="absolute -left-3.5 top-1/2 -translate-y-1/2 w-6 h-6 bg-white border-l-4 border-b-4 border-[#2D231E] rotate-45"></div>

            <div class="space-y-1 sm:space-y-2 text-left">
                <h1 class="text-lg sm:text-2xl font-extrabold tracking-tight text-[#2D231E]">
                    Welcome back, {{ auth()->user()->nickname }}!
                </h1>
                <p class="text-xs sm:text-sm font-medium leading-relaxed text-[#2D231E]">
                    @if ($snapshot['stamped_today'])
                        Today's stamp is in. Keep the streak alive!
                    @elseif ($streak > 0)
                        You're on a <strong class="text-rose-600">{{ $streak }}-day streak</strong>. Do one more reading session to stamp today.
                    @else
                        Let's start a streak. A quick read stamps today and earns points.
                    @endif
                </p>
            </div>

        </div>
    </section>

    <!-- ================= TAB BAR ================= -->
    <nav class="relative" data-hub-tabs aria-label="Hub sections">
        <div role="tablist" class="flex gap-2 sm:gap-3 overflow-x-auto pt-2.5 pb-1 -mx-1 px-1" style="scrollbar-width:none">
            <button type="button" role="tab" data-hub-tab="today" aria-selected="true" tabindex="0"
                class="shrink-0 min-h-[44px] font-pixel text-[10px] sm:text-xs font-bold px-4 sm:px-5 py-2 rounded-t-2xl border-2 border-[#2D231E] border-b-0 bg-amber-400 text-[#2D231E] uppercase shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]">
                Home
            </button>
            <button type="button" role="tab" data-hub-tab="tasks" aria-selected="false" tabindex="-1"
                class="relative shrink-0 min-h-[44px] font-pixel text-[10px] sm:text-xs font-bold px-4 sm:px-5 py-2 rounded-t-2xl border-2 border-[#2D231E] border-b-0 bg-white text-[#2D231E] uppercase shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]">
                Tasks
                @if (($gamer['claimable_tasks'] ?? 0) > 0)
                    <span class="absolute -top-2 -right-2 min-w-[22px] h-[22px] px-1.5 rounded-full bg-rose-500 border-2 border-[#2D231E] text-white font-pixel text-[9px] font-bold flex items-center justify-center shadow-[2px_2px_0px_0px_rgba(45,35,30,0.9)]">
                        {{ $gamer['claimable_tasks'] }}
                    </span>
                @elseif (($gamer['in_progress_tasks'] ?? 0) > 0)
                    <span class="absolute -top-2 -right-2 w-[16px] h-[16px] rounded-full bg-amber-400 border-2 border-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,0.9)]" title="A task is in progress" aria-label="A task is in progress"></span>
                @endif
            </button>
            <button type="button" role="tab" data-hub-tab="stats" aria-selected="false" tabindex="-1"
                class="shrink-0 min-h-[44px] font-pixel text-[10px] sm:text-xs font-bold px-4 sm:px-5 py-2 rounded-t-2xl border-2 border-[#2D231E] border-b-0 bg-white text-[#2D231E] uppercase shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]">
                Stats
            </button>
            <button type="button" role="tab" data-hub-tab="badges" aria-selected="false" tabindex="-1"
                class="shrink-0 min-h-[44px] font-pixel text-[10px] sm:text-xs font-bold px-4 sm:px-5 py-2 rounded-t-2xl border-2 border-[#2D231E] border-b-0 bg-white text-[#2D231E] uppercase shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]">
                Badges
            </button>
            <button type="button" role="tab" data-hub-tab="inbox" aria-selected="false" tabindex="-1"
                class="relative shrink-0 min-h-[44px] font-pixel text-[10px] sm:text-xs font-bold px-4 sm:px-5 py-2 rounded-t-2xl border-2 border-[#2D231E] border-b-0 bg-white text-[#2D231E] uppercase shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]">
                Inbox
                @if ($unreadNotifications > 0)
                    <span class="absolute -top-2 -right-2 min-w-[22px] h-[22px] px-1.5 rounded-full bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] font-pixel text-[9px] font-bold flex items-center justify-center shadow-[2px_2px_0px_0px_rgba(45,35,30,0.9)]">
                        {{ $unreadNotifications > 9 ? '9+' : $unreadNotifications }}
                    </span>
                @endif
            </button>
        </div>

        <div class="bg-white border-4 border-[#2D231E] rounded-3xl rounded-tl-none p-4 sm:p-6 shadow-[5px_5px_0px_0px_rgba(45,35,30,1)]">

            <!-- ============ HOME ============ -->
            <section role="tabpanel" data-hub-panel="today" aria-label="Home">
                <div class="space-y-6">

                    <!-- Snapshot cards -->
                    <div class="grid grid-cols-3 gap-2.5 sm:gap-4">
                        <a href="{{ route('activity', ['tab' => 'streak']) }}" class="bg-white border-2 border-[#2D231E] rounded-2xl p-3.5 sm:p-4 shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-none transition-all">
                            <div class="flex items-start justify-between gap-1.5">
                                <span class="font-pixel text-[8px] sm:text-[9px] font-bold text-[#2D231E] uppercase leading-tight">Streak</span>
                                <x-info label="What is a streak?" modal="hub-help-streak"></x-info>
                            </div>
                            <div class="mt-2 flex items-end justify-between gap-1">
                                <span class="text-lg sm:text-2xl font-extrabold text-rose-600">{{ $streak }}</span>
                                <span class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-rose-600 [&>svg]:h-full [&>svg]:w-full">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'flame') !!}</span>
                            </div>
                        </a>
                        <a href="{{ route('activity', ['tab' => 'keys']) }}" class="bg-white border-2 border-[#2D231E] rounded-2xl p-3.5 sm:p-4 shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-none transition-all">
                            <div class="flex items-start justify-between gap-1">
                                <span class="font-pixel text-[8px] sm:text-[9px] font-bold text-[#2D231E] uppercase leading-tight">Keys</span>
                                <x-info label="What are keys?" modal="hub-help-keys"></x-info>
                            </div>
                            <div class="mt-2 flex items-end justify-between gap-1">
                                <span class="text-lg sm:text-2xl font-extrabold text-amber-600" data-keys-total>{{ number_format($keys) }}</span>
                                <span class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-amber-600 [&>svg]:h-full [&>svg]:w-full">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'key') !!}</span>
                            </div>
                        </a>
                        <a href="{{ route('study') }}" class="bg-white border-2 border-[#2D231E] rounded-2xl p-3.5 sm:p-4 shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-none transition-all">
                            <div class="flex items-start justify-between gap-1">
                                <span class="font-pixel text-[8px] sm:text-[9px] font-bold text-[#2D231E] uppercase leading-tight">Saved Words</span>
                                <x-info label="What are saved words?" modal="hub-help-saved-words"></x-info>
                            </div>
                            <div class="mt-2 flex items-end justify-between gap-1">
                                <span class="text-lg sm:text-2xl font-extrabold text-sky-600">{{ number_format($snapshot['saved_words']) }}</span>
                                <span class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-sky-600 [&>svg]:h-full [&>svg]:w-full">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'spell-book') !!}</span>
                            </div>
                        </a>
                    </div>

                    <!-- Teacher assignments -->
                    @if ($assignments->isNotEmpty())
                        <div class="bg-white border-2 border-[#2D231E] rounded-2xl p-5 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="font-pixel text-[10px] sm:text-[11px] font-bold uppercase text-[#2D231E]">From your teacher</span>
                                <div class="flex items-center gap-2">
                                    @if ($newAssignments > 0)
                                        <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2 py-0.5 rounded-full bg-amber-400 text-[#2D231E]">
                                            {{ $newAssignments }} new
                                        </span>
                                    @endif
                                    <a href="{{ route('student.classwork') }}" class="font-pixel text-[9px] font-bold text-sky-700 hover:text-sky-800 uppercase">View all</a>
                                </div>
                            </div>

                            <div class="space-y-3">
                                @foreach ($assignments->take(3) as $assignment)
                                    @include('partials.hub-assignment-row', ['assignment' => $assignment])
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Recently read -->
                    @if ($continue->isNotEmpty())
                        <x-library-row title="Recently Read">
                            @foreach ($continue as $continueRow)
                                <x-story-card
                                    :story="$continueRow['story']"
                                    :meta="['percent' => $continueRow['percent'], 'hide_progress' => true]"
                                />
                            @endforeach
                        </x-library-row>
                    @else
                        <div class="bg-white border-2 border-[#2D231E] rounded-2xl p-5 space-y-3">
                            <span class="block font-pixel text-[10px] sm:text-[11px] font-bold uppercase text-[#2D231E]">Recently Read</span>
                            <div class="rounded-xl border-2 border-dashed border-[#E6DCCF] p-6 text-center">
                                <p class="font-pixel text-xs font-bold text-[#2D231E] uppercase">Nothing opened yet</p>
                                <p class="text-xs text-[#2D231E] font-semibold mt-1">Pick a story from the library to get reading.</p>
                                <a href="{{ route('library') }}" class="inline-flex mt-3 min-h-[44px] px-5 py-2 bg-amber-400 border-2 border-[#2D231E] rounded-xl font-pixel text-[11px] font-bold text-[#2D231E] shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-none transition-all uppercase">Open library</a>
                            </div>
                        </div>
                    @endif

                    <!-- Today's goals -->
                    @php
                        $goal = (int) $snapshot['daily_reading_goal'];
                        $goalMinutes = round($snapshot['reading_seconds_today'] / 60, 1);
                        $goalPct = $goal > 0 ? (int) round(min(100, (min($goalMinutes, $goal) / $goal) * 100)) : 0;
                        $readingMet = $goal > 0 && $goalMinutes >= $goal;

                        $studyGoal = (int) $snapshot['daily_study_goal'];
                        $studyReviewed = (int) $snapshot['study_reviewed_today'];
                        $studyPct = $studyGoal > 0 ? (int) round(min(100, (min($studyReviewed, $studyGoal) / $studyGoal) * 100)) : 0;
                        $studyMet = $studyGoal > 0 && $studyReviewed >= $studyGoal;

                        $readingGKeys = (int) config('gamification.goal.reading_keys', 5);
                        $studyGKeys = (int) config('gamification.goal.study_keys', 3);
                    @endphp
                    <div class="bg-white border-2 border-[#2D231E] rounded-2xl p-5 space-y-3"
                         data-goal-claim-url="{{ route('hub.goal-claim') }}">
                        <span class="font-pixel text-[11px] font-bold uppercase text-[#2D231E]">Today's Goals</span>
                        <div class="grid sm:grid-cols-2 gap-3 items-stretch">
                            <div class="rounded-xl border-2 border-[#2D231E] bg-[#FAF6EE] p-3">
                                <div class="flex items-start gap-3">
                                    <span class="mt-1 w-9 h-9 shrink-0 bg-emerald-100 border-2 border-[#2D231E] rounded-lg flex items-center justify-center text-emerald-700 [&>svg]:h-5 [&>svg]:w-5">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'open-book') !!}</span>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center justify-between gap-1">
                                            <p class="font-pixel text-[9px] uppercase text-[#2D231E] flex items-center gap-1">
                                                Reading
                                                <x-info label="What is the reading goal?" modal="hub-help-reading-goal"></x-info>
                                            </p>
                                            <div class="flex items-center gap-1.5">
                                                @if ($readingMet && ! $snapshot['reading_goal_rewarded'])
                                                    <button type="button" data-goal-claim="reading"
                                                        class="shrink-0 min-h-[44px] px-2.5 font-pixel text-[8px] font-bold uppercase border-2 border-[#2D231E] rounded-lg bg-emerald-600 text-white shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all goal-claim-pulse cursor-pointer">
                                                        Claim
                                                    </button>
                                                @endif
                                                <button type="button" data-modal-open="hub-reading-goal-modal"
                                                    class="shrink-0 min-h-[44px] px-2.5 font-pixel text-[8px] font-bold uppercase border-2 border-[#2D231E] rounded-lg bg-white text-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all">
                                                    Edit
                                                </button>
                                            </div>
                                        </div>
                                        @if ($goal > 0)
                                            <p class="text-xs font-bold text-[#2D231E]">{{ $goalMinutes }} / {{ $goal }} min</p>
                                            <div class="w-full h-3 bg-white border-2 border-[#2D231E] rounded-full overflow-hidden mt-1">
                                                <div class="h-full bg-emerald-500 rounded-full transition-all" style="width: {{ $goalPct }}%"></div>
                                            </div>
                                        @else
                                            <p class="text-xs font-semibold text-[#2D231E]">No goal set. Every minute still counts!</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="rounded-xl border-2 border-[#2D231E] bg-[#FAF6EE] p-3">
                                <div class="flex items-start gap-3">
                                    <span class="mt-1 w-9 h-9 shrink-0 bg-sky-100 border-2 border-[#2D231E] rounded-lg flex items-center justify-center text-sky-700 [&>svg]:h-5 [&>svg]:w-5">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'spell-book') !!}</span>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center justify-between gap-1">
                                            <p class="font-pixel text-[9px] uppercase text-[#2D231E] flex items-center gap-1">
                                                Study words
                                                <x-info label="What is the study goal?" modal="hub-help-study-goal"></x-info>
                                            </p>
                                            <div class="flex items-center gap-1.5">
                                                @if ($studyMet && ! $snapshot['study_goal_rewarded'])
                                                    <button type="button" data-goal-claim="study"
                                                        class="shrink-0 min-h-[44px] px-2.5 font-pixel text-[8px] font-bold uppercase border-2 border-[#2D231E] rounded-lg bg-emerald-600 text-white shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all goal-claim-pulse cursor-pointer">
                                                        Claim
                                                    </button>
                                                @endif
                                                <button type="button" data-modal-open="hub-study-goal-modal"
                                                    class="shrink-0 min-h-[44px] px-2.5 font-pixel text-[8px] font-bold uppercase border-2 border-[#2D231E] rounded-lg bg-white text-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all">
                                                    Edit
                                                </button>
                                            </div>
                                        </div>
                                        @if ($studyGoal > 0)
                                            <p class="text-xs font-bold text-[#2D231E]">{{ $studyReviewed }} / {{ $studyGoal }} reviewed</p>
                                            <div class="w-full h-3 bg-white border-2 border-[#2D231E] rounded-full overflow-hidden mt-1">
                                                <div class="h-full bg-sky-500 rounded-full transition-all" style="width: {{ $studyPct }}%"></div>
                                            </div>
                                        @else
                                            <p class="text-xs font-semibold text-[#2D231E]">No goal set.</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </section>

            <!-- ============ TASKS ============ -->
            <section role="tabpanel" data-hub-panel="tasks" aria-label="Tasks" hidden>
                @php
                    $claimableTasks = $tasks->filter(fn ($t) => $t['can_claim']);
                @endphp
                <div class="space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="flex items-center gap-1.5 font-pixel text-[11px] font-bold uppercase text-[#2D231E]">Daily Tasks
                            <x-info label="About daily tasks" modal="hub-help-daily-tasks"></x-info>
                        </span>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-sky-100 border-2 border-[#2D231E] rounded-lg text-sky-700 text-[11px] font-bold [&>svg]:h-4 [&>svg]:w-4">
                            {!! \App\Support\LibraryPresentation::iconGlyph('ui', 'snowflake') !!}
                            <span>Rerolls left today: {{ $rerollsRemaining }}</span>
                        </span>
                        <span class="flex items-center gap-2 ml-auto">
                            @if ($claimableTasks->isNotEmpty())
                                <button type="button" data-task-claim-all data-claim-url="{{ route('tasks.claim-all') }}"
                                    class="min-h-[44px] px-3 sm:px-4 font-pixel text-[10px] font-bold uppercase bg-emerald-600 text-white border-2 border-[#2D231E] rounded-xl shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] hover:bg-emerald-700 active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all goal-claim-pulse cursor-pointer">
                                    Claim all
                                </button>
                            @endif
                            <a href="{{ route('tasks') }}"
                               class="inline-flex items-center min-h-[44px] px-3 sm:px-4 py-2 font-pixel text-[10px] font-bold uppercase bg-amber-400 text-[#2D231E] border-2 border-[#2D231E] rounded-xl shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] hover:bg-amber-300 active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all">
                                Full page
                            </a>
                        </span>
                    </div>

                    @if ($tasks->isNotEmpty())
                        @include('partials.task-card', ['tasks' => $tasks, 'rerollsRemaining' => $rerollsRemaining, 'fromHub' => true])
                    @else
                        <div class="bg-[#FAF6EE] border-4 border-[#2D231E] rounded-2xl p-6 text-center">
                            <p class="font-pixel text-sm font-bold text-[#2D231E] uppercase">No tasks today</p>
                            <p class="text-xs text-[#2D231E] font-semibold mt-1">Check back tomorrow for a fresh set.</p>
                        </div>
                    @endif
                </div>

                <x-modal id="task-claim-modal" title="Nice work!" :pixel="true" :closeable="false">
                    <div class="space-y-3 text-center">
                        <p class="text-sm font-semibold text-[#2D231E]">You got this!</p>
                        <div class="flex items-center justify-center gap-2">
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-amber-100 border-2 border-[#2D231E] rounded-md text-amber-700 text-xs font-bold [&>svg]:h-4 [&>svg]:w-4">
                                {!! \App\Support\LibraryPresentation::iconGlyph('ui', 'key') !!} <span data-claim-modal-keys>+6</span>
                            </span>
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-emerald-100 border-2 border-[#2D231E] rounded-md text-emerald-700 text-xs font-bold [&>svg]:h-4 [&>svg]:w-4">
                                {!! \App\Support\LibraryPresentation::iconGlyph('ui', 'laurel-crown') !!} <span data-claim-modal-points>+12 points</span>
                            </span>
                        </div>
                    </div>
                    <button type="button" data-modal-close
                        class="w-full min-h-[44px] font-pixel text-[11px] font-bold uppercase text-white bg-emerald-600 hover:bg-emerald-700 border-2 border-[#2D231E] rounded-xl shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all cursor-pointer">
                        OK
                    </button>
                </x-modal>
            </section>

            <!-- ============ STATS ============ -->
            <section role="tabpanel" data-hub-panel="stats" aria-label="Stats" hidden>
                <div class="space-y-6" data-hub-stats>

                    <!-- Title ladder -->
                    <div class="bg-white border-2 border-[#2D231E] rounded-2xl p-5 space-y-2">
                        <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2">
                            <span class="flex items-center gap-2 font-pixel text-[11px] font-bold uppercase text-[#2D231E] min-w-0">
                                <span class="w-5 h-5 shrink-0 text-violet-700 [&>svg]:h-full [&>svg]:w-full">{!! \App\Support\LibraryPresentation::iconGlyph('ui', $g['tier_icon']) !!}</span>
                                <span class="truncate">{{ $rank['title'] }}</span>
                                <x-info label="About reader ranks" modal="hub-help-reader-ranks"></x-info>
                            </span>
                            <span class="flex items-center gap-x-3 gap-y-2">
                                @if ($rank['next_title'])
                                    <span class="font-pixel text-[10px] text-violet-700 font-bold uppercase whitespace-nowrap">Next: {{ $rank['next_title'] }}</span>
                                @else
                                    <span class="font-pixel text-[10px] text-violet-700 font-bold uppercase whitespace-nowrap">Top of the ladder!</span>
                                @endif
                            </span>
                        </div>
                        <div class="h-4 w-full bg-[#E6DCCF] border-2 border-[#2D231E] rounded-full overflow-hidden">
                            <div class="h-full bg-violet-500 rounded-full transition-all" style="width: {{ (int) round($rank['progress'] * 100) }}%"></div>
                        </div>
                        <p class="text-[11px] text-[#2D231E] font-semibold">
                            <span data-points-total>{{ number_format($rank['points']) }}</span> points{{ $rank['points_to_next'] !== null ? ',' : '' }}
                            @if ($rank['points_to_next'] !== null)
                                {{ number_format($rank['points_to_next']) }} to {{ $rank['next_title'] }}
                            @endif
                        </p>
                        <div class="flex justify-end">
                            <button type="button" data-rank-roadmap-open aria-haspopup="dialog" aria-controls="rank-roadmap-modal"
                                    class="inline-flex items-center justify-center gap-1.5 min-h-[44px] px-3 py-2 font-pixel text-[9px] font-bold uppercase bg-white text-violet-700 border-2 border-[#2D231E] rounded-xl shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] hover:bg-violet-50 active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all">
                                View all ranks
                            </button>
                        </div>
                    </div>

                    <!-- Activity History: chart view (default) + month-by-month history view, swapped client-side -->
                    @php
                        $hasActivity = $series->contains(fn ($d) => $d['reading_seconds'] > 0 || $d['quizzes'] > 0 || $d['games'] > 0 || $d['drills'] > 0);
                        $hasHistory = collect($monthlySeries)->sum(fn ($m) => $m['overall'] ?? 0) > 0;
                        $totalReadingLabel = (function () use ($totalReadingSeconds) {
                            $mins = (int) round($totalReadingSeconds / 60);
                            if ($mins < 60) {
                                return $mins.'m';
                            }
                            $h = intdiv($mins, 60);
                            $m = $mins % 60;
                            return $m > 0 ? $h.'h '.$m.'m' : $h.'h';
                        })();
                        $chartModes = [
                            ['k' => 'today', 'l' => 'Today'],
                            ['k' => 'thisweek', 'l' => 'This Week'],
                            ['k' => 'lastweek', 'l' => 'Last Week'],
                            ['k' => 'all', 'l' => 'All Time'],
                        ];
                        $shownMode = $mode ?? 'thisweek';
                    @endphp
                    <div class="grid lg:grid-cols-5 gap-4 items-start">
                        <div class="lg:col-span-3 min-w-0 bg-white border-2 border-[#2D231E] rounded-2xl p-5 space-y-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="flex items-center gap-1.5">
                                    <span class="font-pixel text-[11px] font-bold uppercase text-[#2D231E]">Activity History</span>
                                    <x-info label="About Activity History" modal="hub-help-activity"></x-info>
                                </span>
                                <span class="flex items-center gap-2">
                                    <span class="font-pixel text-[9px] font-bold uppercase text-[#2D231E] hidden" data-history-months>{{ count($monthlySeries) }} month{{ count($monthlySeries) === 1 ? '' : 's' }}</span>
                                    <button type="button" data-activity-toggle aria-label="Switch between the chart and history" title="Switch between the chart and history"
                                        class="min-w-[44px] min-h-[44px] px-2 inline-flex items-center justify-center bg-white border-2 border-[#2D231E] rounded-xl text-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all cursor-pointer">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4" aria-hidden="true">
                                            <path d="M21 12a9 9 0 1 1-2.64-6.36" />
                                            <polyline points="21 3 21 9 15 9" />
                                            <path d="M3 12a9 9 0 0 1 2.64-6.36" />
                                            <polyline points="3 21 3 15 9 15" />
                                        </svg>
                                    </button>
                                </span>
                            </div>

                            <!-- Chart view (default) -->
                            <div data-activity-view="chart" class="space-y-3">
                                <div class="flex sm:flex-wrap items-center gap-2 overflow-x-auto pb-1 -mx-1 px-1 whitespace-nowrap" style="scrollbar-width:none" role="group" aria-label="Chart time range">
                                    @foreach ($chartModes as $cm)
                                        <button type="button" data-interval="{{ $cm['k'] }}" aria-pressed="{{ $cm['k'] === $shownMode ? 'true' : 'false' }}"
                                            class="shrink-0 min-w-[44px] min-h-[44px] px-3 font-pixel text-[10px] font-bold uppercase border-2 border-[#2D231E] rounded-xl shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all {{ $cm['k'] === $shownMode ? 'bg-amber-400 text-[#2D231E]' : 'bg-white text-[#2D231E]' }}">
                                            {{ $cm['l'] }}
                                        </button>
                                    @endforeach
                                </div>

                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[9px] font-bold uppercase text-[#2D231E]">
                                    <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-emerald-600 border border-[#2D231E]"></span>Reading</span>
                                    <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-sky-500 border border-[#2D231E]"></span>Quiz</span>
                                    <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-amber-400 border border-[#2D231E]"></span>Games</span>
                                    <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-violet-400 border border-[#2D231E]"></span>Drills</span>
                                    <span class="text-[#2D231E]">hover a bar for detail</span>
                                </div>

                                @php
                                    $todayRow = $series->last();
                                    $todayReadMin = (int) round(max($todayRow['reading_seconds'] ?? 0, 0) / 60);
                                @endphp
                                <div class="rounded-xl border-2 border-[#2D231E] bg-[#FAF6EE] px-3 py-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[10px] font-semibold text-[#2D231E]">
                                    <span class="font-pixel text-[9px] font-bold uppercase text-[#2D231E]">Today so far</span>
                                    <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-emerald-600 border border-[#2D231E]"></span>{{ $todayReadMin }} min read</span>
                                    <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-sky-500 border border-[#2D231E]"></span>{{ $todayRow['quizzes'] ?? 0 }} quiz</span>
                                    <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-amber-400 border border-[#2D231E]"></span>{{ $todayRow['games'] ?? 0 }} games</span>
                                    <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-violet-400 border border-[#2D231E]"></span>{{ $todayRow['drills'] ?? 0 }} drills</span>
                                </div>

                                <div data-chart-empty class="{{ $hasActivity ? 'hidden' : '' }} rounded-xl border-2 border-dashed border-[#E6DCCF] p-6 text-center">
                                    <p class="font-pixel text-xs font-bold text-[#2D231E] uppercase">No activity yet</p>
                                    <p class="text-xs text-[#2D231E] font-semibold mt-1">Read, quiz or play a mini-game to fill this chart.</p>
                                </div>

                                <script type="application/json" data-initial-stats>{!! json_encode($initialStats, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
                                <div data-chart-series class="w-full max-w-full h-40 sm:h-48 [&>canvas]:w-full [&>canvas]:max-w-full [&>canvas]:h-full" aria-hidden="true">
                                    <canvas data-chart-canvas></canvas>
                                </div>
                            </div>

                            <!-- History view -->
                            <div data-activity-view="history" class="hidden space-y-3">
                                <div class="flex sm:flex-wrap items-center gap-2 overflow-x-auto pb-1 -mx-1 px-1 whitespace-nowrap sm:whitespace-normal" style="scrollbar-width:none" role="group" aria-label="Activity metric">
                                    @foreach ([
                                        ['k' => 'overall', 'l' => 'Overall', 'c' => 'text-[#2D231E]'],
                                        ['k' => 'reading_minutes', 'l' => 'Reading', 'c' => 'text-emerald-600'],
                                        ['k' => 'quizzes', 'l' => 'Quizzes', 'c' => 'text-sky-500'],
                                        ['k' => 'games', 'l' => 'Games', 'c' => 'text-amber-500'],
                                        ['k' => 'drills', 'l' => 'Drills', 'c' => 'text-violet-500'],
                                    ] as $mt)
                                        <button type="button" data-history-metric="{{ $mt['k'] }}" aria-pressed="{{ $mt['k'] === 'overall' ? 'true' : 'false' }}"
                                                class="shrink-0 min-h-[44px] px-2.5 sm:px-3 font-pixel text-[10px] font-bold uppercase border-2 border-[#2D231E] rounded-xl shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all {{ $mt['k'] === 'overall' ? 'bg-amber-400 text-[#2D231E]' : 'bg-white text-[#2D231E]' }}">
                                            <span class="mr-1.5 inline-block w-2 h-2 rounded-sm border border-[#2D231E] {{ $mt['c'] }}" style="background-color:{{ $mt['k'] === 'overall' ? '#2D231E' : ($mt['k'] === 'reading_minutes' ? '#059669' : ($mt['k'] === 'quizzes' ? '#0EA5E9' : ($mt['k'] === 'games' ? '#F59E0B' : '#8B5CF6'))) }}"></span>
                                            {{ $mt['l'] }}
                                        </button>
                                    @endforeach
                                </div>

                                <p class="text-[10px] font-semibold text-[#2D231E]" data-history-title>Showing <span class="font-bold text-[#2D231E]">Overall activity</span> per month</p>

                                <div data-monthly-empty class="{{ $hasHistory ? 'hidden' : '' }} rounded-xl border-2 border-dashed border-[#E6DCCF] p-6 text-center">
                                    <p class="font-pixel text-xs font-bold text-[#2D231E] uppercase">No activity yet</p>
                                    <p class="text-xs text-[#2D231E] font-semibold mt-1">Read, quiz or play a mini-game to start filling your history chart.</p>
                                </div>
                                <div data-monthly-chart class="w-full max-w-full min-h-[176px] h-44 sm:h-52 [&>canvas]:w-full [&>canvas]:max-w-full [&>canvas]:h-full {{ $hasHistory ? '' : 'hidden' }}" aria-hidden="true">
                                    <canvas data-monthly-canvas></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Learning Metrics & Rank summary (all real data, empty-state safe) -->
                        <div class="lg:col-span-2 min-w-0 bg-white border-2 border-[#2D231E] rounded-2xl p-5 space-y-4">
                            <span class="flex items-center gap-1.5 font-pixel text-[11px] font-bold uppercase text-[#2D231E]">
                                <span class="w-5 h-5 text-violet-700 [&>svg]:h-full [&>svg]:w-full">{!! \App\Support\LibraryPresentation::iconGlyph('ui', $g['tier_icon']) !!}</span>
                                Your Standing
                            </span>

                            <div class="space-y-2">
                                {{-- Title: full-width row so long rank names never truncate --}}
                                <div class="rounded-xl border-2 border-[#2D231E] bg-white p-3 space-y-2 shadow-[2px_2px_0px_0px_rgba(45,35,30,1)]">
                                    <div class="flex items-center justify-between gap-2 min-w-0">
                                        <span class="flex items-center gap-2 min-w-0">
                                            <span class="w-8 h-8 shrink-0 flex items-center justify-center rounded-lg border-2 border-[#2D231E] bg-violet-50 text-violet-700 [&>svg]:w-4 [&>svg]:h-4">{!! \App\Support\LibraryPresentation::iconGlyph('ui', $g['tier_icon']) !!}</span>
                                            <span class="min-w-0">
                                                <span class="block font-pixel text-[8px] uppercase text-[#2D231E]">Title</span>
                                                <span class="block font-pixel text-sm sm:text-base font-bold text-[#2D231E] whitespace-normal break-words">{{ $rank['title'] }}</span>
                                            </span>
                                        </span>
                                        <span class="shrink-0 font-pixel text-[10px] font-bold text-[#2D231E] tabular-nums">{{ number_format($rank['points']) }} pts</span>
                                    </div>
                                    <div class="h-2 bg-[#F3ECE0] rounded-full overflow-hidden" role="progressbar" aria-valuenow="{{ (int) round($rank['progress'] * 100) }}" aria-valuemin="0" aria-valuemax="100" aria-label="Progress to next title">
                                        <div class="h-full rounded-full bg-violet-400" style="width: {{ (int) round($rank['progress'] * 100) }}%"></div>
                                    </div>
                                    <p class="font-pixel text-[8px] uppercase text-[#B5A89C]">
                                        @if ($rank['points_to_next'] !== null)
                                            {{ number_format($rank['points_to_next']) }} pts to {{ $rank['next_title'] }}
                                        @else
                                            Top of the ladder
                                        @endif
                                    </p>
                                </div>

                                <div class="grid grid-cols-2 gap-2">
                                    <div class="rounded-xl border-2 border-[#2D231E] bg-white p-3 flex flex-col gap-1 shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] min-w-0">
                                        <span class="w-8 h-8 flex items-center justify-center rounded-lg border-2 border-[#2D231E] text-sky-600 bg-sky-50 [&>svg]:w-4 [&>svg]:h-4" title="Your quiz-based reading level">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'compass') !!}</span>
                                        <p class="font-pixel text-base font-bold text-[#2D231E] tabular-nums">Grade {{ $readingLevelView['grade'] }}</p>
                                        <p class="font-pixel text-[8px] text-[#2D231E] uppercase">Reading Level</p>
                                        <div class="h-2 bg-[#F3ECE0] rounded-full overflow-hidden" role="progressbar" aria-valuenow="{{ $readingLevelView['pct'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Reading level progress">
                                            <div class="h-full rounded-full {{ $readingLevelView['pct'] >= 75 ? 'bg-emerald-300' : ($readingLevelView['pct'] >= 60 ? 'bg-amber-300' : 'bg-sky-300') }}" style="width: {{ $readingLevelView['pct'] }}%"></div>
                                        </div>
                                        <p class="text-[9px] font-semibold text-[#2D231E] truncate" title="Based on your quiz scores. Your grade is your class, your title is your points.">{{ $readingLevelView['live'] ? 'Based on your quizzes' : 'After your first few quizzes' }}</p>
                                    </div>

                                    <div class="rounded-xl border-2 border-[#2D231E] bg-white p-3 flex flex-col gap-1 shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] min-w-0">
                                        <span class="w-8 h-8 flex items-center justify-center rounded-lg border-2 border-[#2D231E] text-amber-600 bg-amber-50 [&>svg]:w-4 [&>svg]:h-4">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'flame') !!}</span>
                                        @if ($g['streak'] > 0)
                                            <p class="font-pixel text-base font-bold text-[#2D231E] tabular-nums" title="{{ $g['streak'] }} day{{ $g['streak'] === 1 ? '' : 's' }}">{{ $g['streak'] }} day{{ $g['streak'] === 1 ? '' : 's' }}</p>
                                        @else
                                            <p class="font-pixel text-base font-bold text-[#B5A89C]" title="No current streak">No current streak</p>
                                        @endif
                                        <p class="font-pixel text-[8px] text-[#2D231E] uppercase">Streak</p>
                                        <p class="text-[9px] font-semibold text-[#B5A89C]">Best {{ $g['longest_streak'] }}</p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-2">
                                    <div class="rounded-xl border-2 border-[#2D231E] bg-white p-3 flex flex-col gap-1 shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] min-w-0">
                                        <span class="w-8 h-8 flex items-center justify-center rounded-lg border-2 border-[#2D231E] text-emerald-600 bg-emerald-50 [&>svg]:w-4 [&>svg]:h-4">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'hourglass') !!}</span>
                                        <p class="font-pixel text-base font-bold text-[#2D231E] tabular-nums">{{ $totalReadingLabel }}</p>
                                        <p class="font-pixel text-[8px] text-[#2D231E] uppercase">Total Reading Time</p>
                                    </div>

                                    <div class="rounded-xl border-2 border-[#2D231E] bg-white p-3 flex flex-col gap-1 shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] min-w-0">
                                        <span class="w-8 h-8 flex items-center justify-center rounded-lg border-2 border-[#2D231E] text-amber-600 bg-amber-50 [&>svg]:w-4 [&>svg]:h-4">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'open-book') !!}</span>
                                        @if ($reading['finished'] > 0)
                                            <p class="font-pixel text-base font-bold text-[#2D231E] tabular-nums">{{ $reading['finished'] }}</p>
                                        @else
                                            <p class="font-pixel text-base font-bold text-[#B5A89C]">None yet</p>
                                        @endif
                                        <p class="font-pixel text-[8px] text-[#2D231E] uppercase">Stories Finished</p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-2">
                                    <div class="rounded-xl border-2 border-[#2D231E] bg-white p-3 flex flex-col gap-1 shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] min-w-0">
                                        <span class="w-8 h-8 flex items-center justify-center rounded-lg border-2 border-[#2D231E] text-emerald-600 bg-emerald-50 [&>svg]:w-4 [&>svg]:h-4">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'trophy') !!}</span>
                                        @if ($avgQuizScore !== null)
                                            <p class="font-pixel text-base font-bold text-[#2D231E] tabular-nums">{{ $avgQuizScore }}%</p>
                                        @else
                                            <p class="font-pixel text-base font-bold text-[#B5A89C]" title="No quizzes yet">No quizzes yet</p>
                                        @endif
                                        <p class="font-pixel text-[8px] text-[#2D231E] uppercase">Avg Quiz Score</p>
                                    </div>

                                    <div class="rounded-xl border-2 border-[#2D231E] bg-white p-3 flex flex-col gap-1 shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] min-w-0">
                                        <span class="w-8 h-8 flex items-center justify-center rounded-lg border-2 border-[#2D231E] text-rose-500 bg-rose-50 [&>svg]:w-4 [&>svg]:h-4">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'cards') !!}</span>
                                        @if ($quiz['accuracy'] !== null)
                                            <p class="font-pixel text-base font-bold text-[#2D231E] tabular-nums">{{ $quiz['accuracy'] }}%</p>
                                        @else
                                            <p class="font-pixel text-base font-bold text-[#B5A89C]">None yet</p>
                                        @endif
                                        <p class="font-pixel text-[8px] text-[#2D231E] uppercase">Accuracy</p>
                                        @if ($quiz['accuracy'] !== null)
                                            <p class="text-[9px] font-semibold text-[#B5A89C]">{{ $quiz['passed'] }} passed</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Mini-game high scores -->
                    <div class="bg-white border-2 border-[#2D231E] rounded-2xl p-5 space-y-2">
                            <span class="flex items-center gap-1.5 font-pixel text-[11px] font-bold uppercase text-[#2D231E]">High Scores
                                <x-info label="About high scores" modal="hub-help-high-scores"></x-info>
                            </span>
                            <div class="space-y-1.5">
                                @forelse ([
                                    ['k' => 'wordsearch', 'l' => \App\Support\HubStats::GAME_LABELS['wordsearch'], 'i' => 'magnifying-glass', 'c' => 'text-emerald-600'],
                                    ['k' => 'match', 'l' => \App\Support\HubStats::GAME_LABELS['match'], 'i' => 'cards', 'c' => 'text-sky-600'],
                                    ['k' => 'sentence', 'l' => \App\Support\HubStats::GAME_LABELS['sentence'], 'i' => 'book-pile', 'c' => 'text-amber-600'],
                                ] as $gm)
                                    <div class="flex items-center gap-2">
                                        <span class="w-6 h-6 shrink-0 {{ $gm['c'] }} [&>svg]:h-full [&>svg]:w-full">{!! \App\Support\LibraryPresentation::iconGlyph('ui', $gm['i']) !!}</span>
                                        <span class="flex-1 text-xs font-bold text-[#2D231E]">{{ $gm['l'] }}</span>
                                        <span class="font-pixel text-xs font-bold {{ ($gameBests[$gm['k']] ?? 0) > 0 ? 'text-emerald-600' : 'text-[#2D231E]' }}">
                                            {{ $gameBests[$gm['k']] ?? '-' }}
                                        </span>
                                    </div>
                                @empty
                                    <p class="text-xs text-[#2D231E] font-semibold">Play a mini-game to set a high score.</p>
                                @endforelse
                            </div>
                        </div>

                    <!-- Stamp calendar -->
                    <div class="bg-white border-2 border-[#2D231E] rounded-2xl p-5 space-y-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="flex items-center gap-1.5">
                                <span data-cal-title class="font-pixel text-[11px] font-bold uppercase text-[#2D231E]">{{ $currentMonth['month_name'] }} {{ $currentMonth['year'] }}</span>
                                <x-info label="About the stamp calendar" modal="hub-help-stamp-calendar"></x-info>
                            </span>
                                <div class="flex items-center gap-1.5">
                                    <button type="button" data-cal-nav data-cal-action="prev" aria-label="Previous month"
                                            class="w-11 h-11 flex items-center justify-center bg-white border-2 border-[#2D231E] rounded-xl font-pixel text-base font-bold text-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all">‹</button>
                                    <button type="button" data-cal-nav data-cal-action="today" aria-label="Go to today"
                                            class="min-h-[44px] px-3 bg-amber-400 border-2 border-[#2D231E] rounded-xl font-pixel text-[10px] font-bold uppercase text-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all">Today</button>
                                    <button type="button" data-cal-nav data-cal-action="next" aria-label="Next month"
                                            class="w-11 h-11 flex items-center justify-center bg-white border-2 border-[#2D231E] rounded-xl font-pixel text-base font-bold text-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all">›</button>
                                </div>
                            </div>
                            <p data-cal-count class="font-pixel text-[9px] text-emerald-600 font-bold uppercase">{{ $currentMonth['stamped_count'] }} stamped</p>
                            <div data-cal-grid class="grid grid-cols-7 gap-1 text-center">
                                @foreach (['S', 'M', 'T', 'W', 'T', 'F', 'S'] as $d)
                                    <span class="font-pixel text-[8px] text-[#2D231E] uppercase">{{ $d }}</span>
                                @endforeach
                                @foreach ($currentMonth['weeks'] as $week)
                                    @foreach ($week as $cell)
                                        @if ($cell['state'] === 'pad')
                                            <span class="min-h-[44px]"></span>
                                        @elseif ($cell['state'] === 'stamped')
                                            @php
                                                $tierBg = match ($cell['detail']['tier'] ?? 2) {
                                                    1 => 'bg-emerald-400',
                                                    3 => 'bg-emerald-600',
                                                    4 => 'bg-emerald-700',
                                                    default => 'bg-emerald-500',
                                                };
                                            @endphp
                                            <button type="button" data-cal-day data-day="{{ $cell['day'] }}" data-detail="{{ json_encode($cell['detail']) }}" aria-label="Day {{ $cell['day'] }}, stamped. Open details."
                                                    class="min-h-[44px] rounded-lg {{ $tierBg }} border-2 border-[#2D231E] flex items-center justify-center font-pixel text-[10px] font-bold text-white cursor-pointer hover:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-y-[1px] focus-visible:outline-2 focus-visible:outline-amber-400 transition-shadow">
                                                {{ $cell['day'] }}
                                            </button>
                                        @elseif ($cell['state'] === 'active')
                                            <button type="button" data-cal-day data-day="{{ $cell['day'] }}" data-detail="{{ json_encode($cell['detail']) }}" aria-label="Day {{ $cell['day'] }}, active. Open details."
                                                    class="min-h-[44px] rounded-lg bg-amber-100 border-2 border-[#2D231E] flex items-center justify-center font-pixel text-[10px] text-[#2D231E] cursor-pointer hover:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-y-[1px] focus-visible:outline-2 focus-visible:outline-amber-400 transition-shadow">
                                                {{ $cell['day'] }}
                                            </button>
                                        @elseif ($cell['state'] === 'today')
                                            <button type="button" data-cal-day data-day="{{ $cell['day'] }}" data-detail="{{ json_encode($cell['detail']) }}" aria-label="Day {{ $cell['day'] }}, today. Open details."
                                                    class="min-h-[44px] rounded-lg border-2 border-[#2D231E] ring-2 ring-amber-400 flex items-center justify-center font-pixel text-[10px] font-bold text-[#2D231E] cursor-pointer hover:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-y-[1px] focus-visible:outline-2 focus-visible:outline-amber-400 transition-shadow">
                                                {{ $cell['day'] }}
                                            </button>
                                        @elseif ($cell['state'] === 'future')
                                            <span class="min-h-[44px] rounded-lg border border-dashed border-[#E0D6C8] bg-[#F4F0EA] text-[#2D231E] flex items-center justify-center font-pixel text-[10px] select-none" title="Not yet">
                                                {{ $cell['day'] }}
                                            </span>
                                        @else
                                            <span class="min-h-[44px] rounded-lg border border-[#E6DCCF] text-[#2D231E] flex items-center justify-center font-pixel text-[10px]">
                                                {{ $cell['day'] }}
                                            </span>
                                        @endif
                                    @endforeach
                                @endforeach
                            </div>
                            {{-- GitHub-style intensity legend: the calendar's own scale --}}
                            <div class="flex items-center justify-end gap-1" aria-hidden="true">
                                <span class="font-pixel text-[8px] text-[#B5A89C] uppercase mr-1">Less</span>
                                <span class="w-3 h-3 rounded-[4px] border-2 border-[#2D231E] bg-white"></span>
                                <span class="w-3 h-3 rounded-[4px] border-2 border-[#2D231E] bg-emerald-400"></span>
                                <span class="w-3 h-3 rounded-[4px] border-2 border-[#2D231E] bg-emerald-500"></span>
                                <span class="w-3 h-3 rounded-[4px] border-2 border-[#2D231E] bg-emerald-600"></span>
                                <span class="w-3 h-3 rounded-[4px] border-2 border-[#2D231E] bg-emerald-700"></span>
                                <span class="font-pixel text-[8px] text-[#B5A89C] uppercase ml-1">More</span>
                            </div>
                        </div>

                    <x-modal id="hub-day-detail" title="DAY DETAILS" :pixel="true">
                        <div class="space-y-4">
                            <p data-day-date class="font-pixel text-[11px] font-bold uppercase text-[#2D231E]"></p>
                            <div>
                                <span data-day-status class="inline-block"></span>
                            </div>
                            <div data-day-stats class="grid grid-cols-2 gap-2"></div>
                        </div>
                    </x-modal>

                </div>
            </section>

            <!-- ============ BADGES ============ -->
            <section role="tabpanel" data-hub-panel="badges" aria-label="Badges" hidden>
                <div class="space-y-4">
                    <div class="flex justify-end">
                        <a href="{{ route('badges') }}" class="inline-flex items-center min-h-[44px] px-3 sm:px-4 py-2 font-pixel text-[10px] font-bold uppercase bg-amber-400 text-[#2D231E] border-2 border-[#2D231E] rounded-xl shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] hover:bg-amber-300 active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all">Full page</a>
                    </div>
                    @include('partials.badge-shelf', ['rows' => $badgeRows, 'progressValues' => $progressValues])
                </div>
            </section>

            <!-- ============ INBOX ============ -->
            <section role="tabpanel" data-hub-panel="inbox" aria-label="Inbox" hidden>
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="font-pixel text-[11px] font-bold uppercase text-[#2D231E]">Inbox</span>
                        <a href="{{ route('inbox') }}" class="inline-flex items-center min-h-[44px] px-3 sm:px-4 py-2 font-pixel text-[10px] font-bold uppercase bg-amber-400 text-[#2D231E] border-2 border-[#2D231E] rounded-xl shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] hover:bg-amber-300 active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all">View all</a>
                    </div>
                    @if ($inboxNotifications->isEmpty())
                        <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-6 text-center">
                            <p class="text-sm font-semibold text-[#2D231E]">Nothing here yet. Your updates from teachers and achievements will land here.</p>
                        </div>
                    @else
                        <div class="space-y-2">
                            @foreach ($inboxNotifications->take(6) as $notification)
                                @if ($notification->type === 'class.invite.teacher')
                                    <a href="{{ route('inbox') }}" class="w-full text-left flex items-start gap-3 p-3 rounded-2xl border-2 {{ $notification->read_at ? 'bg-white border-[#E6DCCF]' : 'bg-amber-50 border-[#2D231E] shadow-[3px_3px_0px_0px_rgba(45,35,30,0.9)]' }} transition-colors hover:bg-[#FAF6EE]">
                                        <span class="mt-1 w-2.5 h-2.5 shrink-0 rounded-full {{ $notification->read_at ? 'bg-[#E6DCCF]' : 'bg-amber-400' }}"></span>
                                        <span class="flex-1 min-w-0">
                                            <span class="block text-sm font-extrabold text-[#2D231E]">{{ $notification->title }}</span>
                                            <span class="block text-xs font-semibold text-[#2D231E] mt-0.5">Respond in your inbox</span>
                                        </span>
                                        <time class="text-[10px] font-bold text-[#2D231E] shrink-0">{{ $notification->created_at->diffForHumans() }}</time>
                                    </a>
                                @elseif ($notification->type === 'reward.welcome_kit')
                                    <div class="w-full text-left flex items-start gap-3 p-3 rounded-2xl border-2 {{ $notification->read_at ? 'bg-white border-[#E6DCCF]' : 'bg-amber-50 border-[#2D231E] shadow-[3px_3px_0px_0px_rgba(45,35,30,0.9)]' }}">
                                        <span class="mt-1 w-2.5 h-2.5 shrink-0 rounded-full {{ $notification->read_at ? 'bg-[#E6DCCF]' : 'bg-amber-400' }}"></span>
                                        <span class="w-8 h-8 shrink-0 flex items-center justify-center rounded-xl bg-amber-100 border-2 border-[#2D231E] text-amber-600 [&>svg]:h-full [&>svg]:w-full">
                                            {!! \App\Support\LibraryPresentation::iconGlyph('ui', 'key') !!}
                                        </span>
                                        <span class="flex-1 min-w-0">
                                            <span class="block text-sm font-extrabold text-[#2D231E]">{{ $notification->title }}</span>
                                            <span class="block text-xs font-semibold text-[#2D231E] mt-0.5 line-clamp-1">{{ $notification->body }}</span>
                                        </span>
                                        <form method="POST" action="{{ route('inbox.claim', $notification) }}">
                                            @csrf
                                            <button type="submit" class="shrink-0 inline-flex items-center justify-center min-h-[40px] px-3 py-1.5 rounded-xl bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] font-pixel text-[8px] font-bold uppercase shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none hover:bg-amber-500 transition-all cursor-pointer">Claim</button>
                                        </form>
                                    </div>
                                @else
                                    <form method="POST" action="{{ route('inbox.read', $notification) }}">
                                        @csrf
                                        <button
                                            type="submit"
                                            class="w-full text-left flex items-start gap-3 p-3 rounded-2xl border-2 {{ $notification->read_at ? 'bg-white border-[#E6DCCF]' : 'bg-amber-50 border-[#2D231E] shadow-[3px_3px_0px_0px_rgba(45,35,30,0.9)]' }} transition-colors hover:bg-[#FAF6EE] cursor-pointer"
                                        >
                                            <span class="mt-1 w-2.5 h-2.5 shrink-0 rounded-full {{ $notification->read_at ? 'bg-[#E6DCCF]' : 'bg-amber-400' }}"></span>
                                            <span class="flex-1 min-w-0">
                                                <span class="block text-sm font-extrabold text-[#2D231E]">{{ $notification->title }}</span>
                                                @if ($notification->body)
                                                    <span class="block text-xs font-semibold text-[#2D231E] mt-0.5 truncate">{{ $notification->body }}</span>
                                                @endif
                                            </span>
                                            <time class="text-[10px] font-bold text-[#2D231E] shrink-0">{{ $notification->created_at->diffForHumans() }}</time>
                                        </button>
                                    </form>
                                @endif
                            @endforeach
                        </div>
                        @if ($inboxNotifications->count() > 6)
                            <a href="{{ route('inbox') }}" class="block text-center text-xs font-bold text-sky-700 hover:underline">View all {{ $inboxNotifications->count() }}</a>
                        @endif
                    @endif
                </div>
            </section>

        </div>
    </nav>

</div>

<!-- Edit Reading Goal modal (D) — range slider over config('gamification.reading_goal_*'); 0 = no goal (stored null) -->
@php
    $currentGoal = (int) ($snapshot['daily_reading_goal'] ?? 0);
    $readingGoalMin = (int) config('gamification.reading_goal_min');
    $readingGoalMax = (int) config('gamification.reading_goal_max');
    $readingGoalStep = (int) config('gamification.reading_goal_step');
@endphp
<x-modal id="hub-reading-goal-modal" title="EDIT DAILY READING GOAL" pixel>
    <div class="space-y-5 pt-2">
        <p class="text-sm font-semibold text-[#2D231E] leading-relaxed">
                            Pick how many minutes you plan to read each day. The hub's HOME tab tracks your progress against it.
        </p>

        <div class="text-center">
            <span data-hub-reading-goal-output class="font-pixel text-3xl font-bold text-amber-600">{{ $currentGoal === 0 ? 'OFF' : $currentGoal }}</span>
            <span data-hub-reading-goal-unit class="font-pixel text-xs font-bold text-[#2D231E] uppercase ml-1">{{ $currentGoal === 0 ? 'no goal' : 'min / day' }}</span>
        </div>

        <div>
            <input type="range"
                   data-hub-reading-goal-slider
                   min="{{ $readingGoalMin }}" max="{{ $readingGoalMax }}" step="{{ $readingGoalStep }}" value="{{ $currentGoal }}"
                   class="w-full accent-amber-500 cursor-pointer" aria-label="Daily reading goal in minutes">
            <div class="flex justify-between mt-1">
                <span class="font-pixel text-[9px] font-bold text-[#2D231E] uppercase">Less</span>
                <span class="font-pixel text-[9px] font-bold text-[#2D231E] uppercase">More</span>
            </div>
        </div>

        <p data-hub-reading-goal-status aria-live="polite" class="hidden text-xs font-bold text-emerald-700"></p>

        <button type="button" data-hub-save-reading-goal
                class="w-full min-h-[48px] bg-amber-400 border-2 border-[#2D231E] rounded-2xl font-pixel text-xs font-bold text-[#2D231E] shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all cursor-pointer uppercase">
            Save
        </button>
    </div>
</x-modal>

<!-- Edit Study Goal modal — mirrors the Study dashboard's slider -->
@php
    $studyCurrentGoal = (int) ($snapshot['daily_study_goal'] ?? 20);
    $goalMin = (int) config('study.min_goal');
    $goalMax = (int) config('study.max_goal');
    $goalStep = (int) config('study.goal_step');
@endphp
<x-modal id="hub-study-goal-modal" title="EDIT DAILY STUDY GOAL" pixel>
    <div class="space-y-5 pt-2">
        <div class="text-center">
            <span class="font-pixel text-3xl font-bold text-amber-600" data-hub-study-goal-output>{{ $studyCurrentGoal }}</span>
            <span class="font-pixel text-xs font-bold text-[#2D231E] uppercase ml-1">words / day</span>
        </div>

        <div>
            <input type="range"
                   data-hub-study-goal-slider
                   min="{{ $goalMin }}" max="{{ $goalMax }}" step="{{ $goalStep }}" value="{{ $studyCurrentGoal }}"
                   class="w-full accent-amber-500 cursor-pointer" aria-label="Daily study goal">
            <div class="flex justify-between mt-1">
                <span class="font-pixel text-[9px] font-bold text-[#2D231E] uppercase">Less</span>
                <span class="font-pixel text-[9px] font-bold text-[#2D231E] uppercase">More</span>
            </div>
        </div>

        <p class="text-xs font-semibold text-[#2D231E] leading-relaxed">
            Set a study goal to limit how many words you review each day. We recommend around 20 words per day to avoid burnout. Slow and steady wins the race!
        </p>

        <p data-hub-study-goal-status aria-live="polite" class="hidden text-xs font-bold text-emerald-700"></p>

        <button type="button" data-hub-save-study-goal
                class="w-full min-h-[48px] bg-amber-400 border-2 border-[#2D231E] rounded-2xl font-pixel text-xs font-bold text-[#2D231E] shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all cursor-pointer uppercase">
            Save
        </button>
    </div>
</x-modal>

{{-- Standalone hub help modals, opened by an x-info ? with a modal prop. --}}
@include('partials.hub-help-modals')
@endsection

