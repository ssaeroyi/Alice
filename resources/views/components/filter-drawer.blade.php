@props([
    'action',
    'difficulties' => ['easy' => 'Easy', 'intermediate' => 'Intermediate', 'hard' => 'Hard', 'very-hard' => 'Very Hard'],
    'currentDifficulty' => null,
    'genres' => collect(),
    'activeGenre' => null,
    'showGenre' => false,
    'searchName' => 'q',
    'searchValue' => '',
    'currentSort' => 'title',
    'showSort' => true,
    'tabName' => null,
    'tabValue' => '',
])

@php
    use App\Support\LibraryPresentation;

    $sorts = [
        'title' => 'A to Z',
        'newest' => 'Newest',
        'length' => 'Shortest',
        'grade' => 'Easiest first',
    ];
    $hasActive = $currentDifficulty !== null
        || $activeGenre !== null
        || ($currentSort ?? 'title') !== 'title';
@endphp

<form method="GET" action="{{ $action }}" data-filter-drawer-form>
    @if ($tabName)
        <input type="hidden" name="{{ $tabName }}" value="{{ $tabValue }}">
    @endif
    <input type="hidden" name="{{ $searchName }}" value="{{ $searchValue }}">
    <input type="hidden" name="difficulty" value="{{ $currentDifficulty ?? '' }}" data-filter-difficulty-input>
    @if ($showGenre)
        <input type="hidden" name="genre" value="{{ $activeGenre ?? '' }}" data-filter-genre-input>
    @endif
    @if ($showSort)
        <input type="hidden" name="sort" value="{{ $currentSort ?? 'title' }}" data-filter-sort-input>
    @endif

    {{-- Toggle button — lives outside the drawer panel --}}
    <button
        type="button"
        data-filter-drawer-open
        class="relative min-w-[44px] min-h-[44px] w-10 h-10 bg-white border-2 border-[#2D231E] text-[#2D231E] rounded-xl font-bold flex items-center justify-center shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all cursor-pointer"
        aria-label="Open filters"
    >
        <x-icons.sliders class="w-4 h-4" />
        @if ($hasActive)
            <span class="absolute -top-1.5 -right-1.5 w-4 h-4 rounded-full bg-amber-400 border border-[#2D231E] font-pixel text-[7px] font-bold text-[#2D231E] flex items-center justify-center">
                {{ collect([$currentDifficulty, $activeGenre])->filter()->count() + (($currentSort ?? 'title') !== 'title' ? 1 : 0) }}
            </span>
        @endif
    </button>

    {{-- Drawer panel --}}
    <div id="filter-drawer" data-filter-drawer class="fixed inset-0 z-[60] hidden transition-opacity duration-300 ease-out" role="dialog" aria-modal="true" aria-label="Filters">
        {{-- Backdrop --}}
        <div data-filter-drawer-backdrop class="absolute inset-0 bg-[#2D231E]/40 backdrop-blur-sm"></div>

        {{-- Panel --}}
        <div data-filter-drawer-panel class="absolute inset-y-0 right-0 w-full max-w-sm bg-white border-l-4 border-[#2D231E] shadow-[-6px_0_0_0_rgba(45,35,30,1)] flex flex-col transition-transform duration-300 ease-out translate-x-full">
            {{-- Header --}}
            <div class="flex items-center justify-between px-5 py-4 bg-amber-400 border-b-2 border-[#2D231E] shrink-0">
                <h2 class="font-pixel text-xs font-bold text-[#2D231E] uppercase tracking-wider">FILTERS</h2>
                <button
                    type="button"
                    data-filter-drawer-close
                    class="w-9 h-9 font-bold text-[#2D231E] rounded-lg flex items-center justify-center hover:bg-[#FAF6EE] transition-colors cursor-pointer"
                    aria-label="Close filters"
                >✕</button>
            </div>

            {{-- Scrollable body grouped into collapsible dropdowns. --}}
            <div class="flex-1 overflow-y-auto px-5 py-5 space-y-4">

                {{-- Difficulty --}}
                @if (count($difficulties) > 0)
                    <details class="group border-2 border-[#2D231E] rounded-2xl bg-white overflow-hidden" data-filter-group>
                        <summary class="flex items-center justify-between gap-2 px-4 py-3 min-h-[44px] cursor-pointer list-none font-pixel text-[9px] font-bold text-[#2D231E] uppercase tracking-wider select-none marker:hidden">
                            <span>Difficulty</span>
                            <span class="flex items-center gap-2">
                                <span class="normal-case not-italic font-pixel text-[9px] {{ $currentDifficulty !== null ? 'text-[#2D231E]' : 'text-[#8C7B70]' }}" data-filter-difficulty-summary>
                                    {{ $currentDifficulty !== null ? ucwords(str_replace('-', ' ', $currentDifficulty)) : 'Any' }}
                                </span>
                                <svg class="w-3.5 h-3.5 transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 4 4 4-4"/></svg>
                            </span>
                        </summary>
                        <div class="px-4 pb-3 space-y-1">
                            <button
                                type="button"
                                data-filter-difficulty=""
                                class="w-full text-left px-3 py-2 min-h-[40px] rounded-xl border-2 font-pixel text-[9px] font-bold transition-all cursor-pointer {{ $currentDifficulty === null ? 'border-amber-400 bg-amber-100 text-[#2D231E]' : 'border-[#2D231E] bg-white text-[#8C7B70]' }}"
                            >
                                ANY DIFFICULTY
                            </button>
                            @foreach ($difficulties as $value => $label)
                                <button
                                    type="button"
                                    data-filter-difficulty="{{ $value }}"
                                    class="w-full text-left px-3 py-2 min-h-[40px] rounded-xl border-2 font-pixel text-[9px] font-bold transition-all cursor-pointer {{ $currentDifficulty === $value ? 'border-amber-400 bg-amber-100 text-[#2D231E]' : 'border-[#2D231E] bg-white text-[#8C7B70]' }}"
                                >
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                    </details>
                @endif

                {{-- Genre --}}
                @if ($showGenre && $genres->isNotEmpty())
                    <details class="group border-2 border-[#2D231E] rounded-2xl bg-white overflow-hidden" data-filter-group>
                        <summary class="flex items-center justify-between gap-2 px-4 py-3 min-h-[44px] cursor-pointer list-none font-pixel text-[9px] font-bold text-[#2D231E] uppercase tracking-wider select-none marker:hidden">
                            <span>Genre</span>
                            <span class="flex items-center gap-2">
                                <span class="normal-case not-italic font-pixel text-[9px] {{ $activeGenre !== null ? 'text-[#2D231E]' : 'text-[#8C7B70]' }}" data-filter-genre-summary>
                                    {{ $activeGenre !== null ? ucwords(str_replace('-', ' ', $activeGenre)) : 'Any' }}
                                </span>
                                <svg class="w-3.5 h-3.5 transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 4 4 4-4"/></svg>
                            </span>
                        </summary>
                        <div class="px-4 pb-3 space-y-1">
                            <button
                                type="button"
                                data-filter-genre=""
                                class="w-full text-left px-3 py-2 min-h-[40px] rounded-xl border-2 font-pixel text-[9px] font-bold transition-all cursor-pointer {{ $activeGenre === null ? 'border-amber-400 bg-amber-100 text-[#2D231E]' : 'border-[#2D231E] bg-white text-[#8C7B70]' }}"
                            >
                                ANY GENRE
                            </button>
                            @foreach ($genres as $genre)
                                @php
                                    $slug = LibraryPresentation::genreSlug($genre->name);
                                    $isActive = $activeGenre === $slug;
                                @endphp
                                <button
                                    type="button"
                                    data-filter-genre="{{ $slug }}"
                                    class="w-full text-left px-3 py-2 min-h-[40px] rounded-xl border-2 font-pixel text-[9px] font-bold transition-all cursor-pointer {{ $isActive ? 'border-amber-400 bg-amber-100 text-[#2D231E]' : 'border-[#2D231E] bg-white text-[#8C7B70]' }}"
                                >
                                    {{ $genre->name }}
                                </button>
                            @endforeach
                        </div>
                    </details>
                @endif

                {{-- Sort --}}
                @if ($showSort)
                    <details class="group border-2 border-[#2D231E] rounded-2xl bg-white overflow-hidden" data-filter-group>
                        <summary class="flex items-center justify-between gap-2 px-4 py-3 min-h-[44px] cursor-pointer list-none font-pixel text-[9px] font-bold text-[#2D231E] uppercase tracking-wider select-none marker:hidden">
                            <span>Sort</span>
                            <span class="flex items-center gap-2">
                                <span class="normal-case not-italic font-pixel text-[9px] {{ ($currentSort ?? 'title') !== 'title' ? 'text-[#2D231E]' : 'text-[#8C7B70]' }}" data-filter-sort-summary>
                                    {{ $sorts[$currentSort ?? 'title'] ?? 'A to Z' }}
                                </span>
                                <svg class="w-3.5 h-3.5 transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 4 4 4-4"/></svg>
                            </span>
                        </summary>
                        <div class="px-4 pb-3 space-y-1">
                            @foreach ($sorts as $value => $label)
                                @php $isActive = ($currentSort ?? 'title') === $value; @endphp
                                <button
                                    type="button"
                                    data-filter-sort="{{ $value }}"
                                    class="w-full text-left px-3 py-2 min-h-[40px] rounded-xl border-2 font-pixel text-[9px] font-bold transition-all cursor-pointer {{ $isActive ? 'border-amber-400 bg-amber-100 text-[#2D231E]' : 'border-[#2D231E] bg-white text-[#8C7B70]' }}"
                                >
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                    </details>
                @endif
            </div>

            {{-- Sticky footer --}}
            <div class="px-5 py-4 border-t-2 border-[#2D231E] bg-[#FAF6EE] space-y-2 shrink-0">
                <button
                    type="submit"
                    class="w-full min-h-[44px] font-pixel text-xs font-bold px-6 py-3 rounded-2xl bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] uppercase shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] transition-all cursor-pointer"
                >
                    APPLY FILTERS
                </button>
                @if ($hasActive)
                    <a
                        href="{{ strtok($action, '?') }}"
                        class="block w-full min-h-[44px] font-pixel text-[10px] font-bold px-6 py-3 rounded-2xl bg-white border-2 border-[#2D231E] text-[#8C7B70] uppercase text-center shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all"
                    >
                        RESET ALL
                    </a>
                @endif
            </div>
        </div>
    </div>
</form>