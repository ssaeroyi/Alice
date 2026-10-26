@php
    $schoolWide = $assignments->where('scope', '!=', 'group')->values();
    $finishedSchoolWide = $finished->where('scope', '!=', 'group')->values();
    $hasAny = $schoolWide->isNotEmpty() || $joinedClasses->isNotEmpty();
@endphp

<div class="space-y-4" data-cw-root
    data-join-url="{{ route('student.classes.join') }}"
    data-leave-url="{{ route('student.classes.leave', ['group' => '__ID__']) }}">

    {{-- Landing view (study-page register: plain sections + border-2 study cards) --}}
    <div data-cw-view="classes" class="space-y-5">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <h1 class="font-pixel text-[11px] sm:text-sm font-bold text-[#2D231E] uppercase tracking-wider">Classwork</h1>
            <button
                type="button"
                data-modal-open="hub-join-class"
                class="inline-flex items-center gap-1.5 min-h-[44px] px-4 rounded-2xl bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] font-pixel text-[9px] font-bold uppercase shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] hover:bg-amber-300 active:translate-x-[1px] active:translate-y-[1px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] transition-all"
            >
                Join class
            </button>
        </div>

        @if (! $hasAny)
            <div class="bg-white border-2 border-[#2D231E] rounded-2xl p-8 text-center shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]">
                <p class="text-sm font-semibold text-[#2D231E]">Nothing here yet.</p>
                <p class="mt-1 text-xs font-semibold text-[#2D231E]">Join a class with your teacher's code to see assignments here.</p>
            </div>
        @else
            @if ($schoolWide->isNotEmpty())
                <div class="space-y-2">
                    <p class="font-pixel text-[9px] font-bold uppercase text-[#2D231E]">For everyone</p>
                    @foreach ($schoolWide as $assignment)
                        @include('partials.hub-assignment-row', ['assignment' => $assignment])
                    @endforeach
                </div>
            @endif

            @if ($finishedSchoolWide->isNotEmpty())
                <details class="bg-white border-2 border-[#2D231E] rounded-2xl px-4 py-2 shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]">
                    <summary class="flex items-center justify-between cursor-pointer text-sm font-bold text-[#2D231E] min-h-[44px] list-none [&::-webkit-details-marker]:hidden">
                        <span>Finished assignments</span>
                        <span class="text-[10px] font-bold text-[#2D231E]">{{ $finishedSchoolWide->count() }}</span>
                    </summary>
                    <div class="mt-2 space-y-2">
                        @foreach ($finishedSchoolWide as $assignment)
                            <a href="{{ route('student.assignments.show', $assignment) }}"
                                class="flex flex-row items-center gap-3 p-3 bg-[#FAF6EE] border-2 border-[#2D231E]/20 rounded-xl hover:border-sky-600 transition-colors group">
                                <span class="flex-1 min-w-0 text-sm font-bold text-[#2D231E] group-hover:text-sky-700 truncate">{{ $assignment->title }}</span>
                                @if ($assignment->story?->title)
                                    <span class="shrink-0 text-[10px] font-bold text-[#2D231E]">{{ \Illuminate\Support\Str::limit($assignment->story->title, 24) }}</span>
                                @endif
                                <svg class="font-semibold w-3.5 h-3.5 shrink-0 text-[#2D231E] group-hover:text-sky-700" fill="currentColor" viewBox="0 0 24 24"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
                            </a>
                        @endforeach
                    </div>
                </details>
            @endif

            <div class="space-y-3">
                <p class="font-pixel text-[9px] font-bold uppercase text-[#2D231E]">Your classes</p>

                @if ($joinedClasses->isNotEmpty())
                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach ($joinedClasses as $class)
                            <div class="flex flex-col bg-white border-2 border-[#2D231E] rounded-2xl overflow-hidden shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]">
                                <div class="bg-amber-400 border-b-2 border-[#2D231E] px-4 py-3">
                                    <h3 class="text-sm font-extrabold text-[#2D231E] leading-tight">{{ $class->name }}</h3>
                                    <p class="text-[10px] font-bold text-[#2D231E] truncate">
                                        @if ($class->teacher)
                                            {{ $class->teacher->first_name }} {{ $class->teacher->last_name }}
                                        @else
                                            Your teacher
                                        @endif
                                    </p>
                                </div>

                                <div class="px-5 pt-4 flex items-center gap-2 flex-wrap">
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wide px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700 border-2 border-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,0.9)]">
                                        {{ $class->members_count }} {{ $class->members_count === 1 ? 'student' : 'students' }}
                                    </span>
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wide px-2.5 py-1 rounded-full bg-sky-100 text-sky-700 border-2 border-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,0.9)]">
                                        {{ $class->active_assignments->count() }} {{ $class->active_assignments->count() === 1 ? 'assignment' : 'assignments' }}
                                    </span>
                                    @if ($class->finished_assignments->isNotEmpty())
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wide px-2.5 py-1 rounded-full bg-[#FAF6EE] text-[#2D231E] border-2 border-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,0.9)]">
                                            {{ $class->finished_assignments->count() }} finished
                                        </span>
                                    @endif
                                </div>

                                <div class="px-5 pb-5 pt-4 mt-auto">
                                    <div class="flex items-center justify-between mb-3">
                                        <span class="text-[10px] font-bold uppercase tracking-wide text-[#2D231E]">Join code</span>
                                        <span class="font-mono text-xs font-bold text-sky-700 bg-[#FAF6EE] border border-[#2D231E]/20 rounded-lg px-2 py-0.5">{{ $class->join_code }}</span>
                                    </div>

                                    <button
                                        type="button"
                                        data-cw-open="class-{{ $class->id }}"
                                        class="w-full inline-flex items-center justify-center min-h-[44px] rounded-2xl bg-amber-400 border-2 border-[#2D231E] font-pixel text-[10px] font-bold uppercase text-[#2D231E] shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] hover:bg-amber-300 active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all"
                                    >
                                        Open
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="bg-white border-2 border-dashed border-[#E6DCCF] rounded-2xl p-5 text-center shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]">
                        <p class="text-sm font-semibold text-[#2D231E]">No classes yet</p>
                        <p class="mt-1 text-xs font-semibold text-[#2D231E]">Join with your teacher's class code to see it here.</p>
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- One drill-down view per joined class: a single card with an internal
         toolbar (tabs left, action icons right) and tab panels below. --}}
    @foreach ($joinedClasses as $class)
        <div data-cw-view="class-{{ $class->id }}" hidden class="space-y-3">
            <button
                type="button"
                data-cw-back
                aria-label="Back to classes"
                class="inline-flex items-center gap-1.5 min-h-[44px] font-pixel text-[9px] font-bold uppercase text-sky-700 hover:text-sky-800 transition-colors"
            >
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>
                Classes
            </button>

            <div class="max-w-xl mx-auto bg-white border-2 border-[#2D231E] rounded-2xl shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] overflow-hidden">
                <div class="p-4 sm:p-5">
                    <div class="flex items-center justify-between gap-2 mb-4">
                    <div role="tablist" aria-label="Class sections" class="flex gap-1.5 overflow-x-auto min-w-0 pb-1 pr-1" style="scrollbar-width:none">
                        <button
                            type="button"
                            role="tab"
                            data-cw-tab="class-{{ $class->id }}-details"
                            aria-selected="true"
                            class="shrink-0 min-h-[40px] px-3 font-pixel text-[9px] font-bold uppercase border-2 border-[#2D231E] rounded-xl bg-amber-400 text-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)]"
                        >
                            Details
                        </button>
                        <button
                            type="button"
                            role="tab"
                            data-cw-tab="class-{{ $class->id }}-assignments"
                            aria-selected="false"
                            class="shrink-0 min-h-[40px] px-3 font-pixel text-[9px] font-bold uppercase border-2 border-[#2D231E] rounded-xl bg-white text-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)]"
                        >
                            Assignments
                        </button>
                        @if ($class->finished_assignments->isNotEmpty())
                            <button
                                type="button"
                                role="tab"
                                data-cw-tab="class-{{ $class->id }}-past"
                                aria-selected="false"
                                class="shrink-0 min-h-[40px] px-3 font-pixel text-[9px] font-bold uppercase border-2 border-[#2D231E] rounded-xl bg-white text-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)]"
                            >
                            Finished
                        </button>
                        @endif
                    </div>

                    <div class="flex items-center gap-1.5 shrink-0">
                        <button
                            type="button"
                            data-cw-people-open="{{ $class->id }}"
                            aria-haspopup="dialog"
                            aria-controls="cw-people-{{ $class->id }}"
                            aria-label="People in this class"
                            class="min-w-[44px] min-h-[44px] w-10 inline-flex items-center justify-center bg-white border-2 border-[#2D231E] rounded-xl text-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] hover:bg-[#FAF6EE] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                                <circle cx="9" cy="8" r="3.25"/>
                                <path d="M3.5 20a5.5 5.5 0 0 1 11 0"/>
                                <circle cx="17" cy="9" r="2.5"/>
                                <path d="M15.5 14.2a5 5 0 0 1 5 5.8"/>
                            </svg>
                        </button>
                        <button
                            type="button"
                            data-cw-leave-open="{{ $class->id }}"
                            data-class-name="{{ $class->name }}"
                            class="min-h-[40px] px-3 inline-flex items-center bg-rose-600 border-2 border-[#2D231E] rounded-xl font-pixel text-[9px] font-bold uppercase text-white shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] hover:bg-rose-700 active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all"
                        >
                            Leave
                        </button>
                    </div>
                </div>

                    <div role="tabpanel" data-cw-panel="class-{{ $class->id }}-details">
                        <h3 class="text-base font-extrabold text-[#2D231E]">{{ $class->name }}</h3>
                        <p class="mt-0.5 text-xs font-bold text-[#2D231E]">
                            @if ($class->teacher)
                                {{ $class->teacher->first_name }} {{ $class->teacher->last_name }}
                            @else
                                Your teacher
                            @endif
                        </p>
                        <div class="mt-3 flex items-center gap-2 flex-wrap">
                            <span class="inline-flex items-center font-mono text-[11px] font-bold text-sky-700 px-2.5 py-1 rounded-full bg-[#FAF6EE] border-2 border-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,0.9)]">
                                {{ $class->join_code }}
                            </span>
                            <span class="inline-flex items-center text-[10px] font-bold uppercase tracking-wide px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700 border-2 border-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,0.9)]">
                                {{ $class->members_count }} {{ $class->members_count === 1 ? 'student' : 'students' }}
                            </span>
                        </div>
                    </div>

                    <div role="tabpanel" data-cw-panel="class-{{ $class->id }}-assignments" hidden>
                        @if ($class->active_assignments->isNotEmpty())
                            <div class="space-y-2">
                                @foreach ($class->active_assignments as $assignment)
                                    @include('partials.hub-assignment-row', ['assignment' => $assignment])
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm font-semibold text-[#2D231E] text-center py-4">No assignments yet.</p>
                        @endif
                    </div>

                    @if ($class->finished_assignments->isNotEmpty())
                        <div role="tabpanel" data-cw-panel="class-{{ $class->id }}-past" hidden class="space-y-2">
                            @foreach ($class->finished_assignments as $assignment)
                                <a href="{{ route('student.assignments.show', $assignment) }}"
                                    class="flex flex-row items-center gap-3 p-3 bg-[#FAF6EE] border-2 border-[#2D231E]/20 rounded-xl hover:border-sky-600 transition-colors group">
                                    <span class="flex-1 min-w-0 text-sm font-bold text-[#2D231E] group-hover:text-sky-700 truncate">{{ $assignment->title }}</span>
                                    @if ($assignment->story?->title)
                                        <span class="shrink-0 text-[10px] font-bold text-[#2D231E]">{{ \Illuminate\Support\Str::limit($assignment->story->title, 24) }}</span>
                                    @endif
                                    <svg class="font-semibold w-3.5 h-3.5 shrink-0 text-[#2D231E] group-hover:text-sky-700" fill="currentColor" viewBox="0 0 24 24"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endforeach

    {{-- Per-class people drawers: rendered at root level so an open drawer never hides with its view --}}
    @foreach ($joinedClasses as $class)
        <div
            id="cw-people-{{ $class->id }}"
            data-cw-people-drawer
            hidden
            class="fixed inset-0 z-[60]"
            role="dialog"
            aria-modal="true"
            aria-label="People in {{ $class->name }}"
        >
            <div data-cw-people-backdrop class="absolute inset-0 bg-[#2D231E]/40 backdrop-blur-sm"></div>

            <div class="absolute inset-y-0 right-0 w-full max-w-sm bg-white border-l-4 border-[#2D231E] shadow-[-6px_0_0_0_rgba(45,35,30,1)] flex flex-col">
                <div class="flex items-center justify-between px-5 py-4 bg-amber-400 border-b-2 border-[#2D231E] shrink-0">
                    <h2 class="font-pixel text-xs font-bold text-[#2D231E] uppercase tracking-wider">People</h2>
                    <button
                        type="button"
                        data-cw-people-close
                        class="min-w-[44px] min-h-[44px] font-pixel text-xs font-bold text-[#2D231E] bg-white border-2 border-[#2D231E] rounded-xl flex items-center justify-center px-2 shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] transition-all"
                        aria-label="Close people list"
                    >
                        [X]
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto px-5 py-5 space-y-4">
                    @if ($class->teacher)
                        <div class="rounded-2xl border-2 border-[#2D231E] bg-[#FAF6EE] p-3 flex items-center gap-3 shadow-[2px_2px_0px_0px_rgba(45,35,30,0.9)]">
                            <x-avatar :user="$class->teacher" :size="40" />
                            <span class="flex-1 min-w-0">
                                <span class="block text-sm font-extrabold text-[#2D231E] truncate">{{ $class->teacher->first_name }} {{ $class->teacher->last_name }}</span>
                                <span class="inline-flex items-center mt-1 px-2 py-0.5 rounded-md bg-amber-100 border border-[#2D231E] font-pixel text-[8px] font-bold uppercase text-[#2D231E]">Teacher</span>
                            </span>
                        </div>
                    @endif

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <p class="font-pixel text-[9px] font-bold uppercase text-[#2D231E]">Students</p>
                            <p class="text-[10px] font-bold text-[#2D231E]">{{ $class->members_count }}</p>
                        </div>

                        @if ($class->members_count === 1)
                            <div class="rounded-2xl border-2 border-dashed border-[#E6DCCF] bg-[#FAF6EE] p-4 text-center">
                                <p class="text-xs font-semibold text-[#2D231E]">Just you in this class so far.</p>
                                <p class="mt-0.5 text-[10px] font-semibold text-[#2D231E]">Classmates show up here once they join.</p>
                            </div>
                        @else
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($class->members->sortBy('first_name')->values() as $classmate)
                                    <span class="inline-flex items-center gap-1.5 pl-1 pr-2.5 py-1 rounded-full bg-[#FAF6EE] border-2 border-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,0.9)] text-xs font-bold text-[#2D231E]">
                                        <x-avatar :user="$classmate" :size="24" />
                                        {{ $classmate->first_name }} {{ $classmate->last_name }}@if ($classmate->id === auth()->id())<span class="font-semibold ml-0.5">(you)</span>@endif
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>
