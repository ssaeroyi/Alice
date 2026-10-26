@extends('layouts.authenticated')

@section('title', $assignment->title)
@section('page', 'assignment')

@section('content')
    @php
        $story = $assignment->story;
        $stepsTotal = $hasQuiz ? 2 : 1;
        $stepsDone = ($readDone ? 1 : 0) + ($hasQuiz && $quizDone ? 1 : 0);
        // Group-scoped assignments land back inside their class drill-down;
        // school-wide ones fall back to the classes landing view.
        $backUrl = $assignment->group_id
            ? route('student.classwork') . '?class=' . $assignment->group_id . ($assignment->isFinished() ? '&tab=past' : '')
            : route('student.classwork');
    @endphp
    <div class="max-w-3xl mx-auto space-y-5">
        {{-- Back into the class + teacher attribution --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ $backUrl }}"
                class="inline-flex items-center gap-1.5 min-h-[44px] font-pixel text-[9px] font-bold uppercase text-sky-700 hover:text-sky-800 transition-colors"
                aria-label="Back to classwork">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>
                Classwork
            </a>
            <span class="inline-flex items-center gap-1.5 text-xs font-bold text-[#8C7B70]">
                From
                <span class="font-pixel text-[10px] uppercase text-[#2D231E]">{{ $assignment->teacher?->first_name ?: 'Your teacher' }}</span>
            </span>
        </div>

        {{-- One cohesive container: header, instructions and task live together --}}
        <div class="bg-white border-4 border-[#2D231E] rounded-3xl shadow-[5px_5px_0px_0px_rgba(45,35,30,1)] overflow-hidden">

            {{-- Header --}}
            <div class="p-6 sm:p-7">
                <div class="flex flex-col sm:flex-row sm:items-start gap-5">
                    <div class="relative w-24 h-32 shrink-0 bg-sky-400 border-2 border-[#2D231E] rounded-lg overflow-hidden flex items-center justify-center shadow-[3px_3px_0px_0px_rgba(45,35,30,0.9)]">
                        @if ($story && $story->coverUrl() !== '')
                            <div class="absolute inset-0 alice-skeleton rounded-none" aria-hidden="true" data-cover-skel></div>
                            <img src="{{ $story->coverUrl() }}" alt="" loading="lazy" decoding="async" class="w-full h-full object-cover object-left-top" onload="this.previousElementSibling?.remove()" onerror="this.previousElementSibling?.remove(); this.remove()">
                        @else
                            <span class="font-pixel text-[8px] font-extrabold text-[#2D231E] px-1 text-center leading-tight">{{ \Illuminate\Support\Str::limit(strtoupper($story?->title ?? 'ASSIGNMENT'), 14) }}</span>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <h1 class="text-xl sm:text-2xl font-extrabold text-[#2D231E] leading-tight">{{ $assignment->title }}</h1>
                        <p class="text-sm font-bold text-[#8C7B70] mt-1">{{ $story?->title ?? 'No story attached' }}</p>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            @if ($assignment->due_at)
                                <span class="inline-flex items-center gap-1 text-xs font-bold px-3 py-1.5 rounded-full {{ $assignment->isOverdue() ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-800' }}">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M3 11h18M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    Due {{ $assignment->due_at->format('M j, Y') }}
                                    @if ($assignment->isOverdue()) - Overdue @endif
                                </span>
                            @else
                                <span class="text-xs font-bold px-3 py-1.5 rounded-full bg-[#F3ECE0] text-[#8C7B70]">No due date</span>
                            @endif
                            @if ($completed)
                                <span class="inline-flex items-center gap-1 text-xs font-bold px-3 py-1.5 rounded-full bg-emerald-100 text-emerald-700">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                                    Done
                                </span>
                            @else
                                <span class="text-xs font-bold px-3 py-1.5 rounded-full bg-amber-100 text-amber-800">In progress</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            @if ($assignment->instructions)
                <div class="h-px bg-[#2D231E]/15"></div>
                <div class="p-6 sm:p-7">
                    <p class="font-pixel text-[9px] font-bold uppercase tracking-wider text-[#8C7B70] mb-3">Instructions</p>
                    <p class="text-sm font-semibold text-[#2D231E] leading-relaxed whitespace-pre-wrap">{{ $assignment->instructions }}</p>
                </div>
            @endif

            @if ($story)
                <div class="h-px bg-[#2D231E]/15"></div>
                <div class="p-6 sm:p-7 space-y-4">
                    <div class="flex items-center justify-between gap-3">
                        <p class="font-pixel text-[9px] font-bold uppercase tracking-wider text-[#8C7B70]">Your task</p>
                        <span class="text-[10px] font-bold text-[#8C7B70]">{{ $stepsDone }} of {{ $stepsTotal }} done</span>
                    </div>

                    {{-- Step 1: Read --}}
                    <div class="flex items-center gap-3 p-3 rounded-xl border-2 {{ $readDone ? 'bg-emerald-50 border-emerald-300' : 'bg-[#FAF6EE] border-[#2D231E]/20' }}">
                        <span class="w-8 h-8 shrink-0 flex items-center justify-center rounded-full border-2 font-pixel text-xs font-bold {{ $readDone ? 'bg-emerald-500 border-emerald-500 text-white' : 'bg-white border-[#2D231E] text-[#2D231E]' }}">
                            @if ($readDone)
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                            @else
                                1
                            @endif
                        </span>
                        <p class="flex-1 min-w-0 text-sm font-semibold text-[#2D231E]">
                            Read <span class="font-extrabold">"{{ $story->title }}"</span>
                            @if (! $hasQuiz)
                                <span class="text-[#8C7B70]">Finishing marks the assignment complete.</span>
                            @endif
                        </p>
                        <span class="shrink-0 text-[10px] font-bold {{ $readDone ? 'text-emerald-700' : 'text-[#8C7B70]' }}">{{ $readDone ? 'Done' : 'Not yet' }}</span>
                    </div>

                    {{-- Step 2: Quiz (only stories that have one) --}}
                    @if ($hasQuiz)
                        <div class="flex items-center gap-3 p-3 rounded-xl border-2 {{ $quizDone ? 'bg-emerald-50 border-emerald-300' : 'bg-[#FAF6EE] border-[#2D231E]/20' }}">
                            <span class="w-8 h-8 shrink-0 flex items-center justify-center rounded-full border-2 font-pixel text-xs font-bold {{ $quizDone ? 'bg-emerald-500 border-emerald-500 text-white' : 'bg-white border-[#2D231E] text-[#2D231E]' }}">
                                @if ($quizDone)
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                                @else
                                    2
                                @endif
                            </span>
                            <p class="flex-1 min-w-0 text-sm font-semibold text-[#2D231E]">Take the quiz <span class="text-[#8C7B70]">Any score marks it done.</span></p>
                            <span class="shrink-0 text-[10px] font-bold {{ $quizDone ? 'text-emerald-700' : 'text-[#8C7B70]' }}">{{ $quizDone ? 'Done' : 'Not yet' }}</span>
                        </div>
                    @endif

                    {{-- One action that always answers "what should I do right now?" --}}
                    @if ($completed)
                        <div class="rounded-2xl border-2 border-emerald-300 bg-emerald-50 p-5 flex flex-wrap items-center gap-4">
                            <span class="w-12 h-12 shrink-0 flex items-center justify-center rounded-full bg-emerald-500 text-white">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="font-pixel text-[10px] font-bold uppercase tracking-wider text-emerald-700">Assignment complete</p>
                                <p class="text-sm font-semibold text-emerald-900 mt-0.5">You completed it.</p>
                            </div>
                            <a href="{{ route('reader.show', $story) }}" class="shrink-0 inline-flex items-center justify-center min-h-[44px] px-5 py-2.5 bg-white border-2 border-emerald-500 text-emerald-700 rounded-xl text-sm font-extrabold hover:bg-emerald-100 transition-colors">
                                Read again
                            </a>
                        </div>
                    @elseif ($readDone && $hasQuiz && ! $quizDone)
                        <div class="flex flex-wrap gap-3 pt-1">
                            <a href="{{ route('reader.quiz', $story) }}" class="inline-flex items-center justify-center min-h-[44px] px-6 py-2.5 bg-emerald-600 text-white rounded-xl text-sm font-extrabold hover:bg-emerald-700 transition-colors">
                                Take the quiz
                            </a>
                            <a href="{{ route('reader.show', $story) }}" class="inline-flex items-center justify-center min-h-[44px] px-5 py-2.5 bg-white border-2 border-[#2D231E] text-[#2D231E] rounded-xl text-sm font-extrabold hover:bg-[#FAF6EE] transition-colors">
                                Read again
                            </a>
                        </div>
                    @else
                        <div class="flex flex-wrap gap-3 pt-1">
                            <a href="{{ route('reader.show', $story) }}" class="inline-flex items-center justify-center min-h-[44px] px-6 py-2.5 bg-emerald-600 text-white rounded-xl text-sm font-extrabold hover:bg-emerald-700 transition-colors">
                                Start reading
                            </a>
                        </div>
                    @endif
                </div>
            @else
                <div class="h-px bg-[#2D231E]/15"></div>
                <div class="p-6 sm:p-7">
                    <p class="font-pixel text-[9px] font-bold uppercase tracking-wider text-[#8C7B70] mb-2">Your task</p>
                    <p class="text-sm font-semibold text-[#2D231E]">No story attached. Read the instructions above and message your teacher with questions.</p>
                </div>
            @endif
        </div>
    </div>
@endsection