@php
    $meta = $meta ?? [];
    $genre = $story->genres->first();
    $style = \App\Support\LibraryPresentation::genreStyle($genre?->name ?? '');
    $minutes = \App\Support\LibraryPresentation::minutes((int) $story->word_count);
    $percent = $meta['percent'] ?? null;
    $favorite = $meta['favorite'] ?? false;
    $dotsStyle = sprintf(
        'background-image: radial-gradient(%s 0.8px, transparent 0.8px); background-size: 10px 10px;',
        $style['dots'],
    );
@endphp

<a
    href="{{ route('story.preview', $story) }}"
    class="group flex items-center gap-3 bg-white border-2 border-[#2D231E] rounded-2xl p-3 shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-none transition-all"
>
    <div class="w-[72px] h-[72px] shrink-0 rounded-xl border-2 border-[#2D231E] {{ $style['bg'] }} relative flex items-center justify-center overflow-hidden" style="{{ $dotsStyle }}">
        @if ($story->cover_image)
            <div class="absolute inset-0 alice-skeleton rounded-none" aria-hidden="true" data-cover-skel></div>
            <img src="{{ $story->coverUrl() }}" alt="" loading="lazy" decoding="async" class="absolute inset-0 w-full h-full object-cover" onload="this.previousElementSibling?.remove()" onerror="this.previousElementSibling?.remove(); this.remove()">
        @else
            <x-icons.book-open class="w-7 h-7 text-white/90" />
        @endif

        @if ($percent === 100)
            <div class="absolute bottom-1 left-1 bg-emerald-400 border border-[#2D231E] rounded px-1 py-0.5 font-pixel text-[7px] font-bold text-[#2D231E] uppercase">DONE</div>
        @endif
    </div>

    <div class="min-w-0 flex-1 space-y-1">
        <div class="flex items-center gap-2">
            <h3 class="font-bold text-sm text-[#2D231E] truncate group-hover:underline decoration-2 underline-offset-2">{{ $story->title }}</h3>

            @if ($favorite)
                <span class="shrink-0 text-rose-500"><x-icons.heart class="w-4 h-4" /></span>
            @endif
        </div>

        <p class="font-pixel text-[8px] font-bold uppercase tracking-wide text-[#8C7B70] truncate">
            {{ $genre?->name ?? 'STORY' }} &middot; {{ $minutes }} MIN READ
        </p>

        <p class="text-xs text-[#8C7B70] leading-snug line-clamp-1 font-medium">{{ $story->synopsis }}</p>

        @if ($percent !== null && $percent !== 100)
            <div class="flex items-center gap-2 pt-0.5">
                <div class="flex-1 h-2 rounded-full bg-[#E6DCCF] border border-[#2D231E]/20 overflow-hidden">
                    <div class="h-full bg-amber-400" style="width: {{ $percent }}%"></div>
                </div>
                <span class="font-pixel text-[8px] font-bold text-[#2D231E] uppercase shrink-0">{{ $percent }}%</span>
            </div>
        @endif
    </div>
</a>
