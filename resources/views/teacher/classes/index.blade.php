@extends('layouts.teacher')

@section('title', 'Classes')
@section('eyebrow', 'Teaching')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm font-extrabold text-[#2D231E]">
                {{ $groups->count() }} class{{ $groups->count() === 1 ? '' : 'es' }}
            </p>
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
                    <div class="flex flex-col bg-white border border-[#E6DCCF] rounded-xl p-5 hover:border-sky-500 hover:shadow-[0_4px_12px_rgba(45,35,30,0.08)] transition-all group">
                        <a href="{{ route('teacher.classes.show', $group) }}" class="flex items-start justify-between gap-2">
                            <h3 class="text-base font-extrabold text-[#2D231E] truncate group-hover:text-sky-700 transition-colors">
                                {{ $group->name }}
                            </h3>
                            <span class="shrink-0 text-[10px] font-bold uppercase tracking-wide px-2 py-1 rounded-full bg-emerald-100 text-emerald-700 whitespace-nowrap">
                                {{ $group->members_count }} {{ $group->members_count === 1 ? 'student' : 'students' }}
                            </span>
                        </a>

                        <div class="mt-4 pt-3 border-t border-[#E6DCCF] space-y-2">
                            <button type="button" onclick="if (navigator.clipboard) { navigator.clipboard.writeText('{{ $group->join_code }}'); this.querySelector('[data-copy-label]').textContent = 'Copied'; setTimeout(() => this.querySelector('[data-copy-label]').textContent = '{{ $group->join_code }}', 1500); }" title="Click to copy"
                                    class="w-full flex items-center justify-between text-[11px] font-bold px-2.5 py-1.5 rounded-lg bg-[#FAF6EE] border border-[#E6DCCF] hover:border-[#2D231E] transition-colors cursor-pointer">
                                <span class="text-[#8C7B70] uppercase tracking-wide">Join code</span>
                                <span class="font-mono text-sky-700" data-copy-label>{{ $group->join_code }}</span>
                            </button>
                            <div class="flex items-center justify-between text-[11px] font-bold px-2.5">
                                <span class="text-[#2D231E]">Assignments</span>
                                <span class="text-[#2D231E] tabular-nums">{{ $group->assignments_count }}</span>
                            </div>
                        </div>

                        <a href="{{ route('teacher.classes.show', $group) }}" class="mt-4 inline-flex items-center justify-center gap-1.5 min-h-[44px] rounded-lg bg-emerald-600 text-white text-sm font-bold hover:bg-emerald-700 transition-colors">
                            Open class
                        </a>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
