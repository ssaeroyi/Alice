{{-- Per-student <template data-story-drawer-template="…"> content, shared by
     the assignment report and the class roster. MINIMAL by design: only the
     current assignment facts — one card per assigned story (status, quiz
     score, correct count, completed at) — plus the teacher's mark action in
     assignment context. No comprehension grid, no platform-wide history. --}}
<template data-story-drawer-template="{{ $student->id }}">
    <div class="flex items-start justify-between gap-4 p-5 border-b border-[#E6DCCF]">
        <div class="min-w-0">
            <p class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E]">Student</p>
            <h3 class="text-lg font-extrabold text-[#2D231E] mt-1 break-words">{{ $student->nickname ?? strtok($student->email, '@') }}</h3>
        </div>
        <button type="button" data-story-drawer-close aria-label="Close" class="shrink-0 min-h-[44px] min-w-[44px] inline-flex items-center justify-center text-lg leading-none font-bold text-[#2D231E] transition-colors cursor-pointer">&times;</button>
    </div>
    <div class="p-5 space-y-3">
        @php $d = $drawerRow; @endphp
        @forelse ($d['rows'] as $row)
            <div class="rounded-xl bg-[#FAF6EE] border border-[#E6DCCF] px-4 py-3">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-sm font-extrabold text-[#2D231E] min-w-0 truncate">{{ ($row['title'] ?? null) ? $row['title'] : 'Assignment' }}</p>
                    @php
                        $stateMeta = \App\Models\Assignment::statusMeta($row['state']);
                    @endphp
                    <span class="shrink-0 inline-flex items-center gap-1 text-[11px] font-bold px-2 py-1 rounded-full {{ $stateMeta['classes'] }}">{{ $stateMeta['label'] }}</span>
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] font-semibold text-[#2D231E]">
                    @if ($row['attempted'])
                        <span class="inline-flex items-center min-w-[48px] justify-center rounded-full px-2 py-0.5 text-xs font-extrabold tabular-nums {{ $row['passed'] ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-600' }}">{{ $row['score'] }}%</span>
                        <span>{{ $row['raw'] }} correct</span>
                    @else
                        <span class="text-[#8C7B70]">Quiz not taken</span>
                    @endif
                    @if ($row['completedAt'])
                        <span class="text-[#8C7B70]">{{ $row['completedAt']->format('M j, g:i A') }}</span>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-xs font-semibold text-[#2D231E] py-2">No assignments to report for this member yet.</p>
        @endforelse

        {{-- Mark done / undo (assignment context only) --}}
        @if ($markable)
            @php $single = $d['rows'][0] ?? null; @endphp
            @if ($single && $single['state'] !== 'done')
                <form method="POST" action="{{ route('teacher.assignments.mark', $markAssignment) }}" data-submit-guard class="pt-2 border-t border-[#F0E9DD]">
                    @csrf
                    <input type="hidden" name="student_id" value="{{ $student->id }}">
                    <button type="submit" class="inline-flex items-center justify-center min-h-[44px] px-4 py-2 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700 transition-colors cursor-pointer">
                        <span data-submit-idle>Mark done</span>
                        <span data-submit-active class="hidden"><x-spinner /></span>
                    </button>
                </form>
            @elseif ($single && $single['state'] === 'done')
                <form method="POST" action="{{ route('teacher.assignments.mark-remove', [$markAssignment, $student]) }}" data-submit-guard class="pt-2 border-t border-[#F0E9DD]">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex items-center justify-center min-h-[44px] px-4 py-2 bg-rose-600 text-white rounded-lg text-xs font-bold hover:bg-rose-700 transition-colors cursor-pointer">
                        <span data-submit-idle>Undo mark</span>
                        <span data-submit-active class="hidden"><x-spinner /></span>
                    </button>
                </form>
            @endif
        @endif
    </div>
</template>
