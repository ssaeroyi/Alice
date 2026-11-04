@props([
    'percent',
    'color' => null,
    'size' => 9,
])

@php
    $p = max(0, min(100, (int) $percent));
    // Color-free default: the caller decides; nothing stays ambiguous grey.
    // Size rides real SVG width/height attributes - a class-built size never
    // existed in the compiled CSS and let this donut fill its whole card.
    $stroke = $color ?? ($p >= 70 ? '#34D399' : ($p >= 40 ? '#FBBF24' : '#FB7185'));
    $circ = 2 * pi() * 14;
    $px = 36 + $size;
@endphp

<svg width="{{ $px }}" height="{{ $px }}" viewBox="0 0 36 36" class="shrink-0" aria-hidden="true">
    <circle cx="18" cy="18" r="14" fill="none" stroke="#F3ECE0" stroke-width="4" />
    <circle cx="18" cy="18" r="14" fill="none" stroke="{{ $stroke }}" stroke-width="4" stroke-linecap="round" stroke-dasharray="{{ $circ }}" stroke-dashoffset="{{ $circ * (1 - $p / 100) }}" transform="rotate(-90 18 18)" />
</svg>
