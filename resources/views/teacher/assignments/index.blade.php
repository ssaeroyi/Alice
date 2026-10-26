@extends('layouts.teacher')

@section('title', 'Assignments')
@section('eyebrow', 'Teaching')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <nav class="flex items-center gap-1.5 text-sm font-bold text-[#2D231E]">
                    <a href="{{ ($class ?? null) ? route('teacher.classes.show', $class) : route('teacher.classes.index') }}" class="inline-flex items-center gap-1 hover:text-[#2D231E]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path></svg>
                        Back to {{ ($class ?? null) ? $class->name : 'classes' }}
                    </a>
                </nav>
            </div>
            <a
                href="{{ route('teacher.assignments.create') }}"
                class="inline-flex items-center px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-bold hover:bg-emerald-700 transition-colors"
            >
                New Assignment
            </a>
        </div>

        @php
            $labels = ['active' => 'Active', 'draft' => 'Draft', 'cancelled' => 'Cancelled', 'archived' => 'Finished'];
        @endphp

        @if (array_sum($counts) === 0)
            <div class="bg-white border border-[#E6DCCF] rounded-xl p-6 sm:p-10 text-center">
                <p class="text-base font-extrabold text-[#2D231E]">No assignments yet</p>
                <p class="mt-1 text-sm font-semibold text-[#2D231E]">
                    Create your first assignment to share a story with your class.
                </p>
                <a
                    href="{{ route('teacher.assignments.create') }}"
                    class="mt-4 inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-bold hover:bg-emerald-700 transition-colors"
                >
                    Create your first assignment
                </a>
            </div>
        @else
            <x-admin.panel>
                <div class="flex flex-col lg:flex-row lg:items-center gap-3">
                    <x-admin.search-input
                        :action="route('teacher.assignments.index', ['status' => $tab])"
                        :value="request('q')"
                        placeholder="Search assignment or story title"
                        label="Search assignments"
                    />
                    <div class="flex flex-wrap items-center gap-1.5" role="group" aria-label="Filter assignments" data-tab-history>
                @foreach ($tabs as $key)
                    @php
                        $label = $labels[$key];
                        $count = $counts[$key] ?? 0;
                        $active = $key === $tab;
                    @endphp
                    <a
                        href="{{ route('teacher.assignments.index', array_merge(request()->except(['page', 'status']), ['status' => $key])) }}"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-bold transition-colors {{ $active ? 'bg-emerald-600 text-white' : 'text-[#2D231E]' }}"
                    >
                        {{ $label }}
                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full {{ $active ? 'bg-white/20 text-white' : 'bg-[#F3ECE0] text-[#2D231E]' }}">{{ $count }}</span>
                    </a>
                @endforeach
                    </div>
                </div>
            </x-admin.panel>

            @if ($assignments->isEmpty())
                <div class="bg-white border border-[#E6DCCF] rounded-xl p-6 sm:p-10 text-center">
                    <p class="text-base font-extrabold text-[#2D231E]">No {{ strtolower($labels[$tab]) }} assignments</p>
                    <p class="mt-1 text-sm font-semibold text-[#2D231E]">
                        @if ($tab === 'active')
                            Nothing is in progress right now. Create a new assignment to get going.
                        @elseif ($tab === 'draft')
                            You have no drafts saved.
                        @elseif ($tab === 'cancelled')
                            Nothing has been cancelled yet.
                        @else
                            Assignments move here once their due date passes or you finish them.
                        @endif
                    </p>
                </div>
            @else
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($assignments as $assignment)
                    <a
                        href="{{ route('teacher.assignments.show', $assignment) }}"
                        class="group bg-white border border-[#E6DCCF] rounded-xl p-5 hover:border-sky-500 hover:shadow-[0_4px_12px_rgba(45,35,30,0.08)] transition-all"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <h3 class="text-base font-extrabold text-[#2D231E] group-hover:text-sky-700 truncate">
                                    {{ $assignment->title }}
                                </h3>
                                <p class="text-[11px] font-bold text-[#2D231E] mt-0.5">
                                    {{ $assignment->audienceLabel() }}
                                </p>
                            </div>
                            <span class="shrink-0 {{ $assignment->status === 'active' ? 'bg-emerald-100 text-emerald-700' : ($assignment->status === 'cancelled' ? 'bg-rose-100 text-rose-700' : ($assignment->status === 'archived' ? 'bg-[#2D231E] text-white' : 'bg-amber-100 text-amber-700')) }} text-[10px] font-bold uppercase tracking-wide px-2 py-1 rounded-full">
                                {{ $assignment->status === 'archived' ? 'Finished' : $assignment->status }}
                            </span>
                        </div>

                        @if ($assignment->story)
                            <p class="mt-3 text-sm font-semibold text-[#2D231E]">
                                {{ $assignment->story->title }}
                            </p>
                        @else
                            <p class="mt-3 text-sm font-semibold text-[#2D231E] italic">No story attached</p>
                        @endif

                    @php
                        $dueTone = 'border-[#E6DCCF] bg-[#FAF6EE] text-[#2D231E]';
                        if ($assignment->status === 'active' && $assignment->due_at) {
                            if ($assignment->isOverdue()) {
                                $dueTone = 'border-rose-200 bg-rose-50 text-rose-700';
                            } elseif ($assignment->due_at->diffInDays(now()) <= 3) {
                                $dueTone = 'border-amber-200 bg-amber-50 text-amber-800';
                            }
                        }
                    @endphp
                        <div class="mt-4 pt-3 border-t border-[#E6DCCF] flex items-center justify-between gap-2">
                            <span class="shrink-0 text-[11px] font-bold px-2 py-1 rounded-lg border {{ $dueTone }}">
                                @if ($assignment->due_at)
                                    Due {{ $assignment->due_at->format('M j, Y') }}
                                @else
                                    No due date
                                @endif
                            </span>
                            <div class="flex items-center gap-2">
                                @if ($assignment->status === 'active')
                                    @php
                                        $compliance = $assignment->compliancePercent();
                                        $ringColor = $compliance >= 70 ? '#34D399' : ($compliance >= 40 ? '#FBBF24' : '#FB7185');
                                        $ringCirc = 2 * pi() * 14;
                                    @endphp
                                    <svg class="w-9 h-9 shrink-0" viewBox="0 0 36 36" aria-hidden="true">
                                        <circle cx="18" cy="18" r="14" fill="none" stroke="#F3ECE0" stroke-width="4" />
                                        <circle cx="18" cy="18" r="14" fill="none" stroke="{{ $ringColor }}" stroke-width="4" stroke-linecap="round" stroke-dasharray="{{ $ringCirc }}" stroke-dashoffset="{{ $ringCirc * (1 - $compliance / 100) }}" transform="rotate(-90 18 18)" />
                                        <text x="18" y="18" text-anchor="middle" dominant-baseline="central" font-size="9" font-weight="800" fill="#2D231E">{{ $compliance }}%</text>
                                    </svg>
                                @elseif ($assignment->status === 'archived')
                                    <span class="text-[11px] font-bold text-[#2D231E]">
                                        Finished {{ optional($assignment->finished_at)->format('M j, Y') }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        @if ($assignment->share_code)
                            <p class="mt-2 inline-flex items-center gap-1 text-[11px] font-mono font-bold text-sky-700">
                                <span>/a/{{ $assignment->share_code }}</span>
                            </p>
                        @endif
                    </a>
                @endforeach
            </div>
            <div class="pt-4">
                {{ $assignments->links('pagination::teacher') }}
            </div>
        @endif
    @endif
    </div>
@endsection
