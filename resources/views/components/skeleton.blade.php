@props([
    // 'block' single rounded block; 'cover' fills a fixed aspect box;
    // 'line' single text line; 'card' a story-card-shaped placeholder;
    // 'row' a compact list-item placeholder.
    'variant' => 'block',
    'class' => '',
    'when' => true,
])

@if ($when)
    <span class="alice-skeleton inline-block shrink-0 {{ $variant === 'block' ? 'w-full h-24' : '' }}{{ $variant === 'line' ? 'w-full h-3 rounded' : '' }}{{ $variant === 'cover' ? 'w-full h-full rounded-lg' : '' }}{{ $variant === 'card' ? 'w-full max-w-[300px] rounded-2xl' : '' }}{{ $variant === 'row' ? 'w-full h-16 rounded-xl' : '' }} {{ trim($class) }}" role="status" aria-hidden="true"></span>
@endif