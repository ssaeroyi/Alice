@extends('layouts.teacher')

@section('page', 'teacher-student')

@php
    $fullName = trim($student->first_name.' '.$student->last_name);
    $displayName = $fullName ?: ($student->nickname ?? strtok($student->email, '@'));
    $backUrl = $fromAssignment
        ? route('teacher.assignments.show', $fromAssignment)
        : route('teacher.roster', request()->query());
@endphp

@section('title', $displayName)
@section('eyebrow', 'Students')

@section('content')
    <div class="space-y-5">

        <a href="{{ $backUrl }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-[#2D231E] hover:text-sky-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path></svg>
            {{ $fromAssignment ? 'Back to assignment' : 'Back to students' }}
        </a>

        <!-- HEADER -->
        <div class="bg-white border border-[#E6DCCF] rounded-xl p-5 sm:p-6">
            <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                <x-avatar :user="$student" :alt="$displayName" :size="56" />
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-base font-extrabold text-[#2D231E] truncate">
                            {{ $displayName }}
                        </h1>
                        @if ($student->nickname && $fullName)
                            <x-admin.pill>{{ $student->nickname }}</x-admin.pill>
                        @endif
                        <x-admin.pill>Student</x-admin.pill>
                        @if ($student->suspended_at)
                            <x-admin.pill tone="crit">Suspended</x-admin.pill>
                        @endif
                    </div>
                    <p class="text-sm font-semibold text-[#2D231E] mt-0.5 truncate">{{ $student->email }}</p>
                    <p class="text-[11px] font-semibold text-[#2D231E] mt-1 flex items-center flex-wrap gap-x-2 gap-y-1">
                        <x-presence-dot :at="$student->lastActiveAt()" />
                        <span>Joined {{ $student->created_at->format('M j, Y') }}.</span>
                    </p>
                </div>
            </div>
        </div>

        <!-- NEEDS ATTENTION -->
        @if ($student->suspended_at || ! $student->is_setup_complete || $student->last_login_at === null || $student->last_login_at?->lt(now()->subDays(14)) || $student->readingProgress?->slipping)
            <x-admin.panel
                title="Needs attention"
                info="Shows computed flags from account status and activity. Suspensions and resets are admin-only."
            >
                <ul class="space-y-2">
                    @if ($student->suspended_at)
                        <li class="flex items-start gap-2">
                            <span class="shrink-0 mt-1.5 w-2 h-2 rounded-full bg-rose-400"></span>
                            <p class="text-xs font-semibold text-[#2D231E]">
                                <span class="font-extrabold text-rose-600">Suspended</span>
                                since {{ $student->suspended_at->format('M j, Y g:i A') }}. The account cannot sign in until an admin reactivates it.
                            </p>
                        </li>
                    @endif
                    @if (! $student->is_setup_complete)
                        <li class="flex items-start gap-2">
                            <span class="shrink-0 mt-1.5 w-2 h-2 rounded-full bg-amber-400"></span>
                            <p class="text-xs font-semibold text-[#2D231E]">
                                <span class="font-extrabold text-amber-600">Setup pending</span>. The placement quick check is not finished, so the learning app stays locked.
                            </p>
                        </li>
                    @endif
                    @if ($student->last_login_at === null || $student->last_login_at?->lt(now()->subDays(14)))
                        <li class="flex items-start gap-2">
                            <span class="shrink-0 mt-1.5 w-2 h-2 rounded-full bg-[#C9BCAE]"></span>
                            <p class="text-xs font-semibold text-[#2D231E]">
                                <span class="font-extrabold text-[#2D231E]">No recent activity</span>. {{ $student->last_login_at?->diffForHumans() ?? 'Has never signed in' }}. A nudge in class usually helps.
                            </p>
                        </li>
                    @endif
                </ul>
            </x-admin.panel>
        @endif

        <!-- ACCOUNT DETAILS -->
        <x-admin.panel title="Account details">
            <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <div>
                    <dt class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E]">First name</dt>
                    <dd class="mt-1 text-sm font-bold text-[#2D231E]">{{ $student->first_name ?: 'Not set' }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E]">Last name</dt>
                    <dd class="mt-1 text-sm font-bold text-[#2D231E]">{{ $student->last_name ?: 'Not set' }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E]">Username</dt>
                    <dd class="mt-1 text-sm font-bold text-[#2D231E]">{{ $student->nickname ?? 'Not set' }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E]">Grade</dt>
                    <dd class="mt-1 text-sm font-bold text-[#2D231E]">
                        {{ $student->grade_level ? 'Grade '.$student->effectiveGrade() : 'Not set' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E]">Placement</dt>
                    <dd class="mt-1">
                        @if ($student->is_setup_complete)
                            <x-admin.pill tone="ok">Complete</x-admin.pill>
                        @else
                            <x-admin.pill>Pending</x-admin.pill>
                        @endif
                    </dd>
                </div>
            </dl>
        </x-admin.panel>

        <!-- GRADE INFO -->
        <x-admin.panel
            title="Grade"
            subtitle="The reported grade advances each school year."
            info="Shows the reported school year grade and the grade in effect now. Holding stops the June advance for one school year."
        >
            <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <dt class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E]">Reported grade</dt>
                    <dd class="mt-1 text-sm font-bold text-[#2D231E]">
                        {{ $student->grade_level?->label() ?? 'Not set' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E]">Anchored in</dt>
                    <dd class="mt-1 text-sm font-bold text-[#2D231E]">
                        {{ $student->grade_anchor_year ? 'School year '.$student->grade_anchor_year : '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E]">Effective grade</dt>
                    <dd class="mt-1 text-sm font-bold text-[#2D231E]">
                        {{ $student->grade_level ? 'Grade '.$student->effectiveGrade() : 'Not set' }}
                    </dd>
                </div>
            </dl>

            @if ($student->grade_level)
                @php $alreadyHeld = $student->grade_hold_school_year === \App\Support\SchoolYear::startYear(); @endphp
                <div class="mt-5 pt-4 border-t border-[#F0E9DD] flex flex-wrap items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E]">Student repeating or retained?</p>
                        <p class="mt-1 text-xs font-semibold text-[#2D231E]">
                            @if ($alreadyHeld)
                                Grade is held for this school year. Release the hold to resume advancement.
                            @else
                                Hold the grade so it stops advancing this school year. Do this again next year if the student repeats again.
                            @endif
                        </p>
                    </div>
                    @if ($alreadyHeld)
                        <form action="{{ route('teacher.students.unhold-grade', $student) }}" method="POST">
                            @csrf
                            <button
                                type="submit"
                                data-confirm-open="unhold-grade-modal"
                                class="inline-flex items-center justify-center min-h-[44px] px-5 py-2 bg-white border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
                            >
                                Release hold
                            </button>
                        </form>
                    @else
                        <form action="{{ route('teacher.students.hold-grade', $student) }}" method="POST">
                            @csrf
                            <button
                                type="submit"
                                data-confirm-open="hold-grade-modal"
                                class="inline-flex items-center justify-center min-h-[44px] px-5 py-2 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700 transition-colors cursor-pointer"
                            >
                                Hold grade
                            </button>
                        </form>
                    @endif
                </div>
            @endif
        </x-admin.panel>

        <!-- PLACEMENT RESULT -->
        <x-admin.panel
            title="Placement result"
            subtitle="The level check that set this student's starting level."
            info="Shows the placement score that set this student's band and reading level. Live quizzes keep adjusting their level from here."
        >
            @if ($student->comprehension_level && $student->reading_level)
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div class="space-y-4">
                        <div>
                            <p class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E]">Comprehension band</p>
                            <div class="mt-1.5 flex items-center gap-2">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold {{ match ($student->comprehension_level) {
                                    \App\Enums\ComprehensionLevel::Independent => 'bg-emerald-100 text-emerald-700',
                                    \App\Enums\ComprehensionLevel::Instructional => 'bg-amber-100 text-amber-700',
                                    \App\Enums\ComprehensionLevel::Frustration => 'bg-orange-100 text-orange-700',
                                    \App\Enums\ComprehensionLevel::Critical => 'bg-rose-100 text-rose-700',
                                } }}">
                                    {{ $student->comprehension_level->label() }}
                                </span>
                            </div>
                            <p class="mt-2 text-xs font-semibold text-[#2D231E]">{{ $student->comprehension_level->description() }}</p>
                        </div>
                        <div>
                            <p class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E]">Reading grade</p>
                            <p class="mt-1.5 text-sm font-bold text-[#2D231E]">Grade {{ $student->reading_level }}</p>
                        </div>
                    </div>
                    <div>
                        <p class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E]">Score</p>
                        <div class="mt-2.5 flex items-center gap-3">
                            <div class="flex-1 h-3 bg-[#F3ECE0] rounded-full overflow-hidden">
                                <div class="h-full {{ $student->comprehension_bar >= 75 ? 'bg-emerald-300' : ($student->comprehension_bar >= 60 ? 'bg-amber-300' : 'bg-rose-300') }} rounded-full" style="width: {{ min(100, max(0, $student->comprehension_bar)) }}%"></div>
                            </div>
                            <span class="shrink-0 text-sm font-extrabold text-[#2D231E] tabular-nums">{{ $student->comprehension_bar }}%</span>
                        </div>
                        @if (isset($student->placementAttempt->score['correct'], $student->placementAttempt->score['total']))
                            <p class="mt-2 text-[11px] font-semibold text-[#2D231E]">
                                {{ (int) $student->placementAttempt->score['correct'] }} of {{ (int) $student->placementAttempt->score['total'] }} items
                            </p>
                        @endif
                        @if ($student->placementAttempt?->completed_at)
                            <p class="mt-2 text-[11px] font-semibold text-[#2D231E]">
                                Completed {{ $student->placementAttempt->completed_at->format('M j, Y g:i A') }}
                            </p>
                        @endif
                    </div>
                </div>
            @else
                <p class="text-sm font-semibold text-[#2D231E]">
                    No result yet. This student has not finished the placement quick check.
                </p>
            @endif
        </x-admin.panel>

        <!-- READING PROGRESS -->
        @php $rp = $student->readingProgress; @endphp
        <x-admin.panel
            title="Reading progress"
            subtitle="The live, evidence-based level, where this student is now rather than just where they started."
            info="Shows the live level from recent reading and quizzes, not the placement snapshot."
        >
            @if ($rp)
                @if ($rp->slipping)
                    <div class="mb-4 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3">
                        <p class="text-xs font-extrabold text-rose-700">Comprehension slipping</p>
                        <p class="mt-1 text-[11px] font-semibold text-rose-600">
                            This student is failing a recent string of quizzes. Reassess and serve easier short stories, or schedule a fresh placement check. (Repeat low-scoring attempts already earn little to no XP.)
                        </p>
                    </div>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                    <div>
                        <p class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E]">Current level</p>
                        <div class="mt-1.5 flex items-center gap-2">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold {{ match ($rp->comprehension_level ?? $student->comprehension_level) {
                                \App\Enums\ComprehensionLevel::Independent => 'bg-emerald-100 text-emerald-700',
                                \App\Enums\ComprehensionLevel::Instructional => 'bg-amber-100 text-amber-700',
                                \App\Enums\ComprehensionLevel::Frustration => 'bg-orange-100 text-orange-700',
                                \App\Enums\ComprehensionLevel::Critical => 'bg-rose-100 text-rose-700',
                            } }}">
                                {{ ($rp->comprehension_level ?? $student->comprehension_level)->label() }}
                            </span>
                            @if ($rp->trend === 'up')
                                <span class="text-emerald-600 font-bold" title="Moving up since last check">▲</span>
                            @elseif ($rp->trend === 'down')
                                <span class="text-rose-500 font-bold" title="Moving down since last check">▼</span>
                            @elseif ($rp->trend === 'steady')
                                <span class="text-sky-600 font-bold" title="Steady since last check">•</span>
                            @endif
                        </div>
                        <p class="mt-1.5 text-[11px] font-semibold text-[#2D231E]">
                            {{ \App\Services\ReadingProgressService::basisFor($rp) }}
                        </p>
                    </div>

                    <div>
                        <p class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E]">Serving grade</p>
                        <p class="mt-1.5 text-sm font-bold text-[#2D231E]">
                            Grade {{ $rp->reading_grade ?? ($student->reading_level ?: '—') }}
                        </p>
                        <p class="mt-1.5 text-[11px] font-semibold text-[#2D231E]">
                            The stories recommended to this student.
                        </p>
                    </div>

                    <div>
                        <p class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E]">Live score</p>
                        <div class="mt-2.5 flex items-center gap-3">
                            <div class="flex-1 h-3 bg-[#F3ECE0] rounded-full overflow-hidden">
                                <div class="h-full {{ ($rp->teacher_score ?? 0) >= 75 ? 'bg-emerald-300' : (($rp->teacher_score ?? 0) >= 60 ? 'bg-amber-300' : 'bg-rose-300') }} rounded-full" style="width: {{ min(100, max(0, $rp->teacher_score ?? 0)) }}%"></div>
                            </div>
                            <span class="shrink-0 text-sm font-extrabold text-[#2D231E] tabular-nums">{{ $rp->teacher_score ?? '—' }}%</span>
                        </div>
                    </div>
                </div>
            @else
                <p class="text-sm font-semibold text-[#2D231E]">
                    No reading progress recorded yet. It fills in after this student reads and quizzes.
                </p>
            @endif
        </x-admin.panel>

        <!-- RECENT ACTIVITY -->
        <x-admin.panel
            title="Recent activity"
            subtitle="What this student has been doing in the app."
            info="Shows page visits from the same telemetry as admin Traffic, grouped per day."
        >
            @if ($recentActivity->isEmpty())
                <p class="text-sm font-semibold text-[#2D231E]">No activity recorded yet. It fills in as this student uses the app.</p>
            @else
                <ul class="divide-y divide-[#F0E9DD]">
                    @foreach ($recentActivity as $index => $item)
                        <li class="py-2.5 flex items-center justify-between gap-4 {{ $index >= 8 ? 'hidden js-activity-extra' : '' }}">
                            <p class="text-xs font-bold text-[#2D231E]">
                                {{ $item['label'] }}
                                @if ($item['count'] > 1)
                                    <span class="text-[11px] font-semibold text-[#2D231E]">({{ $item['count'] }} times)</span>
                                @endif
                            </p>
                            <span class="shrink-0 text-[11px] font-semibold text-[#2D231E]">
                                {{ $item['at']->format('M j, g:i A') }}
                            </span>
                        </li>
                    @endforeach
                </ul>
                @if ($recentActivity->count() > 8)
                    <button type="button" onclick="document.querySelectorAll('.js-activity-extra').forEach(r => r.classList.toggle('hidden')); this.textContent = this.textContent === 'Show earlier activity' ? 'Show less' : 'Show earlier activity';"
                            class="mt-3 text-xs font-bold text-sky-700 hover:text-sky-800 transition-colors">
                        Show earlier activity
                    </button>
                @endif
            @endif
        </x-admin.panel>

        <!-- QUIZ PERFORMANCE (Chart.js) -->
        @if ($quizHistory->isNotEmpty())
            <x-admin.panel
                title="Quiz scores"
                subtitle="Performance across all quizzes. Flag markers indicate integrity observations."
            >
                <canvas
                    data-quiz-history-canvas
                    data-history="{{ json_encode($quizHistory, JSON_THROW_ON_ERROR | JSON_HEX_APOS | JSON_HEX_QUOT) }}"
                    class="w-full h-52"
                ></canvas>
            </x-admin.panel>
        @endif

        <!-- INTEGRITY OBSERVATIONS (teacher-only) -->
        @if ($flaggedAttempts->isNotEmpty())
            <x-admin.panel
                title="Quiz observations"
                subtitle="Objective behavioral signals from recent attempts."
                info="Shows behavioral signals from recent attempts. Observations guide a conversation; scores never change on their own."
            >
                <div class="space-y-4">
                    @foreach ($flaggedAttempts as $row)
                        <div class="bg-[#FAF6EE] border border-[#E6DCCF] rounded-xl p-4">
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <span class="text-xs font-extrabold text-[#2D231E]">{{ $row['story'] }}</span>
                                <span class="text-xs font-bold {{ $row['passed'] ? 'text-emerald-700 bg-emerald-100' : 'text-rose-700 bg-rose-100' }} px-2 py-0.5 rounded-full">
                                    {{ $row['score'] }}%
                                </span>
                                <span class="text-[11px] font-semibold text-[#2D231E]">{{ $row['at']->diffForHumans() }}</span>
                            </div>
                            <ul class="space-y-1">
                                @foreach ($row['notes'] as $note)
                                    <li class="flex items-start gap-2 text-xs font-semibold text-[#2D231E]">
                                        <span class="shrink-0 mt-1 w-1.5 h-1.5 rounded-full {{ $note['tone'] === 'hint' ? 'bg-amber-500' : 'bg-sky-500' }}"></span>
                                        {{ $note['text'] }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </x-admin.panel>
        @endif

    </div>

    <x-modal id="hold-grade-modal" title="Hold this grade for the school year?">
        <div class="space-y-4">
            <p class="text-sm font-semibold text-[#2D231E] leading-relaxed">
                {{ $student->nickname ?? 'This student' }} reported Grade {{ $student->effectiveGrade() }}. Holding the grade keeps it there for the rest of this school year instead of advancing automatically.
            </p>
            <p class="text-xs font-medium text-[#2D231E] leading-relaxed">
                Use this when a student is repeating or retained in real school. ALICE never decides this on its own. If the student repeats again, hold the grade again next year.
            </p>
            <div class="flex flex-col sm:flex-row gap-3 pt-1">
                <button
                    type="button"
                    data-confirm-approve
                    class="inline-flex items-center justify-center min-h-[44px] flex-1 px-5 py-2 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700 transition-colors cursor-pointer"
                >
                    Hold grade
                </button>
                <button
                    type="button"
                    data-modal-close
                    class="inline-flex items-center justify-center min-h-[44px] flex-1 px-5 py-2 bg-white border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
                >
                    Cancel
                </button>
            </div>
        </div>
    </x-modal>

    <x-modal id="unhold-grade-modal" title="Release the grade hold?">
        <div class="space-y-4">
            <p class="text-sm font-semibold text-[#2D231E] leading-relaxed">
                The hold on {{ $student->nickname ?? 'this student' }}'s grade will be removed. The grade will advance again each June from the original school year it was set.
            </p>
            <p class="text-xs font-medium text-[#2D231E] leading-relaxed">
                Only release the hold if the student is no longer repeating or retained. You can hold the grade again at any time.
            </p>
            <div class="flex flex-col sm:flex-row gap-3 pt-1">
                <button
                    type="button"
                    data-confirm-approve
                    class="inline-flex items-center justify-center min-h-[44px] flex-1 px-5 py-2 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700 transition-colors cursor-pointer"
                >
                    Release hold
                </button>
                <button
                    type="button"
                    data-modal-close
                    class="inline-flex items-center justify-center min-h-[44px] flex-1 px-5 py-2 bg-white border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
                >
                    Cancel
                </button>
            </div>
        </div>
    </x-modal>
@endsection