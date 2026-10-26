@extends('layouts.authenticated')

@section('title', $savedWord->word)

@section('page', 'study-word')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-6 space-y-5">

    <!-- Header row -->
    <div class="flex items-center justify-between gap-3">
        <a href="{{ route('study.words') }}" aria-label="Back to saved words"
           class="w-10 h-10 flex items-center justify-center bg-white hover:bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl text-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all">
            <x-icons.arrow-left class="w-4 h-4" />
        </a>
        <h1 class="font-pixel text-lg font-bold text-[#2D231E] uppercase">{{ $savedWord->word }}</h1>
        <span class="w-10"></span>
    </div>

    <!-- Entry -->
    <div class="bg-white border-2 border-[#2D231E] rounded-2xl p-5 sm:p-6 shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]">
        @include('student.partials.word-entry', [
            'entry' => $entry,
            'context' => 'saved',
            'savedWord' => $savedWord,
            'status' => $status,
            'tier' => $tier,
        ])
    </div>

</div>
@endsection
