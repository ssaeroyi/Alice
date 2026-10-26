<x-admin.panel
    title="External services"
    subtitle="The outside services Alice reaches: the grammar check and the two book-cover lookups."
    info="Each row shows the last probe or real call, and 'Test now' runs a small live request and records the outcome. History is kept per service so you can see when a connection broke."
>
    <div class="flex justify-end mb-3">
        <form method="POST" action="{{ route('admin.status.integrations.test-all') }}" data-ajax data-busy-text="Testing every connection — a moment.">
            @csrf
            <x-admin.button type="submit">Test all</x-admin.button>
        </form>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-[#E6DCCF] text-[11px] uppercase tracking-wider text-[#2D231E]">
                    <th class="px-4 py-3 font-extrabold">Service</th>
                    <th class="px-4 py-3 font-extrabold">Key</th>
                    <th class="px-4 py-3 font-extrabold">Status</th>
                    <th class="px-4 py-3 font-extrabold hidden lg:table-cell">Last check</th>
                    <th class="px-4 py-3 font-extrabold hidden lg:table-cell">History</th>
                    <th class="px-4 py-3 font-extrabold"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#F0E9DD]">
                @foreach (\App\Support\Integrations::definitions() as $integration => $label)
                    @php
                        $latest = $integrations[$integration] ?? null;
                        $checks = $history[$integration] ?? [];
                    @endphp
                    <tr class="odd:bg-[#FAF6EE] hover:bg-[#F3ECE0] transition-colors">
                        <td class="px-4 py-3 text-xs font-bold text-[#2D231E]">{{ $label }}</td>
                        <td class="px-4 py-3">
                            @if ($integration === 'harper')
                                <x-admin.pill tone="neutral">On-device</x-admin.pill>
                            @else
                                <x-admin.pill tone="neutral">HTTP</x-admin.pill>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($latest === null)
                                <x-admin.pill tone="neutral">No checks yet</x-admin.pill>
                            @elseif ($latest['ok'])
                                <x-admin.pill tone="ok">Working</x-admin.pill>
                            @else
                                <x-admin.pill tone="crit">
                                    {{ $latest['error_type'] === 'rate_limited' ? 'Rate limited' : 'Last check failed' }}
                                </x-admin.pill>
                            @endif
                        </td>
                        <td class="px-4 py-3 hidden lg:table-cell">
                            @if ($latest === null)
                                <span class="text-xs font-semibold text-[#2D231E]">—</span>
                            @else
                                <p class="text-xs font-semibold text-[#2D231E]">
                                    {{ \Carbon\Carbon::parse($latest['created_at'])->diffForHumans() }}
                                    &middot; {{ number_format($latest['latency_ms']) }} ms
                                </p>
                                <p class="text-[11px] font-semibold text-[#2D231E] truncate max-w-56">{{ $latest['message'] }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 hidden lg:table-cell">
                            @if (count($checks) > 0)
                                <div class="flex items-center gap-1" aria-hidden="true">
                                    @foreach ($checks as $check)
                                        <span
                                            class="w-2.5 h-2.5 rounded-full {{ $check['ok'] ? 'bg-emerald-400' : ($check['error_type'] === 'rate_limited' ? 'bg-amber-400' : 'bg-rose-400') }}"
                                            title="{{ \Carbon\Carbon::parse($check['created_at'])->format('M j, g:i A') }} · {{ $check['ok'] ? 'ok' : ($check['error_type'] ?? 'failed') }} · {{ number_format($check['latency_ms']) }} ms"
                                        ></span>
                                    @endforeach
                                </div>
                                <p class="text-[10px] font-semibold text-[#2D231E] mt-1">Last {{ count($checks) }} checks</p>
                            @else
                                <span class="text-xs font-semibold text-[#2D231E]">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <form method="POST" action="{{ route('admin.status.integrations.test', $integration) }}" data-ajax data-busy-text="Testing the connection — a moment.">
                                    @csrf
                                    <x-admin.button type="submit" tone="outline">Test now</x-admin.button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-admin.panel>
