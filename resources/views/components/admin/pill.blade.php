@props(['tone' => 'neutral'])

@php
    $tones = [
        'ok' => 'bg-emerald-100 text-emerald-800',
        'warn' => 'bg-amber-100 text-amber-800',
        'crit' => 'bg-rose-100 text-rose-700',
        'neutral' => 'bg-[#F3ECE0] text-[#2D231E]',
    ];
    $classes = $tones[$tone] ?? $tones['neutral'];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider ' . $classes]) }}>
    {{ $slot }}
</span>