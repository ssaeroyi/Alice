@php
    $window = match ($range) {
        168 => 'last 7 days',
        720 => 'last 30 days',
        2160 => 'last 90 days',
        default => 'last 24h',
    };

    // The chart follows the tile window. The 24h window buckets by hour (a
    // day would be one pointless point); the wider windows bucket by day.
    $chartLabel = match ($range) {
        168 => 'per day, last 7 days',
        720 => 'per day, last 30 days',
        2160 => 'per day, last 90 days',
        default => 'per hour, last 24 hours',
    };

    $selectedMeta = $metrics[$selected];
@endphp

<div data-traffic-panel>
    <x-admin.panel
        title="Traffic"
        subtitle="Tap a tile to redraw the chart below it. The window above applies to every tile."
        info="Measures every page load as it happens, so these numbers are live. A sudden drop in requests usually means a fault or a maintenance window; a steady rise means more students are using the app."
    >
        <div class="flex items-center gap-2 mb-4">
            @foreach ([24 => 'Last 24h', 168 => 'Last 7 days', 720 => 'Last 30 days', 2160 => 'Last 90 days'] as $hours => $label)
                <a
                    href="{{ url()->current().'?range='.$hours }}"
                    data-traffic-tab
                    class="px-3 py-1.5 rounded-lg text-xs font-bold transition-colors {{ $range === $hours ? 'bg-emerald-600 text-white' : 'bg-[#F0E9DD] text-[#2D231E] hover:bg-[#E6DCCF]' }}"
                    @if ($range === $hours) aria-current="true" @endif
                >{{ $label }}</a>
            @endforeach
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
            @foreach ($metrics as $key => $meta)
                <a
                    href="{{ url()->current().'?range='.$range.'&metric='.$key }}"
                    data-traffic-metric="{{ $key }}"
                    @if ($selected === $key) data-traffic-active @endif
                    aria-current="{{ $selected === $key ? 'true' : 'false' }}"
                    class="group rounded-xl border px-3.5 py-3 transition-colors cursor-pointer bg-white border-[#E6DCCF] hover:border-amber-300 hover:bg-amber-50/40 data-[traffic-active]:border-amber-400 data-[traffic-active]:bg-amber-50/70 data-[traffic-active]:shadow-[0_0_0_1px_rgba(251,191,36,0.55)]"
                >
                    <span class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-[#2D231E]">
                        @switch($key)
                            @case('traffic.requests')
                                <x-dynamic-component :component="'icons.core.chart-bar'" class="w-3.5 h-3.5 shrink-0 text-[#8C7B70] group-data-[traffic-active]:text-amber-600" />
                                @break
                            @case('traffic.avg_ms')
                                <x-dynamic-component :component="'icons.core.clock'" class="w-3.5 h-3.5 shrink-0 text-[#8C7B70] group-data-[traffic-active]:text-amber-600" />
                                @break
                            @case('traffic.p95_ms')
                                <x-dynamic-component :component="'icons.core.monitor'" class="w-3.5 h-3.5 shrink-0 text-[#8C7B70] group-data-[traffic-active]:text-amber-600" />
                                @break
                            @case('traffic.four_xx')
                                <x-dynamic-component :component="'icons.core.user'" class="w-3.5 h-3.5 shrink-0 text-[#8C7B70] group-data-[traffic-active]:text-amber-600" />
                                @break
                            @case('traffic.five_xx')
                                <x-dynamic-component :component="'icons.core.terminal'" class="w-3.5 h-3.5 shrink-0 text-[#8C7B70] group-data-[traffic-active]:text-amber-600" />
                                @break
                            @case('traffic.failed_logins')
                                <x-dynamic-component :component="'icons.core.ban'" class="w-3.5 h-3.5 shrink-0 text-[#8C7B70] group-data-[traffic-active]:text-amber-600" />
                                @break
                            @case('traffic.distinct_emails')
                                <x-dynamic-component :component="'icons.core.magnifier'" class="w-3.5 h-3.5 shrink-0 text-[#8C7B70] group-data-[traffic-active]:text-amber-600" />
                                @break
                        @endswitch
                        <span class="truncate">{{ $meta['label'] }}</span>
                        @if (! empty($meta['hint']))
                            <x-admin.info>{{ $meta['hint'] }}</x-admin.info>
                        @endif
                    </span>
                    @php
                        $tileValue = match ($key) {
                            'traffic.requests' => $traffic['day_count'],
                            'traffic.avg_ms' => $traffic['day_avg_ms'] !== null ? $traffic['day_avg_ms'].' ms' : '—',
                            'traffic.p95_ms' => $traffic['p95_ms'] !== null ? $traffic['p95_ms'].' ms' : '—',
                            'traffic.four_xx' => $traffic['four_xx'],
                            'traffic.five_xx' => $traffic['five_xx'],
                            'traffic.failed_logins' => $loginThreat['count'],
                            'traffic.distinct_emails' => $loginThreat['emails'],
                        };
                    @endphp
                    <span data-traffic-value class="block mt-1 text-lg font-extrabold text-[#2D231E] tabular-nums">{{ $tileValue }}</span>
                </a>
            @endforeach
        </div>

        @if ($loginThreat['count'] >= 10)
            <p class="text-xs font-semibold text-rose-600 mt-3">
                Unusually many failed logins — this can be a brute-force attempt. Check the recent attempts in
                <a href="{{ route('admin.logs.index') }}" class="font-bold underline hover:text-amber-700">Logs</a>,
                then send reset links for the targeted accounts from Accounts.
            </p>
        @endif

        @if ($traffic['five_xx'] > 0)
            @php
                $severe = $traffic['five_xx'] >= 10;
                $windowLabel = $window === 'last 24h' ? 'the last 24h' : 'this window';
                $warning = $severe
                    ? $traffic['five_xx'].' server failures in '.$windowLabel.' — something is regularly failing.'
                    : $traffic['five_xx'].' server '.Str::plural('failure', $traffic['five_xx']).' in '.$windowLabel.' — a failed request here and there is normal.';
            @endphp
            <p class="text-xs font-semibold {{ $severe ? 'text-rose-600' : 'text-amber-700' }} mt-3">
                {{ $warning }}
                <a href="{{ route('admin.logs.index', ['tab' => 'server', 'status' => '5xx', 'preset' => 'last_24h']) }}" class="font-bold underline hover:text-amber-700">See them in Logs</a>.
            </p>
        @endif

        <div class="mt-5">
            <p data-traffic-chart-label class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E]">{{ $selectedMeta['label'] }} {{ $chartLabel }}</p>
            <div data-traffic-chart-wrap class="relative h-52 mt-2 overflow-x-auto transition-opacity">
                <canvas
                    data-traffic-chart-canvas
                    data-metric="{{ $selected }}"
                    data-unit="{{ $selectedMeta['unit'] }}"
                    data-series-endpoint="{{ route('admin.status.traffic-series') }}"
                    data-range="{{ $range }}"
                    data-series="{{ json_encode(array_values($series), JSON_THROW_ON_ERROR | JSON_HEX_APOS | JSON_HEX_QUOT) }}"
                    class="absolute inset-0 w-full h-full"
                ></canvas>
            </div>
            @if ($selected === 'traffic.requests')
                <noscript>
                    <div class="flex items-end gap-[2px] h-20 mt-2 overflow-hidden">
                        @php $max = max(1, collect($series)->max('count')); @endphp
                        @foreach ($series as $day)
                            <div
                                class="flex-1 bg-amber-500/70 rounded-sm"
                                style="height: {{ max(2, (int) round($day['count'] / $max * 100)) }}%"
                                title="{{ $day['date'] }}: {{ $day['count'] }} requests"
                            ></div>
                        @endforeach
                    </div>
                </noscript>
            @endif
            <p data-traffic-chart-hint class="text-[11px] font-semibold text-[#2D231E] mt-1.5">
                @if ($range === 24)
                    Each point is one hour, read live from the request log (only the newest ~10,000 rows are kept). Hover for the exact count.
                @elseif ($selected === 'traffic.requests')
                    Each point is one day, read from the nightly roll-up (the raw request log only keeps the newest ~10,000 rows). Hover for the exact count.
                @else
                    Each point is one day, read live from the request log (only the newest ~10,000 rows are kept). Hover for the exact count.
                @endif
            </p>
        </div>
    </x-admin.panel>
</div>
