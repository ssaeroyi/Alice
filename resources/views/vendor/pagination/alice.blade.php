@if ($paginator->hasPages())
    <!--ALICE-PAGER-->
    <nav role="navigation" aria-label="Pagination" class="pagination flex items-center justify-center flex-wrap gap-1.5 sm:gap-2">
        <span class="sr-only">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>

        {{-- Previous --}}
        @if ($paginator->onFirstPage())
            <span aria-hidden="true" class="inline-flex items-center justify-center w-9 h-9 sm:w-10 sm:h-10 font-pixel text-xs rounded-xl border-2 border-[#2D231E] bg-white text-[#2D231E]/30 select-none">
                &#8249;
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page" class="inline-flex items-center justify-center w-9 h-9 sm:w-10 sm:h-10 font-pixel text-xs rounded-xl border-2 border-[#2D231E] bg-white text-[#2D231E] shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-none transition-all">
                &#8249;
            </a>
        @endif

        {{-- Numbered pages. Shared 4-chip window with page 1 and the last
             page pinned at the ends (PagerWindow); the ellipsis only ever
             bridges a real gap, so a large list never spills every number. --}}
        @php $numbered = \App\Support\PagerWindow::items((int) $paginator->currentPage(), (int) $paginator->lastPage()); @endphp

        @foreach ($numbered as $item)
            @if (is_string($item))
                <span class="font-pixel text-[9px] text-[#8C7B70] px-0.5 select-none" aria-hidden="true">&#8943;</span>
            @else
                @php $url = $paginator->url($item); @endphp
                @if ($item == $paginator->currentPage())
                    <span aria-current="page" class="inline-flex items-center justify-center w-9 h-9 sm:w-10 sm:h-10 font-pixel text-[10px] sm:text-xs font-bold rounded-xl border-2 border-[#2D231E] bg-amber-400 text-[#2D231E] translate-x-[2px] translate-y-[2px] shadow-none">
                        {{ $item }}
                    </span>
                @else
                    <a href="{{ $url }}" aria-label="Page {{ $item }}" class="inline-flex items-center justify-center w-9 h-9 sm:w-10 sm:h-10 font-pixel text-[10px] sm:text-xs font-bold rounded-xl border-2 border-[#2D231E] bg-white text-[#2D231E] shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] hover:bg-amber-100 active:translate-x-[2px] active:translate-y-[2px] active:shadow-none transition-all">
                        {{ $item }}
                    </a>
                @endif
            @endif
        @endforeach

        {{-- Next --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page" class="inline-flex items-center justify-center w-9 h-9 sm:w-10 sm:h-10 font-pixel text-xs rounded-xl border-2 border-[#2D231E] bg-white text-[#2D231E] shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-none transition-all">
                &#8250;
            </a>
        @else
            <span aria-hidden="true" class="inline-flex items-center justify-center w-9 h-9 sm:w-10 sm:h-10 font-pixel text-xs rounded-xl border-2 border-[#2D231E] bg-white text-[#2D231E]/30 select-none">
                &#8250;
            </span>
        @endif

    </nav>
@endif
