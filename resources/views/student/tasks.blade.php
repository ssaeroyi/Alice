@extends('layouts.authenticated')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-6 space-y-6" data-tasks-hub>

    <!-- Header -->
    <div class="flex items-center gap-3">
        <a href="{{ route('hub') }}" aria-label="Back to hub"
           class="w-11 h-11 flex items-center justify-center bg-white border-2 border-[#2D231E] rounded-xl text-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all">
            <x-icons.arrow-left class="w-4 h-4" />
        </a>
        <h1 class="font-pixel text-base font-bold text-[#2D231E] uppercase">Daily Tasks</h1>
        <x-info label="About daily tasks">Three little wins a day. Finish each task to claim keys and points and keep your daily goals on track.</x-info>
    </div>

    @if(session('error'))
        <div class="bg-rose-100 border-2 border-rose-400 text-rose-700 text-sm font-bold px-4 py-3 rounded-2xl text-center">
            {{ session('error') }}
        </div>
    @endif

    <div class="text-center">
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-sky-100 border-2 border-[#2D231E] rounded-lg text-sky-700 text-[11px] font-bold [&>svg]:h-4 [&>svg]:w-4">
            {!! \App\Support\LibraryPresentation::iconGlyph('ui', 'snowflake') !!}
            <span>Rerolls left today: {{ $rerollsRemaining }}</span>
        </span>
    </div>

    <!-- Task list -->
    <div class="space-y-4">
        @if ($tasks->isNotEmpty())
            @include('partials.task-card', ['tasks' => $tasks, 'rerollsRemaining' => $rerollsRemaining])
        @else
            <div class="bg-[#FAF6EE] border-4 border-[#2D231E] rounded-2xl p-6 text-center">
                <p class="font-pixel text-sm font-bold text-[#2D231E] uppercase">No tasks today</p>
                <p class="text-xs text-[#8C7B70] font-medium mt-1">Check back tomorrow for a fresh set.</p>
            </div>
        @endif
    </div>

</div>
@endsection
