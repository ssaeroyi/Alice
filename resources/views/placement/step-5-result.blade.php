@extends('layouts.placement')

@section('js')
    @vite(['resources/js/app.js'])
@endsection

@section('content')
<!-- Background Canvas -->
<div class="max-w-6xl mx-auto px-4 py-12 min-h-[75vh] flex items-center justify-center relative">

    <!-- Centered Setup Container -->
    <div class="w-full max-w-lg bg-white border-4 border-[#2D231E] rounded-3xl p-6 sm:p-8 shadow-[6px_6px_0px_0px_rgba(45,35,30,1)] space-y-6 text-center">

        <!-- Shared placement result card (shelf, welcome kit, stories, review) -->
        @include('placement.result-card')

    </div>

</div>
@endsection