@props(['metric' => '', 'label' => '', 'icon' => null, 'value' => '', 'href' => null, 'active' => false])

{{--
    One selectable metric of the platform-health explorer. The whole tile is a
    real link, so with JS off it just reloads the dashboard with that metric
    selected; with JS on admin-dashboard.js fetches the series and redraws the
    shared chart instead. Selection is a data attribute so the click handler
    only has to toggle it.
--}}
<a
    href="{{ $href }}"
    data-metric-tile="{{ $metric }}"
    @if ($active) data-metric-active @endif
    aria-current="{{ $active ? 'true' : 'false' }}"
    class="group rounded-xl border px-3.5 py-3 transition-colors cursor-pointer bg-white border-[#E6DCCF] hover:border-amber-300 hover:bg-amber-50/40 data-[metric-active]:border-amber-400 data-[metric-active]:bg-amber-50/70 data-[metric-active]:shadow-[0_0_0_1px_rgba(251,191,36,0.55)]"
>
    <span class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-[#2D231E]">
        @if ($icon)
            <x-dynamic-component :component="'icons.core.'.$icon" class="w-3.5 h-3.5 shrink-0 text-[#2D231E] group-data-[metric-active]:text-amber-600" />
        @endif
        <span class="leading-tight">{{ $label }}</span>
    </span>
    <span data-metric-value class="block mt-1 text-lg font-extrabold text-[#2D231E] tabular-nums">{{ $value }}</span>
</a>
