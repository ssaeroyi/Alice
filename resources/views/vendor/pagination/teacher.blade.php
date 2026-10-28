@if ($paginator->hasPages())
    <!--TEACHER-PAGER-->
    <nav role="navigation" aria-label="Pagination" class="pagination flex items-center justify-center flex-wrap gap-1.5 sm:gap-2">

        {{-- Previous --}}
        @if ($paginator->onFirstPage())
            <span aria-hidden="true" class="inline-flex items-center justify-center w-9 h-9 sm:w-10 sm:h-10 text-xs font-bold rounded-lg border border-[#E6DCCF] bg-white text-[#B5A89C] select-none">
                &#8249;
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page" class="inline-flex items-center justify-center w-9 h-9 sm:w-10 sm:h-10 text-xs font-bold rounded-lg border border-[#E6DCCF] bg-white text-[#2D231E] hover:border-[#2D231E] transition-colors">
                &#8249;
            </a>
        @endif

        {{-- Numbered pages. Shared 4-chip window with page 1 and the last
             page pinned at the ends (PagerWindow); the ellipsis only ever
             bridges a real gap, so a large list never spills every number. --}}
        @php $numbered = \App\Support\PagerWindow::items((int) $paginator->currentPage(), (int) $paginator->lastPage()); @endphp

        @foreach ($numbered as $item)
            @if (is_string($item))
                <span class="text-xs text-[#2D231E] px-0.5 select-none">&#8943;</span>
            @else
                @php $url = $paginator->url($item); @endphp
                @if ($item == $paginator->currentPage())
                    <span aria-current="page" class="inline-flex items-center justify-center w-9 h-9 sm:w-10 sm:h-10 text-xs font-bold rounded-lg bg-emerald-600 text-white">
                        {{ $item }}
                    </span>
                @else
                    <a href="{{ $url }}" aria-label="Page {{ $item }}" class="inline-flex items-center justify-center w-9 h-9 sm:w-10 sm:h-10 text-xs font-bold rounded-lg border border-[#E6DCCF] bg-white text-[#2D231E] hover:border-[#2D231E] transition-colors">
                        {{ $item }}
                    </a>
                @endif
            @endif
        @endforeach

        {{-- Next --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page" class="inline-flex items-center justify-center w-9 h-9 sm:w-10 sm:h-10 text-xs font-bold rounded-lg border border-[#E6DCCF] bg-white text-[#2D231E] hover:border-[#2D231E] transition-colors">
                &#8250;
            </a>
        @else
            <span aria-hidden="true" class="inline-flex items-center justify-center w-9 h-9 sm:w-10 sm:h-10 text-xs font-bold rounded-lg border border-[#E6DCCF] bg-white text-[#B5A89C] select-none">
                &#8250;
            </span>
        @endif

    </nav>
@endif