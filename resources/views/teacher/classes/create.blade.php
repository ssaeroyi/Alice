@extends('layouts.teacher')

@section('title', 'New Class')
@section('eyebrow', 'Classes')

@section('content')
    <div class="max-w-2xl mx-auto">
        <a href="{{ route('teacher.classes.index') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-[#2D231E] hover:text-sky-700 transition-colors mb-4">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path></svg>
            Back to classes
        </a>

        <form method="POST" action="{{ route('teacher.classes.store') }}" class="bg-white border border-[#E6DCCF] rounded-xl p-4 sm:p-6 space-y-6">
            @csrf

            <div>
                <label for="name" class="block text-sm font-extrabold text-[#2D231E] mb-1">Class name</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    value="{{ old('name') }}"
                    required
                    maxlength="80"
                    placeholder="e.g. Grade 7 Diamond"
                    class="w-full px-3 py-2.5 border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] focus:outline-none focus:border-sky-600"
                >
                @error('name')<p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="description" class="block text-sm font-extrabold text-[#2D231E] mb-1">Description <span class="font-semibold text-[#8C7B70]">(optional)</span></label>
                <textarea
                    id="description"
                    name="description"
                    rows="3"
                    maxlength="2000"
                    placeholder="What is this class about? Students see this on the class page."
                    class="w-full px-3 py-2.5 border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] focus:outline-none focus:border-sky-600"
                >{{ old('description') }}</textarea>
                @error('description')<p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p>@enderror
            </div>

            <div class="bg-[#FAF6EE] border border-[#E6DCCF] rounded-xl p-4">
                <p class="text-xs font-bold text-[#2D231E] uppercase tracking-wide mb-1">How students join</p>
                <p class="text-sm font-semibold text-[#2D231E]">A join code is created automatically. Share it with your class. Students enter it (or accept an invite) in their "Classwork" area to enroll.</p>
            </div>

            <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-center sm:justify-end gap-2 sm:gap-3 pt-2 border-t border-[#E6DCCF]">
                <a href="{{ route('teacher.classes.index') }}" class="flex items-center justify-center w-full sm:w-auto px-4 py-2.5 rounded-lg text-sm font-bold text-[#2D231E]">Cancel</a>
                <button type="submit" class="w-full sm:w-auto px-5 py-2.5 rounded-lg bg-emerald-600 text-white text-sm font-bold hover:bg-emerald-700 transition-colors cursor-pointer">Create class</button>
            </div>
        </form>
    </div>
@endsection
