@extends('layouts.authenticated')

@section('page', 'summary')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-8 sm:py-6 space-y-6">

    {{-- Completion header: centered, pixel, mascot-free. --}}
    <div class="pt-2 flex flex-col items-center text-center space-y-3">
        <h1 class="font-pixel text-xl sm:text-2xl font-bold uppercase tracking-wide text-[#2D231E]">
            Story complete!
        </h1>
    </div>

    {{-- Story-clear award (C2): shown once, when the finish button first paid it.
        Re-reads stay quiet. --}}
    @if ($storyClearAward)
        <div data-celebrate="reward" class="bg-white border-4 border-[#2D231E] rounded-3xl shadow-[5px_5px_0px_0px_rgba(45,35,30,1)] p-4 sm:p-5 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <span class="w-9 h-9 flex items-center justify-center rounded-xl bg-amber-100 border-2 border-[#2D231E] text-amber-600 [&>svg]:h-full [&>svg]:w-full">
                    {!! \App\Support\LibraryPresentation::iconGlyph('ui', 'key') !!}
                </span>
                <div>
                    <p class="font-pixel text-[10px] font-bold uppercase text-[#2D231E]">First finish</p>
                    <p class="text-xs font-semibold text-[#8C7B70]">First time finishing this story</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-100 border-2 border-[#2D231E] rounded-lg text-amber-700 text-xs font-bold [&>svg]:h-4 [&>svg]:w-4">
                    {!! \App\Support\LibraryPresentation::iconGlyph('ui', 'key') !!}
                    +{{ $storyClearAward['keys'] }} keys
                </span>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-violet-100 border-2 border-[#2D231E] rounded-lg text-violet-700 text-xs font-bold [&>svg]:h-4 [&>svg]:w-4">
                    {!! \App\Support\LibraryPresentation::iconGlyph('ui', 'laurel-crown') !!}
                    +{{ $storyClearAward['xp'] }} points
                </span>
                <span class="px-2 py-1 bg-[#2D231E] border-2 border-[#2D231E] rounded-lg font-pixel text-[8px] font-bold text-amber-300 uppercase">One time per story</span>
            </div>
        </div>
    @endif

    {{-- No-reward disclosure: shown only when this session truly banked nothing
        (no first-clear card, no points gained). Explains how rewards are earned
        instead of leaving a bare "+0". Server-rendered, no JS. --}}
    @if ($noRewardReason)
        <div class="bg-[#FAF6EE] border-4 border-[#2D231E] rounded-3xl shadow-[5px_5px_0px_0px_rgba(45,35,30,1)] p-4 sm:p-5 flex items-start gap-3">
            <span class="w-9 h-9 shrink-0 flex items-center justify-center rounded-xl bg-white border-2 border-[#2D231E] text-emerald-600 [&>svg]:h-full [&>svg]:w-full">
                {!! \App\Support\LibraryPresentation::iconGlyph('ui', 'hourglass') !!}
            </span>
            <div class="text-left">
                <p class="font-pixel text-[10px] font-bold uppercase text-[#2D231E]">
                    {{ $noRewardReason === 'skipped' ? 'Read to the end' : 'No new rewards' }}
                </p>
                <p class="text-xs font-semibold text-[#8C7B70] mt-1">
                    @if ($noRewardReason === 'skipped')
                        Reading a story to the end for the first time earns 3 keys and 5 points. Open the story and read it to the finish line.
                    @else
                        Finish rewards are one time per story, up to 3 earned a day. Reading a story again earns 1 point per minute.
                    @endif
                </p>
            </div>
        </div>
    @endif

    {{-- Title promotion (T-title): one-shot ceremony when the session crossed
        a Title Ladder tier. Renders the new title + earned keys + badges. --}}
    @if (! empty($titlePromotion) && (! empty($titlePromotion['promoted']) || isset($titlePromotion['titles'])))
        <div data-celebrate="levelup" class="bg-violet-50 border-4 border-[#2D231E] rounded-3xl shadow-[5px_5px_0px_0px_rgba(45,35,30,1)] p-4 sm:p-5">
            <div class="flex items-center gap-3 mb-3">
                <span class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border-2 border-[#2D231E] text-violet-600 [&>svg]:h-full [&>svg]:w-full">
                    {!! \App\Support\LibraryPresentation::iconGlyph('ui', $titlePromotion['icon'] ?? 'queen-crown') !!}
                </span>
                <div>
                    <p class="font-pixel text-[10px] font-bold uppercase text-[#2D231E]">New title reached</p>
                    <p class="font-pixel text-sm font-bold text-violet-700 mt-0.5">{{ $titlePromotion['title'] ?? 'Reader' }}</p>
                </div>
                <span class="ml-auto inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-100 border-2 border-[#2D231E] rounded-lg text-amber-700 text-xs font-bold [&>svg]:h-4 [&>svg]:w-4">
                    {!! \App\Support\LibraryPresentation::iconGlyph('ui', 'key') !!}
                    +{{ $titlePromotion['keys'] ?? 0 }} keys
                </span>
            </div>
            @if (! empty($titlePromotion['badges']))
                <div class="flex flex-wrap items-center gap-2">
                    @foreach ($titlePromotion['badges'] as $badge)
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border-2 border-[#2D231E] rounded-lg text-xs font-bold text-[#2D231E]">
                            <span class="w-4 h-4 [&>svg]:h-full [&>svg]:w-full text-violet-600">
                                {!! \App\Support\LibraryPresentation::iconGlyph('ui', $titlePromotion['icon'] ?? 'queen-crown') !!}
                            </span>
                            {{ $badge['name'] }}
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    {{-- Progress + best streak --}}
    <div class="bg-white border-4 border-[#2D231E] rounded-3xl shadow-[5px_5px_0px_0px_rgba(45,35,30,1)] overflow-hidden">
        <div class="grid grid-cols-2 gap-3 p-5 sm:p-6 bg-[#FAF6EE]">
            <div class="rounded-2xl border-2 border-[#2D231E] bg-white p-4 shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] text-center">
                <p class="font-pixel text-[9px] uppercase text-[#8C7B70]">Read</p>
                <p class="font-pixel text-2xl font-bold text-emerald-600 mt-1">{{ $readPct }}%</p>
                <p class="text-[11px] text-[#8C7B70] font-semibold mt-1">of the story</p>
            </div>
            <div class="rounded-2xl border-2 border-[#2D231E] bg-white p-4 shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] text-center">
                <p class="font-pixel text-[9px] uppercase text-[#8C7B70]">Best streak</p>
                <p class="font-pixel text-2xl font-bold text-amber-600 mt-1">{{ $longestStreak }}</p>
                <p class="text-[11px] text-[#8C7B70] font-semibold mt-1">day{{ $longestStreak === 1 ? '' : 's' }} in a row</p>
            </div>
        </div>
        <div class="px-5 sm:px-6 pb-6 bg-[#FAF6EE]">
            <div class="w-full h-4 bg-[#E6DCCF] border-2 border-[#2D231E] rounded-full overflow-hidden">
                <div class="h-full bg-emerald-500 rounded-full transition-all" style="width: {{ $readPct }}%"></div>
            </div>
            <div class="mt-4 rounded-2xl border-2 border-[#2D231E] bg-white p-4 shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]">
                <div class="flex items-center justify-between text-[11px] font-bold text-[#2D231E]">
                    <span class="font-pixel text-[9px] uppercase text-[#8C7B70]">Points earned this session</span>
                    <span data-points-tally class="font-pixel text-sm text-violet-700">0</span>
                </div>

                {{-- Reader-rank progress: always rendered at the real, final state
                    (the live balance). The tween below replays baseline -> now and
                    flips the title chip whenever an in-session gain crosses a tier. --}}
                <div class="mt-3" data-rank-block>
                    <div class="flex items-center justify-between gap-2">
                        <span data-rank-current class="inline-flex items-center gap-1.5 px-2 py-0.5 bg-violet-100 border-2 border-[#2D231E] rounded-md text-violet-700">
                            <span class="text-violet-700 [&>svg]:h-3.5 [&>svg]:w-3.5">{!! \App\Support\LibraryPresentation::iconGlyph('ui', $rank['icon']) !!}</span>
                            <span data-rank-title class="font-pixel text-[9px] font-bold uppercase">{{ $rank['title'] }}</span>
                        </span>
                        <span data-rank-next class="inline-flex items-center px-2 py-0.5 bg-white border border-[#2D231E]/30 rounded-md font-pixel text-[9px] font-bold text-[#8C7B70] uppercase">{{ $rank['next_title'] ?? 'MAX' }}</span>
                    </div>
                    <div class="mt-2 w-full h-3 bg-[#EDE4D8] border border-[#2D231E] rounded-full overflow-hidden">
                        <div data-rank-bar class="h-full bg-gradient-to-r from-violet-500 to-fuchsia-500 rounded-full" style="width: {{ min(100, max(0, round($rank['progress'] * 100))) }}%"></div>
                    </div>
                    <p data-rank-caption class="mt-1.5 flex items-center gap-1.5 text-[10px] text-[#8C7B70] font-medium">
                        <span class="text-violet-600 [&>svg]:h-3.5 [&>svg]:w-3.5">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'laurel-crown') !!}</span>
                        <span data-rank-caption-text>
                            @if ($rank['next_title'])
                                {{ number_format($rank['points']) }} / {{ number_format($rank['points_to_next'] + $rank['points']) }} points to {{ $rank['next_title'] }}
                            @else
                                MAX: {{ number_format($rank['points']) }}+ points
                            @endif
                        </span>
                    </p>
                </div>
            </div>
        </div>
        <div class="px-5 sm:px-6 py-5 flex flex-wrap items-center justify-center gap-3 border-t-2 border-[#2D231E]/10">
            <a
                href="{{ route('library') }}"
                class="min-h-[44px] px-6 inline-flex items-center font-pixel text-xs font-bold uppercase text-white bg-emerald-600 hover:bg-emerald-700 border-2 border-[#2D231E] rounded-2xl shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all"
            >
                Find another story
            </a>
            <a
                href="{{ route('story.preview', $story) }}"
                class="min-h-[44px] px-5 inline-flex items-center font-pixel text-xs font-bold uppercase bg-white border-2 border-[#2D231E] rounded-2xl shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all"
            >
                Back to story
            </a>
        </div>
    </div>

    {{-- Suggestions: scrollable row with prev/next chevrons. --}}
    @if ($suggestions && count($suggestions) > 0)
        <section>
            <div class="flex items-center justify-between gap-2 mb-3">
                <p class="font-pixel text-[10px] sm:text-xs font-bold uppercase tracking-widest text-[#8C7B70]">You may like next</p>
                <div class="flex items-center gap-2">
                    <div class="flex items-center gap-1" data-suggest-controls>
                        <button
                            type="button"
                            data-suggest-prev
                            aria-label="Previous suggestions"
                            class="w-9 h-9 inline-flex items-center justify-center bg-white border-2 border-[#2D231E] rounded-lg text-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] hover:bg-[#FAF6EE] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all [&>svg]:h-4 [&>svg]:w-4"
                        >
                            <x-icons.arrow-right class="-scale-x-100" />
                        </button>
                        <button
                            type="button"
                            data-suggest-next
                            aria-label="More suggestions"
                            class="w-9 h-9 inline-flex items-center justify-center bg-white border-2 border-[#2D231E] rounded-lg text-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] hover:bg-[#FAF6EE] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all [&>svg]:h-4 [&>svg]:w-4"
                        >
                            <x-icons.arrow-right />
                        </button>
                    </div>
                    <a href="{{ route('library') }}" class="font-pixel text-[9px] font-bold text-amber-600 uppercase hover:underline">
                        View all
                    </a>
                </div>
            </div>
            <div data-suggest-scroll class="flex gap-4 overflow-x-auto pb-2 pt-1 snap-x snap-mandatory scrollbar-none">
                @foreach ($suggestions as $suggestion)
                    <x-story-card :story="$suggestion" />
                @endforeach
            </div>
        </section>
    @endif

</div>

<style>
    @keyframes rank-pop {
        0% { transform: scale(1); }
        40% { transform: scale(1.15); }
        100% { transform: scale(1); }
    }
    [data-rank-current].is-level-up {
        animation: rank-pop 0.5s ease-out;
    }
</style>

<script>
    // Replay the session's point gain as a reader-rank tween: baseline -> now.
    // The bar fills through each tier, and the title chip flips with a small
    // pop whenever an in-session gain crosses into the next reader rank. The
    // server already renders the final state first, so no-JS and reduced-motion
    // viewers always land on the correct answer.
    (function () {
        // baseline = points held when the story was opened; now = the live,
        // fully-banked balance; ladder = the reader-rank table (name/min_points).
        const cfg = {
            baseline: {{ $pointsBaseline }},
            now: {{ $pointsNow }},
            ladder: {!! \Illuminate\Support\Js::from($rankLadder) !!},
        };

        const bar = document.querySelector('[data-rank-bar]');
        const tally = document.querySelector('[data-points-tally]');
        const currentChip = document.querySelector('[data-rank-current]');
        const titleSpan = document.querySelector('[data-rank-title]');
        const nextChip = document.querySelector('[data-rank-next]');
        const captionText = document.querySelector('[data-rank-caption-text]');

        if (!cfg.ladder || !bar || !tally || !currentChip || !titleSpan || !nextChip || !captionText) return;

        const ladder = cfg.ladder;
        const baseline = Number(cfg.baseline);
        const now = Number(cfg.now);
        const delta = Math.max(now - baseline, 0);
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const fmt = new Intl.NumberFormat('en-US');

        const tierAt = (points) => {
            let tier = 0;
            for (let i = 0; i < ladder.length; i += 1) {
                if (points >= ladder[i].min_points) tier = i;
            }
            return tier;
        };

        const render = (points, prevTier) => {
            const tier = tierAt(points);
            const floor = ladder[tier]?.min_points ?? 0;
            const ceiling = ladder[tier + 1]?.min_points ?? null;
            const title = ladder[tier]?.name ?? 'New Reader';
            const nextTitle = ceiling !== null ? (ladder[tier + 1]?.name ?? 'MAX') : 'MAX';
            const gained = Math.max(0, points - baseline);

            bar.style.width = `${ceiling !== null ? Math.min(100, Math.max(0, ((points - floor) / (ceiling - floor)) * 100)) : 100}%`;
            tally.textContent = gained === 0 ? '0' : `+${gained}`;
            // Update only the title text span — not the whole chip — so the
            // rank icon stays put and the pixel font is never lost.
            titleSpan.textContent = title;
            nextChip.textContent = nextTitle;
            captionText.textContent = ceiling !== null
                ? `${fmt.format(points)} / ${fmt.format(ceiling)} points to ${nextTitle}`
                : `MAX: ${fmt.format(points)}+ points`;

            if (tier > prevTier && !reduceMotion) {
                currentChip.classList.remove('is-level-up');
                void currentChip.offsetWidth; // restart the pop
                currentChip.classList.add('is-level-up');
            }

            return tier;
        };

        if (reduceMotion || delta === 0) {
            render(now, tierAt(now));
            return;
        }

        const startTier = tierAt(baseline);
        render(baseline, startTier);

        const step = Math.max(Math.round(delta / 48), 1);
        const tick = 1000 / 48;
        let current = baseline;
        let prevTier = startTier;

        const frame = () => {
            current = Math.min(current + step, now);
            prevTier = render(current, prevTier);

            if (current < now) {
                window.setTimeout(frame, tick);
            }
        };

        frame();
    })();
</script>
@endsection
