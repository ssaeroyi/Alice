@php
    $meta = $meta ?? [];
    // fit: 'strip' = fixed-width card for horizontal carousels,
    //      'grid'  = fills its grid cell (2-up rows on phones).
    $fit = $fit ?? 'strip';
    $widthClass = $fit === 'grid' ? 'w-full' : 'snap-start shrink-0 w-[260px] sm:w-[300px]';
    $genre = $story->genres->first();
    $style = \App\Support\LibraryPresentation::genreStyle($genre?->name ?? '');
    $minutes = \App\Support\LibraryPresentation::minutes((int) $story->word_count);
    $percent = $meta['percent'] ?? null;
    $favorite = $meta['favorite'] ?? false;
    $hideProgress = $meta['hide_progress'] ?? false;
    $cta = match (true) {
        $percent === 100 => 'READ AGAIN',
        $percent !== null => 'RESUME STORY',
        default => 'READ STORY',
    };
    $dotsStyle = sprintf(
        'background-image: radial-gradient(%s 0.8px, transparent 0.8px); background-size: 10px 10px;',
        $style['dots'],
    );
@endphp

<a
    href="{{ route('story.preview', $story) }}"
    class="group {{ $widthClass }} bg-white border-4 border-[#2D231E] rounded-3xl p-4 shadow-[5px_5px_0px_0px_rgba(45,35,30,1)] flex flex-col justify-between space-y-4 hover:-translate-y-0.5 transition-transform"
>
    <div class="space-y-3">
        <div
            class="w-full h-32 rounded-2xl border-2 border-[#2D231E] {{ $style['bg'] }} relative flex items-center justify-center p-3 overflow-hidden"
            style="{{ $dotsStyle }}"
        >
            @if ($story->cover_image)
                <div class="absolute inset-0 alice-skeleton rounded-none" aria-hidden="true" data-cover-skel></div>
                <img src="{{ $story->coverUrl() }}" alt="" loading="lazy" decoding="async" class="absolute inset-0 w-full h-full object-cover" onload="this.previousElementSibling?.remove()" onerror="this.previousElementSibling?.remove(); this.remove()">
            @endif

            @if (!$hideProgress && $percent === 100)
                <div class="absolute top-3 left-3 bg-emerald-400 border-2 border-[#2D231E] rounded-lg px-2 py-0.5 font-pixel text-[8px] sm:text-[9px] font-bold text-[#2D231E] uppercase shadow-[1px_1px_0px_0px_rgba(45,35,30,1)]">
                    COMPLETED
                </div>
            @elseif (!$hideProgress && $percent !== null)
                <div class="absolute top-3 left-3 bg-amber-400 border-2 border-[#2D231E] rounded-lg px-2 py-0.5 font-pixel text-[8px] sm:text-[9px] font-bold text-[#2D231E] uppercase shadow-[1px_1px_0px_0px_rgba(45,35,30,1)]">
                    {{ $percent }}% DONE
                </div>
            @elseif (!$hideProgress && $favorite)
                <div class="absolute top-3 left-3 bg-white border-2 border-[#2D231E] rounded-lg p-1 shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] text-rose-500">
                    <x-icons.heart class="w-3 h-3" />
                </div>
            @endif

            @if ($genre)
                <div class="absolute bottom-3 left-3 bg-white border-2 border-[#2D231E] rounded-lg px-2 py-0.5 font-pixel text-[8px] sm:text-[9px] font-bold text-[#2D231E] uppercase shadow-[1px_1px_0px_0px_rgba(45,35,30,1)]">
                    {{ $genre->name }}
                </div>
            @endif

            <div class="absolute top-3 right-3 bg-white/90 border border-[#2D231E] rounded-lg px-2 py-0.5 font-pixel text-[8px] sm:text-[9px] font-bold text-[#2D231E] uppercase">
                {{ $minutes }} MIN READ
            </div>

            @unless ($story->cover_image)
                <div class="w-12 h-12 rounded-full bg-white border-2 border-[#2D231E] flex items-center justify-center shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] z-10">
                    <x-icons.book-open class="w-6 h-6 text-[#2D231E]" />
                </div>
            @endunless
        </div>

        <div>
            <h3 class="font-bold text-sm sm:text-base text-[#2D231E] line-clamp-1 group-hover:underline decoration-2 underline-offset-2">{{ $story->title }}</h3>
            {{-- 2-line clamp with no display utility on the element: any active
                 display class (hidden/sm:block) beats line-clamp's -webkit-box
                 in the cascade and silently disables the clamp. --}}
            <p class="text-xs text-[#2D231E] leading-relaxed line-clamp-2 mt-1 font-semibold">{{ $story->synopsis }}</p>
        </div>

        @if ($story->difficulty_label)
            <span class="inline-block border border-[#2D231E]/20 rounded-md px-2 py-0.5 font-pixel text-[8px] font-bold uppercase {{ \App\Support\AliceLibrary::difficultyClasses($story->difficulty_label) }}">
                {{ $story->difficulty_label }}
            </span>
        @endif
    </div>

    <span class="w-full min-h-[40px] font-pixel text-xs px-4 py-2.5 rounded-xl bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] font-bold flex items-center justify-center shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] transition-all uppercase">
        {{ $cta }}
    </span>
</a>
