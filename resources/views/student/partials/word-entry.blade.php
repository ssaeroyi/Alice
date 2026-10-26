@props([
    'entry',
    'context' => 'dictionary', // 'dictionary' | 'saved'
    'savedWord' => null,
    'status' => null,
    'tier' => null,
])

@php
    $posColors = [
        'noun' => 'bg-sky-100 text-sky-700',
        'verb' => 'bg-emerald-100 text-emerald-700',
        'adjective' => 'bg-amber-100 text-amber-700',
        'adverb' => 'bg-violet-100 text-violet-700',
        'function' => 'bg-stone-100 text-stone-600',
    ];
    $freqColors = ['COMMON' => 'bg-emerald-100 text-emerald-700 border-emerald-300', 'UNCOMMON' => 'bg-amber-100 text-amber-700 border-amber-300', 'RARE' => 'bg-stone-100 text-stone-600 border-stone-300'];
@endphp

<div data-word-entry class="space-y-5">

    @if ($entry === null)
        <div class="text-center py-10 space-y-2">
            <x-icons.magnifier class="w-10 h-10 mx-auto text-[#2D231E]" />
            <p class="text-sm font-bold text-[#2D231E]">Look up any word to see its meanings here.</p>
        </div>
    @elseif (! $entry['found'])
        <div class="text-center py-10 space-y-2">
            @if ($entry['filtered'] ?? false)
                <x-icons.face-neutral class="w-10 h-10 mx-auto text-[#2D231E]" />
                <p class="font-pixel text-sm font-bold text-[#2D231E] uppercase">Blocked word</p>
                <p class="text-xs sm:text-sm font-semibold text-[#2D231E]">We can't show that word.</p>
            @else
                <x-icons.magnifier class="w-10 h-10 mx-auto text-[#2D231E]" />
                <p class="font-pixel text-sm font-bold text-[#2D231E] uppercase">No results</p>
                <p class="text-xs sm:text-sm font-semibold text-[#2D231E]">
                    @if ($entry['looks_like_name'])
                        "{{ $entry['word'] }}" looks like a name. Try a common word instead.
                    @else
                        We couldn't find a definition for "{{ $entry['word'] }}". Check the spelling and try again.
                    @endif
                </p>
            @endif
        </div>
    @else
        <!-- Headword -->
        <header class="space-y-1.5">
            <div class="flex items-baseline gap-3 flex-wrap">
                <h2 class="font-pixel text-2xl sm:text-3xl font-bold text-[#2D231E] break-words">{{ $entry['resolved_to'] }}</h2>

                @if ($entry['freq_badge'])
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full border-2 font-pixel text-[9px] font-bold uppercase {{ $freqColors[$entry['freq_badge']] ?? 'border-[#E6DCCF]' }}">
                        {{ $entry['freq_badge'] }}
                    </span>
                @endif

                @if ($savedWord !== null && $status !== null)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full border-2 border-[#2D231E] font-pixel text-[9px] font-bold uppercase {{ $tier === 'strong' ? 'bg-emerald-400' : ($tier === 'learning' ? 'bg-sky-400' : 'bg-[#E6DCCF]') }}">
                        {{ $status }}
                    </span>
                @endif
            </div>

            @if ($entry['form_label'])
                <p class="text-xs font-semibold text-[#2D231E] italic">{{ $entry['form_label'] }}</p>
            @endif
        </header>

        <!-- Saved-word review stats -->
        @if ($context === 'saved' && $savedWord !== null)
            <div class="grid grid-cols-3 gap-2">
                <div class="bg-[#FAF6EE] border-2 border-[#E6DCCF] rounded-xl p-2.5 text-center">
                    <span class="block font-pixel text-[8px] font-bold text-[#2D231E] uppercase">Correct</span>
                    <span class="text-sm font-extrabold text-emerald-600">{{ $savedWord->times_correct }}</span>
                </div>
                <div class="bg-[#FAF6EE] border-2 border-[#E6DCCF] rounded-xl p-2.5 text-center">
                    <span class="block font-pixel text-[8px] font-bold text-[#2D231E] uppercase">Missed</span>
                    <span class="text-sm font-extrabold text-rose-600">{{ $savedWord->times_wrong }}</span>
                </div>
                <div class="bg-[#FAF6EE] border-2 border-[#E6DCCF] rounded-xl p-2.5 text-center">
                    <span class="block font-pixel text-[8px] font-bold text-[#2D231E] uppercase">Lookups</span>
                    <span class="text-sm font-extrabold text-[#2D231E]">{{ $savedWord->lookup_count }}</span>
                </div>
            </div>
        @endif

        <!-- Senses -->
        <div class="border-t-2 border-dashed border-[#E6DCCF] pt-4 space-y-4">
            @foreach ($entry['senses'] as $group)
                <div>
                    <span class="inline-flex px-2.5 py-0.5 rounded-full font-pixel text-[9px] font-bold uppercase {{ $posColors[$group['pos']] ?? 'bg-stone-100 text-stone-600' }}">
                        {{ $group['pos'] }}
                    </span>
                    <ol class="mt-2.5 space-y-3 list-none">
                        @foreach ($group['list'] as $sense)
                            <li class="flex gap-2.5">
                                <span class="font-pixel text-[11px] font-bold text-amber-600 mt-0.5">{{ $loop->iteration }}.</span>
                                <div class="flex-1 space-y-1.5 min-w-0">
                                    <p class="text-sm font-bold text-[#2D231E] leading-relaxed">{{ $sense['definition'] }}</p>
                                    @if (! empty($sense['example']))
                                        <div class="flex items-start gap-2 bg-[#FAF6EE] border-l-4 border-amber-400 rounded-r-xl px-3 py-2">
                                            <button type="button" data-say="{{ $sense['example'] }}" title="Listen"
                                                    class="shrink-0 mt-0.5 w-6 h-6 flex items-center justify-center text-[#2D231E] hover:text-[#2D231E] transition-colors cursor-pointer">
                                                <x-icons.speaker class="w-3.5 h-3.5" />
                                            </button>
                                            <p class="text-xs font-semibold text-[#2D231E] italic leading-relaxed">"{{ $sense['example'] }}"</p>
                                        </div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endforeach
        </div>

        <!-- Actions -->
        <div class="border-t-2 border-dashed border-[#E6DCCF] pt-4 space-y-2">
            <div class="flex flex-col sm:flex-row gap-2">
                <button type="button" data-say="{{ $entry['resolved_to'] }}"
                        class="flex-1 min-h-[44px] inline-flex items-center justify-center gap-2 bg-white border-2 border-[#2D231E] rounded-2xl font-pixel text-[11px] font-bold text-[#2D231E] shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-none transition-all cursor-pointer uppercase">
                    <x-icons.speaker class="w-4 h-4" /> Pronounce
                </button>

                @if ($savedWord !== null)
                    <span class="flex-1 min-h-[44px] inline-flex items-center justify-center gap-2 bg-emerald-400 border-2 border-[#2D231E] rounded-2xl font-pixel text-[11px] font-bold text-[#2D231E] uppercase cursor-default" data-saved-flag>
                        In your words
                    </span>
                @else
                    <button type="button" data-save-word="{{ $entry['resolved_to'] }}"
                            class="flex-1 min-h-[44px] inline-flex items-center justify-center gap-2 bg-amber-400 border-2 border-[#2D231E] rounded-2xl font-pixel text-[11px] font-bold text-[#2D231E] shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-none transition-all cursor-pointer uppercase">
                        Save to my words
                    </button>
                @endif
            </div>
            <p class="hidden text-xs font-bold text-rose-600" data-save-error></p>
        </div>

        <!-- Say it out loud: dictation practice for the headword. -->
        <div class="border-t-2 border-dashed border-[#E6DCCF] pt-4">
            <p class="font-pixel text-[9px] font-bold text-[#2D231E] uppercase tracking-wider">Say it out loud</p>
            <div class="pt-2">
                @php
                    // Word not in the dictionary (or a proper noun) is out-of-
                    // vocabulary for the offline speech model: grade it gently.
                    $entryOov = ! (bool) ($entry['found'] ?? true) || (bool) ($entry['looks_like_name'] ?? false);
                @endphp
                <x-pronunciation-check :target="$entry['resolved_to']" :oov="$entryOov" />
            </div>
        </div>
    @endif
</div>
