@props(['id' => null, 'title' => '', 'open' => false, 'closeable' => true, 'pixel' => false, 'wide' => false, 'sticky' => false])

@php
    $isOpen = $open || old('modal') === $id;
@endphp

@if ($pixel)
<div
    id="{{ $id }}"
    data-modal
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $id }}-title"
    class="{{ $isOpen ? 'flex' : 'hidden' }} fixed inset-0 z-50 items-center justify-center bg-[#2D231E]/60 backdrop-blur-sm p-4 min-h-[100vh] min-h-[100dvh]"
    {{ $attributes }}
>
    <div class="w-full {{ $wide ? 'max-w-4xl' : 'max-w-md' }} bg-white border-4 border-[#2D231E] rounded-3xl p-5 sm:p-6 shadow-[6px_6px_0px_0px_rgba(45,35,30,1)] space-y-5 max-h-[85vh] overflow-y-auto overscroll-contain no-scrollbar">
        <div class="flex items-center justify-between {{ $sticky ? 'sticky top-0 bg-white z-10' : '' }} border-b-2 border-dashed border-[#E6DCCF] pb-3">
            <h2 id="{{ $id }}-title" class="font-pixel text-xs sm:text-sm font-bold text-[#2D231E] uppercase tracking-wider">
                {{ $title }}
            </h2>
            @isset($titleExtra)
                {{ $titleExtra }}
            @endisset
            @if ($closeable)
                <button
                    type="button"
                    data-modal-close
                    aria-label="Close"
                    class="min-w-[44px] min-h-[44px] font-pixel text-xs font-bold text-[#2D231E] bg-white border-2 border-[#2D231E] rounded-xl flex items-center justify-center px-2 shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] focus:ring-2 focus:ring-amber-400 transition-all cursor-pointer"
                >
                    [X]
                </button>
            @endif
        </div>

        <div class="space-y-5">
            {{ $slot }}
        </div>
    </div>
</div>
@else
<div
    id="{{ $id }}"
    data-modal
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $id }}-title"
    class="{{ $isOpen ? 'flex' : 'hidden' }} fixed inset-0 z-50 items-center justify-center bg-[#2D231E]/40 p-4"
>
    <div class="w-full {{ $wide ? 'max-w-3xl' : 'max-w-md' }} bg-white border border-[#E6DCCF] rounded-xl p-5 space-y-4 shadow-[0_8px_24px_rgba(45,35,30,0.15)] max-h-[85vh] overflow-y-auto overscroll-contain no-scrollbar">
        <div class="flex items-center justify-between gap-4">
            <h2 id="{{ $id }}-title" class="text-sm font-extrabold text-[#2D231E]">{{ $title }}</h2>
            @isset($titleExtra)
                {{ $titleExtra }}
            @endisset
            @if ($closeable)
                <button
                    type="button"
                    data-modal-close
                    aria-label="Close"
                    class="min-w-[44px] min-h-[44px] flex items-center justify-center text-lg leading-none font-bold text-[#B5A89C] hover:text-[#2D231E] transition-colors cursor-pointer"
                >
                    ×
                </button>
            @endif
        </div>

        <div class="space-y-4">
            {{ $slot }}
        </div>
    </div>
</div>
@endif