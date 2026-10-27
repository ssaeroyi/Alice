@extends('layouts.teacher')

@section('page', 'teacher-overview')
@section('title', 'Dashboard')
@section('eyebrow', 'Class overview')

@section('content')
    <div class="space-y-5">

        <!-- SNAPSHOT -->
        <p class="text-[10px] font-extrabold uppercase tracking-wider text-[#2D231E] flex items-center gap-1.5">
            <x-icons.home class="w-3.5 h-3.5" /> Snapshot
        </p>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <x-admin.stat-card label="Students" icon="users" :value="$counts['students']" hint="This is every real student account, including suspended ones. It opens the roster." :href="route('teacher.roster')" />
            <x-admin.stat-card label="Completed setup" icon="academic-cap" :value="$counts['setup_done_students']" hint="This is the students who finished the quick check and unlocked the app." :href="route('teacher.roster', ['setup' => 'done'])" />
            <x-admin.stat-card label="Active this week" icon="chart-line" :value="$counts['active_week']" hint="This is the students who signed in since Monday." :href="route('teacher.roster')" />
            <x-admin.stat-card label="Suspended" icon="ban" :value="$counts['suspended_students']" hint="This is the students blocked from signing in. It opens the roster filtered to suspended." :href="route('teacher.roster', ['status' => 'suspended'])" />
        </div>

        <!-- PERFORMANCE -->
        <p class="text-[10px] font-extrabold uppercase tracking-wider text-[#2D231E] flex items-center gap-1.5">
            <x-icons.chart-bar class="w-3.5 h-3.5" /> Performance
        </p>

        <form method="GET" action="{{ route('teacher.dashboard') }}" class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-1.5 ml-auto sm:ml-0">
                @foreach (\App\Support\DashboardRange::PRESETS as $preset)
                    <a
                        href="{{ route('teacher.dashboard', ['days' => $preset]) }}"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold transition-colors {{ $activePreset === $preset ? 'bg-emerald-600 text-white' : 'bg-white border border-[#E6DCCF] text-[#2D231E] hover:border-amber-300' }}"
                    >
                        {{ $preset }} days
                    </a>
                @endforeach
            </div>
            <div class="flex items-center gap-2 ml-auto">
                <x-admin.date-range label="{{ $range->humanWindow() }}" :from="$range->from" :to="$range->to" class="w-56" />
                <button
                    type="submit"
                    class="px-3 py-1.5 rounded-lg text-xs font-bold bg-white border border-[#E6DCCF] text-[#2D231E] hover:border-amber-300 transition-colors cursor-pointer"
                >
                    Apply
                </button>
            </div>
        </form>

        <x-admin.panel
            title="Class performance"
            icon="chart-bar"
            subtitle="School-wide, all students. Tap a metric to redraw the chart below it. The date range above applies to every tile."
            info="Each tile shows its own figure for the selected window while the chart shows how it moved."
        >
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 mt-3">
                @foreach ($metrics as $metric)
                    <x-admin.metric-tile
                        :metric="$metric['key']"
                        :label="$metric['label']"
                        :icon="$metric['icon']"
                        :value="$metric['headline']"
                        :href="route('teacher.dashboard', array_merge($tileBase, ['metric' => $metric['key']]))"
                        :active="$activeMetric === $metric['key']"
                    />
                @endforeach
            </div>

            <div data-metric-chart-wrap class="relative h-56 mt-4 transition-opacity">
                <canvas
                    data-metric-canvas
                    data-series-endpoint="{{ route('teacher.series') }}"
                    data-metric="{{ $activeMetric }}"
                    data-label="{{ $metricMeta['label'] }}"
                    data-unit="{{ $metricMeta['unit'] }}"
                    data-precision="{{ $metricMeta['precision'] }}"
                    data-zero="{{ $metricMeta['kind'] === 'avg' ? 0 : 1 }}"
                    data-chart="{{ $metricMeta['kind'] === 'avg' ? 'line' : 'bar' }}"
                    data-series="{{ json_encode($metricSeries, JSON_THROW_ON_ERROR | JSON_HEX_APOS | JSON_HEX_QUOT) }}"
                    class="absolute inset-0 w-full h-full"
                ></canvas>
            </div>

            <p data-metric-subtitle class="text-[11px] font-semibold text-[#2D231E] mt-2">{{ $metricMeta['label'] }} per {{ $range->bucketNoun() }}, {{ $range->humanWindow() }}.</p>
            <p data-metric-hint class="text-[11px] font-semibold text-[#2D231E] mt-1">{{ $metricMeta['hint'] }}</p>

            @php
                $activeDetailUrl = $metricMeta['detail_url'];
                $activeDetailLabel = $metricMeta['detail_label'];
                $activeHeadline = $metrics[$activeMetric]['headline'] ?? '—';
                $activeDetailHidden = $activeDetailUrl === null
                    || $activeDetailLabel === null
                    || in_array($activeHeadline, ['0', '—'], true);
            @endphp
            <a
                data-metric-detail
                href="{{ $activeDetailUrl }}"
                class="{{ $activeDetailHidden ? 'hidden' : '' }} mt-2 inline-flex items-center text-xs font-bold text-amber-700 hover:text-amber-800 transition-colors"
            >
                <span data-metric-detail-label>{{ $activeDetailLabel }}</span>
            </a>

            <p data-metric-empty class="{{ count($metricSeries) > 0 ? 'hidden' : '' }} text-sm font-semibold text-[#2D231E] mt-2">
                Nothing recorded for this metric in the selected range yet.
            </p>
            <p data-metric-chart-error class="hidden text-sm font-semibold text-[#B42318] mt-2">
                Charts could not load here. The tiles still update from the server.
            </p>
        </x-admin.panel>

        <!-- SKILL MASTERY -->
        <p class="text-[10px] font-extrabold uppercase tracking-wider text-[#2D231E] flex items-center gap-1.5">
            <x-icons.compass class="w-3.5 h-3.5" /> Skill mastery
        </p>

        <x-admin.panel
            title="Thinking levels"
            icon="compass"
            subtitle="Share of students scoring 70% or higher on quiz questions at each level, for the selected range."
            info="This is how the class handles each kind of thinking, read straight from quiz answers: Identification counts as Remember, multiple choice and true/false as Understand, Matching as Apply and sequencing as Analyze. Evaluate and Create are not measured because they need written answers. Students with no answers in the range are left out."
        >
            <form method="GET" action="{{ route('teacher.dashboard') }}" class="flex flex-wrap items-center gap-3 mb-4">
                @foreach ($tileBase as $param => $value)
                    @if ($param !== 'thinking_grade')
                        <input type="hidden" name="{{ $param }}" value="{{ $value }}">
                    @endif
                @endforeach
                <label class="flex items-center gap-2">
                    <span class="text-[11px] font-bold uppercase tracking-wide text-[#2D231E]">Grade</span>
                    <select
                        name="thinking_grade"
                        onchange="this.form.submit()"
                        class="h-10 px-3 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] focus:outline-none focus:border-[#2D231E] cursor-pointer"
                    >
                        <option value="">All grades</option>
                        @foreach (\App\Enums\GradeLevel::cases() as $gradeOption)
                            <option value="{{ $gradeOption->value }}" @selected($thinkingGrade === $gradeOption->value)>
                                {{ $gradeOption->label() }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </form>

            @if ($thinking['scored'] > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach (\App\Support\BloomLevels::ORDER as $level)
                        @php
                            $data = $thinking['levels'][$level];
                            $ringRadius = 18;
                            $ringCircumference = 2 * pi() * $ringRadius;
                            $ringOffset = $ringCircumference * (1 - min($data['pct'], 100) / 100);
                            $ringColor = $data['pct'] >= 70 ? '#34D399' : ($data['pct'] >= 40 ? '#FBBF24' : '#FB7185');
                        @endphp
                        <div class="rounded-xl border border-[#E6DCCF] bg-[#FAF6EE] px-4 py-3.5 flex items-start gap-3.5">
                            <svg class="w-12 h-12 shrink-0" viewBox="0 0 44 44" aria-hidden="true">
                                <circle cx="22" cy="22" r="{{ $ringRadius }}" fill="none" stroke="#F3ECE0" stroke-width="5" />
                                <circle
                                    cx="22" cy="22" r="{{ $ringRadius }}"
                                    fill="none"
                                    stroke="{{ $ringColor }}"
                                    stroke-width="5"
                                    stroke-linecap="round"
                                    stroke-dasharray="{{ $ringCircumference }}"
                                    stroke-dashoffset="{{ $ringOffset }}"
                                    transform="rotate(-90 22 22)"
                                />
                                <text x="22" y="23" text-anchor="middle" dominant-baseline="middle" font-size="12" font-weight="800" fill="#2D231E">{{ $data['pct'] }}%</text>
                            </svg>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-extrabold text-[#2D231E]">{{ \App\Support\BloomLevels::label($level) }}</p>
                                <p class="mt-1 text-[11px] font-semibold text-[#2D231E] leading-snug">
                                    Measures students who {{ \Illuminate\Support\Str::lcfirst(rtrim(\App\Support\BloomLevels::description($level), '.')) }}.
                                </p>
                                <p class="mt-1.5 text-[11px] font-bold text-[#2D231E]">
                                    {{ $data['strong'] }} of {{ $data['scored'] }} scored 70% or higher.
                                </p>
                                <a
                                    href="{{ route('teacher.roster', array_filter(['skill' => $level, 'band' => 'strong', 'grade' => $thinkingGrade], fn ($v) => $v !== '')) }}"
                                    class="mt-2 inline-flex items-center text-[11px] font-bold text-sky-600 hover:text-sky-700 transition-colors"
                                >See students</a>
                            </div>
                        </div>
                    @endforeach
                </div>
                <p class="mt-3 text-[11px] font-semibold text-[#2D231E]">
                    Evaluate and Create are not measured: they need written-answer questions, which quizzes do not have.
                </p>
            @else
                <p class="text-sm font-semibold text-[#2D231E]">No quiz answers in this range yet. The tiles fill in as students take quizzes.</p>
            @endif
        </x-admin.panel>

        <!-- READING LEVELS -->
        <p class="text-[10px] font-extrabold uppercase tracking-wider text-[#2D231E] flex items-center gap-1.5">
            <x-icons.book-open class="w-3.5 h-3.5" /> Reading levels
        </p>

        <x-admin.panel
            title="Where students stand"
            icon="book"
            subtitle="Live reading level from each student's recent reading and quiz evidence."
            info="This is each student's current comprehension level, kept live off recent reading and quiz evidence rather than the placement snapshot. Students with no quiz results yet are counted at their placement level and labeled as placement. Pick a band to open the roster filtered to it."
        >
            @if ($placementOnlyCount > 0)
                <p class="mb-3 text-[11px] font-semibold text-[#2D231E]">
                    <span class="font-extrabold">{{ $placementOnlyCount }}</span> placement only: finished placement, no quiz results yet. They are counted in the bands below at their placement level.
                </p>
            @endif
            @if (! empty($comprehensionLevels))
                <div class="flex flex-col sm:flex-row items-center gap-6">
                    {{-- Fixed-size wrapper: Chart.js responsive sizing would
                         otherwise stretch the canvas over the legend. --}}
                    <div class="relative w-44 h-44 shrink-0">
                        <canvas
                            data-reading-canvas
                            data-reading="{{ json_encode(array_map(fn ($level) => [
                                'key' => $level->value,
                                'label' => $level->label(),
                                'description' => $level->description(),
                                'count' => $comprehensionLevels[$level->value] ?? 0,
                                'href' => route('teacher.roster', ['comprehension' => $level->value]),
                            ], array_values(\App\Enums\ComprehensionLevel::cases())), JSON_THROW_ON_ERROR | JSON_HEX_APOS | JSON_HEX_QUOT) }}"
                            class="absolute inset-0 w-full h-full"
                        ></canvas>
                    </div>
                    <ul class="w-full space-y-2">
                        @foreach (\App\Enums\ComprehensionLevel::cases() as $level)
                            @php
                                $total = $comprehensionLevels[$level->value] ?? 0;
                                $grand = array_sum($comprehensionLevels);
                            @endphp
                            <li>
                                <a
                                    href="{{ route('teacher.roster', ['comprehension' => $level->value]) }}"
                                    class="flex items-center gap-3 rounded-lg px-3 py-2 border border-transparent hover:border-[#E6DCCF] hover:bg-[#FAF6EE] transition-colors"
                                >
                                    <span class="shrink-0 w-2.5 h-2.5 rounded-full {{ match ($level) {
                                        \App\Enums\ComprehensionLevel::Independent => 'bg-emerald-400',
                                        \App\Enums\ComprehensionLevel::Instructional => 'bg-amber-400',
                                        \App\Enums\ComprehensionLevel::Frustration => 'bg-orange-400',
                                        \App\Enums\ComprehensionLevel::Critical => 'bg-rose-400',
                                    } }}"></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-xs font-bold text-[#2D231E]">{{ $level->label() }}</span>
                                        <span class="block text-[11px] font-semibold text-[#2D231E]">{{ $level->description() }}</span>
                                    </span>
                                    <span class="shrink-0 text-[11px] font-extrabold text-[#2D231E] tabular-nums">
                                        {{ $total }} <span class="font-semibold text-[#2D231E]">({{ $grand > 0 ? round($total / $grand * 100) : 0 }}%)</span>
                                    </span>
                                    <x-icons.core.arrow-right class="shrink-0 w-3.5 h-3.5 text-[#2D231E]" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <p class="mt-4 pt-3 border-t border-[#F0E9DD] text-[11px] font-semibold text-[#2D231E] leading-relaxed">
                    <span class="font-extrabold text-[#2D231E]">How this is assessed.</span>
                    Each student gets a live score from recent quiz results and reading time, weighted toward the newest work. Bands:
                    <span class="font-bold text-[#2D231E]">85+ Independent</span>,
                    <span class="font-bold text-[#2D231E]">70-84 Instructional</span>,
                    <span class="font-bold text-[#2D231E]">50-69 Frustration</span>,
                    <span class="font-bold text-[#2D231E]">under 50 Critical</span>.
                    Placement covers the first 3 quizzes across 2 different texts, and a band only drops after the new score holds past the threshold.
                </p>
            @else
                <p class="text-sm font-semibold text-[#2D231E]">No reading levels yet. They appear as students read and take quizzes.</p>
            @endif
        </x-admin.panel>

        <!-- GROWTH -->
        <x-admin.panel
            title="Growth since placement"
            icon="chart-line"
            subtitle="Which way each student's live level is moving."
            info="Trend comes from the live reading-progress engine: up means the student's recent quiz evidence beat their placement band, down means it slipped below it, steady means it held. Students with no quiz results yet have no trend."
        >
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                <a href="{{ route('teacher.roster', ['trend' => 'up']) }}" class="rounded-xl border border-[#E6DCCF] bg-[#FAF6EE] px-3.5 py-3 hover:border-emerald-400 hover:bg-white transition-colors">
                    <p class="text-[11px] font-bold text-emerald-700 uppercase">Moving up</p>
                    <p class="mt-0.5 text-2xl font-extrabold text-[#2D231E] tabular-nums">{{ $growthTrends['up'] }}</p>
                </a>
                <a href="{{ route('teacher.roster', ['trend' => 'steady']) }}" class="rounded-xl border border-[#E6DCCF] bg-[#FAF6EE] px-3.5 py-3 hover:border-sky-400 hover:bg-white transition-colors">
                    <p class="text-[11px] font-bold text-sky-700 uppercase">Steady</p>
                    <p class="mt-0.5 text-2xl font-extrabold text-[#2D231E] tabular-nums">{{ $growthTrends['steady'] }}</p>
                </a>
                <a href="{{ route('teacher.roster', ['trend' => 'down']) }}" class="rounded-xl border border-[#E6DCCF] bg-[#FAF6EE] px-3.5 py-3 hover:border-rose-400 hover:bg-white transition-colors">
                    <p class="text-[11px] font-bold text-rose-600 uppercase">Slipping</p>
                    <p class="mt-0.5 text-2xl font-extrabold text-[#2D231E] tabular-nums">{{ $growthTrends['down'] }}</p>
                </a>
                <a href="{{ route('teacher.roster', ['trend' => 'none']) }}" class="rounded-xl border border-[#E6DCCF] bg-[#FAF6EE] px-3.5 py-3 hover:border-amber-400 hover:bg-white transition-colors">
                    <p class="text-[11px] font-bold text-[#8C7B70] uppercase">No live result</p>
                    <p class="mt-0.5 text-2xl font-extrabold text-[#2D231E] tabular-nums">{{ $growthTrends['none'] }}</p>
                </a>
            </div>
        </x-admin.panel>

        <!-- CLASSWORK -->
        <p class="text-[10px] font-extrabold uppercase tracking-wider text-[#2D231E] flex items-center gap-1.5">
            <x-icons.landmark class="w-3.5 h-3.5" /> Classwork
        </p>

        <x-admin.panel
            title="Class & assignments"
            icon="academic-cap"
            info="Overdue counts active assignments whose due date has passed; due this week covers the next 7 days. An assignment is complete when every student in the class has finished reading it and the quiz."
        >
            <x-slot:actions>
                <div class="hidden sm:flex flex-wrap items-center justify-end gap-1.5">
                    <span class="rounded-lg border border-[#E6DCCF] bg-[#FAF6EE] px-2 py-1 text-[11px] font-bold text-[#2D231E] tabular-nums">{{ $assignmentCounts['active'] }} active</span>
                    <span class="rounded-lg border border-amber-200 bg-amber-50 px-2 py-1 text-[11px] font-bold text-amber-800 tabular-nums">{{ $assignmentCounts['due_week'] }} due this week</span>
                    <span class="rounded-lg border px-2 py-1 text-[11px] font-bold tabular-nums {{ $assignmentCounts['overdue'] > 0 ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-[#E6DCCF] bg-[#FAF6EE] text-[#2D231E]' }}">{{ $assignmentCounts['overdue'] }} overdue</span>
                </div>
            </x-slot:actions>

            {{-- Mobile: the count pills live in the body so the header never wraps messily --}}
            <div class="sm:hidden flex flex-wrap items-center gap-1.5 pb-3 border-b border-[#F0E9DD] mb-3">
                <span class="rounded-lg border border-[#E6DCCF] bg-[#FAF6EE] px-2 py-1 text-[11px] font-bold text-[#2D231E] tabular-nums">{{ $assignmentCounts['active'] }} active</span>
                <span class="rounded-lg border border-amber-200 bg-amber-50 px-2 py-1 text-[11px] font-bold text-amber-800 tabular-nums">{{ $assignmentCounts['due_week'] }} due this week</span>
                <span class="rounded-lg border px-2 py-1 text-[11px] font-bold tabular-nums {{ $assignmentCounts['overdue'] > 0 ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-[#E6DCCF] bg-[#FAF6EE] text-[#2D231E]' }}">{{ $assignmentCounts['overdue'] }} overdue</span>
            </div>

            @if ($activeAssignments->isNotEmpty())
                <ul class="divide-y divide-[#F0E9DD]">
                    @foreach ($activeAssignments as $assignment)
                        @php
                            $compliance = $assignment->compliancePercent();
                            $ringColor = $compliance >= 70 ? '#34D399' : ($compliance >= 40 ? '#FBBF24' : '#FB7185');
                            $ringCirc = 2 * pi() * 14;
                        @endphp
                        <li class="py-3 -mx-2 px-2 rounded-lg hover:bg-[#FAF6EE] transition-colors">
                            <div class="flex items-center justify-between gap-4">
                                <a href="{{ route('teacher.assignments.show', $assignment) }}" class="min-w-0 flex-1 group">
                                    <p class="text-sm font-bold text-[#2D231E] truncate group-hover:text-sky-600 transition-colors">{{ $assignment->title }}</p>
                                    <p class="text-[11px] font-semibold text-[#2D231E]">
                                        {{ $assignment->group?->name ?? 'Whole class' }}
                                        @if ($assignment->due_at)
                                            &middot; {{ $assignment->isOverdue() ? 'was due '.$assignment->due_at->format('M j') : 'due '.$assignment->due_at->format('M j') }}
                                        @else
                                            &middot; no due date
                                        @endif
                                    </p>
                                </a>
                                @if ($assignment->due_at && $assignment->isOverdue())
                                    <x-admin.pill tone="crit">Overdue</x-admin.pill>
                                @endif
                                <svg class="w-9 h-9 shrink-0" viewBox="0 0 36 36" aria-hidden="true">
                                    <circle cx="18" cy="18" r="14" fill="none" stroke="#F3ECE0" stroke-width="4" />
                                    <circle cx="18" cy="18" r="14" fill="none" stroke="{{ $ringColor }}" stroke-width="4" stroke-linecap="round" stroke-dasharray="{{ $ringCirc }}" stroke-dashoffset="{{ $ringCirc * (1 - $compliance / 100) }}" transform="rotate(-90 18 18)" />
                                    <text x="18" y="18" text-anchor="middle" dominant-baseline="central" font-size="9" font-weight="800" fill="#2D231E">{{ $compliance }}%</text>
                                </svg>
                            </div>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-2">
                    <a href="{{ route('teacher.assignments.index') }}" class="text-[11px] font-bold text-sky-600 hover:text-sky-700 transition-colors">View all assignments</a>
                </div>
            @else
                <p class="text-sm font-semibold text-[#2D231E]">
                    No active assignments yet.
                    <a href="{{ route('teacher.assignments.create') }}" class="text-sky-600 hover:text-sky-700 transition-colors font-bold">create your first</a>
                </p>
            @endif
        </x-admin.panel>

        <!-- CLASS MAKEUP -->
        <p class="text-[10px] font-extrabold uppercase tracking-wider text-[#2D231E] flex items-center gap-1.5">
            <x-icons.users class="w-3.5 h-3.5" /> Class makeup
        </p>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

            <!-- GRADE BREAKDOWN -->
            <x-admin.panel
                title="Students by grade"
                icon="users"
                subtitle="Effective grade (advances each school year)."
                info="The grade a student reported during the placement quick check, advanced for each new school year and capped at Grade 10. Students without a grade yet are not shown. Click a slice to open the roster filtered to that grade."
            >
                @if (! empty($gradeBreakdown))
                    @php
                        $gradeTotal = array_sum($gradeBreakdown);
                        $gradeCount = count($gradeBreakdown);
                        $gradePalette = ['#34D399', '#38BDF8', '#FBBF24', '#A78BFA', '#FB7185', '#2DD4BF', '#F472B6'];
                    @endphp
                    <div class="flex flex-col sm:flex-row items-center gap-6">
                        <div class="relative w-40 h-40 shrink-0">
                            <canvas
                                data-grade-canvas
                                data-grade='@json(collect($gradeBreakdown)->map(fn ($total, $grade) => [
                                    "grade" => (string) $grade,
                                    "total" => (int) $total,
                                    "color" => $gradePalette[($grade - 7) % count($gradePalette)] ?? "#94A3B8",
                                    "href" => route("teacher.roster", ["grade" => $grade]),
                                ])->values())'
                                class="absolute inset-0 w-full h-full"
                            ></canvas>
                        </div>
                        <ul class="w-full space-y-2">
                            @foreach ($gradeBreakdown as $grade => $total)
                                <li>
                                    <a href="{{ route('teacher.roster', ['grade' => $grade]) }}" class="flex items-center gap-3 rounded-lg px-3 py-2 border border-transparent hover:border-[#E6DCCF] hover:bg-[#FAF6EE] transition-colors">
                                        <span class="shrink-0 w-2.5 h-2.5 rounded-full" style="background: {{ $gradePalette[($grade - 7) % count($gradePalette)] ?? '#94A3B8' }}"></span>
                                        <span class="min-w-0 flex-1 text-xs font-bold text-[#2D231E]">Grade {{ $grade }}</span>
                                        <span class="shrink-0 text-[11px] font-extrabold text-[#2D231E] tabular-nums">
                                            {{ $total }} <span class="font-semibold">({{ $gradeTotal > 0 ? round($total / $gradeTotal * 100) : 0 }}%)</span>
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @else
                    <p class="text-sm font-semibold text-[#2D231E]">No students with a reported grade yet.</p>
                @endif
            </x-admin.panel>

            <!-- TEXT DIFFICULTY -->
            <x-admin.panel
                title="Stories to watch"
                icon="magnifier"
                subtitle="Stories the class scores lowest on, at least 3 attempts."
                info="This is each story's average quiz score across the class, lowest first. A story only appears after at least 3 attempts so one bad day never brands a story. Open the story list to reassign or swap them."
            >
                <x-slot:actions>
                    <a href="{{ route('teacher.stories.index') }}" class="text-[11px] font-bold text-sky-600 hover:text-sky-700 transition-colors">Story list</a>
                </x-slot:actions>
                @if ($textDifficulty->isNotEmpty())
                    @php $worst = $textDifficulty->max('avg') ?: 100; @endphp
                    <ul class="divide-y divide-[#F0E9DD]">
                        @foreach ($textDifficulty as $row)
                            <li class="py-2.5">
                                <div class="flex items-center gap-3">
                                    <span class="shrink-0 w-14 text-center rounded-lg px-2 py-1.5 text-xs font-extrabold tabular-nums {{ $row['avg'] >= 75 ? 'bg-emerald-100 text-emerald-700' : ($row['avg'] >= 60 ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700') }}">
                                        {{ $row['avg'] }}%
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs font-bold text-[#2D231E] truncate">{{ $row['title'] }}</p>
                                        <p class="text-[11px] font-semibold text-[#2D231E]">{{ $row['attempts'] }} attempts</p>
                                    </div>
                                    <a href="{{ route('teacher.stories.index') }}" class="shrink-0 text-[11px] font-bold text-sky-600 hover:text-sky-700 transition-colors">Open</a>
                                </div>
                                {{-- Score bar: visual weight per story, red to green --}}
                                <div class="mt-1.5 ml-[4.25rem] h-2 rounded-full bg-[#F3ECE0] overflow-hidden">
                                    <div class="h-full rounded-full {{ $row['avg'] >= 75 ? 'bg-emerald-500' : ($row['avg'] >= 60 ? 'bg-amber-400' : 'bg-rose-400') }}" style="width: {{ min(100, round($row['avg'] / max($worst, 1) * 100)) }}%"></div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-sm font-semibold text-[#2D231E]">No story has 3 attempts yet. This fills in as the class quizzes.</p>
                @endif
            </x-admin.panel>

        </div>

        <!-- ATTENTION -->
        <p class="text-[10px] font-extrabold uppercase tracking-wider text-[#2D231E] flex items-center gap-1.5">
            <x-icons.face-sad class="w-3.5 h-3.5" /> Attention
        </p>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

            <!-- STRUGGLING STUDENTS -->
            <x-admin.panel
                title="Needs attention"
                icon="face-sad"
                subtitle="Students who likely need a nudge."
                info="A student appears here when their account is suspended, they have not finished the placement quick check, have not signed in for two weeks, or their comprehension is slipping (failing a recent string of quizzes). This list is the teacher's starting point. Visit the roster for full detail."
            >
                <x-slot:actions>
                    <a href="{{ route('teacher.roster') }}" class="text-[11px] font-bold text-sky-600 hover:text-sky-700 transition-colors">View all</a>
                </x-slot:actions>
                @if ($struggling->isNotEmpty())
                    <ul class="divide-y divide-[#F0E9DD]">
                        @foreach ($struggling as $student)
                            <li class="py-2.5 flex items-center justify-between gap-4">
                                <a href="{{ route('teacher.student', $student) }}" class="min-w-0 flex items-center gap-3 group">
                                    <div class="min-w-0">
                                        <p class="text-xs font-bold text-[#2D231E] truncate group-hover:text-sky-600 transition-colors">
                                            {{ $student->nickname ?? strtok($student->email, '@') }}
                                        </p>
                                        <p class="text-[11px] font-semibold text-[#2D231E]">
                                            {{ $student->last_login_at?->diffForHumans() ?? 'Never signed in' }}
                                        </p>
                                    </div>
                                </a>
                                @if ($student->suspended_at)
                                    <x-admin.pill tone="crit">Suspended</x-admin.pill>
                                @elseif (! $student->is_setup_complete)
                                    <x-admin.pill tone="warn">Setup pending</x-admin.pill>
                                @elseif ($student->readingProgress?->slipping)
                                    <x-admin.pill tone="crit">Slipping</x-admin.pill>
                                @else
                                    <x-admin.pill>No recent sign-in</x-admin.pill>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-sm font-semibold text-[#2D231E]">No students need attention right now.</p>
                @endif
            </x-admin.panel>

            <!-- RECENT ACTIVITY (two columns) -->
            <x-admin.panel title="Recent activity" icon="eye">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-5">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-[#2D231E] mb-1">Active</p>
                        @if ($recentActive->isNotEmpty())
                            <ul class="divide-y divide-[#F0E9DD]">
                                @foreach ($recentActive as $student)
                                    <li class="py-2.5 flex items-center justify-between gap-3">
                                        <a href="{{ route('teacher.student', $student) }}" class="min-w-0 flex items-center gap-2.5 group">
                                            <x-avatar :user="$student" :size="32" />
                                            <span class="min-w-0">
                                                <p class="text-xs font-bold text-[#2D231E] truncate group-hover:text-sky-600 transition-colors">
                                                    {{ $student->nickname ?? strtok($student->email, '@') }}
                                                </p>
                                            </span>
                                        </a>
                                        <span class="shrink-0 text-[11px] font-semibold text-[#2D231E]">
                                            {{ $student->last_login_at?->diffForHumans() }}
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-xs font-semibold text-[#2D231E]">No sign-ins recorded yet.</p>
                        @endif
                    </div>
                    <div class="mt-5 sm:mt-0 sm:border-l sm:border-[#F0E9DD] sm:pl-5">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-[#2D231E] mb-1">Joined</p>
                        @if ($recentJoins->isNotEmpty())
                            <ul class="divide-y divide-[#F0E9DD]">
                                @foreach ($recentJoins as $student)
                                    <li class="py-2.5 flex items-center justify-between gap-3">
                                        <a href="{{ route('teacher.student', $student) }}" class="min-w-0 flex items-center gap-2.5 group">
                                            <x-avatar :user="$student" :size="32" />
                                            <span class="min-w-0">
                                                <p class="text-xs font-bold text-[#2D231E] truncate group-hover:text-sky-600 transition-colors">
                                                    {{ $student->nickname ?? strtok($student->email, '@') }}
                                                </p>
                                            </span>
                                        </a>
                                        <span class="shrink-0 text-[11px] font-semibold text-[#2D231E]">
                                            {{ $student->created_at->diffForHumans() }}
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-xs font-semibold text-[#2D231E]">No student accounts yet.</p>
                        @endif
                    </div>
                </div>
            </x-admin.panel>

        </div>

    </div>
@endsection
