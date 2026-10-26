@props(['title' => null, 'subtitle' => null, 'info' => null, 'href' => null, 'icon' => null, 'overflow' => 'overflow-hidden'])

@php
    $tag = $href !== null ? 'a' : 'div';
@endphp
<{{ $tag }}@if ($href !== null) href="{{ $href }}" @endif class="bg-white border border-[#E6DCCF] rounded-xl {{ $overflow }} {{ $href !== null ? 'group relative block hover:border-amber-300 transition-colors' : '' }}">
    @if ($title || $subtitle || $info)
        <div class="flex items-start justify-between gap-4 px-4 sm:px-5 pt-4 sm:pt-5 pb-4 border-b border-[#E6DCCF]">
            <div class="min-w-0">
                @if ($title)
                    <h2 class="text-sm font-extrabold text-[#2D231E] flex items-center gap-1.5">
                        @if ($icon)
                            <x-dynamic-component :component="'icons.core.'.$icon" class="w-4 h-4 shrink-0 {{ $href !== null ? 'text-[#2D231E] group-hover:text-amber-600 transition-colors' : '' }}" />
                        @endif
                        {{ $title }}
                        @if ($info)
                            <x-admin.info>{{ $info }}</x-admin.info>
                        @endif
                        @if ($href !== null)
                            <x-icons.core.arrow-right class="w-4 h-4 ml-1 shrink-0 text-amber-600 opacity-0 -translate-x-1 group-hover:opacity-100 group-hover:translate-x-0 transition-all" />
                        @endif
                    </h2>
                @endif
                @if ($subtitle)
                    <p class="text-xs font-semibold text-[#2D231E] mt-0.5">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="shrink-0">{{ $actions }}</div>
            @endisset
        </div>
    @endif
    <div class="p-4 sm:p-5">
        {{ $slot }}
    </div>
</{{ $tag }}>