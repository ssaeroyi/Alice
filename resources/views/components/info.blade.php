@props(['label' => 'More information', 'modal' => null])

@if ($modal !== null)
    {{-- Modal variant: opens a standalone topic modal (student hub help). --}}
    <button
        type="button"
        data-modal-open="{{ $modal }}"
        aria-label="{{ $label }}"
        {{ $attributes->merge(['class' => 'inline-flex align-middle items-center justify-center font-semibold w-5 h-5 shrink-0 rounded-full bg-[#F3ECE0] border border-[#2D231E]/30 text-[#2D231E] hover:bg-white hover:border-[#2D231E] hover:text-[#2D231E] transition-colors cursor-pointer']) }}
    >
        <span class="font-pixel text-[10px] font-bold leading-none">?</span>
    </button>
@else
    <span {{ $attributes->merge(['class' => 'relative inline-flex align-middle']) }} data-info>
        <button
            type="button"
            data-info-toggle
            aria-label="{{ $label }}"
            aria-expanded="false"
            class="font-semibold w-5 h-5 shrink-0 inline-flex items-center justify-center rounded-full bg-[#F3ECE0] border border-[#2D231E]/30 text-[#2D231E] hover:bg-white hover:border-[#2D231E] hover:text-[#2D231E] transition-colors cursor-pointer"
        >
            <span class="font-pixel text-[10px] font-bold leading-none">?</span>
        </button>
        <span
            data-info-popover
            class="hidden absolute left-0 top-full mt-2 z-40 w-72 max-w-[80vw] max-h-[45vh] overflow-y-auto bg-white border-2 border-[#2D231E] rounded-xl p-3 text-xs font-medium leading-relaxed text-[#2D231E] shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]"
            role="tooltip"
        >
            {{ $slot }}
        </span>
    </span>
@endif
