@props(['tone' => 'neutral', 'type' => 'button'])

@php
    $tones = [
        'neutral' => 'bg-emerald-600 text-white hover:bg-emerald-700',
        'safe' => 'bg-emerald-600 text-white hover:bg-emerald-700',
        'caution' => 'bg-amber-600 text-white hover:bg-amber-700',
        'danger' => 'bg-rose-600 text-white hover:bg-rose-700',
        'outline' => 'bg-[#F3ECE0] border border-[#E6DCCF] text-[#2D231E] hover:border-[#2D231E]',
        'outline-danger' => 'bg-[#F3ECE0] border border-rose-300 text-rose-600 hover:bg-rose-50',
    ];
@endphp

<button
    {{ $attributes->merge([
        'type' => $type,
        'class' => 'inline-flex items-center justify-center min-h-[44px] px-5 py-2 rounded-lg text-xs font-bold transition-colors cursor-pointer '.($tones[$tone] ?? $tones['neutral']),
    ]) }}
>
    {{ $slot }}
</button>