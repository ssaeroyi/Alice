@extends('layouts.authenticated')

@section('page', 'quiz-results')

@php
    // Presentation copy, ported from the quiz player's client maps so the
    // standalone results page stays uniform with the live render it replaces.
    $tierCopy = $scorePct === 100 ? 'PERFECT RUN!' : ($passed ? 'GOOD WORK!' : 'KEEP PRACTICING!');
    $reasonCopy = [
        'quiz_first_clear' => 'You finished the quiz and earned the full reward.',
        'quiz_first_attempt' => 'Nice try! Clear the quiz to earn keys next time.',
        'quiz_improvement' => 'You beat your best score. Well done!',
        'quiz_no_reward' => 'You did not beat your best score yet. Keep practicing!',
    ];
    $reasonText = $reasonCopy[$reason] ?? '';

    // Review grouped by question type so the breakdown grid and the answer
    // review read in clear, consistent sections (placement-card style).
    $groups = collect($review)->groupBy(fn ($item) => (string) ($item['type'] ?? 'Question'));

    // Pick a warm accent for the pass state vs. the keep-practicing state.
    $passAccent = $passed ? 'text-emerald-700' : 'text-amber-600';

    $fmt = fn (float $v) => rtrim(rtrim(number_format($v, 2), '0'), '.');
@endphp

@section('content')
<div class="max-w-2xl mx-auto px-4 py-6 space-y-6">

    {{-- One unified results panel: header + accuracy + by-type + reader-rank
        + reward all live inside a single card, split by light dashed dividers,
        so the page reads as one whole result instead of many scattered boxes. --}}
    <div class="bg-white border-4 border-[#2D231E] rounded-3xl shadow-[5px_5px_0px_0px_rgba(45,35,30,1)] divide-y divide-dashed divide-[#2D231E]/20 overflow-hidden">

        <div class="px-5 sm:px-8 py-6 flex flex-col items-center text-center space-y-3">
            <h1 class="font-pixel text-xl sm:text-2xl font-bold uppercase tracking-wide text-[#2D231E]">
                Quiz Complete!
            </h1>
            <p class="font-pixel text-sm sm:text-base font-bold uppercase tracking-widest {{ $passed ? 'text-emerald-600' : 'text-[#2D231E]' }}">
                {{ $tierCopy }}
            </p>
        </div>

        {{-- Title promotion (T-title): one-shot ceremony when this quiz crossed
            a Title Ladder tier. Renders the new title + earned keys + badges. --}}
        @if (! empty($titlePromotion['quiz']) && (! empty($titlePromotion['quiz']['promoted']) || isset($titlePromotion['quiz']['titles'])))
            <div data-celebrate="levelup" class="px-5 sm:px-8 py-6 bg-violet-50">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border-2 border-[#2D231E] text-violet-600 [&>svg]:h-full [&>svg]:w-full">
                        {!! \App\Support\LibraryPresentation::iconGlyph('ui', $titlePromotion['quiz']['icon'] ?? 'queen-crown') !!}
                    </span>
                    <div class="min-w-0">
                        <p class="font-pixel text-[10px] font-bold uppercase text-[#2D231E]">New title reached</p>
                        <p class="font-pixel text-sm font-bold text-violet-700 mt-0.5">{{ $titlePromotion['quiz']['title'] ?? 'Reader' }}</p>
                    </div>
                    <span class="ml-auto inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-100 border-2 border-[#2D231E] rounded-lg text-amber-700 text-xs font-bold [&>svg]:h-4 [&>svg]:w-4">
                        {!! \App\Support\LibraryPresentation::iconGlyph('ui', 'key') !!}
                        +{{ $titlePromotion['quiz']['keys'] ?? 0 }} keys
                    </span>
                </div>
                @if (! empty($titlePromotion['quiz']['badges']))
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        @foreach ($titlePromotion['quiz']['badges'] as $badge)
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border-2 border-[#2D231E] rounded-lg text-xs font-bold text-[#2D231E]">
                                <span class="w-4 h-4 [&>svg]:h-full [&>svg]:w-full text-violet-600">
                                    {!! \App\Support\LibraryPresentation::iconGlyph('ui', $titlePromotion['quiz']['icon'] ?? 'queen-crown') !!}
                                </span>
                                {{ $badge['name'] }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        <div class="px-5 sm:px-8 py-6">
            <div class="flex items-end justify-between gap-3">
                <p class="font-pixel text-[10px] uppercase tracking-wider text-[#8C7B70]">Accuracy</p>
                <p class="font-pixel text-2xl font-bold {{ $passAccent }}">{{ $scorePct }}%</p>
            </div>
            <div class="mt-3 w-full bg-white border-2 border-[#2D231E] rounded-full h-5 overflow-hidden">
                <div class="h-full {{ $passed ? 'bg-amber-400' : 'bg-[#E6DCCF]' }} transition-all duration-700" style="width: {{ max(0, min(100, $scorePct)) }}%"></div>
            </div>
            <p class="mt-2 text-xs font-semibold text-[#8C7B70]">
                You earned {{ $fmt($pointsEarned) }} of {{ $fmt($pointsTotal) }} points.
            </p>

            @if ($groups->isNotEmpty())
                <div class="mt-5 pt-5 border-t-2 border-dashed border-[#2D231E]/15">
                    <p class="font-pixel text-[10px] font-bold uppercase tracking-widest text-[#8C7B70]">By question type</p>
                    <div class="mt-3 grid grid-cols-2 gap-3 sm:flex sm:flex-wrap sm:justify-center">
                        @foreach ($groups as $type => $items)
                            @php
                                // Points earned / possible per type (third-party partial
                                // credit: matching = 1 per pair, sequence = 1 per item).
                                $earnedPts = (float) $items->sum(fn ($item) => (float) ($item['earned'] ?? 0));
                                $totalPts = (int) $items->sum(fn ($item) => (int) ($item['worth'] ?? 0));
                            @endphp
                            <div class="w-full bg-white border-2 border-[#2D231E] rounded-xl p-3 text-center shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]">
                                <span class="block font-pixel text-[9px] font-bold uppercase text-[#8C7B70]">{{ $type }}</span>
                                <span class="mt-1 block text-sm font-extrabold text-[#2D231E]">{{ $fmt($earnedPts) }}/{{ $totalPts }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="px-5 sm:px-8 py-6" data-rank-block>
            <div class="flex items-center justify-between gap-2 text-[11px] font-bold text-[#2D231E]">
                <span class="font-pixel text-[9px] uppercase text-[#8C7B70]">Points earned</span>
                <span class="font-pixel text-sm text-violet-700">+{{ $xp }} points</span>
            </div>
            <div class="mt-3 flex items-center justify-between gap-2">
                <span data-rank-current class="inline-flex items-center gap-1.5 px-2 py-0.5 bg-violet-100 border-2 border-[#2D231E] rounded-md text-violet-700">
                    <span class="text-violet-700 [&>svg]:h-3.5 [&>svg]:w-3.5">{!! \App\Support\LibraryPresentation::iconGlyph('ui', $rank['icon']) !!}</span>
                    <span data-rank-title class="font-pixel text-[9px] font-bold uppercase">{{ $rank['title'] }}</span>
                </span>
                <span data-rank-next class="inline-flex items-center px-2 py-0.5 bg-white border border-[#2D231E]/30 rounded-md font-pixel text-[9px] font-bold text-[#8C7B70] uppercase">{{ $rank['next_title'] ?? 'MAX' }}</span>
            </div>
            <div class="mt-2 w-full h-3 bg-[#EDE4D8] border border-[#2D231E] rounded-full overflow-hidden">
                <div data-rank-bar class="h-full bg-gradient-to-r from-violet-500 to-fuchsia-500 rounded-full" style="width: {{ min(100, max(0, round($rank['progress'] * 100))) }}%"></div>
            </div>
            <p class="mt-1.5 flex items-center gap-1.5 text-[10px] text-[#8C7B70] font-medium">
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

        @if ($keys > 0)
            <div data-celebrate="reward" class="px-5 sm:px-8 py-6 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="w-9 h-9 flex items-center justify-center rounded-xl bg-amber-100 border-2 border-[#2D231E] text-amber-600 [&>svg]:h-full [&>svg]:w-full">
                        {!! \App\Support\LibraryPresentation::iconGlyph('ui', 'key') !!}
                    </span>
                    <div>
                        <p class="font-pixel text-[10px] font-bold uppercase text-[#2D231E]">Rewards earned</p>
                        <p class="text-xs font-semibold text-[#8C7B70]">{{ $reasonText }}</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-100 border-2 border-[#2D231E] rounded-lg text-amber-700 text-xs font-bold [&>svg]:h-4 [&>svg]:w-4">
                        {!! \App\Support\LibraryPresentation::iconGlyph('ui', 'key') !!}
                        +{{ $keys }} keys
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-violet-100 border-2 border-[#2D231E] rounded-lg text-violet-700 text-xs font-bold [&>svg]:h-4 [&>svg]:w-4">
                        {!! \App\Support\LibraryPresentation::iconGlyph('ui', 'laurel-crown') !!}
                        +{{ $xp }} points
                    </span>
                    @if ($multiplier != 1.0)
                        <span class="px-2 py-1 bg-[#2D231E] border-2 border-[#2D231E] rounded-lg font-pixel text-[8px] font-bold text-amber-300 uppercase">×{{ $fmt($multiplier) }}</span>
                    @endif
                </div>
            </div>
        @elseif ($xp > 0)
            <div class="px-5 sm:px-8 py-6 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="w-9 h-9 flex items-center justify-center rounded-xl bg-violet-100 border-2 border-[#2D231E] text-violet-600 [&>svg]:h-full [&>svg]:w-full">
                        {!! \App\Support\LibraryPresentation::iconGlyph('ui', 'laurel-crown') !!}
                    </span>
                    <div>
                        <p class="font-pixel text-[10px] font-bold uppercase text-[#2D231E]">Points earned</p>
                        <p class="text-xs font-semibold text-[#8C7B70]">{{ $reasonText }}</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-violet-100 border-2 border-[#2D231E] rounded-lg text-violet-700 text-xs font-bold [&>svg]:h-4 [&>svg]:w-4">
                        {!! \App\Support\LibraryPresentation::iconGlyph('ui', 'laurel-crown') !!}
                        +{{ $xp }} points
                    </span>
                </div>
            </div>
        @else
            <div class="px-5 sm:px-8 py-6 flex items-start gap-3">
                <span class="w-9 h-9 shrink-0 flex items-center justify-center rounded-xl bg-white border-2 border-[#2D231E] text-emerald-600 [&>svg]:h-full [&>svg]:w-full">
                    {!! \App\Support\LibraryPresentation::iconGlyph('ui', 'key') !!}
                </span>
                <div class="text-left">
                    <p class="font-pixel text-[10px] font-bold uppercase text-[#2D231E]">No new reward</p>
                    <p class="text-xs font-semibold text-[#8C7B70] mt-1">{{ $reasonText ?: 'Keep going. Clear the quiz to earn keys and points.' }}</p>
                </div>
            </div>
        @endif
    </div>


    {{-- Answer review: placement-card style (chip + your answer + correct answer),
        no point totals, and the disclosure chevron hidden. --}}
    <details class="group text-left bg-white border-2 border-[#2D231E] rounded-2xl overflow-hidden list-none [&::-webkit-details-marker]:hidden">
        <summary class="flex items-center justify-between gap-2 min-h-[44px] px-4 py-3 cursor-pointer bg-[#FAF6EE] hover:bg-[#F3ECE0] transition-colors select-none list-none [&::-webkit-details-marker]:hidden">
            <span class="font-pixel text-[9px] font-bold uppercase tracking-widest text-[#2D231E]">SEE MY ANSWERS</span>
            <span class="text-xs font-bold text-[#8C7B70] group-open:hidden">tap to open</span>
            <span class="text-xs font-bold text-[#8C7B70] hidden group-open:inline">tap to close</span>
        </summary>
        @if ($groups->isNotEmpty())
            <div class="px-4 py-4 space-y-5">
                @foreach ($groups as $type => $items)
                    <div>
                        <span class="font-pixel text-[9px] font-bold uppercase tracking-wider text-[#8C7B70]">{{ $type }}</span>
                        <ul class="mt-2 space-y-2">
                            @foreach ($items as $index => $item)
                                @php
                                    $correct = (bool) ($item['correct'] ?? false);
                                    $partial = ! $correct && (float) ($item['earned'] ?? 0) > 0;
                                    $chip = $correct ? 'CORRECT' : ($partial ? 'PARTIAL' : 'WRONG');
                                    $chipClass = $correct ? 'text-emerald-600' : ($partial ? 'text-amber-600' : 'text-rose-600');
                                @endphp
                                <li class="border-2 border-[#E6DCCF] rounded-xl px-3 py-2">
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="text-xs font-bold text-[#2D231E] min-w-0 break-words">{{ ($index + 1) }}. {{ $item['prompt'] ?? '' }}</p>
                                        <span class="shrink-0 font-pixel text-xs {{ $chipClass }}">{{ $chip }}</span>
                                    </div>
                                    <div class="mt-1.5 text-xs font-semibold text-[#2D231E] min-w-0 break-words">
                                        <span class="font-bold text-[#8C7B70]">Your answer: </span>{{ $item['submitted'] ?? '-' }}
                                    </div>
                                    @if (! $correct)
                                        <div class="text-xs font-semibold text-emerald-700 min-w-0 break-words">
                                            <span class="font-bold text-emerald-700">Correct answer: </span>{{ $item['expected'] ?? '-' }}
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        @else
            <div class="px-4 py-4 text-xs font-semibold text-[#8C7B70]">No answers to review.</div>
        @endif
    </details>

    {{-- Actions: clean text-only pixel buttons, no chevron glyphs. --}}
    <div class="flex flex-wrap items-center justify-center gap-3">
        <a
            href="{{ $retakeUrl }}"
            class="min-h-[48px] inline-flex items-center px-5 font-pixel text-[10px] font-bold uppercase text-white bg-emerald-600 hover:bg-emerald-700 border-2 border-[#2D231E] rounded-xl shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-none transition-all"
        >
            TAKE AGAIN
        </a>
        <a
            href="{{ $backUrl }}"
            class="min-h-[44px] inline-flex items-center px-4 font-pixel text-[9px] font-bold uppercase bg-white border-2 border-[#2D231E] rounded-xl shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all"
        >
            BACK TO STORY
        </a>
    </div>

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
    // Replay the points this quiz banked as a reader-rank tween: before -> now.
    // baseline = the rank position before this quiz paid (now minus this
    // attempt's xp/points), now = the live post-settlement balance. The server
    // renders the final state first, so no-JS and reduced-motion viewers always
    // land on the correct answer, and a refresh (read-only page) replays the
    // same bounded delta.
    (function () {
        const cfg = {
            baseline: {{ $rankBaseline }},
            now: {{ $rankNow }},
            ladder: {!! \Illuminate\Support\Js::from($rankLadder) !!},
        };

        const bar = document.querySelector('[data-rank-bar]');
        const currentChip = document.querySelector('[data-rank-current]');
        const titleSpan = document.querySelector('[data-rank-title]');
        const nextChip = document.querySelector('[data-rank-next]');
        const captionText = document.querySelector('[data-rank-caption-text]');

        if (!cfg.ladder || !bar || !currentChip || !titleSpan || !nextChip || !captionText) return;

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

            bar.style.width = `${ceiling !== null ? Math.min(100, Math.max(0, ((points - floor) / (ceiling - floor)) * 100)) : 100}%`;
            // Update only the title text span — not the whole chip — so the
            // rank icon stays put and the pixel font is never lost.
            titleSpan.textContent = title;
            nextChip.textContent = nextTitle;
            captionText.textContent = ceiling !== null
                ? `${fmt.format(points)} / ${fmt.format(ceiling)} points to ${nextTitle}`
                : `MAX: ${fmt.format(points)}+ points`;

            if (tier > prevTier && !reduceMotion) {
                currentChip.classList.remove('is-level-up');
                void currentChip.offsetWidth;
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

@if ($freezeUsed)
<script>
    (() => {
        const fire = () => {
            if (window.showToast) {
                window.showToast('A Streak Restore shielded your streak after a missed day.', { tone: 'freeze' });
            }
        };
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fire);
        } else {
            fire();
        }
    })();
</script>
@endif
@endsection
