@extends('layouts.admin')

@section('page', 'admin-status')
@section('title', 'System status')
@section('eyebrow', 'System')

@section('content')
    <div class="space-y-5">

        @php
            $app = $status['app'];
            $database = $status['database'];
            $cache = $status['cache'];
            $session = $status['session'];
            $queue = $status['queue'];
            $mail = $status['mail'];
            $storage = $status['storage'];
            $counts = $status['counts'];
        @endphp

        <!-- TABS -->
        <div data-status-tabs class="flex items-center gap-1 overflow-x-auto no-scrollbar border-b border-[#E6DCCF]">
            @foreach (['overview' => 'Overview', 'external' => 'External checks', 'dependencies' => 'Dependencies', 'traffic' => 'Traffic'] as $key => $label)
                <a
                    href="#{{ $key }}"
                    data-status-tab="{{ $key }}"
                    aria-current="{{ ($tab ?? 'overview') === $key ? 'true' : 'false' }}"
                    class="shrink-0 px-4 py-2.5 text-xs font-bold uppercase tracking-wide rounded-t-lg transition-colors {{ ($tab ?? 'overview') === $key ? 'bg-emerald-600 text-white' : 'bg-white text-[#2D231E]' }}"
                >{{ $label }}</a>
            @endforeach
        </div>

        <!-- ERROR LOG NOTICE -->
        @if (($errorLogCount ?? 0) > 0)
            <div class="flex flex-wrap items-center gap-2 px-3 py-3 sm:px-4 bg-amber-50 border border-amber-300 rounded-xl">
                <p class="text-sm font-semibold text-[#2D231E]">
                    The server log recorded {{ $errorLogCount }} {{ \Illuminate\Support\Str::plural('error', $errorLogCount) }} since the last roll-up.
                </p>
                <a
                    href="{{ route('admin.logs.index', ['tab' => 'server', 'status' => '5xx']) }}"
                    class="inline-flex items-center gap-1.5 text-xs font-bold text-amber-700 hover:text-amber-800 transition-colors"
                >
                    Review them in Logs
                    <x-icons.core.arrow-right class="w-3.5 h-3.5" />
                </a>
            </div>
        @endif

        <!-- OVERVIEW TAB -->
        <div data-status-panel="overview" class="space-y-5">

        <x-admin.panel
            title="Application"
            subtitle="What Alice is running on, and the switches that change behaviour."
        >
            <dl class="grid grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <dt class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E]">Platform</dt>
                    <dd class="mt-1 text-sm font-bold text-[#2D231E]">{{ $app['name'] }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E]">Framework</dt>
                    <dd class="mt-1 text-sm font-bold text-[#2D231E]">{{ $app['framework'] }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E]">PHP</dt>
                    <dd class="mt-1 text-sm font-bold text-[#2D231E]">{{ $app['php'] }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E] flex items-center gap-1.5">
                        Environment
                        <x-admin.info label="About the environment">
                            Which machine this copy of Alice thinks it is. Local means your development machine;
                            production means the live site students use.
                        </x-admin.info>
                    </dt>
                    <dd class="mt-1 text-sm font-bold text-[#2D231E] uppercase">{{ $app['env'] }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E] flex items-center gap-1.5">
                        Debug mode
                        <x-admin.info label="About debug mode">
                            Debug mode shows detailed error pages with stack traces to whoever hits an error. It reads
                            the APP_DEBUG setting in the .env file. It is ON in this local development copy (that is
                            expected and useful), and it must be OFF in production — leaving it on leaks internal file
                            paths and database queries to visitors.
                        </x-admin.info>
                    </dt>
                    <dd class="mt-1">
                        @if ($app['debug'])
                            <x-admin.pill tone="crit">On</x-admin.pill>
                        @else
                            <x-admin.pill tone="ok">Off</x-admin.pill>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E]">App URL</dt>
                    <dd class="mt-1 text-sm font-bold text-[#2D231E] truncate">{{ $app['url'] }}</dd>
                </div>
            </dl>
        </x-admin.panel>

        <x-admin.panel
            title="Database"
            subtitle="Where Alice stores everything: size, updates applied, last update."
        >
            <dl class="grid grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <dt class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E] flex items-center gap-1.5">
                        Driver
                        <x-admin.info label="About the driver">
                            The database software Alice stores everything in. MariaDB and MySQL are the usual
                            choices and behave the same way for Alice.
                        </x-admin.info>
                    </dt>
                    <dd class="mt-1 text-sm font-bold text-[#2D231E]">{{ $database['driver'] }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E]">Host</dt>
                    <dd class="mt-1 text-sm font-bold text-[#2D231E]">{{ $database['host'] }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E]">Database</dt>
                    <dd class="mt-1 text-sm font-bold text-[#2D231E] truncate">{{ $database['name'] }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E]">Size</dt>
                    <dd class="mt-1 text-sm font-bold text-[#2D231E]">
                        {{ $database['size_mb'] !== null ? $database['size_mb'].' MB' : 'Not measured (non-MySQL)' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E] flex items-center gap-1.5">
                        Migrations
                        <x-admin.info label="About migrations">
                            How many database updates have been applied so far. New deployments run any missing
                            ones automatically, so this number only ever grows.
                        </x-admin.info>
                    </dt>
                    <dd class="mt-1 text-sm font-bold text-[#2D231E]">{{ number_format($database['migrations']) }} updates applied</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E]">Last migration</dt>
                    <dd class="mt-1 text-sm font-bold text-[#2D231E] truncate">{{ $database['last_migration'] }}</dd>
                </div>
            </dl>
        </x-admin.panel>

        <x-admin.panel
            title="Services"
            subtitle="The background helpers Alice leans on: cache, sessions, jobs, outgoing email."
        >
            <dl class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <dt class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E] flex items-center gap-1.5">
                        Cache
                        <x-admin.info label="About the cache">
                            Where temporary data is kept between requests so Alice skips repeated work.
                            Database is fine for a small site; a dedicated cache gets faster under heavy load.
                        </x-admin.info>
                    </dt>
                    <dd class="mt-1 text-sm font-bold text-[#2D231E] capitalize">{{ $cache['driver'] }}</dd>
                    @if ($cache['entries'] !== null)
                        <dd class="text-[11px] font-semibold text-[#2D231E]">{{ $cache['entries'] }} cached keys</dd>
                    @endif
                </div>
                <div>
                    <dt class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E] flex items-center gap-1.5">
                        Sessions
                        <x-admin.info label="About sessions">
                            Where signed-in users are remembered. Database stores each session as a row, so a
                            student stays signed in until the session expires or it is cleared.
                        </x-admin.info>
                    </dt>
                    <dd class="mt-1 text-sm font-bold text-[#2D231E] capitalize">{{ $session['driver'] }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E] flex items-center gap-1.5">
                        Queue
                        <x-admin.info label="About the queue">
                            Background jobs that finish after the page responds, such as sending email or
                            building reports.
                        </x-admin.info>
                    </dt>
                    <dd class="mt-1 text-sm font-bold text-[#2D231E] capitalize">{{ $queue['connection'] }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E]">Mail</dt>
                    <dd class="mt-1 text-sm font-bold text-[#2D231E] capitalize">{{ $mail['mailer'] }}</dd>
                    <dd class="text-[11px] font-semibold text-[#2D231E]">
                        {{ $mail['host'] }}:{{ $mail['port'] }} &middot; {{ $mail['from'] }}
                    </dd>
                </div>
            </dl>
        </x-admin.panel>

        <x-admin.panel
            title="Storage"
            subtitle="Files Alice keeps on disk — uploads, cached pages, logs."
        >
            <dl class="grid grid-cols-2 gap-4">
                <div>
                    <dt class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E]">Used</dt>
                    <dd class="mt-1 text-sm font-bold text-[#2D231E]">
                        {{ $storage['bytes'] >= 1048576
                            ? number_format($storage['bytes'] / 1048576, 2).' MB'
                            : number_format($storage['bytes'] / 1024, 1).' KB' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E]">Location</dt>
                    <dd class="mt-1 text-sm font-bold text-[#2D231E] truncate">{{ storage_path() }}</dd>
                </div>
            </dl>
        </x-admin.panel>

        </div>

        <!-- EXTERNAL TAB -->
        <div data-status-panel="external" class="space-y-5">

<x-admin.panel
            title="Mail health"
            subtitle="Based on the last mail test you ran in Control → Mail test."
            info="Sends a test email through the configured mail server from Control → Mail test. 'Working' only means the server accepted it, not that it reached an inbox."
        >
            @if ($mailHealth['tested'])
                <div class="flex items-center gap-3">
                    <x-admin.pill :tone="$mailHealth['ok'] ? 'ok' : 'crit'">
                        {{ $mailHealth['ok'] ? 'Working' : 'Failing' }}
                    </x-admin.pill>
                    <p class="text-xs font-semibold text-[#2D231E]">
                        Last test {{ $mailHealth['at']->diffForHumans() }}
                        @if ($mailHealth['ok'] && $mailHealth['duration_ms'] !== null)
                            &middot; delivered in {{ number_format($mailHealth['duration_ms'] / 1000, 1) }}s
                        @endif
                    </p>
                </div>
                @if (! $mailHealth['ok'])
                    <p class="text-xs font-semibold text-rose-600 mt-2">
                        The last test failed — check the mail settings and run another test from Control.
                    </p>
                @endif
            @else
                <p class="text-sm font-semibold text-[#2D231E]">
                    No mail test yet — run one from Control → Mail test. It reports the delivery time here afterwards.
                </p>
            @endif
        </x-admin.panel>

        <div data-swap-region="integrations">
            @include('admin.partials.status-integrations')
        </div>

        <x-admin.panel
            title="Local tools"
            subtitle="Offline tools bundled with the app — real on-disk checks, no remote API."
            info="Checks the real files and data on this server — the WordNet dump, the Kokoro narrator and the offline Vosk speech model. Nothing here makes a network call."
        >
            @php
                $detail = fn (string $text, ?string $className = null) => preg_replace(
                    '/`([^`]*)`/',
                    '<span class="font-mono font-bold text-[#2D231E]'.($className ? ' '.$className : '').'">$1</span>',
                    e($text)
                );
            @endphp
            <div class="overflow-x-auto table-scroll">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-[#E6DCCF] text-[11px] uppercase tracking-wider text-[#2D231E]">
                            <th class="px-3 py-3 sm:px-4 font-extrabold">Tool</th>
                            <th class="px-3 py-3 sm:px-4 font-extrabold">Status</th>
                            <th class="px-3 py-3 sm:px-4 font-extrabold hidden lg:table-cell">Detail</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#F0E9DD]">
                        @foreach ($localTools as $tool)
                            <tr class="odd:bg-[#FAF6EE] hover:bg-[#F3ECE0] transition-colors">
                                <td class="px-3 py-3 sm:px-4 text-xs font-bold text-[#2D231E]">{{ $tool['name'] }}</td>
                                <td class="px-3 py-3 sm:px-4">
                                    <x-admin.pill :tone="$tool['tone']">{{ $tool['status'] }}</x-admin.pill>
                                </td>
                                <td class="px-3 py-3 sm:px-4 hidden lg:table-cell text-xs font-semibold text-[#2D231E]">
                                    {!! $detail($tool['detail']) !!}
                                    @if ($tool['name'] === 'Kokoro (narration voice)' && $tool['ok'])
                                        &middot; {{ count($narrationWanted) }} narration request{{ count($narrationWanted) === 1 ? '' : 's' }} queued.
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-admin.panel>

        </div>

        <!-- DEPENDENCIES TAB -->
        <div data-status-panel="dependencies" class="space-y-5">

            <x-admin.panel
                title="Local assets"
                subtitle="Offline models Alice ships with."
                info="Real on-disk checks — the WordNet dump, the Kokoro narrator, the offline Vosk speech model and the cached audio stores. Nothing here makes a network call."
            >
                <div class="overflow-x-auto table-scroll">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-[#E6DCCF] text-[11px] uppercase tracking-wider text-[#2D231E]">
                                <th class="px-3 py-3 sm:px-4 font-extrabold">Asset</th>
                                <th class="px-3 py-3 sm:px-4 font-extrabold">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#F0E9DD]">
                            @foreach ($maintenance['assets'] as $row)
                                <tr class="odd:bg-[#FAF6EE] hover:bg-[#F3ECE0] transition-colors" title="{{ $row['detail'] }}">
                                    <td class="px-3 py-3 sm:px-4 text-xs font-bold text-[#2D231E]">{{ $row['name'] }}</td>
                                    <td class="px-3 py-3 sm:px-4">
                                        <x-admin.pill :tone="$row['tone']">{{ $row['verdict'] }}</x-admin.pill>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-admin.panel>

        </div>

        <!-- TRAFFIC TAB -->
        <div data-status-panel="traffic" class="space-y-5">

        @include('admin.partials.traffic')

        <x-admin.panel
            title="Security overview"
            subtitle="Top request sources and suspicious 403 spikes from the request log."
            info="Shows who hits the app hardest and which IPs probe paths they should not see. A high 403 count from one IP often means a scanner poking at admin endpoints."
        >
            @php
                $probeCount = count($security['probeIps']);
                $recentFailed = collect($security['loginAttempts'])->where('ok', false);
                $recentOk = collect($security['loginAttempts'])->where('ok', true)->take(5);
            @endphp

            <div class="flex items-center gap-3 mb-5">
                @if ($probeCount > 0)
                    <x-admin.pill tone="crit">Possible probing</x-admin.pill>
                    <p class="text-xs font-semibold text-[#2D231E]">
                        {{ $probeCount }} IP(s) probing paths they should not see — details below.
                    </p>
                @elseif ($loginThreat['count'] > 0)
                    <x-admin.pill tone="warn">Watching</x-admin.pill>
                    <p class="text-xs font-semibold text-[#2D231E]">
                        {{ $loginThreat['count'] }} failed sign-in(s) in the last {{ $range }}h — details below.
                    </p>
                @else
                    <x-admin.pill tone="ok">No active threats</x-admin.pill>
                    <p class="text-xs font-semibold text-[#2D231E]">
                        No scanners probing and no failed sign-ins in this window.
                    </p>
                @endif
            </div>

            @if ($probeCount > 0)
                <div class="mb-5">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-[#2D231E] flex items-center gap-1.5 mb-2">
                        <x-dynamic-component :component="'icons.core.ban'" class="w-4 h-4 shrink-0" />
                        Probing blocked paths
                    </p>
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-[#E6DCCF] text-[11px] uppercase tracking-wider text-[#2D231E]">
                                <th class="px-3 py-3 sm:px-4 font-extrabold">IP</th>
                                <th class="px-3 py-3 sm:px-4 font-extrabold">403s</th>
                                <th class="px-3 py-3 sm:px-4 font-extrabold">Unique paths</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#F0E9DD]">
                            @foreach ($security['probeIps'] as $row)
                                <tr>
                                    <td class="px-3 py-3 sm:px-4 text-xs font-semibold text-[#2D231E] font-mono">{{ $row['ip'] }}</td>
                                    <td class="px-3 py-3 sm:px-4 text-xs font-bold text-rose-600">{{ $row['count_403'] }}</td>
                                    <td class="px-3 py-3 sm:px-4 text-xs font-semibold text-[#2D231E]">{{ $row['paths'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="mb-5">
                <p class="text-[11px] font-bold uppercase tracking-wider text-[#2D231E] flex items-center gap-1.5 mb-2">
                    <x-dynamic-component :component="'icons.core.chart-bar'" class="w-4 h-4 shrink-0" />
                    Top request sources — last {{ $range }}h
                </p>
                @if ($security['topIps'] !== [])
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-[#E6DCCF] text-[11px] uppercase tracking-wider text-[#2D231E]">
                                <th class="px-3 py-3 sm:px-4 font-extrabold">IP</th>
                                <th class="px-3 py-3 sm:px-4 font-extrabold">Requests</th>
                                <th class="px-3 py-3 sm:px-4 font-extrabold">Errors</th>
                                <th class="px-3 py-3 sm:px-4 font-extrabold">Last seen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#F0E9DD]">
                            @foreach ($security['topIps'] as $row)
                                <tr>
                                    <td class="px-3 py-3 sm:px-4 text-xs font-semibold text-[#2D231E] font-mono">{{ $row['ip'] }}</td>
                                    <td class="px-3 py-3 sm:px-4 text-xs font-semibold text-[#2D231E]">{{ number_format($row['total']) }}</td>
                                    <td class="px-3 py-3 sm:px-4 text-xs {{ $row['errors'] > 0 ? 'font-bold text-amber-700' : 'font-semibold text-[#2D231E]' }}">{{ $row['errors'] }}</td>
                                    <td class="px-3 py-3 sm:px-4 text-xs font-semibold text-[#8C7B70]">{{ \Carbon\Carbon::parse($row['last_seen'])->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="text-xs font-semibold text-[#8C7B70] px-4">No IP data yet — request_logs must have IP addresses recorded (migration added 2026-09-07).</p>
                @endif
            </div>

            @if ($security['loginAttempts'] !== [])
                <div class="border-t border-[#E6DCCF] pt-5">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-[#2D231E] flex items-center gap-1.5 mb-2">
                        <x-dynamic-component :component="'icons.core.users'" class="w-4 h-4 shrink-0" />
                        Recent sign-ins
                    </p>
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-[#E6DCCF] text-[11px] uppercase tracking-wider text-[#2D231E]">
                                <th class="px-3 py-3 sm:px-4 font-extrabold">Email</th>
                                <th class="px-3 py-3 sm:px-4 font-extrabold">IP</th>
                                <th class="px-3 py-3 sm:px-4 font-extrabold">Result</th>
                                <th class="px-3 py-3 sm:px-4 font-extrabold">When</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#F0E9DD]">
                            @foreach ($recentFailed as $row)
                                <tr class="bg-rose-50">
                                    <td class="px-3 py-3 sm:px-4 text-xs font-semibold text-[#2D231E]">{{ $row['email'] }}</td>
                                    <td class="px-3 py-3 sm:px-4 text-xs font-semibold text-[#2D231E] font-mono">{{ $row['ip'] }}</td>
                                    <td class="px-3 py-3 sm:px-4"><x-admin.pill tone="crit">Fail</x-admin.pill></td>
                                    <td class="px-3 py-3 sm:px-4 text-xs font-semibold text-[#8C7B70]">{{ \Carbon\Carbon::parse($row['at'])->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                            @foreach ($recentOk as $row)
                                <tr>
                                    <td class="px-3 py-3 sm:px-4 text-xs font-semibold text-[#8C7B70]">{{ $row['email'] }}</td>
                                    <td class="px-3 py-3 sm:px-4 text-xs font-semibold text-[#8C7B70] font-mono">{{ $row['ip'] }}</td>
                                    <td class="px-3 py-3 sm:px-4"><x-admin.pill tone="ok">OK</x-admin.pill></td>
                                    <td class="px-3 py-3 sm:px-4 text-xs font-semibold text-[#8C7B70]">{{ \Carbon\Carbon::parse($row['at'])->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-admin.panel>

    <div data-swap-region="client-errors">
        @include('admin.partials.status-client-errors')
    </div>

        </div>

    </div>

    <!-- CLIENT ERRORS CONFIRM -->
    <x-modal id="client-errors-modal" title="Clear all reported errors?">
        <p class="text-xs font-semibold text-[#2D231E]">
            Every reported client error is permanently removed. Use this after you have finished investigating.
            Recorded in the audit log.
        </p>
        <label class="flex items-start gap-3 cursor-pointer">
            <input type="checkbox" data-double-confirm class="mt-0.5 w-4 h-4 accent-rose-600 cursor-pointer">
            <span class="text-xs font-semibold text-[#2D231E]">
                I understand — all reported errors will be deleted.
            </span>
        </label>
        <div class="flex items-center justify-end gap-2">
            <x-admin.button tone="outline" type="button" data-modal-close>Cancel</x-admin.button>
            <x-admin.button tone="danger" type="button" data-confirm-approve>Clear all</x-admin.button>
        </div>
    </x-modal>
@endsection
