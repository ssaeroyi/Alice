@extends('layouts.teacher')

@section('title', 'Invite to '.$group->name)
@section('eyebrow', 'Classes')

@section('content')
    <div class="max-w-3xl mx-auto">
        <a href="{{ route('teacher.classes.show', $group) }}" class="inline-flex items-center gap-1 text-sm font-bold text-[#2D231E] mb-2">
            <x-icons.core.arrow-left class="w-4 h-4" />
            Back to {{ $group->name }}
        </a>
        <p class="text-sm font-bold text-[#2D231E]">Select students. They'll get a "join this class" notification in their inbox.</p>

        <form method="POST" action="{{ route('teacher.classes.members.invite', $group) }}" class="mt-5 bg-white border border-[#E6DCCF] rounded-xl p-4 sm:p-6 space-y-4">
            @csrf

            @if ($availableStudents->isEmpty())
                <p class="text-sm font-semibold text-[#2D231E]">{{ $anyStudentsExist ? 'Every student is already in this class.' : 'There are no students to invite yet. They appear here once accounts exist.' }}</p>
            @else
                <div>
                    <label for="member-search" class="block text-sm font-extrabold text-[#2D231E] mb-1">Search students</label>
                    <input
                        id="member-search"
                        type="text"
                        placeholder="Search by name or nickname"
                        data-member-search
                        class="w-full px-3 py-2.5 border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] bg-white focus:outline-none focus:border-sky-600 mb-3"
                    >
                </div>

                <div class="max-h-80 overflow-y-auto border border-[#E6DCCF] rounded-lg p-2 space-y-1">
                    @foreach ($availableStudents as $student)
                        <label
                            class="flex items-center gap-3 px-2 py-1.5 hover:bg-[#FAF6EE] rounded cursor-pointer"
                            data-member-row
                            data-search='{{ strtolower(trim(($student->nickname ?? '').' '.$student->first_name.' '.$student->last_name.' '.$student->email)) }}'
                        >
                            <input type="checkbox" name="student_ids[]" value="{{ $student->id }}" class="accent-sky-600">
                            <x-avatar :user="$student" :size="32" />
                            <span class="min-w-0">
                                <span class="block text-sm font-bold text-[#2D231E] truncate">{{ $student->nickname ?? strtok($student->email, '@') }}</span>
                                <span class="block text-xs font-semibold text-[#2D231E] truncate">{{ $student->email }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>

                <p data-member-empty class="hidden text-sm font-bold text-[#2D231E] py-2 text-center">No students named that.</p>

                <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center gap-2 sm:gap-3">
                    <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center min-h-[44px] px-5 py-2.5 rounded-xl bg-emerald-600 text-white text-sm font-bold hover:bg-emerald-700 transition-colors cursor-pointer">Send invitations</button>
                    <a href="{{ route('teacher.classes.show', $group) }}" class="flex items-center justify-center w-full sm:w-auto px-4 py-2.5 rounded-lg text-sm font-bold text-[#2D231E]">Cancel</a>
                </div>
            @endif
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const search = document.querySelector('[data-member-search]');
            if (!search) {
                return;
            }
            const rows = Array.from(document.querySelectorAll('[data-member-row]'));
            const empty = document.querySelector('[data-member-empty]');
            search.addEventListener('input', function () {
                const q = search.value.trim().toLowerCase();
                let visible = 0;
                rows.forEach(function (row) {
                    const haystack = row.getAttribute('data-search') || '';
                    const show = !q || haystack.includes(q);
                    row.style.display = show ? '' : 'none';
                    if (show) {
                        visible++;
                    }
                });
                if (empty) {
                    empty.classList.toggle('hidden', !q || visible > 0);
                }
            });
        });
    </script>
@endpush
