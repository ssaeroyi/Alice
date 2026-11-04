@extends('layouts.teacher')

@section('title', $assignment->title)
@section('eyebrow', 'Assignments')

@section('content')
    <div class="space-y-6">
        {{-- Page header: back + status + share + actions --}}
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
                            class="inline-flex items-center gap-1.5 min-h-[28px] text-xs font-mono font-bold px-3 py-1.5 rounded-lg bg-[#FAF6EE] border border-[#E6DCCF] text-[#2D231E] hover:border-amber-300 transition-colors cursor-pointer">
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
                            class="min-h-[28px] min-w-[28px] inline-flex items-center justify-center bg-white border border-[#E6DCCF] rounded-lg text-sm font-bold text-[#2D231E] hover:border-amber-300 transition-colors cursor-pointer"
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

        {{-- HERO STRIP: the four numbers a teacher reads first --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
            <x-teacher.tile
                label="Completion"
                value="{{ $headline['completed_count'] }} / {{ $headline['audience_count'] }}"
                sub="done · {{ $headline['started_count'] }} started"
                :ring="$headline['completion_pct']"
                tooltip="Done means the student read the story and took the quiz, or the teacher marked them done."
                :href="route('teacher.assignments.show', ['assignment' => $assignment, 'tab' => 'participants'])"
            />
            <x-teacher.tile
                :label="'Quiz accuracy'"
                :value="$headline['avg_score'] !== null ? $headline['avg_score'].'%' : '—'"
                :sub="$headline['attempted_count'] > 0 ? $headline['attempted_count'].' '.Str::plural('attempt', $headline['attempted_count']) : 'no attempts yet'"
                :ring="$headline['avg_score'] ?? 0"
                tooltip="Average of every student's latest attempt percentage."
                :href="route('teacher.assignments.show', ['assignment' => $assignment, 'tab' => 'questions'])"
            />
            <x-teacher.tile
                :label="'Pass rate'"
                :value="$headline['pass_rate'] !== null ? $headline['pass_rate'].'%' : '—'"
                :sub="$headline['pass_count'] > 0 ? $headline['pass_count'].' passed the quiz' : 'none passed yet'"
                :ring="$headline['pass_rate'] ?? 0"
                tooltip="Share of attempted quizzes at or over the pass mark."
                :href="route('teacher.assignments.show', ['assignment' => $assignment, 'tab' => 'overview'])"
            />
            <x-teacher.tile
                label="Participation"
                :value="$headline['started_count'].' / '.$headline['audience_count']"
                :sub="Str::plural('student', $headline['audience_count']).' in the audience'"
                :bar="$headline['audience_count'] > 0 ? (int) round(100 * $headline['started_count'] / $headline['audience_count']) : 0"
                tooltip="Students who opened the story or sat the quiz since it went live."
                :href="route('teacher.assignments.show', ['assignment' => $assignment, 'tab' => 'participants'])"
            />
        </div>

        {{-- Meta row: story + due + instructions absorb the old quick-facts card --}}
        <div class="bg-white border border-[#E6DCCF] rounded-xl px-4 sm:px-5 py-4 flex flex-wrap items-center gap-x-8 gap-y-3">
            <div class="min-w-0">
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-[#8C7B70]">Story</p>
                <p class="text-sm font-extrabold text-[#2D231E] truncate">
                    {{ $assignment->story?->title ?? 'No story' }}
                    @if ($assignment->story)
                        <a href="{{ route('teacher.stories.edit', $assignment->story) }}" class="text-[11px] font-bold text-sky-700 hover:underline ml-1">View</a>
                        <form method="POST" action="{{ route('teacher.stories.duplicate', $assignment->story) }}" class="inline">
                            @csrf
                            <button type="submit" class="text-[11px] font-bold text-sky-700 hover:underline">Make a copy</button>
                        </form>
                    @endif
                </p>
            </div>
            <div>
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-[#8C7B70]">Due</p>
                <p class="text-sm font-bold {{ ($assignment->status === 'active' && $assignment->due_at && $assignment->isOverdue()) ? 'text-rose-600' : 'text-[#2D231E]' }}">
                    {{ $assignment->due_at?->format('M j, Y') ?? 'No due date' }}
                </p>
            </div>
            <div>
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-[#8C7B70]">Assigned</p>
                <p class="text-sm font-bold text-[#2D231E]">{{ optional($assignment->gateStartsAt())->format('M j, Y') }}</p>
            </div>
            @if ($assignment->instructions)
                <div class="min-w-0 flex-1 basis-full">
                    <p class="text-[10px] font-extrabold uppercase tracking-wider text-[#8C7B70]">Instructions</p>
                    <p class="text-xs font-semibold text-[#2D231E] whitespace-pre-wrap">{{ $assignment->instructions }}</p>
                </div>
            @endif
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

        @if ($assignment->status === 'draft')
            <div class="px-5 py-6 text-center bg-white border border-[#E6DCCF] rounded-xl">
                <p class="text-sm font-semibold text-[#2D231E]">This assignment is still a draft. Publish it to make it visible to students.</p>
            </div>
        @else
            {{-- TAB BAR (server state via ?tab=) --}}
            <div id="report-start" class="sticky top-0 z-20 -mx-1 px-1 py-1.5 bg-[#FAF6EE]/95 backdrop-blur-sm flex gap-1.5 border-b border-[#E6DCCF]">
                @foreach (['participants' => 'Participants', 'questions' => 'Questions', 'overview' => 'Overview'] as $key => $label)
                    @continue($key !== 'participants' && ! $hasQuiz)
                    <a
                        href="{{ route('teacher.assignments.show', array_merge(['assignment' => $assignment, 'tab' => $key, 'sort' => $sort], $keyword ? ['q' => $keyword] : [])) }}"
                        class="px-3.5 py-2 rounded-lg font-pixel text-[9px] sm:text-[10px] font-bold uppercase tracking-wider transition-colors {{ $tab === $key ? 'bg-amber-400 text-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)]' : 'bg-white text-[#8C7B70] hover:text-[#2D231E] hover:border hover:border-amber-300 border border-[#E6DCCF]' }}"
                        @if ($tab === $key) aria-current="page" @endif
                    >{{ $label }}</a>
                @endforeach
            </div>

            @php $query = array_filter(['tab' => $tab, 'sort' => $sort, 'q' => $keyword]); @endphp

            @if ($tab === 'participants')
                @include('teacher.partials.assignment-participants')
            @elseif ($tab === 'questions')
                {{-- QUESTIONS: per-question bars, hardest first --}}
                <div class="space-y-2">
                    @php $qIndex = 0; @endphp
                    @forelse ($questionRows as $row)
                        @php $qIndex++; @endphp
                        <div class="bg-white border border-[#E6DCCF] rounded-xl p-4">
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <p class="text-sm font-bold text-[#2D231E] min-w-0"><span class="text-[#8C7B70] font-mono text-xs mr-2">Q{{ $qIndex }}</span>{{ Str::limit($row['text'], 120) }}</p>
                                <span class="text-2xl font-extrabold tabular-nums {{ $row['correct_pct'] >= 70 ? 'text-emerald-600' : ($row['correct_pct'] >= 40 ? 'text-amber-600' : 'text-rose-600') }}">{{ $row['correct_pct'] }}%</span>
                            </div>
                            <div class="mt-2 h-3 rounded-full bg-[#F3ECE0] overflow-hidden">
                                <div class="h-full rounded-full {{ $row['correct_pct'] >= 70 ? 'bg-emerald-400' : ($row['correct_pct'] >= 40 ? 'bg-amber-400' : 'bg-rose-400') }}" style="width: {{ $row['correct_pct'] }}%"></div>
                            </div>
                            <p class="mt-1.5 text-[11px] font-semibold text-[#8C7B70]">
                                {{ $row['correct'] }} fully correct · {{ $row['partial'] }} partial · {{ $row['wrong'] }} wrong
                            </p>
                        </div>
                    @empty
                        <div class="bg-white border border-[#E6DCCF] rounded-xl px-4 py-6 text-center text-sm font-semibold text-[#2D231E]">
                            This story has no questions on record for the report yet.
                        </div>
                    @endforelse
                </div>
            @elseif ($tab === 'overview')
                {{-- OVERVIEW: the correctness heat grid --}}
                <div class="bg-white border border-[#E6DCCF] rounded-xl p-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-[10px] font-extrabold uppercase tracking-wider text-[#8C7B70] border-b border-[#E6DCCF]">
                                <th class="px-2 py-2.5 sticky left-0 bg-white z-10 min-w-[160px]">Student</th>
                                <th class="px-2 py-2.5">Score</th>
                                @foreach ($questionStats as $id => $row)
                                    <th class="px-1.5 py-2.5 text-center" title="{{ $row['text'] }}">
                                        <span class="inline-block w-6">Q{{ $loop->iteration + 1 }}</span>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($grid as $row)
                                <tr class="border-b border-[#F0E9DD] last:border-0 hover:bg-[#FAF6EE]/60 transition-colors">
                                    <td class="px-2 py-2 sticky left-0 bg-white">
                                        <button type="button" data-story-drawer-open="{{ $row['student']->id }}" class="flex items-center gap-2 text-left cursor-pointer group">
                                            <x-teacher.student-chip :student="$row['student']" :size="24" />
                                            <span class="font-bold text-[#2D231E] group-hover:text-sky-700 truncate max-w-[140px]">{{ trim($row['student']->first_name.' '.$row['student']->last_name) ?: ($row['student']->nickname ?? strtok($row['student']->email, '@')) }}</span>
                                        </button>
                                    </td>
                                    <td class="px-2 py-2 whitespace-nowrap">
                                        @if ($row['score'] !== null)
                                            <span class="inline-block min-w-[44px] text-center text-[11px] font-extrabold tabular-nums rounded-md px-1.5 py-1 {{ $row['passed'] ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-600' }}">{{ $row['score'] }}%</span>
                                        @else
                                            <span class="text-[#B5A89C] text-[11px]">—</span>
                                        @endif
                                    </td>
                                    @foreach ($questionStats as $id => $q)
                                        @php $cell = $row['cells'][$id] ?? ['state' => 'none']; @endphp
                                        <td class="px-1 py-2 text-center">
                                            <span
                                                title="{{ $q['text'] }} — @if($cell['state'] === 'none') no attempt @else {{ $cell['earned'] ?? '' }} ({{ mb_substr($cell['answer'] ?? '', 0, 60) }}) @endif"
                                                class="inline-block w-5 h-5 rounded-md {{ $cell['state'] === 'correct' ? 'bg-emerald-400' : ($cell['state'] === 'partial' ? 'bg-amber-400' : ($cell['state'] === 'wrong' ? 'bg-rose-400' : 'bg-[#F3ECE0]')) }}"
                                            ></span>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="mt-3 flex flex-wrap items-center gap-4 text-[10px] font-bold text-[#8C7B70]">
                        <span class="inline-flex items-center gap-1.5"><span class="w-3.5 h-3.5 rounded-md bg-emerald-400"></span>Correct</span>
                        <span class="inline-flex items-center gap-1.5"><span class="w-3.5 h-3.5 rounded-md bg-amber-400"></span>Partial</span>
                        <span class="inline-flex items-center gap-1.5"><span class="w-3.5 h-3.5 rounded-md bg-rose-400"></span>Wrong</span>
                        <span class="inline-flex items-center gap-1.5"><span class="w-3.5 h-3.5 rounded-md bg-[#F3ECE0]"></span>No attempt</span>
                    </div>
                </div>
            @endif
        @endif
    </div>

    {{-- Drawer shell shared with the participants tab + overview rows --}}
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
            <button type="button" data-modal-close class="min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-amber-300 transition-colors cursor-pointer">Cancel</button>
            <button type="button" data-confirm-approve class="inline-flex items-center justify-center min-h-[44px] px-5 py-2 bg-rose-600 text-white rounded-lg text-xs font-bold hover:bg-rose-700 transition-colors cursor-pointer">Delete assignment</button>
        </div>
    </x-modal>

    <x-modal id="notify-class-modal" title="Remind the class?">
        <p class="text-xs font-semibold text-[#2D231E]">
            Students still working on this assignment get a friendly inbox reminder. Students who are already done
            stay undisturbed, and reminding again too soon is skipped automatically.
        </p>
        <div class="flex items-center justify-end gap-2">
            <button type="button" data-modal-close class="min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-amber-300 transition-colors cursor-pointer">Cancel</button>
            <button type="button" data-confirm-approve class="inline-flex items-center justify-center min-h-[44px] px-5 py-2 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700 transition-colors cursor-pointer">Send reminder</button>
        </div>
    </x-modal>

    <x-modal id="cancel-assignment-modal" title="Cancel this assignment?">
        <p class="text-xs font-semibold text-[#2D231E]">
            Use this if the assignment should not have gone out. Students lose access and it stops being counted.
            Unlike finishing, which wraps up completed work, cancelling ends it for everyone.
        </p>
        <div class="flex items-center justify-end gap-2">
            <button type="button" data-modal-close class="min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-amber-300 transition-colors cursor-pointer">Keep assignment</button>
            <button type="button" data-confirm-approve class="inline-flex items-center justify-center min-h-[44px] px-5 py-2 bg-rose-600 text-white rounded-lg text-xs font-bold hover:bg-rose-700 transition-colors cursor-pointer">Cancel assignment</button>
        </div>
    </x-modal>

    <x-modal id="archive-assignment-modal" title="Finish this assignment?">
        <p class="text-xs font-semibold text-[#2D231E]">
            This wraps up a finished period of work. Students keep their history under "Past assignments" and you
            can still open it to review results. This action is recorded in the audit log.
        </p>
        <div class="flex items-center justify-end gap-2">
            <button type="button" data-modal-close class="min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-amber-300 transition-colors cursor-pointer">Cancel</button>
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
        });
    </script>
@endpush
