@extends('layouts.admin')

@section('title', 'Student reports')
@section('eyebrow', 'Student reports')

@section('content')
    <div class="space-y-5">

        @php
            $reportFilterCount = collect([
                request('status') ? 1 : 0,
                request('category') ? 1 : 0,
            ])->sum();
        @endphp

        <x-admin.panel>
            <div class="flex items-center gap-2">
                <x-admin.search-input
                    :action="route('admin.reports.index')"
                    :value="request('q')"
                    placeholder="Search story, issue or student"
                    label="Search reports"
                />
                <x-admin.filter-drawer
                    label="Filters"
                    :action="route('admin.reports.index')"
                    :count="$reportFilterCount"
                    search-name=""
                    :reset-url="route('admin.reports.index')"
                >
                    <input type="hidden" name="q" value="{{ request('q') }}">

                    <label class="block">
                        <span class="text-[11px] font-bold uppercase tracking-wide text-[#2D231E]">Status</span>
                        <select name="status" class="mt-1 w-full h-11 px-3 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] focus:outline-none focus:border-[#2D231E] cursor-pointer">
                            <option value="">All statuses</option>
                            <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                            <option value="resolved" @selected(request('status') === 'resolved')>Resolved</option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="text-[11px] font-bold uppercase tracking-wide text-[#2D231E]">Category</span>
                        <select name="category" class="mt-1 w-full h-11 px-3 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] focus:outline-none focus:border-[#2D231E] cursor-pointer">
                            <option value="">All categories</option>
                            <option value="typo" @selected(request('category') === 'typo')>Typo</option>
                            <option value="content" @selected(request('category') === 'content')>Content</option>
                            <option value="other" @selected(request('category') === 'other')>Other</option>
                        </select>
                    </label>
                </x-admin.filter-drawer>
            </div>

            <p class="mt-3 text-xs font-semibold text-[#2D231E]">
                {{ $reports->total() }} report{{ $reports->total() === 1 ? '' : 's' }} found
            </p>
        </x-admin.panel>

        <x-admin.panel>
            <form
                id="reports-bulk-form"
                method="POST"
                action="{{ route('admin.reports.bulk-delete') }}"
                class="flex flex-wrap items-center justify-between gap-3 px-4 pt-4"
            >
                @csrf
                <p class="text-xs font-semibold text-[#2D231E]">Tick reports and act on the selection, or use the per-row buttons below.</p>
                <div class="flex items-center gap-2">
                    <button
                        type="submit"
                        form="reports-bulk-form"
                        formaction="{{ route('admin.reports.bulk-resolve') }}"
                        name="bulk-action"
                        value="resolve"
                        class="hidden min-h-[36px] px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-bold hover:bg-emerald-700 transition-colors cursor-pointer disabled:opacity-40 disabled:pointer-events-none"
                        disabled
                    >
                        Resolve selected
                    </button>
                    <button
                        type="button"
                        data-bulk-delete-open="bulk-delete-reports-modal"
                        class="hidden min-h-[36px] px-3 py-1.5 rounded-lg bg-rose-600 text-white text-xs font-bold hover:bg-rose-700 transition-colors cursor-pointer disabled:opacity-40 disabled:pointer-events-none"
                        disabled
                    >
                        Delete selected
                    </button>
                </div>
            </form>

            <div class="overflow-x-auto table-scroll">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-[#E6DCCF] text-[11px] uppercase tracking-wider text-[#2D231E]">
                            <th class="px-4 py-3 w-10">
                                <span class="sr-only">Select</span>
                                <input
                                    type="checkbox"
                                    data-bulk-toggle-all
                                    class="w-4 h-4 accent-amber-600 cursor-pointer rounded"
                                    aria-label="Select all reports"
                                >
                            </th>
                            <th class="px-4 py-3 font-extrabold hidden sm:table-cell">Story</th>
                            <th class="px-4 py-3 font-extrabold">Issue</th>
                            <th class="px-4 py-3 font-extrabold hidden md:table-cell">Student</th>
                            <th class="px-4 py-3 font-extrabold hidden lg:table-cell">When</th>
                            <th class="px-4 py-3 font-extrabold">Status</th>
                            <th class="px-4 py-3 font-extrabold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#F0E9DD]">
                        @forelse ($reports as $report)
                            <tr class="odd:bg-[#FAF6EE] hover:bg-[#F3ECE0] transition-colors align-top">
                                <td class="px-4 py-3">
                                    <input
                                        type="checkbox"
                                        name="ids[]"
                                        value="{{ $report->id }}"
                                        form="reports-bulk-form"
                                        data-bulk-check
                                        class="w-4 h-4 accent-amber-600 cursor-pointer rounded"
                                        aria-label="Select report {{ $report->id }}"
                                    >
                                </td>
                                <td class="px-4 py-3 hidden sm:table-cell">
                                    @if ($report->story_id)
                                        <a
                                            href="{{ route('story.preview', $report->story) }}"
                                            target="_blank"
                                            rel="noopener"
                                            class="text-xs font-bold text-amber-700 hover:text-[#2D231E] transition-colors"
                                        >
                                            {{ $report->story?->title ?? 'Story #'.$report->story_id }}
                                        </a>
                                    @else
                                        <p class="text-xs font-bold text-[#2D231E]">Deleted story</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider {{ $report->category === 'typo' ? 'bg-amber-100 text-amber-800' : ($report->category === 'content' ? 'bg-rose-100 text-rose-700' : 'bg-[#F3ECE0] text-[#2D231E]') }}">
                                        {{ $report->category }}
                                    </span>
                                    <p class="mt-1.5 text-xs font-semibold text-[#2D231E] leading-relaxed whitespace-pre-line">{{ $report->description }}</p>
                                </td>
                                <td class="px-4 py-3 hidden md:table-cell text-xs font-semibold text-[#2D231E]">
                                    @if ($report->user)
                                        <a
                                            href="{{ route('admin.accounts.show', $report->user) }}"
                                            class="text-amber-700 hover:text-[#2D231E] transition-colors"
                                        >
                                            {{ $report->user?->nickname ?? $report->user?->email }}
                                        </a>
                                    @else
                                        Deleted user
                                    @endif
                                </td>
                                <td class="px-4 py-3 hidden lg:table-cell text-xs font-semibold text-[#2D231E]">
                                    {{ $report->created_at->format('M j, Y g:i A') }}
                                </td>
                                <td class="px-4 py-3">
                                    @if ($report->status === 'resolved')
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider bg-emerald-100 text-emerald-800">Resolved</span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider bg-rose-100 text-rose-700">Pending</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        @if ($report->status !== 'resolved')
                                            <form method="POST" action="{{ route('admin.reports.resolve', $report) }}" data-submit-guard>
                                                @csrf
                                                <x-admin.button tone="safe" type="submit">Resolve</x-admin.button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('admin.reports.destroy', $report) }}" data-submit-guard>
                                            @csrf
                                            @method('DELETE')
                                            <x-admin.button tone="outline-danger" type="button" data-confirm-open="delete-report-{{ $report->id }}">Delete</x-admin.button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center">
                                    <p class="text-sm font-bold text-[#2D231E]">No reports match these filters.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pt-4">
                {{ $reports->links('pagination::teacher') }}
            </div>
        </x-admin.panel>

    </div>

    @foreach ($reports as $report)
        <x-modal id="delete-report-{{ $report->id }}" title="Delete this report?">
            <p class="text-xs font-semibold text-[#2D231E]">The report is removed permanently. Recorded in the audit log.</p>
            <div class="flex items-center justify-end gap-2">
                <x-admin.button tone="outline" type="button" data-modal-close>Cancel</x-admin.button>
                <x-admin.button tone="danger" type="button" data-confirm-approve>Delete report</x-admin.button>
            </div>
        </x-modal>
    @endforeach

    <x-modal id="bulk-delete-reports-modal" title="Delete selected reports?">
        <p class="text-xs font-semibold text-[#2D231E]">The selected reports are removed permanently. Each reporter is notified that their report was closed without changes. Recorded in the audit log.</p>
        <label class="flex items-start gap-3 cursor-pointer">
            <input type="checkbox" data-double-confirm class="mt-0.5 w-4 h-4 accent-rose-600 cursor-pointer">
            <span class="text-xs font-semibold text-[#2D231E]">
                I understand — these reports will be permanently deleted.
            </span>
        </label>
        <div class="flex items-center justify-end gap-2">
            <x-admin.button tone="outline" type="button" data-modal-close>Cancel</x-admin.button>
            <x-admin.button tone="danger" type="button" data-bulk-delete-approve>Delete selected</x-admin.button>
        </div>
    </x-modal>

    <script>
    (function () {
        var checks = document.querySelectorAll('[data-bulk-check]');
        var toggleAll = document.querySelector('[data-bulk-toggle-all]');
        var resolveBtn = document.querySelector('[formaction*="bulk-resolve"]');
        var deleteBtn = document.querySelector('[data-bulk-delete-open]');

        function selected() {
            return Array.from(checks).filter(function (el) { return el.checked; }).length;
        }

        function sync() {
            var n = selected();
            if (toggleAll) {
                var all = checks.length > 0 && n === checks.length;
                toggleAll.checked = all;
            }
            // Bulk actions stay invisible until something is ticked.
            if (resolveBtn) { resolveBtn.disabled = n === 0; resolveBtn.classList.toggle('hidden', n === 0); }
            if (deleteBtn) { deleteBtn.disabled = n === 0; deleteBtn.classList.toggle('hidden', n === 0); }
        }

        checks.forEach(function (el) { el.addEventListener('change', sync); });
        if (toggleAll) {
            toggleAll.addEventListener('change', function () {
                checks.forEach(function (el) { el.checked = toggleAll.checked; });
                sync();
            });
        }

        if (deleteBtn) {
            deleteBtn.addEventListener('click', function () {
                var form = deleteBtn.closest('form');
                if (!form || selected() === 0) { return; }
                var modal = document.getElementById('bulk-delete-reports-modal');
                if (modal) {
                    var checkbox = modal.querySelector('[data-double-confirm]');
                    if (checkbox) { checkbox.checked = false; }
                    syncDouble();
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                    modal.dispatchEvent(new CustomEvent('alice:modal-open'));
                }
            });
        }

        var approve = document.querySelector('[data-bulk-delete-approve]');
        var doubleConfirm = document.querySelector('#bulk-delete-reports-modal [data-double-confirm]');

        function syncDouble() {
            if (approve) {
                var ok = !doubleConfirm || doubleConfirm.checked;
                approve.disabled = !ok;
                approve.classList.toggle('opacity-40', !ok);
                approve.classList.toggle('pointer-events-none', !ok);
            }
        }

        if (doubleConfirm) { doubleConfirm.addEventListener('change', syncDouble); }

        if (approve) {
            approve.addEventListener('click', function () {
                if (!doubleConfirm || !doubleConfirm.checked) { return; }
                var form = document.querySelector('#reports-bulk-form');
                var modal = approve.closest('[data-modal]');
                if (modal) {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                }
                if (form) { form.submit(); }
            });
        }

        sync();
        syncDouble();
    })();
    </script>
@endsection