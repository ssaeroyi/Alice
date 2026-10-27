@props([
    'at' => null,
    'showLabel' => true,
    'compact' => false,
])

@php
    $state = \App\Support\Presence::state($at);
    $label = \App\Support\Presence::label($at);
@endphp

<span class="inline-flex {{ $compact ? 'items-center gap-1' : 'items-center gap-1.5' }} whitespace-nowrap" title="{{ $label }}">
    <span class="inline-block rounded-full {{ \App\Support\Presence::dotClass($state) }} {{ $compact ? 'h-2 w-2' : 'h-2.5 w-2.5' }}"></span>
    @if ($showLabel)
        <span class="text-[11px] font-semibold text-[#2D231E]">{{ $compact ? \Illuminate\Support\Str::limit($label, 26) : $label }}</span>
    @endif
</span>
