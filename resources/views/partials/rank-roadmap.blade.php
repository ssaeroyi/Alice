@php
    // Rank roadmap modal (R1 gamification, interactive). Server renders the
    // FINAL state (fill width, marker, tier rows, default detail) so the modal
    // is fully usable with no JS; rank-roadmap.js only adds the wipe/stagger
    // animation and the tap-for-detail behaviour. Rendered once in the
    // authenticated layout, guarded by @if ($gamer) so immersive pages (where
    // $gamer is null) never include it.
    $roadmapTiers = collect(config('gamification.points.titles', []))
        ->sortBy('min_points')
        ->values();
    $roadmapTiers = $roadmapTiers->isEmpty()
        ? collect([['name' => 'New Reader', 'icon' => 'open-book', 'min_points' => 0]])
        : $roadmapTiers;

    $roadmapPoints = max(0, (int) ($points ?? ($gamer['rank']['points'] ?? 0)));
    $roadmapCount = $roadmapTiers->count();
    $roadmapCurrent = 0;
    foreach ($roadmapTiers as $ri => $rt) {
        if ($roadmapPoints >= (int) $rt['min_points']) {
            $roadmapCurrent = $ri;
        }
    }
    $roadmapLastMin = (int) $roadmapTiers->last()['min_points'];
    $roadmapCount = max(1, $roadmapCount);
    $roadmapTop = $roadmapCurrent >= $roadmapCount - 1;
    $roadmapNext = $roadmapTop ? null : $roadmapTiers[$roadmapCurrent + 1];
    $roadmapToNext = $roadmapNext !== null ? max(0, (int) $roadmapNext['min_points'] - $roadmapPoints) : null;
    $roadmapDefaultDetail = $roadmapTop
        ? 'You reached the top of the ladder!'
        : number_format($roadmapToNext).' more points to reach '.$roadmapNext['name'];
@endphp

<x-modal id="rank-roadmap-modal" title="TITLE LADDER" :closeable="true" pixel wide>
    <style>
        [data-rank-map-row].rank-map-row-anim {
            opacity: 0;
            transform: translateY(6px);
            transition: opacity .45s ease, transform .45s ease;
        }
    </style>

    <div class="space-y-4 text-xs font-semibold text-[#2D231E]">

        <!-- Tap/focus detail line -->
        <p data-rank-map-detail class="font-semibold min-h-[18px] text-[11px] text-[#2D231E]" aria-live="polite">{{ $roadmapDefaultDetail }}</p>

        <!-- Tier list: one vertical rail (timeline) line per tier -->
        <ul class="relative space-y-2" data-rank-rail>
            <span class="absolute left-[15px] top-2 bottom-2 w-0.5 bg-[#E6DCCF] border-l border-[#E6DCCF]" aria-hidden="true"></span>
            @foreach ($roadmapTiers as $ri => $rt)
                @php
                    $rowState = $roadmapPoints >= (int) $rt['min_points'] ? ($ri === $roadmapCurrent ? 'current' : 'unlocked') : 'locked';
                @endphp
                <li data-rank-map-row data-index="{{ $ri }}"
                    tabindex="0"
                    role="button"
                    aria-pressed="{{ $rowState === 'current' ? 'true' : 'false' }}"
                    aria-label="{{ $rt['name'] }} — {{ $rowState === 'locked' ? 'locked' : ($rowState === 'current' ? 'you are here' : 'unlocked') }}"
                    class="relative flex items-center gap-2.5 rounded-xl border-2 p-2 min-h-[44px] cursor-pointer focus:outline-none focus:ring-2 focus:ring-amber-400 transition-all {{ $rowState === 'locked' ? 'bg-white border-[#E6DCCF]' : ($rowState === 'current' ? 'bg-amber-400 border-[#2D231E] shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]' : 'bg-violet-50 border-[#2D231E]') }}">
                    <span class="relative z-10 w-8 h-8 shrink-0 rounded-lg border-2 border-[#2D231E] bg-white flex items-center justify-center {{ $rowState === 'locked' ? 'text-[#B5A89C]' : 'text-violet-700' }} [&>svg]:h-4 [&>svg]:w-4">
                        {!! \App\Support\LibraryPresentation::iconGlyph('ui', $rt['icon'] ?? 'open-book') !!}
                    </span>
                    <span class="flex-1 min-w-0">
                        <span class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                            <span class="font-pixel text-[9px] font-bold uppercase truncate {{ $rowState === 'locked' ? 'text-[#2D231E]' : 'text-[#2D231E]' }}">{{ $rt['name'] }}</span>
                            @if ($rowState === 'current')
                                <span class="font-pixel text-[8px] font-bold uppercase text-rose-600 bg-white border border-[#2D231E] rounded-full px-1.5 py-0.5">You are here</span>
                            @endif
                        </span>
                        <span class="block text-[10px] font-semibold {{ $rowState === 'locked' ? 'text-[#B5A89C]' : 'text-[#2D231E]' }}">Reach {{ number_format((int) $rt['min_points']) }} pts</span>
                    </span>
                    <span class="shrink-0 font-pixel text-[8px] font-bold uppercase {{ $rowState === 'locked' ? 'text-[#B5A89C]' : ($rowState === 'current' ? 'text-rose-600' : 'text-emerald-600') }}">
                        {{ $rowState === 'locked' ? 'Locked' : ($rowState === 'current' ? 'Now' : 'Unlocked') }}
                    </span>
                </li>
            @endforeach
        </ul>

        <script type="application/json" data-rank-map-data>
{!! json_encode([
    'points' => $roadmapPoints,
    'tiers' => $roadmapTiers->map(fn ($rt) => ['name' => (string) $rt['name'], 'min' => (int) $rt['min_points']])->values(),
    'currentTier' => $roadmapCurrent,
    'isTop' => $roadmapTop,
    'next' => $roadmapNext !== null ? ['name' => (string) $roadmapNext['name'], 'min' => (int) $roadmapNext['min_points']] : null,
    'toNext' => $roadmapToNext,
    'lastMin' => $roadmapLastMin,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}
        </script>
    </div>
</x-modal>