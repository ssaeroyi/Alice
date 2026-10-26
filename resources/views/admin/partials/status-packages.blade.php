<x-admin.panel
    title="Packages"
    subtitle="Every direct dependency with its installed and latest version."
    info="Per-package lists from composer and npm. An Update button applies a within-range update — the newest release the existing constraint allows, touching only the lockfile, never composer.json or package.json. npm updates also rebuild the frontend bundle."
>
    <x-slot:actions>
        <form method="POST" action="{{ route('admin.status.packages.refresh') }}" data-ajax data-busy-text="Refreshing the package list — a moment.">
            @csrf
            <x-admin.button type="submit" tone="outline">Refresh</x-admin.button>
        </form>
    </x-slot:actions>

    @php
        $ecosystems = $maintenance['managers'] ?? [];
    @endphp

    <div class="space-y-8">
        @foreach ($ecosystems as $eco)
            @php
                $packages = $eco['packages'] ?? [];
                $outdated = collect($packages)->where('outdated', true)->values();
                $current = collect($packages)->where('outdated', false)->values();
            @endphp

            <div>
                <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1 border-b border-[#E6DCCF] pb-2">
                    <h3 class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E]">
                        {{ ($eco['key'] ?? '') === 'composer' ? 'PHP · composer' : 'Frontend · npm' }}
                    </h3>
                    @if ($eco['present'] ?? false)
                        <span class="text-xs font-semibold text-[#2D231E]">
                            {{ count($packages) }} direct ·
                            @if (($eco['outdated_count'] ?? 0) > 0)
                                <span class="text-amber-700">{{ $eco['outdated_count'] }} outdated</span>
                            @else
                                <span class="text-[#3A6B4D]">0 outdated</span>
                            @endif
                        </span>
                    @endif
                </div>

                @if (! ($eco['present'] ?? false))
                    <div class="flex items-center gap-3 py-3">
                        <span class="text-xs font-bold text-[#2D231E]">Not present</span>
                        <span class="text-xs font-semibold text-[#2D231E]">{{ $eco['absent_detail'] ?? '' }}</span>
                    </div>
                @else
                    @if ($outdated->isEmpty())
                        <p class="py-3 text-xs font-semibold text-[#2D231E]">
                            All {{ count($packages) }} direct dependencies are up to date.
                        </p>
                    @else
                        <table class="w-full text-left text-sm">
                            <tbody class="divide-y divide-[#F0E9DD]">
                                @foreach ($outdated as $package)
                                    <tr class="odd:bg-[#FAF6EE] hover:bg-[#F3ECE0] transition-colors">
                                        <td class="px-4 py-3 text-xs font-mono font-bold text-[#2D231E]">
                                            {{ $package['name'] }}
                                            @if ($package['dev'])
                                                <span class="ml-1 text-[10px] font-semibold text-[#2D231E]">(dev)</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-xs font-semibold text-[#2D231E]">{{ $package['version'] }}</td>
                                        <td class="px-4 py-3">
                                            <x-admin.pill tone="warn">{{ $package['version'] }} → {{ $package['latest'] }}</x-admin.pill>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <form method="POST" action="{{ route('admin.status.packages.update') }}" data-ajax data-busy-text="Updating {{ $package['name'] }} — this can take a couple of minutes.">
                                                @csrf
                                                <input type="hidden" name="ecosystem" value="{{ $eco['key'] }}" />
                                                <input type="hidden" name="package" value="{{ $package['name'] }}" />
                                                <x-admin.button type="submit" tone="outline">Update</x-admin.button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif

                    @if ($current->isNotEmpty())
                        <details class="mt-2">
                            <summary class="cursor-pointer text-xs font-bold text-[#2D231E] hover:text-amber-700">
                                Show {{ $current->count() }} current
                            </summary>
                            <table class="mt-2 w-full text-left text-sm">
                                <tbody class="divide-y divide-[#F0E9DD]">
                                    @foreach ($current as $package)
                                        <tr class="odd:bg-[#FAF6EE] hover:bg-[#F3ECE0] transition-colors">
                                            <td class="px-4 py-3 text-xs font-mono font-bold text-[#2D231E]">
                                                {{ $package['name'] }}
                                                @if ($package['dev'])
                                                    <span class="ml-1 text-[10px] font-semibold text-[#2D231E]">(dev)</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-xs font-semibold text-[#2D231E]">{{ $package['version'] }}</td>
                                            <td class="px-4 py-3">
                                                <x-admin.pill tone="ok">Current</x-admin.pill>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </details>
                    @endif
                @endif
            </div>
        @endforeach
    </div>
</x-admin.panel>
