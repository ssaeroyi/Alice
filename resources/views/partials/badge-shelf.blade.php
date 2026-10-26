@php
    $categories = config('badges.categories', []);
    $earnedCount = $rows->where('earned', true)->count();
    $totalCount = $rows->count();
    $grouped = $rows->groupBy('category');
    // Reach stats for the detail modal. Callers pass them; default to an empty
    // shelf so the partial still renders standalone.
    $unlockStats = $unlockStats ?? ['holders' => [], 'students' => 0];
    $holders = $unlockStats['holders'];
    $students = (int) $unlockStats['students'];
@endphp

<p class="font-pixel text-[11px] font-bold text-[#2D231E] uppercase tracking-wider text-center">
    {{ $earnedCount }} of {{ $totalCount }} unlocked
</p>

@if ($totalCount === 0)
    <div class="bg-white border-4 border-[#2D231E] rounded-2xl p-8 text-center shadow-[4px_4px_0px_0px_rgba(45,35,30,1)]">
        <p class="text-sm text-[#2D231E] font-medium">No badges yet. You're at the start of a great adventure!</p>
        <p class="text-xs text-[#2D231E] font-semibold mt-1">Read a story, ace a quiz, or play a mini-game to earn your first one.</p>
    </div>
@endif

@foreach ($categories as $catKey => $catLabel)
    @php
        $catRows = $grouped->get($catKey, collect());
        if ($catRows->isEmpty()) { continue; }
        $catEarned = $catRows->where('earned', true)->count();
        $nextUp = $catRows
            ->reject(fn ($r) => $r['earned'])
            ->sortByDesc(function ($r) use ($progressValues) {
                $v = $progressValues[$r['badge']->key] ?? null;

                return $v !== null && $v['target'] > 0 ? $v['current'] / $v['target'] : 0;
            })
            ->first();
    @endphp

    <!-- Category header -->
    <div class="pt-2">
        <div class="flex items-center justify-between gap-3 border-b-2 border-dashed border-[#E6DCCF] pb-2 mb-3">
            <h2 class="font-pixel text-[11px] font-bold text-[#2D231E] uppercase tracking-wider">{{ $catLabel }}</h2>
            <span class="text-[11px] font-bold text-[#2D231E] uppercase tracking-wider">{{ $catEarned }}/{{ $catRows->count() }}</span>
        </div>
    </div>

    @if ($nextUp)
        <div class="inline-flex items-center gap-2 bg-amber-100 border-2 border-[#2D231E] rounded-xl px-3 py-1.5 mb-3">
            <span class="w-4 h-4 [&>svg]:h-4 [&>svg]:w-4 text-[#2D231E]">{!! \App\Support\LibraryPresentation::iconGlyph('ui', $nextUp['icon']) !!}</span>
            <span class="text-[11px] font-bold text-[#2D231E]">Next up: {{ $nextUp['badge']->name }}</span>
        </div>
    @endif

    <!-- Badge shelf -->
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
        @foreach ($catRows as $r)
            @php
                $rarity = config('badges.rarity.' . $r['rarity'], ['text' => 'text-stone-700', 'bg' => 'bg-stone-100', 'border' => 'border-stone-400']);
                $v = $progressValues[$r['badge']->key] ?? null;
                $hasTarget = $v !== null && $v['target'] > 0;
                $pct = $r['earned'] ? 100 : ($hasTarget ? (int) min(100, round(($v['current'] / $v['target']) * 100)) : 0);
                // Locked cards only show a bar once there is something to show.
                // Titles always qualify (points owed are worth seeing); the rest
                // wait until they have real progress rather than a bar at zero.
                $showProgress = ! $r['earned'] && $hasTarget && ($v['current'] > 0 || $v['unit'] === 'pts');
                $isNext = $nextUp && $nextUp['badge']->id === $r['badge']->id;

                // Everything the detail modal cannot read off the card itself.
                $held = (int) ($holders[$r['badge']->id] ?? 0);
                $detailJson = json_encode([
                    'name' => $r['badge']->name,
                    'description' => $r['badge']->description,
                    'rarity' => $r['badge']->rarity->label(),
                    'earned' => $r['earned'],
                    'unlockedAt' => $r['awarded_at']?->format('M j, Y \a\t g:i A'),
                    'hasProgress' => $showProgress,
                    'current' => $showProgress ? $v['current'] : 0,
                    'target' => $showProgress ? $v['target'] : 0,
                    'unit' => $showProgress ? $v['unit'] : null,
                    'pct' => $pct,
                    'holders' => $held,
                    'students' => $students,
                    'percent' => $students > 0 ? (int) round($held / $students * 100) : null,
                ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            @endphp
            <div
                class="card-hover cursor-pointer relative flex flex-col items-center text-center bg-white border-4 border-[#2D231E] rounded-2xl p-4 shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] {{ $isNext ? 'ring-4 ring-amber-400' : '' }} {{ $r['earned'] ? '' : 'opacity-70 saturate-0' }}"
                title="{{ $r['earned'] ? $r['badge']->name . ' — unlocked ' . optional($r['awarded_at'])->format('M j, Y') : 'Locked' }}"
                data-modal-open="badge-detail-modal"
                data-badge-detail="{{ $detailJson }}"
                role="button"
                tabindex="0"
            >
                @if ($r['earned'])
                <span class="absolute top-2 right-2 w-5 h-5 rounded-full bg-emerald-500 border-2 border-[#2D231E] flex items-center justify-center text-white">
                    <svg class="w-3 h-3 stroke-current" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3.5" d="M5 13l4 4L19 7"/>
                    </svg>
                </span>
                @else
                <span class="font-semibold absolute top-2 right-2 w-5 h-5 rounded-full bg-[#D8CDBF] border-2 border-[#2D231E] flex items-center justify-center text-[#2D231E]">
                    <svg class="w-3 h-3 stroke-current" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 12h14M5 12l4-4m-4 4l4 4m6-4l-4-4m4 4l-4 4"/>
                    </svg>
                </span>
                @endif

                <span class="w-16 h-16 mb-3 {{ $rarity['bg'] }} {{ $rarity['border'] }} border-[3px] border-[#2D231E] rounded-2xl flex items-center justify-center {{ $rarity['text'] }} [&>svg]:h-9 [&>svg]:w-9">
                    {!! \App\Support\LibraryPresentation::iconGlyph('ui', $r['icon']) !!}
                </span>

                <h3 class="font-pixel text-[11px] font-bold text-[#2D231E] uppercase leading-tight">{{ $r['badge']->name }}</h3>
                <p class="text-[11px] text-[#2D231E] font-semibold mt-1 leading-snug">{{ $r['badge']->description }}</p>

                @if ($r['earned'])
                <span class="mt-3 inline-flex items-center gap-1 px-2 py-0.5 {{ $rarity['text'] }} {{ $rarity['bg'] }} {{ $rarity['border'] }} border-2 rounded-md text-[10px] font-bold uppercase tracking-wider">
                    <span class="w-3 h-3 [&>svg]:h-full [&>svg]:w-full">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'crown') !!}</span>
                    {{ $r['badge']->rarity->label() }}
                </span>
                @elseif ($showProgress)
                <div class="mt-3 w-full">
                    <div class="h-2 bg-[#F1E9DD] border border-[#E6DCCF] rounded overflow-hidden">
                        <div class="h-full bg-amber-400" style="width: {{ $pct }}%"></div>
                    </div>
                    <p class="text-[9px] text-[#2D231E] font-semibold mt-1 text-center">
                        {{ number_format($v['current']) }} / {{ number_format($v['target']) }}{{ $v['unit'] !== null ? ' ' . $v['unit'] : '' }}
                    </p>
                </div>
                @endif
            </div>
        @endforeach
    </div>
@endforeach

<!-- Badge detail: one instance shared by every card on the shelf (and the hub's
     embedded copy). Cards carry data-badge-detail; badge-detail.js fills this. -->
<x-modal id="badge-detail-modal" title="BADGE DETAILS" :closeable="true" pixel>
    <div class="space-y-4 text-xs font-semibold text-[#2D231E]">
        <div class="flex items-center gap-3">
            <span data-badge-icon class="w-14 h-14 shrink-0 bg-amber-100 border-[3px] border-[#2D231E] rounded-2xl flex items-center justify-center [&>svg]:h-8 [&>svg]:w-8"></span>
            <div class="min-w-0">
                <p data-badge-name class="font-pixel text-xs font-bold uppercase text-[#2D231E]"></p>
                <p data-badge-rarity class="mt-1 font-pixel text-[9px] font-bold uppercase text-amber-600"></p>
            </div>
        </div>

        <p data-badge-description class="text-[11px] text-[#2D231E] font-semibold leading-relaxed"></p>

        <!-- Earned: the exact moment. Locked: progress toward it. -->
        <div class="bg-[#FAF6EE] border-2 border-[#E6DCCF] rounded-xl p-3">
            <p data-badge-status-label class="font-pixel text-[9px] uppercase text-[#2D231E]">Unlocked at</p>
            <p data-badge-status-text class="mt-1 text-[11px] font-bold text-[#2D231E]"></p>
            <div data-badge-status-bar class="mt-2 h-2 bg-white border border-[#E6DCCF] rounded overflow-hidden">
                <div data-badge-status-fill class="h-full bg-amber-400" style="width: 0%"></div>
            </div>
        </div>

        <!-- How rare it is among the student body. -->
        <div data-badge-reach class="bg-amber-50 border-2 border-[#2D231E] rounded-xl p-3">
            <p class="font-pixel text-[9px] uppercase text-[#2D231E]">THIS BADGE</p>
            <p data-badge-reach-text class="mt-1 text-[11px] font-bold text-[#2D231E]"></p>
            <div class="mt-2 h-2 bg-white border border-[#E6DCCF] rounded overflow-hidden">
                <div data-badge-reach-fill class="h-full bg-emerald-400" style="width: 0%"></div>
            </div>
        </div>
    </div>
</x-modal>
