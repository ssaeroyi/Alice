@props(['label' => null])

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2']) }} aria-hidden="true">
    <svg class="w-4 h-4 animate-spin text-current" fill="none" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity="0.25" stroke-width="3"/>
        <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
    </svg>
    @if ($label)
        <span class="text-[11px] font-bold">{{ $label }}</span>
    @endif
</span>