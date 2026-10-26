@extends('layouts.teacher')

@section('title', 'Classes')
@section('eyebrow', 'Teaching')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-[#2D231E]">
                    Each class is its own space for managing the roster and assigning stories from one place.
                </p>
            </div>
            <a href="{{ route('teacher.classes.create') }}" class="inline-flex items-center justify-center gap-2 min-h-[44px] px-5 py-2.5 rounded-xl bg-emerald-600 text-white text-sm font-bold hover:bg-emerald-700 transition-colors">
                New class
            </a>
        </div>

        @if ($groups->isEmpty())
            <div class="bg-white border border-[#E6DCCF] rounded-xl p-6 sm:p-10 text-center">
                <p class="text-base font-extrabold text-[#2D231E]">No classes yet</p>
                <p class="text-sm font-semibold text-[#2D231E] mt-1">Create your first class to start assigning work.</p>
                <a href="{{ route('teacher.classes.create') }}" class="inline-block mt-4 px-5 py-2.5 rounded-xl bg-emerald-600 text-white text-sm font-bold hover:bg-emerald-700 transition-colors">Create a class</a>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($groups as $group)
                    <div class="flex flex-col bg-white border border-[#E6DCCF] rounded-xl p-5 hover:border-sky-500 hover:shadow-[0_4px_12px_rgba(45,35,30,0.08)] transition-all">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <h3 class="text-base font-extrabold text-[#2D231E] truncate">
                                    {{ $group->name }}
                                </h3>
                                <p class="text-[11px] font-bold text-[#2D231E] mt-0.5">{{ $group->displayLabel() }}</p>
                            </div>
                            <span class="shrink-0 text-[10px] font-bold uppercase tracking-wide px-2 py-1 rounded-full bg-emerald-100 text-emerald-700">
                                {{ $group->members_count }} {{ $group->members_count === 1 ? 'student' : 'students' }}
                            </span>
                        </div>

                        <div class="mt-4 pt-3 border-t border-[#E6DCCF] space-y-2">
                            <div class="flex items-center justify-between text-[11px] font-bold">
                                <span class="text-[#2D231E]">Join code</span>
                                <span class="font-mono text-sky-700">{{ $group->join_code }}</span>
                            </div>
                            <div class="flex items-center justify-between text-[11px] font-bold">
                                <span class="text-[#2D231E]">Assignments</span>
                                <span class="text-[#2D231E]">{{ $group->assignments_count }}</span>
                            </div>
                        </div>

                        <a href="{{ route('teacher.classes.show', $group) }}" class="mt-4 inline-flex items-center justify-center gap-1.5 min-h-[44px] rounded-lg bg-[#FAF6EE] border border-[#E6DCCF] text-sm font-bold text-[#2D231E] hover:bg-amber-50 hover:border-amber-300 transition-colors">
                            Open dashboard
                        </a>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
