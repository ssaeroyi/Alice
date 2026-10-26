<x-admin.panel
    title="Browser errors"
    subtitle="Errors reported from students' browsers — JavaScript crashes and failed requests that never reached the server."
    info="Collects browser-side failures — script crashes, failed requests — with the page URL and signed-in account. Server-side problems appear in the Server errors tab instead."
>
    @if ($clientErrorCount > 0)
        <p class="text-[11px] font-semibold text-[#2D231E] mb-3">
            {{ $clientErrorCount }} reported {{ $clientErrorCount === 1 ? 'error' : 'errors' }} in the last 24 hours.
        </p>
    @endif
    @if (count($clientErrors) > 0)
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-[#E6DCCF] text-[11px] uppercase tracking-wider text-[#2D231E]">
                        <th class="px-4 py-3 font-extrabold">When</th>
                        <th class="px-4 py-3 font-extrabold">Student</th>
                        <th class="px-4 py-3 font-extrabold">Message</th>
                        <th class="px-4 py-3 font-extrabold hidden lg:table-cell">Page</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#F0E9DD]">
                    @foreach ($clientErrors as $error)
                        <tr class="odd:bg-[#FAF6EE] hover:bg-[#F3ECE0] transition-colors">
                            <td class="px-4 py-3 whitespace-nowrap text-xs font-semibold text-[#2D231E]">
                                {{ $error['at']->format('M j, g:i A') }}
                            </td>
                            <td class="px-4 py-3">
                                @if ($error['account_id'])
                                    <a
                                        href="{{ route('admin.accounts.show', $error['account_id']) }}"
                                        class="inline-flex items-center min-h-[44px] px-3 py-1.5 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-[11px] font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors"
                                    >
                                        {{ $error['nickname'] ?: 'Account #'.$error['account_id'] }}
                                    </a>
                                @else
                                    <span class="text-xs font-semibold text-[#2D231E]">Not signed in</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <details class="group">
                                    <summary class="cursor-pointer text-xs font-semibold text-[#2D231E] group-open:text-amber-700">
                                        {{ \Illuminate\Support\Str::limit($error['message'], 160) }}
                                    </summary>
                                    @if ($error['stack'])
                                        <pre class="mt-2 bg-[#2D231E] text-[#FAF6EE] text-[11px] leading-relaxed p-3 rounded-lg font-mono whitespace-pre-wrap break-words">{{ \Illuminate\Support\Str::limit($error['stack'], 2000) }}</pre>
                                    @endif
                                    <p class="mt-2 text-[11px] font-semibold text-[#2D231E]">
                                        {{ $error['user_agent'] }}
                                    </p>
                                    @if ($error['hint'])
                                        <p class="mt-2 text-[11px] font-bold text-amber-700">{{ $error['hint'] }}</p>
                                    @endif
                                </details>
                            </td>
                            <td class="px-4 py-3 hidden lg:table-cell text-xs font-semibold text-[#2D231E] break-all">
                                {{ $error['url'] }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="pt-4 flex justify-end">
            <form method="POST" action="{{ route('admin.logs.client-errors.clear') }}" data-ajax data-busy-text="Clearing the reported errors — a moment.">
                @csrf
                <x-admin.button tone="outline-danger" type="button" data-confirm-open="client-errors-modal">Clear reported errors</x-admin.button>
            </form>
        </div>
    @else
        <p class="text-sm font-semibold text-[#2D231E]">
            No client errors reported. When a student's browser hits a script crash or a failed request, it shows up here.
        </p>
    @endif
</x-admin.panel>
