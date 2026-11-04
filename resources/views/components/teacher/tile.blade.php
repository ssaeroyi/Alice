@props([
    'label',
    'value',
    'sub' => null,
    'href' => null,
    'ring' => null,
    'bar' => null,
    'ringColor' => null,
    'barColor' => 'bg-amber-400',
    'muted' => false,
])

@php
    $tag = $href !== null ? 'a' : 'div';
    $tone = $muted ? 'text-[#8C7B70]' : 'text-[#2D231E]';
@endphp

<{{ $tag }}
    @if ($href !== null) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => 'block bg-white border border-[#E6DCCF] rounded-xl p-4 hover:border-amber-300 hover:shadow-[0_4px_14px_rgba(45,35,30,0.07)] transition-all '.($href !== null ? 'cursor-pointer' : '')]) }}
>
    <p class="flex items-center gap-1.5 text-[10px] font-extrabold uppercase tracking-wider {{ $tone }}">
        {{ $label }}
        @if ($attributes->has('tooltip'))
            <x-admin.info>{{ $attributes->get('tooltip') }}</x-admin.info>
        @endif
    </p>

    <div class="mt-1.5 flex items-end justify-between gap-2">
        <p class="min-w-0 text-xl sm:text-2xl font-extrabold tabular-nums {{ $tone }}">
            {{ $value }}
        </p>

        @if ($ring !== null)
            <x-teacher.ring :percent="$ring" :color="$ringColor" />
        @elseif ($bar !== null)
            <div class="shrink-0 w-20 h-2.5 rounded-full bg-[#F3ECE0] overflow-hidden">
                <div class="h-full rounded-full {{ $barColor }}" style="width: {{ max(0, min(100, (int) $bar)) }}%"></div>
            </div>
        @endif
    </div>

    @if ($sub !== null)
        <p class="text-[11px] font-semibold text-[#8C7B70] mt-1">{{ $sub }}</p>
    @endif
</{{ $tag }}>
