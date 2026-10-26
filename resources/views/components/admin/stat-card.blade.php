@props(['label' => '', 'value' => '', 'hint' => null, 'href' => null, 'icon' => null])

@php
    $tag = $href !== null ? 'a' : 'div';
@endphp
<{{ $tag }}@if ($href !== null) href="{{ $href }}" @endif class="bg-white border border-[#E6DCCF] rounded-xl px-5 py-4 {{ $href !== null ? 'group relative block hover:border-amber-300 hover:bg-amber-50/40 transition-colors' : '' }}">
    <p class="text-[11px] font-bold uppercase tracking-wider text-[#2D231E] flex items-center gap-1.5 pr-6">
        @if ($icon)
            <x-dynamic-component :component="'icons.core.'.$icon" class="w-4 h-4 shrink-0 {{ $href !== null ? 'text-[#2D231E] group-hover:text-amber-600 transition-colors' : '' }}" />
        @endif
        {{ $label }}
        @if ($hint)
            <x-admin.info>{{ $hint }}</x-admin.info>
        @endif
    </p>
    <p {{ $attributes->whereStartsWith('data-') }} class="text-2xl font-extrabold text-[#2D231E] mt-1 tabular-nums">{{ $value }}</p>
    @if ($href !== null)
        <x-icons.core.arrow-right class="absolute top-4 right-4 w-4 h-4 text-amber-600 opacity-0 -translate-x-1 group-hover:opacity-100 group-hover:translate-x-0 transition-all" />
    @endif
</{{ $tag }}>
