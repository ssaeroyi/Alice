@extends('layouts.authenticated')

@section('page', 'classwork')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-6 space-y-4">

    @include('partials.classwork-widget', [
        'assignments' => $assignments,
        'finished' => $finished,
        'joinedClasses' => $joinedClasses,
        'activeCount' => $activeCount,
    ])
</div>

<!-- Join class modal — opened via data-modal-open/modals.js; submit handled by hub-classwork.js -->
<x-modal id="hub-join-class" title="JOIN A CLASS" pixel>
    <div class="space-y-5 pt-2">
        <p class="text-sm font-semibold text-[#2D231E] leading-relaxed">
            Type the code your teacher shared to join their class. Once you're in, their assignments show up here.
        </p>

        <form id="hub-join-class-form" method="POST" action="{{ route('student.classes.join') }}" class="space-y-3">
            @csrf
            <label class="block text-sm font-extrabold text-[#2D231E] mb-1" for="join_code">Class code</label>
            <input
                type="text"
                name="join_code"
                id="join_code"
                placeholder="e.g. CLS-ABCD"
                required
                maxlength="16"
                autocomplete="off"
                class="w-full px-4 py-3 border-2 border-[#2D231E] rounded-xl bg-white text-sm font-bold text-[#2D231E] placeholder:text-[#C4B6AB] uppercase tracking-wide focus:outline-none focus:border-amber-500"
            >
            <p data-cw-join-error aria-live="polite" class="text-xs font-bold text-rose-600"></p>
            <button
                type="submit"
                class="w-full min-h-[48px] bg-amber-400 border-2 border-[#2D231E] rounded-2xl font-pixel text-xs font-bold text-[#2D231E] shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all cursor-pointer uppercase"
            >
                Join class
            </button>
        </form>
    </div>
</x-modal>

<!-- Leave class modal — confirmation only; the leave POST runs via hub-classwork.js -->
<x-modal id="hub-leave-class" title="LEAVE CLASS" pixel>
    <div class="space-y-5 pt-2">
        <p class="text-sm font-semibold text-[#2D231E] leading-relaxed">
            Leave <span data-cw-leave-name class="font-extrabold"></span>? You can rejoin anytime with the code.
        </p>
        <div class="flex flex-col sm:flex-row gap-3">
            <button
                type="button"
                data-modal-close
                class="flex-1 min-h-[48px] bg-white border-2 border-[#2D231E] rounded-2xl font-pixel text-xs font-bold text-[#2D231E] shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] transition-all cursor-pointer uppercase"
            >
                Cancel
            </button>
            <button
                type="button"
                data-cw-leave-confirm
                class="flex-1 min-h-[48px] bg-rose-600 border-2 border-[#2D231E] rounded-2xl font-pixel text-xs font-bold text-white shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] transition-all cursor-pointer uppercase"
            >
                Leave class
            </button>
        </div>
    </div>
</x-modal>
@endsection
