@extends('layouts.authenticated')

@section('title', match($filter) {
    'strong' => 'Strong Words',
    'learning' => 'Learning Words',
    'not-studied' => 'New Words',
    'forgotten' => 'Forgotten Words',
    default => 'All Saved Words',
})

@section('page', 'study-words')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-8 sm:py-6 space-y-5 pb-32 md:pb-10">

    <!-- Header row -->
    <div class="flex items-center justify-between gap-3">
        <a href="{{ route('study') }}" aria-label="Back to Study"
           class="w-10 h-10 flex items-center justify-center bg-white hover:bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl text-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all">
            <x-icons.arrow-left class="w-4 h-4" />
        </a>
        <div class="flex-1 min-w-0">
            <h1 class="font-pixel text-sm sm:text-lg font-bold text-[#2D231E] uppercase text-center leading-tight">
                @if($filter === 'strong')
                    Strong words
                @elseif($filter === 'learning')
                    Learning words
                @elseif($filter === 'not-studied')
                    New words
                @elseif($filter === 'forgotten')
                    Forgotten words
                @else
                    All saved words
                @endif
            </h1>
        </div>
        <button type="button" data-edit-toggle aria-pressed="false"
                class="min-h-[40px] px-3 bg-white hover:bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl font-pixel text-[9px] sm:text-[10px] font-bold text-[#2D231E] uppercase shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all cursor-pointer">
            <span data-edit-label>Edit</span>
        </button>
    </div>

    <!-- Toolbar: search + sort -->
    <div class="flex flex-col sm:flex-row gap-2.5" data-toolbar>
        <div class="relative flex-1">
            <x-icons.magnifier class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-[#2D231E] pointer-events-none" />
            <input type="search" name="q" value="{{ $q }}" placeholder="Search your words…" data-word-search autocomplete="off"
                   class="w-full min-h-[44px] pl-10 pr-4 bg-white border-2 border-[#2D231E] rounded-xl text-sm font-bold text-[#2D231E] placeholder-[#B5A89C] focus:outline-none focus:ring-2 focus:ring-amber-400 transition-all">
        </div>
    </div>

    <!-- Sort pills -->
    <div class="flex items-center gap-2 overflow-x-auto -mx-1 px-1 pb-1" style="scrollbar-width:none" role="group" aria-label="Sort words">
        @foreach ([
            ['v' => 'date', 'l' => 'Date added'],
            ['v' => 'alpha', 'l' => 'Alphabetical'],
            ['v' => 'mastery', 'l' => 'Mastery'],
        ] as $opt)
            <button type="button" data-word-sort="{{ $opt['v'] }}" aria-pressed="{{ $sort === $opt['v'] ? 'true' : 'false' }}"
                    class="shrink-0 min-h-[40px] px-3 font-pixel text-[9px] sm:text-[10px] font-bold uppercase border-2 border-[#2D231E] rounded-xl shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all cursor-pointer {{ $sort === $opt['v'] ? 'bg-amber-400 text-[#2D231E]' : 'bg-white hover:bg-[#FAF6EE] text-[#2D231E]' }}">
                {{ $opt['l'] }}
            </button>
        @endforeach
    </div>

    <!-- Total count -->
    <p class="font-pixel text-[9px] sm:text-[10px] font-bold text-[#2D231E] uppercase"><span data-total-words>{{ $totalCount }}</span> words</p>

    <!-- AJAX-swapped results region -->
    <div data-search-results>
        @include('student.partials.study-word-results', ['groups' => $groups, 'q' => $q, 'filter' => $filter])
    </div>

</div>

<!-- Sticky bulk action bar (edit mode + selection only) -->
<div data-bulk-bar
     class="hidden fixed inset-x-0 bottom-[calc(4.25rem+env(safe-area-inset-bottom))] md:bottom-4 z-30 px-4">
    <div class="max-w-2xl mx-auto bg-white border-2 border-[#2D231E] rounded-2xl shadow-[5px_5px_0px_0px_rgba(45,35,30,1)] p-3 space-y-2.5">
        <div class="flex items-center justify-between gap-2">
            <span class="font-pixel text-[10px] font-bold text-[#2D231E] uppercase"><span data-selected-count>0</span> selected</span>
            <div class="flex gap-3">
                <button type="button" data-select-all class="min-h-[40px] px-2 font-pixel text-[9px] font-bold text-[#2D231E] uppercase underline underline-offset-2 cursor-pointer">All</button>
                <button type="button" data-select-none class="min-h-[40px] px-2 font-pixel text-[9px] font-bold text-[#2D231E] uppercase underline underline-offset-2 cursor-pointer">None</button>
            </div>
        </div>
        <div class="grid grid-cols-3 gap-2">
            <button type="button" data-bulk-action="delete"
                    class="min-h-[44px] bg-rose-500 hover:bg-rose-600 border-2 border-[#2D231E] rounded-xl font-pixel text-[9px] font-bold text-white shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all cursor-pointer uppercase disabled:opacity-50 disabled:cursor-not-allowed">
                Delete
            </button>
            <button type="button" data-bulk-action="reset"
                    class="min-h-[44px] bg-white hover:bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl font-pixel text-[9px] font-bold text-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all cursor-pointer uppercase disabled:opacity-50 disabled:cursor-not-allowed">
                Reset progress
            </button>
            <button type="button" data-bulk-action="master"
                    class="min-h-[44px] bg-emerald-400 hover:bg-emerald-500 border-2 border-[#2D231E] rounded-xl font-pixel text-[9px] font-bold text-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all cursor-pointer uppercase disabled:opacity-50 disabled:cursor-not-allowed">
                Mastered
            </button>
        </div>
    </div>
</div>

<!-- Delete confirm -->
<button type="button" data-modal-open="bulk-confirm-modal" data-bulk-confirm-trigger class="hidden" aria-hidden="true" tabindex="-1"></button>
<x-modal id="bulk-confirm-modal" title="ARE YOU SURE?" pixel>
    <p class="text-sm font-bold text-[#2D231E]" data-bulk-confirm-copy>Delete selected words?</p>
    <p class="text-xs font-semibold text-[#2D231E] mt-1">This can't be undone.</p>
    <div class="flex gap-2 pt-3">
        <button type="button" data-bulk-confirm-go
                class="flex-1 min-h-[44px] bg-rose-500 hover:bg-rose-600 border-2 border-[#2D231E] rounded-2xl font-pixel text-[11px] font-bold text-white shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-none transition-all cursor-pointer uppercase">
            Yes, do it
        </button>
        <button type="button" data-modal-close
                class="flex-1 min-h-[44px] bg-white hover:bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl font-pixel text-[11px] font-bold text-[#2D231E] shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-none transition-all cursor-pointer uppercase">
            Cancel
        </button>
    </div>
</x-modal>
@endsection
