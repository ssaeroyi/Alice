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

        <x-admin.panel>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-[#E6DCCF] text-[11px] uppercase tracking-wider text-[#2D231E]">
                            <th class="px-4 py-3 font-extrabold">Student</th>
                            <th class="px-4 py-3 font-extrabold hidden sm:table-cell">Grade</th>
                            <th class="px-4 py-3 font-extrabold hidden md:table-cell">Reading grade</th>
                            <th class="px-4 py-3 font-extrabold hidden lg:table-cell">Comprehension</th>
                            <th class="px-4 py-3 font-extrabold hidden lg:table-cell">Last login</th>
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
                                            <a href="{{ route('teacher.student', $student) }}" class="block font-bold text-sky-700 hover:text-sky-800 hover:underline underline-offset-2 truncate">
                                                {{ trim($student->first_name.' '.$student->last_name) ?: ($student->nickname ?? strtok($student->email, '@')) }}
                                            </a>
                                            <p class="text-[11px] font-semibold text-[#2D231E] truncate">{{ $student->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 hidden sm:table-cell text-xs font-bold text-[#2D231E]">
                                    {{ $student->grade_level ? 'Grade '.$student->effectiveGrade() : '—' }}
                                </td>
                                <td class="px-4 py-3 hidden md:table-cell text-xs font-bold text-[#2D231E]">
                                        @php $served = $student->readingProgress?->reading_grade ?? $student->reading_level; @endphp
                                        {{ $served ? 'Grade '.$served : '—' }}
                                </td>
                                <td class="px-4 py-3 hidden lg:table-cell">
                                    @php $rp = $student->readingProgress; @endphp
                                    @if ($rp?->comprehension_level)
                                        <div class="flex items-center gap-1.5">
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold {{ match ($rp->comprehension_level) {
                                                \App\Enums\ComprehensionLevel::Independent => 'bg-emerald-100 text-emerald-700',
                                                \App\Enums\ComprehensionLevel::Instructional => 'bg-amber-100 text-amber-700',
                                                \App\Enums\ComprehensionLevel::Frustration => 'bg-orange-100 text-orange-700',
                                                \App\Enums\ComprehensionLevel::Critical => 'bg-rose-100 text-rose-700',
                                            } }}">
                                                {{ $rp->comprehension_level->label() }}
                                            </span>
                                            @if ($rp->trend === 'up')
                                                <span class="text-emerald-600 font-bold" title="Moving up">▲</span>
                                            @elseif ($rp->trend === 'down')
                                                <span class="text-rose-500 font-bold" title="Moving down">▼</span>
                                            @endif
                                            @if ($rp->slipping)
                                                <x-admin.pill tone="crit">Slipping</x-admin.pill>
                                            @endif
                                        </div>
                                        @if ($rp->sample_count > 0)
                                            @php $basis = \App\Services\ReadingProgressService::basisFor($rp); @endphp
                                            @if ($basis !== 'from placement only')
                                                <p class="mt-1 text-[10px] font-semibold text-[#2D231E]">
                                                    {{ $basis }}
                                                </p>
                                            @endif
                                        @endif
                                    @elseif ($student->comprehension_level && $student->is_setup_complete)
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-[#F3ECE0] text-[#2D231E] border border-[#E6DCCF]">{{ $student->comprehension_level->label() }}</span>
                                        <p class="mt-1 text-[10px] font-semibold text-[#2D231E]">From placement, no quiz results yet</p>
                                    @else
                                        <span class="text-xs font-semibold text-[#2D231E]">No live result yet</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 hidden lg:table-cell text-xs font-semibold text-[#2D231E]">
                                    {{ $student->last_login_at?->diffForHumans() ?? 'Never' }}
                                </td>
                                <td class="px-4 py-3">
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
                                    <td colspan="6" class="px-4 py-10 text-center">
                                    <p class="text-sm font-bold text-[#2D231E]">No students match these filters.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pt-4">
                {{ $students->links('pagination::teacher') }}
            </div>
        </x-admin.panel>

    </div>
@endsection