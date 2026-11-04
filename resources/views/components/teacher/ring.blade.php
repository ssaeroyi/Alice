@props([
    'percent',
    'color' => null,
    'size' => 9,
])

@php
    $p = max(0, min(100, (int) $percent));
    // Color-free default: the caller decides; nothing stays ambiguous grey.
    $stroke = $color ?? ($p >= 70 ? '#34D399' : ($p >= 40 ? '#FBBF24' : '#FB7185'));
    $circ = 2 * pi() * 14;
    $textPx = $size;
@endphp

<svg class="shrink-0 w-[{{ 36 + $size }}px] h-[{{ 36 + $size }}px]" viewBox="0 0 36 36" aria-hidden="true">
    <circle cx="18" cy="18" r="14" fill="none" stroke="#F3ECE0" stroke-width="4" />
    <circle cx="18" cy="18" r="14" fill="none" stroke="{{ $stroke }}" stroke-width="4" stroke-linecap="round" stroke-dasharray="{{ $circ }}" stroke-dashoffset="{{ $circ * (1 - $p / 100) }}" transform="rotate(-90 18 18)" />
    <text x="18" y="18" text-anchor="middle" dominant-baseline="central" font-size="9" font-weight="800" fill="#2D231E">{{ $p }}%</text>
</svg>
