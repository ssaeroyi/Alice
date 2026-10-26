{{-- Feasibility gallery, step 1 of the add/edit modal (swapped in place by the AJAX mutate handler). --}}
<p class="text-xs font-semibold text-[#2D231E]">
    Pick a move the story's words can support. Green tiles are already added, and red tiles are short on words.
</p>

@php
    $missionGroupTitles = [
        'scan' => 'Scan the story',
        'sense' => 'Word sense',
        'build' => 'Build the sentence',
    ];
@endphp

@foreach ($missionGroupTitles as $missionGroupKey => $missionGroupTitle)
    @php $missionGroupRows = collect($missionCatalog)->where('group', $missionGroupKey); @endphp
    @if ($missionGroupRows->isEmpty())
        @continue
    @endif
    <h3 class="mt-3 text-[11px] font-extrabold uppercase tracking-wide text-emerald-700">{{ $missionGroupTitle }}</h3>
    <div class="mt-1.5 grid grid-cols-1 sm:grid-cols-2 gap-2">
        @foreach ($missionGroupRows as $row)
            @php
                $rowAdded = $missionActions->contains('action_key', $row['action_key']);
                $rowUnfit = $row['count'] < $row['need_floor'];
                $rowHasNeed = in_array($row['type'], ['defmatch', 'sentblank', 'affix'], true) ? '0' : '1';
            @endphp
            <button
                type="button"
                data-mission-rule
                data-rule-type="{{ $row['type'] }}"
                data-rule-value="{{ $row['value'] }}"
                data-rule-label="{{ $row['label'] }}"
                data-rule-count="{{ $row['count'] }}"
                data-rule-prompt="{{ $row['prompt'] }}"
                data-rule-flavor="{{ $row['flavor'] }}"
                data-rule-need="{{ $rowHasNeed }}"
                data-rule-multi="{{ $row['type'] === 'initial' ? '1' : '0' }}"
                data-rule-letters="{{ isset($row['letters']) ? json_encode($row['letters']) : '' }}"
                @disabled($rowUnfit || $rowAdded)
                class="w-full text-left p-3 border rounded-lg transition-colors cursor-pointer disabled:cursor-not-allowed hover:border-emerald-600 disabled:hover:border-[#E6DCCF] {{ $rowAdded ? 'bg-emerald-50 border-emerald-200' : ($rowUnfit ? 'bg-rose-50 border-rose-200' : 'bg-white border-[#E6DCCF]') }}"
            >
                <span class="flex items-center justify-between gap-2">
                    <span class="min-w-0 text-xs font-bold text-[#2D231E]">{{ $row['label'] }}</span>
                    <span class="shrink-0 inline-flex items-center px-1.5 py-0.5 bg-emerald-100 rounded text-[9px] font-extrabold uppercase tracking-wider text-emerald-800">{{ strtoupper($row['kind']) }}</span>
                </span>
                <span class="mt-1.5 flex items-center justify-between gap-2">
                    <span class="text-[10px] font-bold text-[#2D231E] tabular-nums">{{ $row['count'].' '.$row['unit'] }}</span>
                    @if ($rowAdded)
                        <span class="shrink-0 inline-flex items-center px-1.5 py-0.5 bg-emerald-600 rounded text-[9px] font-extrabold uppercase tracking-wider text-white">Added</span>
                    @elseif ($rowUnfit)
                        <span class="shrink-0 inline-flex items-center px-1.5 py-0.5 bg-rose-100 rounded text-[9px] font-extrabold uppercase tracking-wider text-rose-700">Needs {{ $row['need_floor'] }}</span>
                    @endif
                </span>
            </button>
        @endforeach
    </div>
@endforeach

<p class="mt-3 text-[11px] font-semibold text-[#2D231E]">
    Panel shows this story's word pools. Action turns the pick into one battle move, and every battle draws 3 random moves from the pool.
</p>
