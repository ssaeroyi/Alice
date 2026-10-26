@extends('layouts.authenticated')

@section('title', 'Dictionary')

@section('page', 'study-dictionary')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-6 space-y-5">

    <!-- Header row -->
    <div class="flex items-center justify-between gap-3">
        <a href="{{ $back ?? route('study') }}" aria-label="{{ $back ? 'Back to the story' : 'Back to Study' }}"
           class="w-10 h-10 flex items-center justify-center bg-white hover:bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl text-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all">
            <x-icons.arrow-left class="w-4 h-4" />
        </a>
        <h1 class="font-pixel text-lg font-bold text-[#2D231E] uppercase">Dictionary</h1>
        <span class="w-10"></span>
    </div>

    <!-- Search box -->
    <form method="GET" action="{{ route('study.dictionary') }}" data-dictionary-search class="relative">
        <x-icons.magnifier class="w-5 h-5 absolute left-4 top-1/2 -translate-y-1/2 text-[#2D231E] pointer-events-none" />
        <input
            type="search"
            name="word"
            value="{{ $query }}"
            placeholder="Type a word…"
            autocomplete="off"
            autofocus
            class="w-full min-h-[52px] pl-12 pr-4 bg-white border-2 border-[#2D231E] rounded-2xl text-base font-bold text-[#2D231E] placeholder-[#B5A89C] shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] focus:outline-none focus:ring-2 focus:ring-amber-400 transition-all"
        >
    </form>

    <!-- Entry -->
    <div data-entry-region class="bg-white border-2 border-[#2D231E] rounded-2xl p-5 sm:p-6 shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]">
        @include('student.partials.word-entry', [
            'entry' => $entry,
            'context' => 'dictionary',
            'savedWord' => $savedWord,
            'status' => $status,
            'tier' => $tier,
        ])
    </div>

</div>
@endsection
