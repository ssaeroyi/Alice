@extends('layouts.admin')

@section('page', 'admin-dashboard')
@section('title', 'Dashboard')
@section('eyebrow', 'Overview')

@section('content')
    <div class="space-y-5">

        <!-- STAT CARDS + VITALS -->
        @php
            $uptime = $vitals['uptime_seconds'];
            $uptimeBootedAt = $vitals['uptime_booted_at'];
            $uptimeLabel = 'n/a';
            if ($uptime !== null) {
                $days = intdiv($uptime, 86400);
                $hours = intdiv($uptime % 86400, 3600);
                $minutes = intdiv($uptime % 3600, 60);
                $seconds = $uptime % 60;
                $uptimeLabel = "{$days}d {$hours}h {$minutes}m {$seconds}s";
            }
        @endphp
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            <x-admin.stat-card label="Total accounts" icon="users" href="{{ route('admin.accounts.index') }}" :value="$counts['users']" hint="This is every account on Alice, all three roles together. Opens the Accounts page." />
            <x-admin.stat-card label="Students" icon="user" href="{{ route('admin.accounts.index', ['role' => 'student']) }}" :value="$counts['students']" hint="This is every student account, including suspended ones. Opens the Accounts page filtered to students." />
            <x-admin.stat-card label="Teachers" icon="academic-cap" href="{{ route('admin.accounts.index', ['role' => 'teacher']) }}" :value="$counts['teachers']" hint="This is every teacher account. Admins are counted in the total above but have no card of their own." />
            <x-admin.stat-card label="Suspended" icon="ban" href="{{ route('admin.accounts.index', ['status' => 'suspended']) }}" :value="$counts['suspended']" hint="This is the number of accounts blocked from signing in until you reactivate them. Opens the Accounts page filtered to suspended people." />
            <x-admin.stat-card label="Active sessions" icon="monitor" :value="$activeSessions" hint="This is the number of sessions open right now on every device. A quiet number outside school hours is normal." />
            <x-admin.stat-card
                label="Uptime"
                icon="clock"
                :value="$uptimeLabel"
                data-uptime="{{ $uptimeBootedAt !== null ? '1' : '' }}"
                data-booted-at="{{ $uptimeBootedAt ?? 0 }}"
                data-rendered-at="{{ time() }}"
                hint="This is how long the server has been running since it last rebooted. A value that just reset means the machine restarted."
            />
        </div>

        <!-- DATE RANGE -->
        <form method="GET" action="{{ route('admin.dashboard') }}" class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-1.5 ml-auto sm:ml-0">
                @foreach (\App\Support\DashboardRange::PRESETS as $preset)
                    <a
                        href="{{ route('admin.dashboard', ['days' => $preset]) }}"
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

        <!-- PLATFORM HEALTH EXPLORER -->
        <x-admin.panel
            title="Platform health"
            icon="heart"
            subtitle="Tap a metric to redraw the chart below it. The date range above applies to every tile."
            info="Each tile shows its own figure for the selected window while the chart shows how it moved. Traffic tiles include today, and the nightly roll-up fills the finished days."
        >
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 mt-3">
                @foreach ($metrics as $metric)
                    <x-admin.metric-tile
                        :metric="$metric['key']"
                        :label="$metric['label']"
                        :icon="$metric['icon']"
                        :value="$metric['headline']"
                        :href="route('admin.dashboard', array_merge($tileBase, ['metric' => $metric['key']]))"
                        :active="$activeMetric === $metric['key']"
                    />
                @endforeach
            </div>

            <div data-metric-chart-wrap class="relative h-56 mt-4 transition-opacity">
                <canvas
                    data-metric-canvas
                    data-series-endpoint="{{ route('admin.dashboard.series') }}"
                    data-metric="{{ $activeMetric }}"
                    data-label="{{ $metricMeta['label'] }}"
                    data-unit="{{ $metricMeta['unit'] }}"
                    data-precision="{{ $metricMeta['precision'] }}"
                    data-zero="{{ $metricMeta['kind'] === 'latest' ? 0 : 1 }}"
                    data-series="{{ json_encode($metricSeries, JSON_THROW_ON_ERROR | JSON_HEX_APOS | JSON_HEX_QUOT) }}"
                    class="absolute inset-0 w-full h-full"
                ></canvas>
            </div>

            <p data-metric-subtitle class="text-[11px] font-semibold text-[#2D231E] mt-2">{{ $metricSubtitle }}</p>
            <p data-metric-hint class="text-[11px] font-semibold text-[#8C7B70] mt-1">{{ $metricMeta['hint'] }}</p>

            @php
                $activeDetailUrl = \App\Support\OpsMetrics::detailUrl($activeMetric);
                $activeDetailLabel = $metricMeta['detail_label'] ?? null;
                $activeTotal = $metrics[$activeMetric]['headline'] ?? '—';
                $activeDetailHidden = $activeDetailUrl === null
                    || $activeDetailLabel === null
                    || in_array($activeTotal, ['0', '—'], true);
            @endphp
            <a
                data-metric-detail
                href="{{ $activeDetailUrl }}"
                class="{{ $activeDetailHidden ? 'hidden' : '' }} mt-2 inline-flex items-center gap-1.5 text-xs font-bold text-amber-700 hover:text-amber-800 transition-colors"
            >
                <span data-metric-detail-label>{{ $activeDetailLabel }}</span>
                <x-icons.core.arrow-right class="w-3.5 h-3.5" />
            </a>

            <p data-metric-empty class="{{ count($metricSeries) > 0 ? 'hidden' : '' }} text-sm font-semibold text-[#2D231E] mt-2">
                Nothing recorded for this metric in the selected range yet.
            </p>
            <p data-metric-chart-error class="hidden text-sm font-semibold text-[#B42318] mt-2">
                Charts could not load here. The tiles still update from the server.
            </p>
        </x-admin.panel>

        <!-- GROWTH + ONBOARDING -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <x-admin.panel
                title="Account growth"
                icon="chart-line"
                href="{{ route('admin.accounts.index', ['role' => 'student', 'sort' => 'newest']) }}"
                subtitle="Students registered per {{ $range->bucketNoun() }}, {{ $range->humanWindow() }}."
                info="Tracks new student accounts per day so you can see whether sign-ups are climbing or stalled. Preview accounts are excluded."
            >
                @if (count(array_filter($growthSeries, fn ($p) => $p['count'] > 0)) > 0)
                    <div class="relative h-56">
                        <canvas
                            data-growth-canvas
                            data-series="{{ json_encode($growthSeries, JSON_THROW_ON_ERROR | JSON_HEX_APOS | JSON_HEX_QUOT) }}"
                            class="absolute inset-0 w-full h-full"
                        ></canvas>
                    </div>
                @else
                    <p class="text-sm font-semibold text-[#2D231E]">No student accounts registered in this date range yet.</p>
                @endif
            </x-admin.panel>

            <x-admin.panel
                title="Onboarding"
                icon="compass"
                href="{{ route('admin.accounts.index', ['setup' => 'pending']) }}"
                subtitle="Quick checks finished per {{ $range->bucketNoun() }}, {{ $range->humanWindow() }}."
                info="Counts students who finished the quick check, the step that unlocks the app. Registered students sitting here have not started yet."
            >
                @if ($counts['students'] > 0)
                    <div class="flex items-center justify-between text-xs font-bold text-[#2D231E]">
                        <span>{{ $setupRate }}% complete</span>
                        <span class="text-[#2D231E]">
                            {{ $counts['setup_done_students'] }} of {{ $counts['students'] }} students finished the quick check{{ $setupPending > 0 ? ' — '.$setupPending.' still pending' : '' }}
                        </span>
                    </div>
                    @if (collect($onboardingSeries)->sum('count') > 0)
                        <div class="relative h-56 mt-3">
                            <canvas
                                data-onboarding-canvas
                                data-series="{{ json_encode($onboardingSeries, JSON_THROW_ON_ERROR | JSON_HEX_APOS | JSON_HEX_QUOT) }}"
                                class="absolute inset-0 w-full h-full"
                            ></canvas>
                        </div>
                    @else
                        <p class="text-sm font-semibold text-[#2D231E] mt-3">No quick checks finished in this date range yet.</p>
                    @endif
                @else
                    <p class="text-sm font-semibold text-[#2D231E]">
                        No student accounts yet — this fills in as students register and finish the placement quick check.
                    </p>
                @endif
            </x-admin.panel>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

            <!-- RECENT AUDIT -->
            <x-admin.panel title="Recent audit events">
                @if ($recentAudit->isEmpty())
                    <p class="text-sm font-semibold text-[#2D231E]">No audit events yet.</p>
                @else
                    <ul class="divide-y divide-[#F0E9DD]">
                        @foreach ($recentAudit as $entry)
                            <li class="py-2.5 flex items-center justify-between gap-4">
                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-[#2D231E] truncate">{{ $entry->action }}</p>
                                    <p class="text-[11px] font-semibold text-[#2D231E]">
                                        {{ $entry->actor?->nickname ?? $entry->actor?->email ?? 'System' }}
                                    </p>
                                </div>
                                <span class="shrink-0 text-[11px] font-semibold text-[#2D231E]">
                                    {{ $entry->created_at->diffForHumans() }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                    <div class="pt-3">
                        <a
                            href="{{ route('admin.logs.index') }}"
                            class="inline-flex items-center gap-1.5 text-xs font-bold text-amber-700 hover:text-[#2D231E] transition-colors"
                        >
                            View full audit log
                            <x-icons.core.arrow-right class="w-3.5 h-3.5" />
                        </a>
                    </div>
                @endif
            </x-admin.panel>

            <!-- RECENT JOINS -->
            <x-admin.panel title="Recently joined">
                @if ($recentJoins->isEmpty())
                    <p class="text-sm font-semibold text-[#2D231E]">No accounts yet.</p>
                @else
                    <ul class="divide-y divide-[#F0E9DD]">
                        @foreach ($recentJoins as $account)
                            <li class="py-2.5 flex items-center justify-between gap-4">
                                <div class="flex items-center gap-3 min-w-0">
                                    <x-avatar :user="$account" :size="32" />
                                    <div class="min-w-0">
                                        <p class="text-xs font-bold text-[#2D231E] truncate">
                                            {{ $account->nickname ?? strtok($account->email, '@') }}
                                        </p>
                                        <p class="text-[11px] font-semibold text-[#2D231E] truncate">{{ $account->email }}</p>
                                    </div>
                                </div>
                                <div class="shrink-0 flex items-center gap-2">
                                    <x-admin.pill :tone="$account->role === \App\Enums\Role::Admin ? 'warn' : ($account->role === \App\Enums\Role::Teacher ? 'ok' : 'neutral')">
                                        {{ $account->role->value }}
                                    </x-admin.pill>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    <div class="pt-3">
                        <a
                            href="{{ route('admin.accounts.index') }}"
                            class="inline-flex items-center gap-1.5 text-xs font-bold text-amber-700 hover:text-[#2D231E] transition-colors"
                        >
                            Manage accounts
                            <x-icons.core.arrow-right class="w-3.5 h-3.5" />
                        </a>
                    </div>
                @endif
            </x-admin.panel>

        </div>

    </div>
@endsection
