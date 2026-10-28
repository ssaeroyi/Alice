@extends('layouts.teacher')

@section('title', $assignment->title)
@section('eyebrow', 'Assignments')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <a href="{{ $assignment->group_id ? route('teacher.classes.show', $assignment->group_id) : route('teacher.assignments.index') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-[#2D231E] hover:text-sky-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path></svg>
                {{ $assignment->group_id ? 'Back to class' : 'Back to assignments' }}
            </a>
            <div class="flex flex-wrap items-center justify-end gap-2">
                    <span class="text-xs font-bold px-3 py-1.5 rounded-full {{ $assignment->status === 'active' ? 'bg-emerald-100 text-emerald-700' : ($assignment->status === 'cancelled' ? 'bg-rose-100 text-rose-700' : ($assignment->status === 'archived' ? 'bg-stone-200 text-stone-700' : 'bg-amber-100 text-amber-700')) }} capitalize">
                        {{ $assignment->status === 'archived' ? 'Finished' : $assignment->status }}
                    </span>
                    <button type="button" data-copy-share data-share-url="{{ url('/a/'.$assignment->share_code) }}"
                            title="Copy the share link for this class"
                            class="inline-flex items-center gap-1.5 min-h-[28px] text-xs font-mono font-bold px-3 py-1.5 rounded-lg bg-[#FAF6EE] border border-[#E6DCCF] text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"></path>
                        </svg>
                        {{ $assignment->share_code }}
                    </button>
                @if ($assignment->status === 'draft')
                        <form method="POST" action="{{ route('teacher.assignments.publish', $assignment) }}">
                            @csrf
                            <button type="submit" title="Make this assignment visible to students" class="inline-flex items-center justify-center min-h-[28px] text-xs font-bold px-3 py-1.5 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 transition-colors cursor-pointer">Publish</button>
                        </form>
                    @endif
                    @if ($assignment->status === 'active')
                        <form method="POST" action="{{ route('teacher.assignments.archive', $assignment) }}">
                            @csrf
                            <button type="submit" data-confirm-open="archive-assignment-modal" class="inline-flex items-center justify-center min-h-[28px] text-xs font-bold px-3 py-1.5 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 transition-colors cursor-pointer">Finish</button>
                        </form>
                    @endif
                    <div class="relative">
                        <button
                            type="button"
                            data-dropdown-toggle="rollup-actions-menu"
                            aria-label="More actions for {{ $assignment->title }}"
                            aria-haspopup="true"
                            class="min-h-[28px] min-w-[28px] inline-flex items-center justify-center bg-white border border-[#E6DCCF] rounded-lg text-sm font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
                        >⋯</button>
                        <div id="rollup-actions-menu" data-dropdown class="hidden absolute right-0 top-full mt-1 z-50 w-48 p-1.5 bg-white border border-[#E6DCCF] rounded-xl shadow-[0_8px_24px_rgba(45,35,30,0.15)]">
                            @if ($assignment->status === 'active' && $assignment->story_id)
                                <form method="POST" action="{{ route('teacher.assignments.notify', $assignment) }}">
                                    @csrf
                                    <button type="submit" data-confirm-open="notify-class-modal" title="Send a friendly reminder to students still working on it" class="w-full flex items-center justify-between gap-2 px-3 py-2 rounded-lg text-[11px] font-bold text-[#2D231E] hover:bg-[#FAF6EE] transition-colors cursor-pointer">
                                        Remind class
                                    </button>
                                </form>
                            @endif
                            <a href="{{ route('teacher.assignments.edit', $assignment) }}" class="flex items-center justify-between gap-2 px-3 py-2 rounded-lg text-[11px] font-bold text-[#2D231E] hover:bg-[#FAF6EE] transition-colors">Edit</a>
                            @if ($assignment->status === 'active')
                                <form method="POST" action="{{ route('teacher.assignments.cancel', $assignment) }}">
                                    @csrf
                                    <button type="submit" data-confirm-open="cancel-assignment-modal" class="w-full flex items-center justify-between gap-2 px-3 py-2 rounded-lg text-[11px] font-bold text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer">
                                        Cancel
                                    </button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('teacher.assignments.destroy', $assignment) }}" data-submit-guard>
                                @csrf
                                @method('DELETE')
                                <button type="submit" data-confirm-open="delete-assignment-modal" class="w-full flex items-center justify-between gap-2 px-3 py-2 rounded-lg text-[11px] font-bold text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer">
                                    <span data-submit-idle>Delete</span>
                                    <span data-submit-active class="hidden"><x-spinner label="Deleting..." /></span>
                                </button>
                            </form>
                        </div>
                    </div>
            </div>
        </div>

        {{-- Quick facts --}}
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="bg-white border border-[#E6DCCF] rounded-xl p-4">
                <p class="flex items-center gap-1.5 text-xs font-bold text-[#2D231E] uppercase tracking-wide">Story
                    <x-admin.info>Shows the assigned story and quiz link. Make a copy to edit without touching the original.</x-admin.info>
                </p>
                <p class="mt-1 text-sm font-extrabold text-[#2D231E]">{{ $assignment->story?->title ?? '—' }}</p>
                <p class="mt-0.5 text-xs font-semibold text-[#2D231E]">{{ $hasQuiz ? 'Includes a quiz' : 'No quiz attached' }}</p>
                @if ($assignment->story)
                    <div class="mt-2 flex items-center gap-2">
                        <a href="{{ route('teacher.stories.edit', $assignment->story) }}" class="text-xs font-bold text-sky-700 hover:underline">
                            View story
                        </a>
                        <form method="POST" action="{{ route('teacher.stories.duplicate', $assignment->story) }}" class="inline">
                            @csrf
                            <button type="submit" class="text-xs font-bold text-sky-700 hover:underline">Make a copy</button>
                        </form>
                    </div>
                @endif
            </div>
            <div class="bg-white border border-[#E6DCCF] rounded-xl p-4">
                <p class="flex items-center gap-1.5 text-xs font-bold text-[#2D231E] uppercase tracking-wide">Due date
                    <x-admin.info>Shows when the assignment is due and when it was assigned.</x-admin.info>
                </p>
                @php
                    $dueClass = 'text-[#2D231E]';
                    if ($assignment->status === 'active' && $assignment->due_at) {
                        if ($assignment->isOverdue()) {
                            $dueClass = 'text-rose-600';
                        } elseif ($assignment->due_at->diffInDays(now()) <= 3) {
                            $dueClass = 'text-amber-700';
                        }
                    }
                @endphp
                <p class="mt-1 text-sm font-extrabold {{ $dueClass }}">
                    {{ $assignment->due_at?->format('M j, Y') ?? 'No due date' }}
                </p>
                <p class="mt-0.5 text-xs font-semibold text-[#2D231E]">Assigned {{ optional($assignment->gateStartsAt())->format('M j, Y') }}</p>
            </div>
            <div class="bg-white border border-[#E6DCCF] rounded-xl p-4">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="flex items-center gap-1.5 text-xs font-bold text-[#2D231E] uppercase tracking-wide">Compliance
                            <x-admin.info>Shows how many students finished. Done means every student read it and took the quiz.</x-admin.info>
                        </p>
                        @php
                            $rc = $assignment->compliancePercent();
                            $ringColor = $rc >= 70 ? '#34D399' : ($rc >= 40 ? '#FBBF24' : '#FB7185');
                            $ringCirc = 2 * pi() * 14;
                        @endphp
                        <p class="mt-1 text-sm font-extrabold text-[#2D231E]">
                            {{ $assignment->completedCount() }} / {{ $assignment->audienceCount() }} complete
                        </p>
                    </div>
                    <svg class="w-9 h-9 shrink-0" viewBox="0 0 36 36" aria-hidden="true">
                        <circle cx="18" cy="18" r="14" fill="none" stroke="#F3ECE0" stroke-width="4" />
                        <circle cx="18" cy="18" r="14" fill="none" stroke="{{ $ringColor }}" stroke-width="4" stroke-linecap="round" stroke-dasharray="{{ $ringCirc }}" stroke-dashoffset="{{ $ringCirc * (1 - $rc / 100) }}" transform="rotate(-90 18 18)" />
                        <text x="18" y="18" text-anchor="middle" dominant-baseline="central" font-size="9" font-weight="800" fill="#2D231E">{{ $rc }}%</text>
                    </svg>
                </div>
            </div>
        </div>

        @if ($assignment->status === 'archived')
            <div class="bg-emerald-600 text-white rounded-xl p-4">
                <p class="text-sm font-bold">
                    This assignment is finished.
                    @if ($assignment->finished_at)
                        Archived on {{ $assignment->finished_at->format('M j, Y') }}.
                    @endif
                    Students can still see it under "Past assignments" on their hub.
                </p>
            </div>
        @endif

        {{-- Work panel --}}
        <div data-rollup-tabs>
            @foreach ([['students', 'Students']] as [$key, $label])
                <button type="button" data-rollup-tab="{{ $key }}" data-active="{{ $key === 'students' ? 'true' : 'false' }}"
                    class="px-3 py-1.5 rounded-lg text-sm font-bold transition-colors {{ $key === 'students' ? 'bg-emerald-600 text-white' : 'text-[#2D231E] hover:bg-[#F3ECE0]' }}">{{ $label }}</button>
            @endforeach

            {{-- Students panel --}}
            <div data-rollup-panel="students" class="pt-4 hidden md:block">
                @if ($assignment->instructions)
                    <div class="bg-white border border-[#E6DCCF] rounded-xl px-4 py-3 mb-3">
                        <p class="text-[10px] font-bold text-[#2D231E] uppercase tracking-wide">Instructions</p>
                        <p class="text-sm font-semibold text-[#2D231E] whitespace-pre-wrap mt-1">{{ $assignment->instructions }}</p>
                    </div>
                @endif
                @if ($assignment->status === 'draft')
                    <div class="px-5 py-6 text-center bg-white border border-[#E6DCCF] rounded-xl">
                        <p class="text-sm font-semibold text-[#2D231E]">This assignment is still a draft. Publish it to make it visible to students.</p>
                    </div>
                @else
                    <div class="flex flex-wrap items-center gap-2 mb-3">
                        <form method="GET" action="{{ route('teacher.assignments.show', $assignment) }}" data-auto-filter class="flex-1 min-w-[180px] relative">
                            <input
                                type="search"
                                name="q"
                                value="{{ $keyword }}"
                                placeholder="Search students"
                                aria-label="Search students"
                                class="w-full min-h-[40px] pl-9 pr-3 py-2 bg-white border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] placeholder:text-[#B5A89C] focus:outline-none focus:border-sky-500"
                            >
                            <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-[#B5A89C]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <circle cx="11" cy="11" r="7"></circle>
                                <path stroke-linecap="round" d="M21 21l-4.35-4.35"></path>
                            </svg>
                        </form>
                        <a href="{{ route('teacher.assignments.export', array_merge(['assignment' => $assignment], $keyword ? ['q' => $keyword] : [])) }}" class="inline-flex items-center gap-1.5 min-h-[40px] px-3 py-2 bg-emerald-600 rounded-lg text-xs font-bold text-white hover:bg-emerald-700 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16"></path>
                            </svg>
                            Download CSV
                        </a>
                    </div>
                    <div class="bg-white border border-[#E6DCCF] rounded-xl overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-xs font-bold text-[#2D231E] border-b border-[#E6DCCF]">
                                        <th class="px-5 py-2.5 flex items-center gap-1.5">Student
                                            <x-admin.info>Shows each student's quiz score and completion. Open a student for full attempt history.</x-admin.info>
                                        </th>
                                        @if ($hasQuiz)
                                            <th class="px-5 py-2.5">Quiz</th>
                                        @endif
                                        <th class="px-5 py-2.5">Status</th>
                                        <th class="px-5 py-2.5">Completed</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($audience as $student)
                                        @php
                                            $progress = $completion[$student->id] ?? ['read' => false, 'quiz' => false, 'completed' => false];
                                            $marked = $markedIds->contains($student->id);
                                            $summary = $quizSummaries->get($student->id);
                                            $drawerRow = $drawer->get($student->id);
                                            $completedAt = $marked && ! $progress['completed']
                                                ? $drawerRow['mark']?->created_at
                                                : $drawerRow['progress']?->completed_at;
                                            $rowState = $progress['completed'] ? 'done' : ($marked ? 'done' : (($progress['read'] || $progress['quiz']) ? 'in_progress' : 'not_started'));
                                            $rowMeta = \App\Models\Assignment::statusMeta($rowState);
                                        @endphp
                                        <tr class="border-b border-[#E6DCCF]/60 last:border-0">
                                            <td class="px-5 py-2.5">
                                                <div class="flex items-center gap-3 min-w-0">
                                                    <x-avatar :user="$student" :alt="$student->nickname ?? strtok($student->email, '@')" :size="36" />
                                                    <div class="min-w-0">
                                                        <button type="button" data-story-drawer-open="{{ $student->id }}" class="block w-full text-left font-bold text-[#2D231E] hover:text-sky-700 truncate cursor-pointer">{{ trim($student->first_name.' '.$student->last_name) ?: ($student->nickname ?? strtok($student->email, '@')) }}</button>
                                                        <p class="text-[11px] font-semibold text-[#2D231E] truncate">{{ $student->email }}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            @if ($hasQuiz)
                                                <td class="px-5 py-2.5 whitespace-nowrap">
                                                    @if ($summary['attempted'])
                                                        <span title="{{ $summary['raw'] ? $summary['raw'].' correct' : '' }}" class="inline-flex items-center min-w-[52px] justify-center rounded-full px-2.5 py-1 text-xs font-extrabold tabular-nums {{ $summary['passed'] ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-600' }}">
                                                            {{ $summary['score'] }}%
                                                        </span>
                                                    @else
                                                        <span class="text-xs font-semibold text-[#c9bfb4]">Not taken</span>
                                                    @endif
                                                </td>
                                            @endif
                                            <td class="px-5 py-2.5 whitespace-nowrap">
                                                @if ($progress['completed'])
                                                    <span class="inline-flex items-center gap-1 text-xs font-bold px-2 py-1 rounded-full bg-emerald-100 text-emerald-700">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                                                        Done
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center gap-1 text-xs font-bold px-2 py-1 rounded-full {{ $rowMeta['classes'] }}">{{ $rowMeta['label'] }}</span>
                                                @endif
                                            </td>
                                            <td class="px-5 py-2.5 whitespace-nowrap text-xs font-semibold text-[#2D231E]">
                                                {{ $completedAt?->format('M j, g:i A') ?? '—' }}
                                            </td>
                                            <template data-story-drawer-template="{{ $student->id }}">
                                                <div class="flex items-start justify-between gap-4 p-5 border-b border-[#E6DCCF]">
                                                    <div class="min-w-0">
                                                        <p class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E]">Student</p>
                                                        <h3 class="text-lg font-extrabold text-[#2D231E] mt-1 break-words">{{ $student->nickname ?? strtok($student->email, '@') }}</h3>
                                                        <p class="text-xs font-semibold text-[#2D231E] mt-0.5">{{ $student->email }}</p>
                                                    </div>
                                                    <button type="button" data-story-drawer-close aria-label="Close" class="shrink-0 min-h-[44px] min-w-[44px] inline-flex items-center justify-center text-lg leading-none font-bold text-[#2D231E] transition-colors cursor-pointer">&times;</button>
                                                </div>
                                                <div class="p-5 space-y-5">
                                                    @php $d = $drawer->get($student->id); @endphp
                                                    {{-- Context strip --}}
                                                    @php $rp = $student->readingProgress ?? null; @endphp
                                                    @if ($student->last_login_at || $student->reading_level || $rp)
                                                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-3 text-xs">
                                                            @if ($student->reading_level)
                                                                <div>
                                                                        <dt class="text-[10px] font-extrabold uppercase tracking-wider text-[#2D231E]">Reading grade</dt>
                                                                        <dd class="font-bold text-[#2D231E] mt-0.5">Grade {{ $student->reading_level }}</dd>
                                                                </div>
                                                            @endif
                                                            @if ($rp && $rp->comprehension_level)
                                                                <div>
                                                                    <dt class="text-[10px] font-extrabold uppercase tracking-wider text-[#2D231E]">Comprehension</dt>
                                                                    <dd class="mt-0.5">
                                                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-extrabold {{ match ($rp->comprehension_level) {
                                                                            \App\Enums\ComprehensionLevel::Independent => 'bg-emerald-100 text-emerald-700',
                                                                            \App\Enums\ComprehensionLevel::Instructional => 'bg-amber-100 text-amber-700',
                                                                            \App\Enums\ComprehensionLevel::Frustration => 'bg-orange-100 text-orange-700',
                                                                            \App\Enums\ComprehensionLevel::Critical => 'bg-rose-100 text-rose-700',
                                                                        } }}">{{ $rp->comprehension_level->label() }}</span>
                                                                        @if ($rp->slipping)
                                                                            <span class="ml-1 text-rose-600 font-bold" title="Comprehension slipping">▼ slipping</span>
                                                                        @endif
                                                                    </dd>
                                                                </div>
                                                            @endif
                                                            @if ($student->last_login_at)
                                                                <div>
                                                                    <dt class="text-[10px] font-extrabold uppercase tracking-wider text-[#2D231E]">Last login</dt>
                                                                    <dd class="font-bold text-[#2D231E] mt-0.5">{{ $student->last_login_at->diffForHumans() }}</dd>
                                                                </div>
                                                            @endif
                                                        </dl>
                                                    @endif

                                                    {{-- Reading detail --}}
                                                    @php $sp = $d['progress']; @endphp
                                                    <div>
                                                        <p class="text-[10px] font-extrabold uppercase tracking-wider text-[#2D231E] mb-2">Reading</p>
                                                        @if ($sp)
                                                            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-3 text-xs">
                                                                <div>
                                                                    <dt class="text-[10px] font-extrabold uppercase tracking-wider text-[#2D231E]">Status</dt>
                                                                    <dd class="mt-0.5">
                                                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-extrabold {{ $sp->completed_at ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">{{ $sp->status->label() }}</span>
                                                                    </dd>
                                                                </div>
                                                                @if ($sp->best_score_pct !== null)
                                                                    <div>
                                                                        <dt class="text-[10px] font-extrabold uppercase tracking-wider text-[#2D231E]">Best score</dt>
                                                                        <dd class="font-bold text-[#2D231E] mt-0.5">{{ $sp->best_score_pct }}%</dd>
                                                                    </div>
                                                                @endif
                                                                @if ($sp->time_spent_seconds > 0)
                                                                    <div>
                                                                        <dt class="text-[10px] font-extrabold uppercase tracking-wider text-[#2D231E]">Time spent</dt>
                                                                        <dd class="font-bold text-[#2D231E] mt-0.5">{{ (int) floor($sp->time_spent_seconds / 60) }}m {{ $sp->time_spent_seconds % 60 }}s</dd>
                                                                    </div>
                                                                @endif
                                                                @if ($sp->completed_at)
                                                                    <div>
                                                                        <dt class="text-[10px] font-extrabold uppercase tracking-wider text-[#2D231E]">Completed</dt>
                                                                        <dd class="font-bold text-[#2D231E] mt-0.5">{{ $sp->completed_at->format('M j, g:i A') }}</dd>
                                                                    </div>
                                                                @endif
                                                            </dl>
                                                        @else
                                                            <p class="text-xs font-semibold text-[#2D231E]">Not started.</p>
                                                        @endif
                                                    </div>

                                                    {{-- Status / mark trail --}}
                                                    <div>
                                                        <p class="text-[10px] font-extrabold uppercase tracking-wider text-[#2D231E] mb-2">Status</p>
                                                        @php $drawerMark = $d['mark']; @endphp
                                                        @if ($progress['completed'])
                                                            <p class="text-xs font-bold text-emerald-700">Done</p>
                                                            @if ($drawerMark)
                                                                <p class="text-[11px] font-semibold text-[#2D231E] mt-0.5">Marked by {{ $drawerMark->teacher?->nickname ?? 'teacher' }} on {{ $drawerMark->created_at->format('M j, g:i A') }}</p>
                                                            @elseif ($progress['read'] || $progress['quiz'])
                                                                <p class="text-[11px] font-semibold text-[#2D231E] mt-0.5">Evidence-based completion.</p>
                                                            @endif
                                                        @elseif ($drawerMark)
                                                            <p class="text-xs font-bold text-emerald-700">Done (teacher)</p>
                                                            <p class="text-[11px] font-semibold text-[#2D231E] mt-0.5">Marked by {{ $drawerMark->teacher?->nickname ?? 'teacher' }} on {{ $drawerMark->created_at->format('M j, g:i A') }}</p>
                                                        @elseif ($progress['read'] || $progress['quiz'])
                                                            <p class="text-xs font-bold text-amber-700">In progress</p>
                                                        @else
                                                            <p class="text-xs font-semibold text-[#2D231E]">Not started.</p>
                                                        @endif
                                                    </div>

                                                    {{-- Quiz history --}}
                                                    <div>
                                                        <p class="text-[10px] font-extrabold uppercase tracking-wider text-[#2D231E] mb-2">Quiz history</p>
                                                        @forelse ($d['attempts'] as $row)
                                                            <div class="rounded-xl bg-[#FAF6EE] border border-[#E6DCCF] px-4 py-3 mb-2 last:mb-0">
                                                                <div class="flex flex-wrap items-center gap-2">
                                                                    <span class="inline-flex items-center min-w-[52px] justify-center rounded-full px-2.5 py-1 text-xs font-extrabold tabular-nums {{ $row['passed'] ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-600' }}">{{ $row['score'] }}%</span>
                                                                    <span class="text-[11px] font-semibold text-[#2D231E]">{{ $row['at']->diffForHumans() }} ({{ $row['at']->format('M j, g:i A') }})</span>
                                                                </div>
                                                                <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] font-semibold text-[#2D231E]">
                                                                    <span>{{ $row['correct'] }} / {{ $row['total'] }} correct</span>
                                                                    @if ($row['time'] > 0)
                                                                        <span>{{ (int) floor($row['time'] / 60) }}m {{ $row['time'] % 60 }}s</span>
                                                                    @endif
                                                                    @if ($row['hints'] > 0)
                                                                        <span>{{ $row['hints'] }} hint{{ $row['hints'] === 1 ? '' : 's' }}</span>
                                                                    @endif
                                                                    @if ($row['xp'] > 0)
                                                                        <span>+{{ $row['xp'] }} XP</span>
                                                                    @endif
                                                                </div>
                                                                @if ($row['notes'] !== [])
                                                                    <ul class="mt-1.5 space-y-1">
                                                                        @foreach ($row['notes'] as $note)
                                                                            <li class="flex items-start gap-2 text-[11px] font-semibold text-[#2D231E]">
                                                                                <span class="shrink-0 mt-1 w-1.5 h-1.5 rounded-full {{ $note['tone'] === 'hint' ? 'bg-amber-500' : 'bg-sky-500' }}"></span>
                                                                                {{ $note['text'] }}
                                                                            </li>
                                                                        @endforeach
                                                                    </ul>
                                                                @endif
                                                            </div>
                                                        @empty
                                                            <p class="text-xs font-semibold text-[#2D231E]">No attempts yet.</p>
                                                        @endforelse
                                                    </div>

                                                    {{-- Mark done / undo form --}}
                                                    @if ($assignment->status === 'active')
                                                        @if (! $progress['completed'] && ! $marked)
                                                            <form method="POST" action="{{ route('teacher.assignments.mark', $assignment) }}" class="pt-2 border-t border-[#F0E9DD]">
                                                                @csrf
                                                                <input type="hidden" name="student_id" value="{{ $student->id }}">
                                                                <button type="submit" class="inline-flex items-center justify-center min-h-[44px] px-4 py-2 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700 transition-colors cursor-pointer">Mark done</button>
                                                            </form>
                                                        @elseif ($marked && ! $progress['completed'])
                                                            <form method="POST" action="{{ route('teacher.assignments.mark-remove', [$assignment, $student]) }}" class="pt-2 border-t border-[#F0E9DD]">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="inline-flex items-center justify-center min-h-[44px] px-4 py-2 bg-rose-600 text-white rounded-lg text-xs font-bold hover:bg-rose-700 transition-colors cursor-pointer">Undo</button>
                                                            </form>
                                                        @endif
                                                    @endif

                                                    {{-- Open full profile link --}}
                                                    <div class="pt-2 border-t border-[#F0E9DD]">
                                                        <a href="{{ route('teacher.student', ['user' => $student, 'from' => 'assignment:'.$assignment->id]) }}" class="inline-flex items-center justify-center min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors">Open full profile</a>
                                                    </div>
                                                </div>
                                            </template>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="{{ $hasQuiz ? 4 : 3 }}" class="px-5 py-6 text-center text-sm font-semibold text-[#2D231E]">No {{ $keyword ? 'students match your search' : 'students match this audience' }} yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Students, mobile card list: same data, no sideways table --}}
            <div data-rollup-panel="students" class="pt-4 md:hidden">
                <div class="bg-white border border-[#E6DCCF] rounded-xl p-3">
                    @forelse ($audience as $student)
                        @php
                            $progress = $completion[$student->id] ?? ['read' => false, 'quiz' => false, 'completed' => false];
                            $marked = $markedIds->contains($student->id);
                            $summary = $quizSummaries->get($student->id);
                            $drawerRow = $drawer->get($student->id);
                            $rowState = ($progress['completed'] || $marked) ? 'done' : (($progress['read'] || $progress['quiz']) ? 'in_progress' : 'not_started');
                            $rowMeta = \App\Models\Assignment::statusMeta($rowState);
                        @endphp
                        <a href="{{ route('teacher.student', ['user' => $student, 'from' => 'assignment:'.$assignment->id]) }}" class="block py-2.5 border-b border-[#F0E9DD] last:border-b-0">
                            <div class="flex items-center gap-3 min-w-0">
                                <x-avatar :user="$student" :alt="$student->nickname ?? strtok($student->email, '@')" :size="36" />
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-bold text-[#2D231E] truncate">{{ trim($student->first_name.' '.$student->last_name) ?: ($student->nickname ?? strtok($student->email, '@')) }}</p>
                                    <p class="text-[11px] font-semibold text-[#2D231E] truncate">{{ $student->email }}</p>
                                </div>
                                <span class="shrink-0 inline-flex items-center gap-1 text-[11px] font-bold px-2 py-1 rounded-full {{ $rowMeta['classes'] }}">{{ $rowMeta['label'] }}</span>
                            </div>
                            <p class="mt-1.5 text-[11px] font-semibold text-[#8C7B70]">
                                @if ($hasQuiz)
                                    {{ $summary['attempted'] ? 'Quiz '.$summary['score'].'%' : 'Quiz not taken' }}
                                @else
                                    No quiz
                                @endif
                            </p>
                        </a>
                    @empty
                        <p class="text-sm font-semibold text-[#2D231E] py-8 text-center">No students in this audience yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Students panel desktop wraps up here. The drawer/mobile panels above
         are the work panel's own tails; everything below renders app-wide. --}}
    <div data-story-drawer class="fixed inset-0 z-40 pointer-events-none">
        <div data-story-drawer-backdrop class="absolute inset-0 bg-[#2D231E]/40 opacity-0 transition-opacity duration-300 pointer-events-none"></div>
        <div
            data-story-drawer-panel
            role="dialog"
            aria-modal="true"
            aria-label="Student details"
            class="absolute inset-y-0 right-0 w-full max-w-md bg-[#FFFDF9] shadow-2xl transform translate-x-full transition-transform duration-300 pointer-events-auto overflow-y-auto"
        >
            <div data-story-drawer-body></div>
        </div>
    </div>

    <x-modal id="delete-assignment-modal" title="Delete this assignment?">
        <p class="text-xs font-semibold text-[#2D231E]">
            Students lose access to this assignment and the results disappear from your records. This action is
            recorded in the audit log.
        </p>
        <div class="flex items-center justify-end gap-2">
            <button type="button" data-modal-close class="min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer">Cancel</button>
            <button type="button" data-confirm-approve class="inline-flex items-center justify-center min-h-[44px] px-5 py-2 bg-rose-600 text-white rounded-lg text-xs font-bold hover:bg-rose-700 transition-colors cursor-pointer">Delete assignment</button>
        </div>
    </x-modal>

    <x-modal id="notify-class-modal" title="Remind the class?">
        <p class="text-xs font-semibold text-[#2D231E]">
            Students still working on this assignment get a friendly inbox reminder. Students who are already done
            stay undisturbed, and reminding again too soon is skipped automatically.
        </p>
        <div class="flex items-center justify-end gap-2">
            <button type="button" data-modal-close class="min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer">Cancel</button>
            <button type="button" data-confirm-approve class="inline-flex items-center justify-center min-h-[44px] px-5 py-2 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700 transition-colors cursor-pointer">Send reminder</button>
        </div>
    </x-modal>

    <x-modal id="cancel-assignment-modal" title="Cancel this assignment?">
        <p class="text-xs font-semibold text-[#2D231E]">
            Use this if the assignment should not have gone out. Students lose access and it stops being counted.
            Unlike finishing, which wraps up completed work, cancelling ends it for everyone.
        </p>
        <div class="flex items-center justify-end gap-2">
            <button type="button" data-modal-close class="min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer">Keep assignment</button>
            <button type="button" data-confirm-approve class="inline-flex items-center justify-center min-h-[44px] px-5 py-2 bg-rose-600 text-white rounded-lg text-xs font-bold hover:bg-rose-700 transition-colors cursor-pointer">Cancel assignment</button>
        </div>
    </x-modal>

    <x-modal id="archive-assignment-modal" title="Finish this assignment?">
        <p class="text-xs font-semibold text-[#2D231E]">
            This wraps up a finished period of work. Students keep their history under "Past assignments" and you
            can still open it to review results. This action is recorded in the audit log.
        </p>
        <div class="flex items-center justify-end gap-2">
            <button type="button" data-modal-close class="min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer">Cancel</button>
            <button type="button" data-confirm-approve class="inline-flex items-center justify-center min-h-[44px] px-5 py-2 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700 transition-colors cursor-pointer">Finish assignment</button>
        </div>
    </x-modal>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const copyBtn = document.querySelector('[data-copy-share]');
            if (copyBtn) {
                copyBtn.addEventListener('click', function () {
                    const url = this.dataset.shareUrl;
                    navigator.clipboard.writeText(url).then(function () {
                        copyBtn.textContent = 'Copied!';
                        setTimeout(function () { copyBtn.textContent = '/a/{{ $assignment->share_code }}'; }, 1500);
                    });
                });
            }

            const root = document.querySelector('[data-rollup-tabs]');
            if (!root) return;
            const tabs = root.querySelectorAll('[data-rollup-tab]');
            const panels = root.querySelectorAll('[data-rollup-panel]');
            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    if (tab.hasAttribute('tabindex') && tab.getAttribute('tabindex') === '-1') return;
                    const key = tab.dataset.rollupTab;
                    tabs.forEach(function (t) {
                        const active = t === tab;
                        t.setAttribute('data-active', active ? 'true' : 'false');
                        const cls = active ? 'bg-emerald-600 text-white' : 'text-[#2D231E] hover:bg-[#F3ECE0]';
                        t.className = 'px-3 py-1.5 rounded-lg text-sm font-bold transition-colors ' + cls;
                    });
                    panels.forEach(function (p) {
                        p.hidden = p.dataset.rollupPanel !== key;
                    });
                });
            });
        });
    </script>
@endpush
