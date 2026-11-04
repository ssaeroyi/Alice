@extends('layouts.teacher')

@section('title', $group->name)
@section('eyebrow', 'Teaching')

@section('content')
    <div class="space-y-6">
        {{-- Header --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('teacher.classes.index') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-[#2D231E] hover:text-sky-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path></svg>
                Back to classes
            </a>
            <form method="POST" action="{{ route('teacher.classes.destroy', $group) }}" data-submit-guard>
                @csrf
                @method('DELETE')
                <button type="submit" data-confirm-open="delete-class-modal" class="inline-flex items-center gap-1.5 min-h-[36px] text-xs font-bold px-3 py-1.5 rounded-lg bg-rose-100 text-rose-700 hover:bg-rose-200 transition-colors cursor-pointer">
                    <span data-submit-idle>Remove class</span>
                    <span data-submit-active class="hidden"><x-spinner label="Removing..." /></span>
                </button>
            </form>
        </div>

        {{-- JOIN CODE + CLASS NOTES: the class's own affordance, always first --}}
        <div class="bg-white border border-[#E6DCCF] rounded-xl p-5 grid gap-5 lg:grid-cols-2">
            <div>
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-[#8C7B70] mb-1.5">Join code</p>
                <div class="flex flex-wrap items-center gap-3">
                    <span class="text-2xl font-mono font-extrabold text-[#2D231E]">{{ $group->join_code }}</span>
                    <button
                        type="button"
                        data-copy-code
                        data-code="{{ $group->join_code }}"
                        class="inline-flex items-center gap-1.5 min-h-[36px] text-xs font-bold px-3 py-1.5 rounded-lg bg-[#F3ECE0] border border-[#E6DCCF] text-[#2D231E] hover:border-amber-300 transition-colors cursor-pointer"
                    >
                        Copy code
                    </button>
                    <a href="{{ route('teacher.classes.members.create', $group) }}" class="inline-flex items-center gap-1.5 min-h-[36px] text-xs font-bold px-3 py-1.5 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 transition-colors">
                        Invite students
                    </a>
                </div>
            </div>
            <div class="lg:border-l lg:border-[#E6DCCF] lg:pl-5">
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-[#8C7B70] mb-1.5">Class notes</p>
                <form method="POST" action="{{ route('teacher.classes.update', $group) }}" data-submit-guard class="space-y-2">
                    @csrf
                    @method('PATCH')
                    <textarea
                        name="description"
                        rows="3"
                        maxlength="2000"
                        placeholder="What is this class about? Students see this on the class page."
                        class="w-full px-3 py-2.5 border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] focus:outline-none focus:border-amber-300"
                    >{{ old('description', $group->description) }}</textarea>
                    <div class="flex justify-end">
                        <button type="submit" class="inline-flex items-center min-h-[36px] text-xs font-bold px-4 py-1.5 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 transition-colors cursor-pointer">
                            <span data-submit-idle>Save description</span>
                            <span data-submit-active class="hidden"><x-spinner label="Saving..." /></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- HERO STRIP: how this class is doing overall --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
            <x-teacher.tile
                label="Completion"
                :value="$tiles['completion_pct'] !== null ? $tiles['completion_pct'].'%' : '—'"
                :sub="$tiles['assignments_total'] > 0 ? 'across '.Str::plural('assignment', $tiles['assignments_total']) : 'no assignments yet'"
                :ring="$tiles['completion_pct'] ?? 0"
                tooltip="Average completion of this class's assignments."
            />
            <x-teacher.tile
                label="Quiz score"
                :value="$tiles['avg_score'] !== null ? $tiles['avg_score'].'%' : '—'"
                :sub="$tiles['avg_score'] !== null ? 'latest attempt average' : 'no quizzes yet'"
                :ring="$tiles['avg_score'] ?? 0"
                tooltip="Average of each member's most recent quiz attempt."
            />
            <x-teacher.tile
                label="Stories finished"
                :value="$tiles['stories_finished']"
                :sub="Str::plural('member story', $tiles['stories_finished']).' completed'"
                :bar="min(100, $tiles['stories_finished'] * 10)"
            />
            <x-teacher.tile
                label="Active today"
                :value="$tiles['active_today'].' / '.$tiles['member_count']"
                :sub="$tiles['active_today'] > 0 ? $tiles['active_today'].' of '.$tiles['member_count'].' members online' : 'nobody online yet today'"
                :bar="$tiles['member_count'] > 0 ? (int) round(100 * $tiles['active_today'] / $tiles['member_count']) : 0"
                :barColor="$tiles['active_today'] > 0 ? 'bg-amber-400' : 'bg-[#F3ECE0]'"
            />
        </div>

        {{-- Assignments strip --}}
        <div>
            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                <h2 class="font-pixel text-[11px] sm:text-xs font-bold text-[#2D231E] uppercase tracking-wider">Assignments</h2>
                <div class="flex flex-wrap items-center gap-2">
                    <a
                        href="{{ route('teacher.assignments.index', ['class' => $group->id]) }}"
                        class="inline-flex items-center gap-1.5 min-h-[36px] text-xs font-bold px-3 py-1.5 rounded-lg bg-[#F3ECE0] border border-[#E6DCCF] text-[#2D231E] hover:border-amber-300 transition-colors"
                    >
                        View all
                    </a>
                    <a
                        href="{{ route('teacher.assignments.create', ['class' => $group->id]) }}"
                        class="inline-flex items-center gap-1.5 min-h-[36px] text-xs font-bold px-3 py-1.5 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 transition-colors"
                    >
                        Add assignment
                    </a>
                </div>
            </div>

            @if ($assignmentRows->isEmpty())
                <div class="border border-[#E6DCCF] rounded-lg p-6 text-center">
                    <p class="text-sm font-bold text-[#2D231E]">Nothing assigned yet</p>
                    <p class="mt-1 text-sm font-semibold text-[#2D231E]">Assign a story to this class and it'll show up here for you to track.</p>
                    <a href="{{ route('teacher.assignments.create', ['class' => $group->id]) }}" class="mt-3 inline-flex items-center gap-1 text-sm font-bold text-sky-700 hover:text-sky-800">Assign a story →</a>
                </div>
            @else
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($assignmentRows as $row)
                        <a href="{{ route('teacher.assignments.show', $row['assignment']) }}" class="block bg-white border border-[#E6DCCF] rounded-xl p-4 hover:border-amber-300 hover:shadow-[0_4px_14px_rgba(45,35,30,0.07)] transition-all">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <span class="block text-sm font-extrabold text-[#2D231E] truncate">{{ $row['assignment']->title }}</span>
                                    <span class="block text-xs font-semibold text-[#2D231E] mt-0.5 truncate">{{ $row['assignment']->story?->title ?? 'No story' }}</span>
                                </div>
                                <span class="shrink-0 {{ $row['assignment']->status === 'active' ? 'bg-emerald-100 text-emerald-700' : ($row['assignment']->status === 'cancelled' ? 'bg-rose-100 text-rose-700' : ($row['assignment']->status === 'archived' ? 'bg-stone-200 text-stone-700' : 'bg-amber-100 text-amber-700')) }} capitalize text-[10px] font-bold uppercase tracking-wide px-2 py-1 rounded-full">
                                    {{ $row['assignment']->status === 'archived' ? 'Finished' : $row['assignment']->status }}
                                </span>
                            </div>
                            <div class="mt-3 flex items-end justify-between gap-3">
                                <div class="min-w-0 text-[11px] font-bold text-[#2D231E] space-y-0.5">
                                    @if ($row['assignment']->due_at)
                                        <p class="{{ $row['assignment']->isOverdue() ? 'text-rose-600' : '' }}">Due {{ $row['assignment']->due_at->format('M j, Y') }}</p>
                                    @else
                                        <p class="text-[#8C7B70]">No due date</p>
                                    @endif
                                    <p class="text-[#8C7B70]">{{ $row['started'] }} started · {{ $row['completed'] }}/{{ $row['audience'] }} done</p>
                                </div>
                                <div class="shrink-0 flex items-center gap-1.5">
                                    <span class="text-sm font-extrabold tabular-nums {{ $row['pct'] >= 70 ? 'text-emerald-600' : ($row['pct'] >= 40 ? 'text-amber-600' : 'text-rose-600') }}">{{ $row['pct'] }}%</span>
                                    <x-teacher.ring :percent="$row['pct']" :size="7" />
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ROSTER: plain list, capped, in-page drawer on click --}}
        <div>
            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                <h2 class="font-pixel text-[11px] sm:text-xs font-bold text-[#2D231E] uppercase tracking-wider">
                    Students <span class="text-[#8C7B70]">({{ $members->count() }})</span>
                </h2>
            </div>

            @php
                $showAll = request()->boolean('all');
                $rosterCap = $showAll ? $members->count() : min($members->count(), 8);
            @endphp

            @if ($members->isEmpty())
                <div class="border border-[#E6DCCF] rounded-lg p-6 text-center">
                    <p class="text-sm font-bold text-[#2D231E]">No students yet</p>
                    <p class="mt-1 text-sm font-semibold text-[#2D231E]">Share the join code above or invite them, and they show up here.</p>
                </div>
            @else
                <div class="bg-white border border-[#E6DCCF] rounded-xl divide-y divide-[#F0E9DD] overflow-hidden">
                    @foreach ($members->take($rosterCap) as $student)
                        @php
                            $stat = $roster[$student->id];
                            $score = $stat['score'];
                        @endphp
                        <button type="button" data-story-drawer-open="{{ $student->id }}" class="w-full text-left flex items-center gap-3 px-4 py-2.5 hover:bg-[#FAF6EE] transition-colors cursor-pointer">
                            <x-teacher.student-chip :student="$student" :size="32" />
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-bold text-[#2D231E] truncate">{{ trim($student->first_name.' '.$student->last_name) ?: ($student->nickname ?? strtok($student->email, '@')) }}</p>
                                <p class="text-[11px] font-semibold text-[#8C7B70] truncate">{{ $student->email }}</p>
                            </div>
                            <div class="shrink-0 hidden sm:block text-right text-[11px] font-semibold text-[#8C7B70]">
                                @if ($score !== null)
                                    <span class="tabular-nums font-bold {{ $stat['passed'] ? 'text-emerald-600' : 'text-rose-600' }}">{{ $score }}%</span>
                                @else
                                    <span class="text-[#8C7B70]">No quiz yet</span>
                                @endif
                            </div>
                            <x-presence-dot :at="$stat['activity_at']" compact />
                        </button>
                    @endforeach
                </div>

                @if ($members->count() > 8 && ! $showAll)
                    <a
                        href="{{ route('teacher.classes.show', ['group' => $group, 'all' => 1]) }}"
                        class="mt-2 inline-flex items-center min-h-[36px] text-xs font-bold text-sky-700 hover:text-sky-800 transition-colors"
                    >
                        View all {{ $members->count() }} students →
                    </a>
                @endif
            @endif
        </div>
    </div>

    {{-- Drawer shell: opens the clicked student without leaving the page --}}
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

    <x-modal id="delete-class-modal" title="Remove this class?">
        <p class="text-xs font-semibold text-[#2D231E]">
            This deletes the class, its assignments, and removes all students from it. They'll lose access to any class assignments. This can't be undone.
        </p>
        <div class="flex items-center justify-end gap-2">
            <button type="button" data-modal-close class="min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-amber-300 transition-colors cursor-pointer">Cancel</button>
            <button type="button" data-confirm-approve class="inline-flex items-center justify-center min-h-[44px] px-5 py-2 bg-rose-600 text-white rounded-lg text-xs font-bold hover:bg-rose-700 transition-colors cursor-pointer">Remove class</button>
        </div>
    </x-modal>

    @foreach ($members as $student)
        @include('teacher.partials.member-drawer', ['markAssignment' => null, 'student' => $student, 'drawerRow' => $detail($student), 'markable' => false])
        <x-modal id="remove-member-{{ $student->id }}" title="Remove {{ trim($student->first_name.' '.$student->last_name) ?: ($student->nickname ?? strtok($student->email, '@')) }} from this class?">
            <p class="text-xs font-semibold text-[#2D231E]">
                {{ trim($student->first_name.' '.$student->last_name) ?: ($student->nickname ?? strtok($student->email, '@')) }} will lose access to this class's assignments. They can rejoin using the class code if needed.
            </p>
            <div class="flex items-center justify-end gap-2">
                <button type="button" data-modal-close class="min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-amber-300 transition-colors cursor-pointer">Cancel</button>
                <button type="button" data-confirm-approve class="inline-flex items-center justify-center min-h-[44px] px-5 py-2 bg-rose-600 text-white rounded-lg text-xs font-bold hover:bg-rose-700 transition-colors cursor-pointer">Remove from class</button>
            </div>
        </x-modal>
    @endforeach
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const copyBtn = document.querySelector('[data-copy-code]');
            if (copyBtn) {
                copyBtn.addEventListener('click', function () {
                    const code = this.dataset.code;
                    const original = this.textContent;
                    navigator.clipboard?.writeText(code).then(() => {
                        this.textContent = 'Copied!';
                        setTimeout(() => { this.textContent = original; }, 1500);
                    }).catch(() => {});
                });
            }
        });
    </script>
@endpush
