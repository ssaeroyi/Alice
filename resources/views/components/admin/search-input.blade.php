@props([
    'action',
    'name' => 'q',
    'value' => '',
    'placeholder' => 'Search…',
    'label' => 'Search',
    'formClass' => 'flex-1 relative',
    'formId' => null,
])

<form
    method="GET"
    action="{{ $action }}"
    data-auto-filter
    class="{{ $formClass }} {{ $attributes->get('class') }}"
    @if ($formId) id="{{ $formId }}" @endif
>
    <label for="{{ $name }}" class="sr-only">{{ $label }}</label>
    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-[#B5A89C] pointer-events-none">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z"></path></svg>
    </span>
    <input
        id="{{ $name }}"
        type="search"
        name="{{ $name }}"
        value="{{ $value }}"
        placeholder="{{ $placeholder }}"
        class="w-full h-11 pl-11 {{ $value ? 'pr-10' : 'pr-3' }} bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] placeholder:text-[#B5A89C] focus:outline-none focus:border-[#2D231E]"
    >
    @if ($value)
        <button
            type="button"
            data-search-clear
            aria-label="Clear {{ $label }}"
            class="absolute right-2 top-1/2 -translate-y-1/2 min-w-[28px] h-7 inline-flex items-center justify-center rounded-md text-[#2D231E] hover:bg-[#F3ECE0] transition-colors cursor-pointer"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"></path></svg>
        </button>
    @endif
</form>