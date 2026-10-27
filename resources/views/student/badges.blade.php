@extends('layouts.authenticated')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-8 sm:py-6 space-y-6">

    <!-- Header -->
    <div class="flex items-center gap-3">
        <a href="{{ route('hub') }}" aria-label="Back to hub"
           class="w-11 h-11 flex items-center justify-center bg-white border-2 border-[#2D231E] rounded-xl text-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all">
            <x-icons.arrow-left class="w-4 h-4" />
        </a>
        <h1 class="font-pixel text-base font-bold text-[#2D231E] uppercase">My Badges</h1>
    </div>

    @include('partials.badge-shelf', ['rows' => $rows, 'progressValues' => $progressValues])

</div>
@endsection
