@php
    $assignmentDone = isset($assignmentCompletion) && is_array($assignmentCompletion)
        ? ($assignmentCompletion[$assignment->id] ?? false)
        : (method_exists($assignment, 'completedFor') ? $assignment->completedFor(auth()->user()) : false);
@endphp
<a href="{{ route('student.assignments.show', $assignment) }}"
    class="flex flex-row items-center gap-3 p-3 bg-[#FAF6EE] border-2 border-[#2D231E]/20 rounded-xl hover:border-sky-600 hover:bg-white transition-colors group">
    <span class="w-11 h-14 shrink-0 bg-amber-400 border-2 border-[#2D231E] rounded-lg overflow-hidden flex items-center justify-center relative">
        @if (method_exists($assignment, 'story') && $assignment->story && $assignment->story->coverUrl() !== '')
            <img src="{{ $assignment->story->coverUrl() }}" alt="" loading="lazy" decoding="async" class="w-full h-full object-cover object-left-top" onerror="this.remove()">
        @else
            <span class="font-pixel text-[7px] font-extrabold text-[#2D231E] px-1 text-center leading-tight">{{ \Illuminate\Support\Str::limit(strtoupper($assignment->story?->title ?? 'ASSIGNMENT'), 14) }}</span>
        @endif
    </span>
    <span class="flex-1 min-w-0 space-y-0.5">
        <span class="block text-sm font-bold text-[#2D231E] leading-snug group-hover:text-sky-700 truncate">{{ $assignment->title }}</span>
        <span class="block text-xs font-semibold text-[#2D231E] truncate">{{ $assignment->story?->title ?? 'No story attached' }}</span>
        <span class="inline-flex items-center gap-2 mt-0.5">
            @if ($assignmentDone)
                <span class="inline-flex items-center gap-1 text-[9px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                    Done
                </span>
            @else
                <span class="inline-flex items-center gap-1 text-[9px] font-bold px-2 py-0.5 rounded-full bg-sky-100 text-sky-700">
                    <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
                    In progress
                </span>
            @endif
            @if ($assignment->due_at)
                <span class="text-[10px] font-bold {{ ! $assignmentDone && $assignment->isOverdue() ? 'text-rose-600' : 'text-[#2D231E]' }}">
                    Due {{ $assignment->due_at->format('M j') }}
                </span>
            @endif
        </span>
    </span>
    <svg class="font-semibold w-4 h-4 shrink-0 text-[#2D231E] group-hover:text-sky-700" fill="currentColor" viewBox="0 0 24 24"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
</a>