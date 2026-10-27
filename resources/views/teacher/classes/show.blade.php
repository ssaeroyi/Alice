@extends('layouts.teacher')

@section('title', $group->name)
@section('eyebrow', 'Teaching')

@section('content')
    <div class="space-y-6">
        {{-- Header --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <nav class="flex items-center gap-1.5 text-sm font-bold text-[#2D231E] mb-2">
                    <a href="{{ route('teacher.classes.index') }}" class="inline-flex items-center gap-1 hover:text-[#2D231E]">
                        <x-icons.core.arrow-left class="w-4 h-4" />
                        Back to classes
                    </a>
                    <span class="text-[#D8CBB9]">/</span>
                    <span class="text-[#2D231E] truncate">{{ $group->name }}</span>
                </nav>
                <p class="text-sm font-bold text-[#2D231E]">{{ $group->displayLabel() }} — {{ $group->members->count() }} student{{ $group->members->count() === 1 ? '' : 's' }}, {{ $group->assignments->count() }} assignment{{ $group->assignments->count() === 1 ? '' : 's' }}</p>
            </div>
            <form method="POST" action="{{ route('teacher.classes.destroy', $group) }}" data-submit-guard>
                @csrf
                @method('DELETE')
                <button type="submit" data-confirm-open="delete-class-modal" class="inline-flex items-center gap-1.5 min-h-[36px] text-xs font-bold px-3 py-1.5 rounded-lg bg-rose-100 text-rose-700 hover:bg-rose-200 transition-colors cursor-pointer">
                    <span data-submit-idle>Remove class</span>
                    <span data-submit-active class="hidden"><x-spinner label="Removing..." /></span>
                </button>
            </form>
        </div>

        {{-- Join code + description --}}
        <div class="bg-white border border-[#E6DCCF] rounded-xl p-5 space-y-4">
            <div>
                <p class="text-xs font-bold text-[#2D231E] uppercase tracking-wide mb-2">Join code</p>
                <div class="flex flex-wrap items-center gap-3">
                    <span class="text-2xl font-mono font-extrabold text-[#2D231E]">{{ $group->join_code }}</span>
                    <button
                        type="button"
                        data-copy-code
                        data-code="{{ $group->join_code }}"
                        class="inline-flex items-center gap-1.5 min-h-[36px] text-xs font-bold px-3 py-1.5 rounded-lg bg-[#F3ECE0] border border-[#E6DCCF] text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
                >
                    Copy code
                </button>
                </div>
                <p class="mt-2 text-sm font-semibold text-[#2D231E]">Students type this in their "Class & Assignments" tab to join.</p>
            </div>

            <div class="border-t border-[#E6DCCF] pt-4">
                <p class="text-xs font-bold text-[#2D231E] uppercase tracking-wide mb-2">Class description</p>
                <form method="POST" action="{{ route('teacher.classes.update', $group) }}" data-submit-guard class="space-y-2">
                    @csrf
                    @method('PATCH')
                    <textarea
                        name="description"
                        rows="3"
                        maxlength="2000"
                        placeholder="What is this class about? Students see this on the class page."
                        class="w-full px-3 py-2.5 border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] focus:outline-none focus:border-sky-600"
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

        {{-- Assignments --}}
        <div class="bg-white border border-[#E6DCCF] rounded-xl p-5">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <h3 class="text-base font-extrabold text-[#2D231E]">Assignments</h3>
                <div class="flex flex-wrap items-center gap-2">
                    <a
                        href="{{ route('teacher.assignments.index', ['class' => $group->id]) }}"
                        class="inline-flex items-center gap-1.5 min-h-[36px] text-xs font-bold px-3 py-1.5 rounded-lg bg-[#F3ECE0] border border-[#E6DCCF] text-[#2D231E] hover:border-[#2D231E] transition-colors"
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

            @if ($group->assignments->isEmpty())
                <div class="border border-[#E6DCCF] rounded-lg p-6 text-center">
                    <p class="text-sm font-bold text-[#2D231E]">Nothing assigned yet</p>
                    <p class="mt-1 text-sm font-semibold text-[#2D231E]">Assign a story to this class and it'll show up here for you to track.</p>
                    <a href="{{ route('teacher.assignments.create', ['class' => $group->id]) }}" class="mt-3 inline-flex items-center gap-1 text-sm font-bold text-sky-700 hover:text-sky-800">Assign a story →</a>
                </div>
            @else
                <div class="space-y-2">
                    @foreach ($group->assignments->take(3) as $assignment)
                        <a href="{{ route('teacher.assignments.show', $assignment) }}" class="block bg-white border border-[#E6DCCF] rounded-lg p-4 hover:border-sky-500 hover:shadow-[0_4px_12px_rgba(45,35,30,0.08)] transition-all">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <span class="block text-sm font-extrabold text-[#2D231E] truncate">{{ $assignment->title }}</span>
                                    <span class="block text-xs font-semibold text-[#2D231E] mt-0.5">{{ $assignment->story?->title ?? 'No story attached' }}</span>
                                </div>
                                <span class="shrink-0 {{ $assignment->status === 'active' ? 'bg-emerald-100 text-emerald-700' : ($assignment->status === 'cancelled' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700') }} text-[10px] font-bold uppercase tracking-wide px-2 py-1 rounded-full">
                                    {{ $assignment->status }}
                                </span>
                            </div>
                            <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-[11px] font-bold">
                                <span class="text-[#2D231E]">
                                    Due {{ $assignment->due_at?->format('M j, Y') ?? 'no due date' }}
                                </span>
                                @if ($assignment->status === 'active')
                                    <span class="{{ $assignment->isOverdue() ? 'text-rose-600' : 'text-emerald-700' }}">
                                        {{ $assignment->completedCount() }}/{{ $assignment->audienceCount() }} done
                                    </span>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Members --}}
        <div class="bg-white border border-[#E6DCCF] rounded-xl p-5">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                <h3 class="text-base font-extrabold text-[#2D231E]">Students ({{ $group->members->count() }})</h3>
                <a href="{{ route('teacher.classes.members.create', $group) }}" class="inline-flex items-center gap-1.5 min-h-[36px] text-xs font-bold px-3 py-1.5 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 transition-colors">
                    Invite students
                </a>
            </div>

            @if ($group->members->isEmpty())
                <p class="text-sm font-semibold text-[#2D231E]">No students yet. Share the join code above or invite them.</p>
            @else
                <ul class="divide-y divide-[#E6DCCF]">
                    @foreach ($group->members as $student)
                        <li class="flex items-center justify-between gap-3 py-2.5">
                            <div class="flex items-center gap-3 min-w-0">
                                <x-avatar :user="$student" :size="36" />
                                <span class="min-w-0">
                                    <span class="block text-sm font-bold text-[#2D231E] truncate">{{ trim($student->first_name.' '.$student->last_name) ?: ($student->nickname ?? strtok($student->email, '@')) }}</span>
                                    @if (filled(trim($student->first_name.' '.$student->last_name)))
                                        <span class="block text-xs font-semibold text-[#2D231E]">{{ $student->nickname ?? strtok($student->email, '@') }}</span>
                                    @endif
                                    <span class="block text-xs font-semibold text-[#2D231E]">{{ $student->email }}</span>
                                </span>
                            </div>
                            <form method="POST" action="{{ route('teacher.classes.remove-member', [$group, $student]) }}" data-submit-guard>
                                @csrf
                                @method('DELETE')
                                <button type="submit" data-confirm-open="remove-member-{{ $student->id }}" class="text-xs font-bold text-rose-600 hover:text-rose-700 cursor-pointer">
                                    <span data-submit-idle>Remove</span>
                                    <span data-submit-active class="hidden"><x-spinner label="Removing..." /></span>
                                </button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <x-modal id="delete-class-modal" title="Remove this class?">
        <p class="text-xs font-semibold text-[#2D231E]">
            This deletes the class, its assignments, and removes all students from it. They'll lose access to any class assignments. This can't be undone.
        </p>
        <div class="flex items-center justify-end gap-2">
            <button type="button" data-modal-close class="min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer">Cancel</button>
            <button type="button" data-confirm-approve class="inline-flex items-center justify-center min-h-[44px] px-5 py-2 bg-rose-600 text-white rounded-lg text-xs font-bold hover:bg-rose-700 transition-colors cursor-pointer">Remove class</button>
        </div>
    </x-modal>

    @foreach ($group->members as $student)
        <x-modal id="remove-member-{{ $student->id }}" title="Remove {{ trim($student->first_name.' '.$student->last_name) ?: ($student->nickname ?? strtok($student->email, '@')) }} from this class?">
            <p class="text-xs font-semibold text-[#2D231E]">
                {{ trim($student->first_name.' '.$student->last_name) ?: ($student->nickname ?? strtok($student->email, '@')) }} will lose access to this class's assignments. They can rejoin using the class code if needed.
            </p>
            <div class="flex items-center justify-end gap-2">
                <button type="button" data-modal-close class="min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer">Cancel</button>
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
