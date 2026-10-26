@props(['label' => 'More information'])

<span {{ $attributes->merge(['class' => 'relative inline-flex align-middle']) }} data-info>
    <button
        type="button"
        data-info-toggle
        aria-label="{{ $label }}"
        aria-expanded="false"
        class="w-6 h-6 min-h-0 shrink-0 inline-flex items-center justify-center rounded-full bg-[#F3ECE0] text-[#2D231E] hover:bg-amber-100 transition-colors cursor-pointer"
    >
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="12" cy="12" r="10"></circle>
            <path stroke-linecap="round" d="M12 16v-4M12 8h.01"></path>
        </svg>
    </button>
    <span
        data-info-popover
        class="hidden absolute left-0 top-full mt-2 z-40 w-72 max-w-[80vw] max-h-[45vh] overflow-y-auto bg-[#2D231E] text-[#FAF6EE] rounded-xl p-4 text-xs font-semibold leading-relaxed shadow-[0_8px_24px_rgba(45,35,30,0.25)]"
        role="tooltip"
    >
        {{ $slot }}
    </span>
</span>