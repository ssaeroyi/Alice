@extends(auth()->check() && auth()->user()->role === \App\Enums\Role::Student ? 'layouts.authenticated' : 'layouts.guest')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8 sm:py-12 space-y-6">

    <!-- Companion Dialogue Bubble (ALICE) -->
    <div class="flex flex-row items-center gap-3 sm:gap-4">
        <div class="shrink-0 flex items-center justify-center relative">
            <div class="absolute w-16 h-16 sm:w-20 sm:h-20 rounded-full bg-[#2D231E]/20 blur-md"></div>
            <img
                src="{{ asset('image/Alice/Light mode/alice-lt-casual.png') }}"
                width="1280" height="1280"
                alt="ALICE"
                class="relative z-10 w-16 h-16 sm:w-20 sm:h-20 object-contain scale-110 drop-shadow-[2px_2px_0px_rgba(45,35,30,1)]"
            >
        </div>

        <div class="relative flex-1 min-w-0 bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-3 sm:p-5 shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]">
            <div class="absolute -left-2.5 sm:-left-3 top-1/2 -translate-y-1/2 w-4 h-4 sm:w-5 sm:h-5 bg-[#FAF6EE] border-l-2 border-b-2 border-[#2D231E] rotate-45"></div>

            <p class="text-xs sm:text-sm font-bold text-[#2D231E] leading-relaxed text-left">
                The boring-but-important part! Here's how we keep things safe and fair for everyone.
            </p>
        </div>
    </div>

    <!-- Terms Panel -->
    <div class="bg-white border-4 border-[#2D231E] rounded-3xl p-6 sm:p-8 shadow-[6px_6px_0px_0px_rgba(45,35,30,1)] space-y-6">

        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2 border-b-2 border-dashed border-[#E6DCCF] pb-3">
            <span class="font-pixel text-xs sm:text-sm font-bold text-[#2D231E] uppercase tracking-wider">
                TERMS & CONDITIONS
            </span>
            <span class="font-pixel text-[9px] sm:text-[10px] bg-[#E6DCCF] border border-[#2D231E] text-[#2D231E] px-2 py-1 rounded-md font-bold uppercase">
                LAST UPDATED: SEP 2026
            </span>
        </div>

        <!-- Sections -->
        @include('footer.partials.terms-sections')

        <!-- Close CTA -->
        <div class="pt-2">
            <button
                type="button"
                onclick="history.back()"
                class="w-full min-h-[44px] font-pixel text-xs px-6 py-3 rounded-2xl bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] font-bold flex items-center justify-center shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all cursor-pointer uppercase"
            >
                <span>CLOSE</span>
            </button>
        </div>

    </div>

</div>
@endsection
