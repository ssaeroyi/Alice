@props([
    'title',
    'viewAllUrl' => null,
    'emptyMessage' => null,
])

<section class="space-y-4" {{ $attributes }}>
    <div class="flex items-baseline justify-between gap-3">
        <h2 class="min-w-0 font-pixel text-[9px] sm:text-[11px] font-bold text-[#2D231E] uppercase tracking-wider flex items-center gap-2 leading-relaxed">
            {{ $title }}
        </h2>

        @if ($viewAllUrl)
            <a href="{{ $viewAllUrl }}" class="shrink-0 font-pixel text-[9px] sm:text-xs font-bold text-[#2D231E] hover:text-[#2D231E] uppercase inline-flex items-center px-1.5 py-1.5 min-h-[32px] transition-colors">
                <span>VIEW ALL</span>
            </a>
        @endif
    </div>

    @if ($emptyMessage !== null && trim($slot) === '')
        <div class="bg-white border-2 border-dashed border-[#2D231E]/40 rounded-3xl p-6 text-center">
            <p class="text-xs font-bold text-[#2D231E]">{{ $emptyMessage }}</p>
        </div>
    @else
        <div class="flex gap-4 overflow-x-auto pb-4 pt-1 snap-x snap-mandatory scrollbar-none">
            {{ $slot }}
        </div>
    @endif
</section>
