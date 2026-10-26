@extends('layouts.authenticated')

@section('page', 'library')

@php
    $genre = $story->genres->first();
    $style = \App\Support\LibraryPresentation::genreStyle($genre?->name ?? '');
    $dotsStyle = sprintf(
        'background-image: radial-gradient(%s 0.8px, transparent 0.8px); background-size: 10px 10px;',
        $style['dots'],
    );
@endphp

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8 space-y-6">

    <div class="flex items-center gap-3">
        <a
            href="{{ route('library') }}"
            class="w-10 h-10 shrink-0 bg-white border-2 border-[#2D231E] text-[#2D231E] rounded-xl font-bold flex items-center justify-center shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-none transition-all"
            aria-label="Go back"
        >
            <x-icons.arrow-left class="w-4 h-4" />
        </a>
        <h1 class="font-pixel text-xs sm:text-sm font-bold text-[#2D231E] uppercase tracking-wider">Story Details</h1>
    </div>

    <div class="bg-white border-4 border-[#2D231E] rounded-3xl shadow-[5px_5px_0px_0px_rgba(45,35,30,1)] overflow-hidden">

        <div
            class="relative w-full h-44 sm:h-56 {{ $style['bg'] }} flex items-center justify-center overflow-hidden"
            style="{{ $dotsStyle }}"
        >
            @if ($story->cover_image)
                <div class="absolute inset-0 alice-skeleton rounded-none" aria-hidden="true" data-cover-skel></div>
                <img src="{{ $story->coverUrl() }}" alt="" loading="lazy" decoding="async" class="absolute inset-0 w-full h-full object-cover" onload="this.previousElementSibling?.remove()" onerror="this.previousElementSibling?.remove(); this.remove()">
            @else
                <div class="w-16 h-16 rounded-full bg-white border-2 border-[#2D231E] flex items-center justify-center shadow-[2px_2px_0px_0px_rgba(45,35,30,1)]">
                    <x-icons.book-open class="w-7 h-7 text-[#2D231E]" />
                </div>
            @endif

            @if ($genre)
                <span class="absolute bottom-3 left-3 bg-white border-2 border-[#2D231E] rounded-lg px-2 py-0.5 font-pixel text-[8px] sm:text-[9px] font-bold text-[#2D231E] uppercase shadow-[1px_1px_0px_0px_rgba(45,35,30,1)]">
                    {{ $genre->name }}
                </span>
            @endif

            <form method="POST" action="{{ route('story.favorite', $story) }}" class="absolute top-3 right-3">
                @csrf
                <button
                    type="submit"
                    aria-pressed="{{ $favorite ? 'true' : 'false' }}"
                    aria-label="{{ $favorite ? 'Remove from your shelf' : 'Save to your shelf' }}"
                    class="min-h-[44px] min-w-[44px] px-3 inline-flex items-center justify-center gap-1.5 font-pixel text-[9px] font-bold uppercase rounded-xl border-2 border-[#2D231E] cursor-pointer transition-all active:translate-x-[1px] active:translate-y-[1px] {{ $favorite ? 'bg-rose-100 text-rose-600 shadow-[1px_1px_0px_0px_rgba(45,35,30,1)]' : 'bg-white text-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)]' }}"
                >
                    <x-icons.heart class="w-4 h-4 {{ $favorite ? 'fill-current' : '' }}" />
                    {{ $favorite ? 'SAVED' : 'SAVE' }}
                </button>
            </form>
        </div>

        <div class="p-6 sm:p-8 space-y-5">
            <div class="space-y-1">
                <h2 class="font-bold text-xl sm:text-2xl text-[#2D231E]">{{ $story->title }}</h2>
                <p class="text-sm font-bold text-[#2D231E]">by {{ $story->author }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span class="border border-[#2D231E]/20 rounded-md px-2 py-0.5 font-pixel text-[9px] font-bold uppercase {{ \App\Support\AliceLibrary::difficultyClasses($band) }}">
                    {{ $band }}
                </span>

                <span class="font-pixel text-[9px] bg-white border-2 border-[#2D231E] text-[#2D231E] px-2 py-1 rounded-lg font-bold uppercase">
                    GRADE {{ $story->reading_level }}
                </span>

                <span class="font-pixel text-[9px] bg-white border-2 border-[#2D231E] text-[#2D231E] px-2 py-1 rounded-lg font-bold uppercase">
                    {{ $minutes }} MIN READ
                </span>
            </div>

            @if ($story->genres->count() > 1)
                <div class="flex flex-wrap gap-2">
                    @foreach ($story->genres->skip(1) as $chipGenre)
                        <span class="text-xs font-bold text-[#2D231E] bg-[#FAF6EE] border-2 border-[#2D231E] px-3 py-1 rounded-xl uppercase">{{ $chipGenre->name }}</span>
                    @endforeach
                </div>
            @endif

            @if ($story->synopsis)
                <p class="text-sm font-bold text-[#2D231E] leading-relaxed">{{ $story->synopsis }}</p>
            @endif

            @php
                $readDone = in_array($readStatus, [\App\Enums\StoryProgressStatus::Read, \App\Enums\StoryProgressStatus::Completed], true);
                $readHint = $readDone
                    ? 'Finished'
                    : ($readPct >= 1 ? "Last left off at {$readPct}%" : 'Not started');
                $quizHint = $quizPassed
                    ? "Passed with {$quizBest}%"
                    : ($quizBest !== null ? "Best {$quizBest}%, needs {$passPct}%" : 'Not attempted');
                $stepsDone = (int) $readDone + (int) ($hasQuiz && $quizPassed);
                $stepsTotal = $hasQuiz ? 2 : 1;
            @endphp

            {{-- What "finish" means before the student taps a button: read it,
                 then pass its quiz at the configured pass mark. Same shape as
                 the assignment checklist, state details ride a tap/hover tip.
                 Collapsed accordion (tap the header to expand); the summary
                 always keeps the "N of M done" progress visible. --}}
            <details class="group rounded-2xl border-2 border-[#2D231E] bg-white overflow-hidden shadow-[2px_2px_0px_0px_rgba(45,35,30,1)]">
                <summary class="flex items-center justify-between gap-3 p-4 min-h-[44px] cursor-pointer list-none select-none [&::-webkit-details-marker]:hidden">
                    <span class="flex items-center gap-2">
                        <svg class="w-3.5 h-3.5 shrink-0 text-[#2D231E] transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 4 4 4-4"/></svg>
                        <p class="text-[11px] font-bold uppercase tracking-wide text-[#2D231E]">To finish this story</p>
                    </span>
                    <span class="text-[10px] font-bold text-[#2D231E]">{{ $stepsDone }} of {{ $stepsTotal }} done</span>
                </summary>

                <div class="px-4 pb-4 space-y-3">
                    {{-- Step 1: Read --}}
                    <div class="flex items-center gap-3 p-3 rounded-xl border-2 {{ $readDone ? 'bg-emerald-50 border-emerald-300' : 'bg-[#FAF6EE] border-[#2D231E]/20' }}">
                        <span class="w-8 h-8 shrink-0 flex items-center justify-center rounded-full border-2 text-xs font-bold {{ $readDone ? 'bg-emerald-500 border-emerald-500 text-white' : 'bg-white border-[#2D231E] text-[#2D231E]' }}">
                            @if ($readDone)
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                            @else
                                1
                            @endif
                        </span>
                        <p class="flex-1 min-w-0 text-sm font-semibold text-[#2D231E]">Read and finish the story</p>
                        <span
                            tabindex="0"
                            role="button"
                            aria-label="{{ $readHint }}"
                            data-tip="{{ $readHint }}"
                            class="shrink-0 text-[10px] font-bold cursor-help {{ $readDone ? 'text-emerald-700' : 'text-[#2D231E]' }}"
                        >{{ $readDone ? 'Done' : 'Not yet' }}</span>
                    </div>

                    {{-- Step 2: Quiz (only stories that have one) --}}
                    @if ($hasQuiz)
                        <div class="flex items-center gap-3 p-3 rounded-xl border-2 {{ $quizPassed ? 'bg-emerald-50 border-emerald-300' : 'bg-[#FAF6EE] border-[#2D231E]/20' }}">
                            <span class="w-8 h-8 shrink-0 flex items-center justify-center rounded-full border-2 text-xs font-bold {{ $quizPassed ? 'bg-emerald-500 border-emerald-500 text-white' : 'bg-white border-[#2D231E] text-[#2D231E]' }}">
                                @if ($quizPassed)
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                                @else
                                    2
                                @endif
                            </span>
                            <p class="flex-1 min-w-0 text-sm font-semibold text-[#2D231E]">Take and pass the quiz</p>
                            <span
                                tabindex="0"
                                role="button"
                                aria-label="{{ $quizHint }}"
                                data-tip="{{ $quizHint }}"
                                class="shrink-0 text-[10px] font-bold cursor-help {{ $quizPassed ? 'text-emerald-700' : 'text-[#2D231E]' }}"
                            >{{ $quizPassed ? 'Done' : 'Not yet' }}</span>
                        </div>
                    @endif
                </div>
            </details>

            <div class="flex flex-col sm:flex-row gap-3 pt-1">
                <a
                    href="{{ route('reader.show', $story) }}"
                    class="flex-1 min-h-[44px] font-pixel text-xs px-6 py-3 rounded-2xl bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] font-bold flex items-center justify-center shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] transition-all uppercase"
                >
                    READ STORY
                </a>

                @if ($hasQuiz)
                    <a
                        href="{{ route('reader.quiz', $story) }}"
                        class="flex-1 min-h-[44px] font-pixel text-xs px-6 py-3 rounded-2xl bg-[#FAF6EE] border-2 border-[#2D231E] text-[#2D231E] font-bold flex items-center justify-center shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] transition-all uppercase"
                    >
                        TAKE QUIZ
                    </a>
                @endif
            </div>

            <footer class="border-t-2 border-dashed border-[#E6DCCF] pt-4 space-y-2">
                @if ($story->license || $story->source_url)
                    <p class="text-xs font-bold leading-relaxed text-[#2D231E]">
                        @if ($story->license)
                            License: {{ $story->license }}.
                        @endif
                        @if ($story->source_url)
                            Source: <a href="{{ $story->source_url }}" target="_blank" rel="noopener" class="underline break-all hover:text-[#2D231E]">{{ $story->source_url }}</a>
                        @endif
                    </p>
                @endif
                @if ($story->provenance)
                    <p class="text-xs font-bold leading-relaxed text-[#2D231E]">{{ $story->provenance }}</p>
                @endif
            </footer>
        </div>
    </div>

</div>
@endsection
