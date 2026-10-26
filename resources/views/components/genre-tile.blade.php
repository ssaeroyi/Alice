@php
    $style = \App\Support\LibraryPresentation::genreStyle($genre->name);
    $slug = \App\Support\LibraryPresentation::genreSlug($genre->name);
    $dotsStyle = sprintf(
        'background-image: radial-gradient(%s 0.8px, transparent 0.8px); background-size: 10px 10px;',
        $style['dots'],
    );
@endphp

<a
    href="{{ route('library.genre', $slug) }}"
    class="group snap-start shrink-0 w-[180px] sm:w-[200px] border-4 border-[#2D231E] rounded-3xl p-4 shadow-[5px_5px_0px_0px_rgba(45,35,30,1)] {{ $style['bg'] }} relative flex flex-col items-center justify-center space-y-3 min-h-[140px] hover:-translate-y-0.5 transition-transform"
    style="{{ $dotsStyle }}"
>
    <div class="w-12 h-12 rounded-full bg-white border-2 border-[#2D231E] flex items-center justify-center shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] z-10">
        @php($glyphSvg = \App\Support\LibraryPresentation::genreGlyphSvg($genre->name))
        @if ($glyphSvg !== null)
            {{-- Vendored glyph inlined (background rect stripped) and tinted
                 through currentColor; safe to print raw. --}}
            <span class="w-7 h-7 text-[#2D231E] [&>svg]:h-full [&>svg]:w-full">{!! $glyphSvg !!}</span>
        @else
            <x-dynamic-component :component="'icons.'.$style['icon']" class="w-6 h-6 text-[#2D231E]" />
        @endif
    </div>

    <div class="text-center z-10">
        <h3 class="font-pixel text-[10px] font-bold text-white uppercase leading-relaxed drop-shadow-[2px_2px_0px_rgba(45,35,30,1)]">{{ $genre->name }}</h3>
        <p class="font-pixel text-[8px] text-white/90 mt-1 drop-shadow-[1px_1px_0px_rgba(45,35,30,1)]">{{ $genre->stories_count }} {{ $genre->stories_count === 1 ? 'STORY' : 'STORIES' }}</p>
    </div>
</a>
