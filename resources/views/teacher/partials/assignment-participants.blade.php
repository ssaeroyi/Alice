{{-- Participants tab: sortable roster (desktop table + mobile cards) + the
     per-student drawer templates. Shares the drawer shell rendered by
     resources/views/teacher/assignments/rollup.blade.php. --}}
<div class="space-y-3">
    @if ($assignment->status !== 'draft')
        <div class="flex flex-wrap items-center gap-2">
            <form method="GET" action="{{ route('teacher.assignments.show', $assignment) }}" data-auto-filter class="flex-1 min-w-[180px] relative">
                <input type="hidden" name="tab" value="participants">
                <input
                    type="search"
                    name="q"
                    value="{{ $keyword }}"
                    placeholder="Search students"
                    aria-label="Search students"
                    class="w-full min-h-[40px] pl-9 pr-3 py-2 bg-white border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] placeholder:text-[#B5A89C] hover:border-amber-300 focus:outline-none focus:border-amber-300"
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
    @endif

    @if ($assignment->status === 'draft')
        <div class="px-5 py-6 text-center bg-white border border-[#E6DCCF] rounded-xl">
            <p class="text-sm font-semibold text-[#2D231E]">Draft assignments have no results yet.</p>
        </div>
    @else
        {{-- Desktop table --}}
        <div class="bg-white border border-[#E6DCCF] rounded-xl overflow-hidden hidden md:block">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[10px] font-extrabold uppercase tracking-wider text-[#8C7B70] border-b border-[#E6DCCF]">
                            <th class="px-5 py-2.5">Student</th>
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
                            <tr class="border-b border-[#E6DCCF]/60 last:border-0 hover:bg-[#FAF6EE]/60 transition-colors">
                                <td class="px-5 py-2.5">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <x-teacher.student-chip :student="$student" :size="36" />
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
                                <td class="px-5 py-2.5 whitespace-nowrap text-xs font-semibold text-[#2D231E] tabular-nums">
                                    {{ $completedAt?->format('M j, g:i A') ?? '—' }}
                                </td>
                                @include('teacher.partials.member-drawer', ['markAssignment' => $assignment, 'student' => $student, 'drawerRow' => ['rows' => [[
                                    'title' => null,
                                    'state' => $marked && ! $progress['completed'] ? 'marked' : $rowState,
                                    'attempted' => (bool) ($summary['attempted'] ?? false),
                                    'score' => $summary['score'] ?? null,
                                    'passed' => (bool) ($summary['passed'] ?? false),
                                    'raw' => $summary['raw'] ?? null,
                                    'completedAt' => $completedAt,
                                ]], 'mark' => $drawerRow['mark']], 'markable' => $assignment->status === 'active'])
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $hasQuiz ? 4 : 3 }}" class="px-5 py-8 text-center text-sm font-semibold text-[#2D231E]">No {{ $keyword ? 'students match your search' : 'students match this audience' }} yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Mobile cards (drawer templates render once per student, above) --}}
        <div class="md:hidden grid grid-cols-1 sm:grid-cols-2 gap-2.5">
            @forelse ($audience as $student)
                @php
                    $progress = $completion[$student->id] ?? ['read' => false, 'quiz' => false, 'completed' => false];
                    $marked = $markedIds->contains($student->id);
                    $summary = $quizSummaries->get($student->id);
                    $rowState = ($progress['completed'] || $marked) ? 'done' : (($progress['read'] || $progress['quiz']) ? 'in_progress' : 'not_started');
                    $rowMeta = \App\Models\Assignment::statusMeta($rowState);
                @endphp
                <button type="button" data-story-drawer-open="{{ $student->id }}" class="text-left bg-white border border-[#E6DCCF] rounded-xl p-3.5 hover:border-amber-300 transition-all cursor-pointer">
                    <div class="flex items-center gap-3 min-w-0">
                        <x-teacher.student-chip :student="$student" :size="36" />
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-[#2D231E] truncate">{{ trim($student->first_name.' '.$student->last_name) ?: ($student->nickname ?? strtok($student->email, '@')) }}</p>
                            <p class="text-[11px] font-semibold text-[#2D231E] truncate">{{ $student->email }}</p>
                        </div>
                        <span class="shrink-0 inline-flex items-center gap-1 text-[11px] font-bold px-2 py-1 rounded-full {{ $rowMeta['classes'] }}">{{ $rowMeta['label'] }}</span>
                    </div>
                    @if ($hasQuiz)
                        <p class="mt-2 text-[11px] font-semibold text-[#8C7B70]">
                            {{ $summary['attempted'] ? 'Quiz '.$summary['score'].'%'.($summary['passed'] ? ' · passed' : ' · below pass') : 'Quiz not taken' }}
                        </p>
                    @endif
                </button>
            @empty
                <p class="text-sm font-semibold text-[#2D231E] py-8 text-center col-span-full">No students in this audience yet.</p>
            @endforelse
        </div>
    @endif
</div>
