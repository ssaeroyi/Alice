{{-- Per-student <template data-story-drawer-template="…"> content, shared by
     the assignment participants tab and the class roster drawer. Receives:
     $student, $drawerRow (progress / attempts / mark or member variant),
     $drawerMarked (bool), $drawerCompleted (bool), $markable (bool),
     $profileFrom (string context tag). --}}
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
        @php $d = $drawerRow; @endphp

        {{-- Context strip: reading grade, comprehension, last login --}}
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
        @php $sp = $d['progress'] ?? null; @endphp
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
                            <dd class="font-bold text-[#2D231E] mt-0.5 tabular-nums">{{ $sp->best_score_pct }}%</dd>
                        </div>
                    @endif
                    @if ($sp->time_spent_seconds > 0)
                        <div>
                            <dt class="text-[10px] font-extrabold uppercase tracking-wider text-[#2D231E]">Time spent</dt>
                            <dd class="font-bold text-[#2D231E] mt-0.5 tabular-nums">{{ (int) floor($sp->time_spent_seconds / 60) }}m {{ $sp->time_spent_seconds % 60 }}s</dd>
                        </div>
                    @endif
                    @if ($sp->completed_at)
                        <div>
                            <dt class="text-[10px] font-extrabold uppercase tracking-wider text-[#2D231E]">Completed</dt>
                            <dd class="font-bold text-[#2D231E] mt-0.5">{{ $sp->completed_at->format('M j, g:i A') }}</dd>
                        </div>
                    @endif
                </dl>
            @elseif (($d['finished_count'] ?? 0) > 0)
                <div class="flex items-end justify-between gap-3">
                    <p class="text-xs font-semibold text-[#2D231E]">
                        {{ $d['finished_count'] }} {{ $d['finished_count'] === 1 ? 'story' : 'stories' }} finished so far.
                    </p>
                    <div class="shrink-0 w-24 h-2.5 rounded-full bg-[#F3ECE0] overflow-hidden">
                        <div class="h-full rounded-full bg-emerald-400" style="width: {{ min(100, max(6, ($d['finished_count'] ?? 0) * 10)) }}%"></div>
                    </div>
                </div>
            @else
                <p class="text-xs font-semibold text-[#2D231E]">No story results on record yet.</p>
            @endif
        </div>

        {{-- Status / mark trail (assignment context only) --}}
        @if ($drawerMarked !== null)
            <div>
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-[#2D231E] mb-2">Status</p>
                @php $drawerMark = $d['mark'] ?? null; @endphp
                @if ($drawerCompleted)
                    <p class="text-xs font-bold text-emerald-700">Done</p>
                    @if ($drawerMark)
                        <p class="text-[11px] font-semibold text-[#2D231E] mt-0.5">Marked by {{ $drawerMark->teacher?->nickname ?? 'teacher' }} on {{ $drawerMark->created_at->format('M j, g:i A') }}</p>
                    @endif
                @elseif ($drawerMarked)
                    <p class="text-xs font-bold text-emerald-700">Done (teacher)</p>
                    <p class="text-[11px] font-semibold text-[#2D231E] mt-0.5">Marked by {{ $drawerMark->teacher?->nickname ?? 'teacher' }} on {{ $drawerMark->created_at->format('M j, g:i A') }}</p>
                @elseif ($d['progress'] !== null)
                    <p class="text-xs font-bold text-amber-700">In progress</p>
                @else
                    <p class="text-xs font-semibold text-[#2D231E]">Not started.</p>
                @endif
            </div>
        @endif

        {{-- Quiz history --}}
        <div>
            <p class="text-[10px] font-extrabold uppercase tracking-wider text-[#2D231E] mb-2">Quiz history</p>
            @forelse ($d['attempts'] ?? [] as $row)
                <div class="rounded-xl bg-[#FAF6EE] border border-[#E6DCCF] px-4 py-3 mb-2 last:mb-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center min-w-[52px] justify-center rounded-full px-2.5 py-1 text-xs font-extrabold tabular-nums {{ $row['passed'] ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-600' }}">{{ $row['score'] }}%</span>
                        <span class="text-[11px] font-semibold text-[#2D231E]">{{ $row['at']->diffForHumans() }} ({{ $row['at']->format('M j, g:i A') }})</span>
                        @if ($row['story'] ?? null)
                            <span class="text-[11px] font-bold text-sky-700 truncate">{{ $row['story'] }}</span>
                        @endif
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

        {{-- Mark done / undo (assignment context only) --}}
        @if ($markable)
            @if (! $drawerCompleted && ! $drawerMarked)
                <form method="POST" action="{{ route('teacher.assignments.mark', $markAssignment) }}" class="pt-2 border-t border-[#F0E9DD]">
                    @csrf
                    <input type="hidden" name="student_id" value="{{ $student->id }}">
                    <button type="submit" data-submit-guard class="inline-flex items-center justify-center min-h-[44px] px-4 py-2 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700 transition-colors cursor-pointer">
                        <span data-submit-idle>Mark done</span>
                        <span data-submit-active class="hidden"><x-spinner /></span>
                    </button>
                </form>
            @elseif ($drawerMarked && ! $drawerCompleted)
                <form method="POST" action="{{ route('teacher.assignments.mark-remove', [$markAssignment, $student]) }}" class="pt-2 border-t border-[#F0E9DD]">
                    @csrf
                    @method('DELETE')
                    <button type="submit" data-submit-guard class="inline-flex items-center justify-center min-h-[44px] px-4 py-2 bg-rose-600 text-white rounded-lg text-xs font-bold hover:bg-rose-700 transition-colors cursor-pointer">
                        <span data-submit-idle>Undo mark</span>
                        <span data-submit-active class="hidden"><x-spinner /></span>
                    </button>
                </form>
            @endif
        @endif

        {{-- Open full profile --}}
        <div class="pt-2 border-t border-[#F0E9DD]">
            <a href="{{ route('teacher.student', ['user' => $student, 'from' => $profileFrom]) }}" class="inline-flex items-center justify-center min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-amber-300 transition-colors">Open full profile</a>
        </div>
    </div>
</template>
