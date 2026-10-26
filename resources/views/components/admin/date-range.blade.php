@props([
    'label' => 'All time',
    'from' => null,
    'to' => null,
])

@php
    $resettable = is_string(request()->query('from'));
@endphp

<div {{ $attributes->merge(['class' => 'relative']) }} data-range-picker>
    <label for="range-display" class="sr-only">Date range</label>
    <input
        id="range-display"
        type="text"
        data-date-display
        readonly
        value="{{ $label }}"
        aria-haspopup="dialog"
        aria-expanded="false"
        class="w-full h-11 px-3 {{ $resettable ? 'pr-12' : '' }} flex items-center justify-between gap-2 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] focus:outline-none focus:border-[#2D231E] cursor-pointer"
    >
    @if ($resettable)
        <button
            type="button"
            data-range-clear
            aria-label="Clear custom dates"
            class="absolute right-8 top-1/2 -translate-y-1/2 w-5 h-5 flex items-center justify-center rounded text-[#2D231E] hover:text-[#2D231E] hover:bg-[#F3ECE0] transition-colors cursor-pointer"
        >
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    @endif
    <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-[#2D231E]">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"></path>
        </svg>
    </span>

    <input type="hidden" name="from" data-range-from value="{{ $from }}">
    <input type="hidden" name="to" data-range-to value="{{ $to }}">
</div>