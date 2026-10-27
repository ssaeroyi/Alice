@extends('layouts.teacher')

@section('title', 'Students')
@section('eyebrow', 'Roster')

@section('content')
    <div class="space-y-5">

        @php
            $rosterFilterCount = collect([
                request('grade') ? 1 : 0,
                request('comprehension') ? 1 : 0,
                request('status') ? 1 : 0,
                request('setup') ? 1 : 0,
                request('attention') ? 1 : 0,
                request('presence') ? 1 : 0,
            ])->sum();
        @endphp

        <x-admin.panel>
            <div class="flex flex-wrap items-center gap-2">
            <x-admin.search-input
                :action="route('teacher.roster')"
                :value="request('q')"
                placeholder="Search nickname or email"
                label="Search students"
                form-class="relative min-w-0 flex-1"
            />

            <div class="flex flex-wrap items-center gap-2">
            <x-admin.filter-drawer
                label="Filters"
                :action="route('teacher.roster')"
                :count="$rosterFilterCount"
                search-name=""
                :reset-url="route('teacher.roster')"
            >
                <input type="hidden" name="q" value="{{ request('q') }}">

                <label class="block">
                    <span class="text-[11px] font-bold uppercase tracking-wide text-[#2D231E]">Grade</span>
                    <select name="grade" class="mt-1 w-full h-11 px-3 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] focus:outline-none focus:border-[#2D231E] cursor-pointer">
                        <option value="">Any grade</option>
                        @foreach (\App\Enums\GradeLevel::cases() as $gradeOption)
                            <option value="{{ $gradeOption->value }}" @selected(request('grade') === $gradeOption->value)>
                                {{ $gradeOption->label() }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-[11px] font-bold uppercase tracking-wide text-[#2D231E]">Comprehension</span>
                    <select name="comprehension" class="mt-1 w-full h-11 px-3 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] focus:outline-none focus:border-[#2D231E] cursor-pointer">
                        <option value="">Any comprehension</option>
                        @foreach (\App\Enums\ComprehensionLevel::cases() as $levelOption)
                            <option value="{{ $levelOption->value }}" @selected(request('comprehension') === $levelOption->value)>
                                {{ $levelOption->label() }}
                            </option>
                        @endforeach
                        <option value="none" @selected(request('comprehension') === 'none')>No result yet</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-[11px] font-bold uppercase tracking-wide text-[#2D231E]">Status</span>
                    <select name="status" class="mt-1 w-full h-11 px-3 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] focus:outline-none focus:border-[#2D231E] cursor-pointer">
                        <option value="">Any status</option>
                        <option value="active" @selected(request('status') === 'active')>Active</option>
                        <option value="suspended" @selected(request('status') === 'suspended')>Suspended</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-[11px] font-bold uppercase tracking-wide text-[#2D231E]">Setup</span>
                    <select name="setup" class="mt-1 w-full h-11 px-3 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] focus:outline-none focus:border-[#2D231E] cursor-pointer">
                        <option value="">Any setup</option>
                        <option value="done" @selected(request('setup') === 'done')>Setup done</option>
                        <option value="pending" @selected(request('setup') === 'pending')>Setup pending</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-[11px] font-bold uppercase tracking-wide text-[#2D231E]">Presence</span>
                    <select name="presence" class="mt-1 w-full h-11 px-3 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] focus:outline-none focus:border-[#2D231E] cursor-pointer">
                        <option value="">Any presence</option>
                        <option value="online" @selected(request('presence') === 'online')>Online now</option>
                        <option value="recent" @selected(request('presence') === 'recent')>Recently active (24h)</option>
                        <option value="away" @selected(request('presence') === 'away')>Away (over a day)</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-[11px] font-bold uppercase tracking-wide text-[#2D231E]">Needs attention</span>
                    <select name="attention" class="mt-1 w-full h-11 px-3 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] focus:outline-none focus:border-[#2D231E] cursor-pointer">
                        <option value="">Any</option>
                        <option value="slipping" @selected(request('attention') === 'slipping')>Comprehension slipping</option>
                    </select>
                </label>
            </x-admin.filter-drawer>
            </div>
            </div>

            <p class="mt-3 text-xs font-semibold text-[#2D231E]">
                {{ $students->total() }} student{{ $students->total() === 1 ? '' : 's' }} found
            </p>
        </x-admin.panel>

        {{-- Mobile: stacked card list replaces the table entirely. --}}
        <x-admin.panel class="md:hidden">
            @forelse ($students as $student)
                <a href="{{ route('teacher.student', $student) }}" class="block py-3 border-b border-[#F0E9DD] last:border-b-0 hover:bg-[#FAF6EE] transition-colors -mx-2 px-2 rounded-lg">
                    <div class="flex items-center gap-3 min-w-0">
                        <x-avatar :user="$student" :size="40" />
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-[#2D231E] truncate">
                                {{ trim($student->first_name.' '.$student->last_name) ?: ($student->nickname ?? strtok($student->email, '@')) }}
                            </p>
                            <p class="text-[11px] font-semibold text-[#2D231E] truncate">{{ $student->email }}</p>
                        </div>
                    </div>
                    <div class="mt-2 flex flex-wrap items-center gap-1.5">
                        <x-presence-dot :at="$student->lastActiveAt()" compact />
                        @if ($student->grade_level)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#F3ECE0] text-[#2D231E]">Grade {{ $student->effectiveGrade() }}</span>
                        @endif
                        @if ($student->suspended_at)
                            <x-admin.pill tone="crit">Suspended</x-admin.pill>
                        @elseif ($student->is_setup_complete)
                            <x-admin.pill tone="ok">Active</x-admin.pill>
                        @else
                            <x-admin.pill>Setup pending</x-admin.pill>
                        @endif
                    </div>
                </a>
            @empty
                <p class="text-sm font-bold text-[#2D231E] py-8 text-center">No students match these filters.</p>
            @endforelse
            <div class="pt-4">
                {{ $students->links('pagination::teacher') }}
            </div>
        </x-admin.panel>

        <x-admin.panel class="hidden md:block">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-[#E6DCCF] text-[11px] uppercase tracking-wider text-[#2D231E]">
                            <th class="px-4 py-3 font-extrabold">Student</th>
                            <th class="px-4 py-3 font-extrabold whitespace-nowrap">Grade</th>
                            <th class="px-4 py-3 font-extrabold whitespace-nowrap">Reading grade</th>
                            <th class="px-4 py-3 font-extrabold whitespace-nowrap">Activity</th>
                            <th class="px-4 py-3 font-extrabold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#F0E9DD]">
                        @forelse ($students as $student)
                            <tr class="odd:bg-[#FAF6EE] hover:bg-[#F3ECE0] transition-colors">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <x-avatar :user="$student" :size="36" />
                                        <div class="min-w-0">
                                            <a href="{{ route('teacher.student', $student) }}" class="block font-bold text-sky-700 hover:text-sky-800 hover:underline underline-offset-2 truncate max-w-[220px]">
                                                {{ trim($student->first_name.' '.$student->last_name) ?: ($student->nickname ?? strtok($student->email, '@')) }}
                                            </a>
                                            <p class="text-[11px] font-semibold text-[#2D231E] truncate max-w-[220px]">{{ $student->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-xs font-bold text-[#2D231E]">
                                    {{ $student->grade_level ? 'Grade '.$student->effectiveGrade() : '—' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-xs font-bold text-[#2D231E]">
                                        @php $served = $student->readingProgress?->reading_grade ?? $student->reading_level; @endphp
                                        {{ $served ? 'Grade '.$served : '—' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <x-presence-dot :at="$student->lastActiveAt()" compact />
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if ($student->suspended_at)
                                        <x-admin.pill tone="crit">Suspended</x-admin.pill>
                                    @elseif ($student->is_setup_complete)
                                        <x-admin.pill tone="ok">Active</x-admin.pill>
                                    @else
                                        <x-admin.pill>Setup pending</x-admin.pill>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-10 text-center">
                                    <p class="text-sm font-bold text-[#2D231E]">No students match these filters.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

            <div class="pt-4">
                {{ $students->links('pagination::teacher') }}
            </div>
        </x-admin.panel>

    </div>
@endsection