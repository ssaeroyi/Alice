@php
    // One display-name rule everywhere: real name, else username, else a safe
    // word. No surface can ever render a blank person again.
    $displayName = fn ($u) => $u ? (trim($u->first_name.' '.$u->last_name) ?: ($u->nickname ?: 'Classmate')) : 'Teacher';
    // One merged, sorted assignment list per surface: not-done first (by due
    // date, undated last), done sinks to the bottom. Work never disappears —
    // it just stops leading. Status pills live in hub-assignment-row.
    $schoolWide = $assignments->merge($finished)
        ->filter(fn ($a) => $a->scope !== 'group')
        ->values()
        ->sortBy(fn ($a) => [
            ($assignmentCompletion[$a->id] ?? false) ? 1 : 0,
            $a->due_at ? $a->due_at->timestamp : PHP_FLOAT_MAX,
        ])
        ->values();
    $hasAny = $schoolWide->isNotEmpty() || $joinedClasses->isNotEmpty();
@endphp

<div class="space-y-5" data-cw-root
    data-join-url="{{ route('student.classes.join') }}"
    data-leave-url="{{ route('student.classes.leave', ['group' => '__ID__']) }}">

    {{-- Landing view --}}
    <div data-cw-view="classes" class="space-y-5">
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

            <div class="space-y-3">
                <div class="flex items-center justify-between flex-wrap gap-2">
                    <p class="font-pixel text-[9px] font-bold uppercase text-[#2D231E]">Your classes</p>
                    <button
                        type="button"
                        data-modal-open="hub-join-class"
                        class="inline-flex items-center gap-1.5 min-h-[40px] px-4 rounded-xl bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] font-pixel text-[9px] font-bold uppercase shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] hover:bg-amber-300 active:translate-x-[1px] active:translate-y-[1px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] transition-all"
                    >
                        Join class
                    </button>
                </div>

                @if ($joinedClasses->isNotEmpty())
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($joinedClasses as $class)
                            <div class="flex flex-col bg-white border-2 border-[#2D231E] rounded-2xl overflow-hidden shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]">
                                <div class="bg-amber-400 border-b-2 border-[#2D231E] px-4 py-3">
                                    <h3 class="font-pixel text-sm font-bold text-[#2D231E] leading-snug">{{ $class->name }}</h3>
                                    <p class="mt-1 text-[10px] font-bold uppercase tracking-wider text-[#2D231E]/80 truncate">{{ $displayName($class->teacher) }}</p>
                                </div>

                                <div class="px-4 pb-4 pt-4 mt-auto">
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

    {{-- One drill-down view per joined class --}}
    @foreach ($joinedClasses as $class)
        @php
            $classAssignments = $class->active_assignments->merge($class->finished_assignments)
                ->sortBy(fn ($a) => [
                    ($assignmentCompletion[$a->id] ?? false) ? 1 : 0,
                    $a->due_at ? $a->due_at->timestamp : PHP_FLOAT_MAX,
                ])
                ->values();
        @endphp
        <div data-cw-view="class-{{ $class->id }}" hidden class="space-y-4">
            <button
                type="button"
                data-cw-back
                aria-label="Back to classes"
                class="w-11 h-11 shrink-0 bg-white border-2 border-[#2D231E] rounded-xl text-[#2D231E] flex items-center justify-center shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] hover:bg-[#FAF6EE] active:translate-x-[1px] active:translate-y-[1px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] transition-all cursor-pointer"
            >
                <x-icons.arrow-left class="w-4 h-4" />
            </button>

            {{-- Class hero banner --}}
            <div class="bg-amber-400 border-2 border-[#2D231E] rounded-2xl shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] px-5 py-4">
                <h2 class="font-pixel text-lg sm:text-xl font-bold text-[#2D231E] leading-snug">{{ $class->name }}</h2>
                @if ($class->section_label)
                    <p class="mt-0.5 text-xs font-bold uppercase tracking-wider text-[#2D231E]/80">{{ $class->section_label }}</p>
                @endif
                <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs font-bold text-[#2D231E]">
                    <span class="font-extrabold">{{ $displayName($class->teacher) }}</span>
                    <span class="font-extrabold bg-[#2D231E] text-amber-300 px-2 py-0.5 rounded-md">{{ $class->members_count }} {{ $class->members_count === 1 ? 'STUDENT' : 'STUDENTS' }}</span>
                    <span class="font-mono">{{ $class->join_code }}</span>
                </div>
            </div>

            <div class="bg-white border-2 border-[#2D231E] rounded-2xl shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] overflow-hidden">
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
                                About
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

                    <div role="tabpanel" data-cw-panel="class-{{ $class->id }}-details" class="space-y-4">
                        <p class="font-pixel text-[9px] font-bold uppercase tracking-wider text-[#8C7B70]">About this class</p>
                        @if ($class->description)
                            <p class="text-sm font-semibold text-[#2D231E] leading-relaxed whitespace-pre-wrap">{{ $class->description }}</p>
                        @else
                            <p class="text-sm font-semibold text-[#8C7B70] italic">No description yet. Your teacher can add one from the teacher portal.</p>
                        @endif
                    </div>

                    <div role="tabpanel" data-cw-panel="class-{{ $class->id }}-assignments" hidden>
                        @if ($classAssignments->isNotEmpty())
                            <div class="space-y-2">
                                @foreach ($classAssignments as $assignment)
                                    @include('partials.hub-assignment-row', ['assignment' => $assignment])
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm font-semibold text-[#2D231E] text-center py-4">No assignments yet.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    {{-- Per-class people drawers: rendered at root level so an open drawer never hides with its view --}}
    @foreach ($joinedClasses as $class)
        @php
            $mates = $class->members
                ->map(fn ($m) => ['user' => $m, 'name' => $displayName($m)])
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values();
        @endphp
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

                <div class="flex-1 overflow-y-auto px-5 py-5 space-y-5">
                    <div>
                        <p class="font-pixel text-[9px] font-bold uppercase text-[#2D231E] mb-2">Teacher</p>
                        <div class="flex items-center gap-3 rounded-2xl border-2 border-[#2D231E] bg-amber-400/40 p-3">
                            @if ($class->teacher)
                                <x-avatar :user="$class->teacher" :size="40" />
                            @else
                                <span class="w-10 h-10 shrink-0 rounded-full bg-white border-2 border-[#2D231E] flex items-center justify-center font-pixel text-xs font-bold text-[#2D231E]">?</span>
                            @endif
                            <span class="min-w-0">
                                <span class="block text-sm font-extrabold text-[#2D231E] truncate">{{ $displayName($class->teacher) }}</span>
                                <span class="inline-flex items-center mt-0.5 px-2 py-0.5 rounded-md bg-amber-100 border border-[#2D231E] font-pixel text-[8px] font-bold uppercase text-[#2D231E]">Teacher</span>
                            </span>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <p class="font-pixel text-[9px] font-bold uppercase text-[#2D231E]">Students</p>
                            <span class="font-pixel text-[8px] font-bold bg-[#2D231E] text-amber-300 px-1.5 py-0.5 rounded-md">{{ $class->members_count }}</span>
                        </div>

                        @if ($class->members_count === 1)
                            <div class="rounded-2xl border-2 border-dashed border-[#E6DCCF] bg-[#FAF6EE] p-4 text-center">
                                <p class="text-xs font-semibold text-[#2D231E]">Just you in this class so far.</p>
                                <p class="mt-0.5 text-[10px] font-semibold text-[#2D231E]">Classmates show up here once they join.</p>
                            </div>
                        @else
                            <div class="space-y-1.5">
                                @foreach ($mates as $mate)
                                    <div class="flex items-center gap-3 px-3 py-2 rounded-xl bg-[#FAF6EE] border-2 border-[#2D231E]/15">
                                        <x-avatar :user="$mate['user']" :size="32" />
                                        <span class="text-sm font-bold text-[#2D231E] truncate">
                                            {{ $mate['name'] }}@if ($mate['user']->id === auth()->id())<span class="font-semibold text-[#8C7B70] ml-1">(you)</span>@endif
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>
