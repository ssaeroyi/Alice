@extends('layouts.admin')

@section('title', 'Logs')
@section('eyebrow', 'Logs')

@section('content')
    <div class="space-y-4">
@php
    $tabQueryBase = request()->only(['preset', 'from', 'to', 'actor', 'action', 'category', 'status']);
    $logFilterCount = collect([
        request('action') ? 1 : 0,
        request('actor') ? 1 : 0,
        request('category') ? 1 : 0,
        request('from') || request('to') ? 1 : 0,
    ])->sum();
@endphp

{{-- Tab bar --}}
<div class="flex flex-wrap gap-2">
    @foreach ($tabs as $tabKey => $tabLabel)
        <a
            href="{{ route('admin.logs.index', array_merge($tabKey === 'audit' ? request()->only(['preset', 'from', 'to', 'actor', 'action', 'category']) : ['preset' => request('preset')], ['tab' => $tabKey])) }}"
            class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-colors {{ $tab === $tabKey ? 'bg-[#2D231E] text-[#FAF6EE]' : 'bg-white border border-[#E6DCCF] text-[#2D231E] hover:border-amber-300' }}"
        >{{ $tabLabel }}</a>
        @endforeach
    </div>

@if ($tab === 'audit')
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <p class="text-xs font-semibold text-[#2D231E]">
            {{ $entries->total() }} event{{ $entries->total() === 1 ? '' : 's' }} found
            @if ($activeCategory)
                &middot; {{ $categories[$activeCategory] ?? $activeCategory }}
            @endif
            @if (($range['preset'] ?? null) && $range['preset'] !== 'all')
                &middot; {{ $range['label'] }}
            @endif
        </p>
        <div class="flex flex-wrap items-center gap-2">
            <a
                href="{{ route('admin.logs.export', request()->only(['preset', 'from', 'to', 'action', 'actor', 'category'])) }}"
                class="inline-flex items-center gap-1.5 min-h-[44px] px-3 rounded-lg bg-white border border-[#E6DCCF] text-xs font-bold text-[#2D231E] hover:border-amber-300 transition-colors"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                Export CSV
            </a>
            <x-admin.filter-drawer
                label="Filters"
                :action="route('admin.logs.index')"
                :count="$logFilterCount"
                :reset-url="route('admin.logs.index', ['tab' => 'audit'])"
                form-id="logs-filter-form"
            >
                <input type="hidden" name="tab" value="audit">

                <label class="block">
                    <span class="text-[11px] font-bold uppercase tracking-wide text-[#2D231E]">Action</span>
                    <select name="action" class="mt-1 w-full h-11 px-3 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] focus:outline-none focus:border-[#2D231E] cursor-pointer">
                        <option value="">All actions</option>
                        @foreach ($actions as $actionKey)
                            <option value="{{ $actionKey }}" @selected(request('action') === $actionKey)>{{ App\Support\AuditCatalog::entry($actionKey)['label'] }} ({{ $actionKey }})</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-[11px] font-bold uppercase tracking-wide text-[#2D231E]">Category</span>
                    <select name="category" class="mt-1 w-full h-11 px-3 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] focus:outline-none focus:border-[#2D231E] cursor-pointer">
                        <option value="">All categories</option>
                        @foreach ($categories as $categoryKey => $categoryLabel)
                            <option value="{{ $categoryKey }}" @selected($activeCategory === $categoryKey)>{{ $categoryLabel }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-[11px] font-bold uppercase tracking-wide text-[#2D231E]">Actor</span>
                    <input
                        type="search"
                        name="actor"
                        value="{{ request('actor') }}"
                        placeholder="Nickname or email"
                        class="mt-1 w-full h-11 px-3 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] placeholder:text-[#B5A89C] focus:outline-none focus:border-[#2D231E]"
                    >
                </label>

                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wide text-[#2D231E]">Range</span>
                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                        @foreach (App\Support\AuditRange::PRESETS as $presetKey => $presetLabel)
                            <button
                                type="submit"
                                name="preset"
                                value="{{ $presetKey }}"
                                class="px-2.5 py-1.5 rounded-lg text-xs font-bold border transition-colors {{ ($range['preset'] ?? null) === $presetKey ? 'bg-[#2D231E] text-[#FAF6EE] border-[#2D231E]' : 'bg-white text-[#2D231E] border-[#E6DCCF] hover:border-amber-300' }}"
                            >{{ $presetLabel }}</button>
                        @endforeach
                    </div>
                </div>

                <label class="block">
                    <span class="text-[11px] font-bold uppercase tracking-wide text-[#2D231E]">Custom range</span>
                    <div class="mt-1.5">
                        <x-admin.date-range
                            :label="$range['label']"
                            :from="request('from')"
                            :to="request('to')"
                        />
                    </div>
                </label>
            </x-admin.filter-drawer>
        </div>
    </div>

        <x-admin.panel>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-[#E6DCCF] text-[11px] uppercase tracking-wider text-[#2D231E]">
                            <th class="px-4 py-3 font-extrabold">Event</th>
                            <th class="px-4 py-3 font-extrabold hidden sm:table-cell">Target</th>
                            <th class="px-4 py-3 font-extrabold hidden md:table-cell">Actor</th>
                            <th class="px-4 py-3 font-extrabold hidden lg:table-cell">IP address</th>
                            <th class="px-4 py-3 font-extrabold">When</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#F0E9DD]">
                        @forelse ($entries as $entry)
                            @php
                                $meta = App\Support\AuditCatalog::entry($entry->action);
                            @endphp
                            <tr class="odd:bg-[#FAF6EE] hover:bg-[#F3ECE0] transition-colors">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <x-admin.pill :tone="$meta['severity']">{{ $meta['label'] }}</x-admin.pill>
                                        <span class="text-[11px] font-semibold text-[#8C7B70]">{{ App\Support\AuditCatalog::CATEGORIES[$meta['category']] ?? $meta['category'] }}</span>
                                    </div>
                                    @if ($entry->meta)
                                        <details class="group mt-1">
                                            <summary class="cursor-pointer text-[11px] font-semibold text-[#8C7B70] hover:text-[#2D231E] transition-colors">
                                                <code>{{ $entry->action }}</code> &middot; details
                                            </summary>
                                            <pre class="mt-1 bg-[#2D231E] text-[#FAF6EE] text-[11px] leading-relaxed p-2.5 rounded-lg font-mono whitespace-pre-wrap break-words">{{ json_encode($entry->meta) }}</pre>
                                        </details>
                                    @else
                                        <p class="mt-1 text-[11px] font-semibold text-[#8C7B70]"><code>{{ $entry->action }}</code></p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 hidden sm:table-cell text-xs font-semibold text-[#2D231E]">
                                    {{ $entry->target_type ? $entry->target_type.' #'.$entry->target_id : 'System' }}
                                </td>
                                <td class="px-4 py-3 hidden md:table-cell text-xs font-semibold text-[#2D231E]">
                                    @if ($entry->actor)
                                        <a
                                            href="{{ route('admin.accounts.show', $entry->actor) }}"
                                            class="text-amber-700 hover:text-[#2D231E] transition-colors"
                                        >
                                            {{ $entry->actor?->nickname ?? $entry->actor?->email }}
                                        </a>
                                    @else
                                        System
                                    @endif
                                </td>
                                <td class="px-4 py-3 hidden lg:table-cell text-xs font-semibold text-[#2D231E]">{{ $entry->ip_address }}</td>
                                <td class="px-4 py-3 text-xs font-semibold text-[#2D231E]">{{ $entry->created_at->format('M j, Y g:i A') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-10 text-center">
                                    <p class="text-sm font-bold text-[#2D231E]">No audit events match these filters.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pt-4">
                {{ $entries->links('pagination::teacher') }}
            </div>
        </x-admin.panel>
@endif

@if ($tab === 'server')
    @php
        $chipPresets = [
            'last_24h' => '24h',
            'last_7_days' => '7 days',
            'last_30_days' => '30 days',
            'last_90_days' => '90 days',
            'all' => 'All time',
        ];
        $statusChips = ['all' => 'All errors', '4xx' => 'Client-side (4xx)', '5xx' => 'Server-side (5xx)'];
    @endphp
    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-2">
            @foreach ($statusChips as $statusKey => $statusLabel)
                <a
                    href="{{ route('admin.logs.index', array_merge($tabQueryBase, ['tab' => 'server', 'status' => $statusKey])) }}"
                    class="px-3 py-1.5 rounded-lg text-xs font-bold transition-colors {{ $serverStatus === $statusKey ? ($statusKey === '5xx' ? 'bg-rose-600 text-white' : 'bg-[#2D231E] text-[#FAF6EE]') : 'bg-white border border-[#E6DCCF] text-[#2D231E] hover:border-amber-300' }}"
                >{{ $statusLabel }}</a>
            @endforeach
            <span class="w-px h-5 bg-[#E6DCCF] mx-1"></span>
            @foreach ($chipPresets as $presetKey => $presetLabel)
                <a
                    href="{{ route('admin.logs.index', array_merge($tabQueryBase, ['tab' => 'server', 'preset' => $presetKey])) }}"
                    class="px-3 py-1.5 rounded-lg text-xs font-bold transition-colors {{ ($range['preset'] ?? null) === $presetKey ? 'bg-[#2D231E] text-[#FAF6EE]' : 'bg-white border border-[#E6DCCF] text-[#2D231E] hover:border-amber-300' }}"
                >{{ $presetLabel }}</a>
            @endforeach
        </div>

        <p class="text-xs font-semibold text-[#2D231E]">
            {{ $entries->total() }} failing request{{ $entries->total() === 1 ? '' : 's' }} found
        </p>

        <x-admin.panel>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-[#E6DCCF] text-[11px] uppercase tracking-wider text-[#2D231E]">
                            <th class="px-4 py-3 font-extrabold">When</th>
                            <th class="px-4 py-3 font-extrabold">Status</th>
                            <th class="px-4 py-3 font-extrabold">Request</th>
                            <th class="px-4 py-3 font-extrabold hidden md:table-cell">Duration</th>
                            <th class="px-4 py-3 font-extrabold hidden lg:table-cell">IP address</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#F0E9DD]">
                        @forelse ($entries as $row)
                            @php
                                $tone = $row->status >= 500 ? 'crit' : 'warn';
                            @endphp
                            <tr class="odd:bg-[#FAF6EE] hover:bg-[#F3ECE0] transition-colors">
                                <td class="px-4 py-3 text-xs font-semibold text-[#2D231E] whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($row->created_at)->format('M j, Y g:i A') }}</td>
                                <td class="px-4 py-3"><x-admin.pill :tone="$tone">{{ $row->status }}</x-admin.pill></td>
                                <td class="px-4 py-3 text-xs font-semibold text-[#2D231E]">
                                    <span class="inline-block px-1.5 py-0.5 bg-[#F3ECE0] border border-[#E6DCCF] rounded text-[10px] font-bold uppercase mr-1.5">{{ $row->method }}</span>
                                    <span class="font-mono break-all">{{ $row->path }}</span>
                                </td>
                                <td class="px-4 py-3 hidden md:table-cell text-xs font-semibold text-[#2D231E]">{{ number_format($row->duration_ms) }} ms</td>
                                <td class="px-4 py-3 hidden lg:table-cell text-xs font-semibold text-[#2D231E]">{{ $row->ip_address }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-10 text-center">
                                    <p class="text-sm font-bold text-[#2D231E]">No failing requests in this window. That is the healthy state.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pt-4">
                {{ $entries->links('pagination::teacher') }}
            </div>
        </x-admin.panel>
    </div>
@endif

@if ($tab === 'browser')
    <div data-swap-region="client-errors">
        @include('admin.logs.partials.browser-errors')
    </div>

    <x-modal id="client-errors-modal" title="Clear all reported errors?">
        <p class="text-xs font-semibold text-[#2D231E]">
            Every reported client error is permanently removed. Use this after you have finished investigating.
        </p>
    </x-modal>
@endif
    </div>
@endsection
