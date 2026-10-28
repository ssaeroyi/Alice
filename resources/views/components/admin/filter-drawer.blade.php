@props([
    'action',
    'label' => 'Filters',
    'count' => 0,
    'searchName' => 'q',
    'searchValue' => '',
    'resetUrl' => null,
    'formId' => null,
    'hideButton' => false,
])

@php
    $fid = $formId ?? 'filter-drawer-form';
@endphp

@if (! $hideButton)
<button
    type="button"
    data-filter-drawer-open
    class="relative min-w-[44px] min-h-[44px] inline-flex items-center gap-1.5 px-3 bg-white border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
    aria-label="Open {{ $label }}"
>
    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M6 12h12M10 18h4"></path>
    </svg>
    <span class="hidden sm:inline">{{ $label }}</span>
    @if ($count > 0)
        <span class="min-w-[18px] h-[18px] px-1 rounded-full bg-rose-500 text-white text-[10px] font-bold inline-flex items-center justify-center">
            {{ $count > 9 ? '9+' : $count }}
        </span>
    @endif
</button>
@endif

<div data-filter-drawer class="fixed inset-0 z-[70] hidden transition-opacity duration-300 ease-out" role="dialog" aria-modal="true" aria-label="{{ $label }}">
    <div data-filter-drawer-backdrop class="absolute inset-0 bg-[#2D231E]/40 backdrop-blur-sm"></div>

    <div data-filter-drawer-panel class="absolute inset-y-0 right-0 w-full max-w-sm bg-white border-l-2 border-[#E6DCCF] flex flex-col transition-transform duration-300 ease-out translate-x-full">
        <div class="flex items-center justify-between px-4 sm:px-5 py-4 bg-[#F3ECE0] border-b border-[#E6DCCF] shrink-0">
            <h2 class="text-xs font-extrabold uppercase tracking-wider text-[#2D231E]">{{ $label }}</h2>
            <button
                type="button"
                data-filter-drawer-close
                aria-label="Close {{ $label }}"
                class="min-w-[44px] min-h-[44px] inline-flex items-center justify-center rounded-lg text-[#2D231E] hover:bg-white/70 transition-colors cursor-pointer"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <form method="GET" action="{{ $action }}" id="{{ $fid }}" class="flex-1 overflow-y-auto px-4 sm:px-5 py-4 space-y-4">
            @if ($searchName)
                <label class="block">
                    <span class="text-[11px] font-bold uppercase tracking-wide text-[#2D231E]">Search</span>
                    <input
                        type="search"
                        name="{{ $searchName }}"
                        value="{{ $searchValue }}"
                        placeholder="Search…"
                        class="mt-1 w-full min-h-[44px] rounded-lg border border-[#E6DCCF] bg-white px-3 text-sm font-semibold text-[#2D231E] placeholder:text-[#8C7B70]/60 focus:outline-none focus:ring-2 focus:ring-emerald-200 focus:border-[#2D231E]"
                    >
                </label>
            @endif

            {{ $slot }}
        </form>

        <div class="px-4 sm:px-5 py-4 border-t border-[#E6DCCF] bg-[#FAF6EE] space-y-2 shrink-0">
            <button
                type="submit"
                form="{{ $fid }}"
                class="w-full min-h-[44px] rounded-xl bg-emerald-600 text-white text-sm font-bold px-6 uppercase tracking-wide hover:bg-[#3D332C] transition-colors cursor-pointer"
            >
                Apply Filters
            </button>
            @if ($resetUrl)
                <a
                    href="{{ $resetUrl }}"
                    class="flex items-center justify-center w-full min-h-[44px] rounded-xl bg-white border border-[#E6DCCF] text-[#2D231E] text-xs font-bold px-6 uppercase tracking-wide transition-colors"
                >
                    Reset
                </a>
            @endif
        </div>
    </div>
</div>