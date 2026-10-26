@props(['groups', 'q' => null, 'filter' => null])

@php
    $tierChip = fn ($tier) => match ($tier) {
        'strong' => 'bg-emerald-400',
        'learning' => 'bg-sky-400',
        default => 'bg-[#E6DCCF]',
    };
@endphp

<div data-results-inner class="space-y-6">

    @forelse ($groups as $date => $items)
        <div data-group class="space-y-2">
            <div class="flex items-center justify-between gap-3">
                <h3 class="font-pixel text-[10px] font-bold text-[#2D231E] uppercase tracking-wider">
                    @if ($date === 'unknown')
                        Earlier
                    @else
                        Added {{ \Carbon\Carbon::parse($date)->format('M j, Y') }}
                    @endif
                </h3>
                <span class="flex-1 h-px bg-[#E4DCD2]"></span>
                <button type="button" data-select-all-group
                        class="hidden edit-only min-h-[32px] px-2 font-pixel text-[9px] font-bold text-[#2D231E] uppercase underline underline-offset-2 cursor-pointer">
                    Select all
                </button>
            </div>

            @foreach ($items as $item)
                @php $word = $item['model']; @endphp
                <div data-word-row data-word-id="{{ $word->id }}"
                     class="card-hover bg-white border-2 border-[#2D231E] rounded-2xl shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] min-h-[72px] px-4 py-3 flex items-center gap-3 transition-all">

                    <!-- Edit-mode checkbox -->
                    <input type="checkbox" data-word-check value="{{ $word->id }}" aria-label="Select {{ $word->word }}"
                           class="hidden edit-only w-5 h-6 shrink-0 accent-amber-500 cursor-pointer">

                    <!-- Row body: link normally, selection toggle in edit mode -->
                    <a href="{{ route('study.words.show', $word) }}" data-word-link
                       class="group flex-1 min-w-0 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="font-bold text-sm sm:text-base text-[#2D231E] break-words">{{ $word->word }}</p>
                                <span class="inline-flex px-2 py-0.5 rounded-full border-2 border-[#2D231E] font-pixel text-[8px] font-bold uppercase {{ $tierChip($item['tier']) }}">
                                    {{ $item['status'] }}
                                </span>
                            </div>
                            <p class="text-xs font-semibold text-[#2D231E] truncate mt-0.5">
                                {{ $word->definition ?? 'Tap to look it up.' }}
                            </p>
                        </div>
                        <x-icons.arrow-right class="w-5 h-5 text-[#2D231E] shrink-0 group-hover:translate-x-0.5 transition-transform" />
                    </a>
                </div>
            @endforeach
        </div>
    @empty
        <div class="text-center py-12 space-y-3">
            <x-icons.magnifier class="w-10 h-10 mx-auto text-[#2D231E]" />
            @if ($q !== null)
                <p class="font-pixel text-sm font-bold text-[#2D231E] uppercase">No matches</p>
                <p class="text-xs font-semibold text-[#2D231E]">Nothing matches "{{ $q }}". Try a different word.</p>
                <button type="button" data-clear-search
                        class="min-h-[40px] px-4 mt-1 bg-white hover:bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl font-pixel text-[10px] font-bold text-[#2D231E] uppercase shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all cursor-pointer">
                    Clear search
                </button>
            @elseif ($filter === 'forgotten')
                <p class="font-pixel text-sm font-bold text-[#2D231E] uppercase">Nothing forgotten!</p>
                <p class="text-xs font-semibold text-[#2D231E]">You haven't missed any words lately. Keep it up!</p>
            @else
                <p class="font-pixel text-sm font-bold text-[#2D231E] uppercase">No words yet</p>
                <p class="text-xs font-semibold text-[#2D231E]">Save any word while reading and it will show up here.</p>
            @endif
        </div>
    @endforelse

</div>
