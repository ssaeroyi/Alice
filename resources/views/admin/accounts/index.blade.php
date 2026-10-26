@extends('layouts.admin')

@section('title', 'Accounts')
@section('eyebrow', 'Accounts')

@section('content')
    <div class="space-y-5">

        @php
            $accountFilterCount = collect([
                request('role') ? 1 : 0,
                request('grade') ? 1 : 0,
                request('status') ? 1 : 0,
                request('setup') ? 1 : 0,
                request('sort') && request('sort') !== 'newest' ? 1 : 0,
            ])->sum();
        @endphp

        <x-admin.panel>
            <div class="flex flex-wrap items-center gap-2">
                <x-admin.search-input
                    :action="route('admin.accounts.index')"
                    :value="request('q')"
                    placeholder="Search nickname or email"
                    label="Search accounts"
                    form-class="relative min-w-0 flex-1"
                />

                <div class="flex flex-wrap items-center gap-2">
                <x-admin.filter-drawer
                    label="Filters"
                    :action="route('admin.accounts.index')"
                    :count="$accountFilterCount"
                    search-name=""
                    :reset-url="route('admin.accounts.index')"
                >
                    <input type="hidden" name="q" value="{{ request('q') }}">

                    <label class="block">
                        <span class="text-[11px] font-bold uppercase tracking-wide text-[#2D231E]">Role</span>
                        <select name="role" class="mt-1 w-full h-11 px-3 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] focus:outline-none focus:border-[#2D231E] cursor-pointer">
                            <option value="">Any role</option>
                            @foreach (\App\Enums\Role::cases() as $roleOption)
                                <option value="{{ $roleOption->value }}" @selected(request('role') === $roleOption->value)>
                                    {{ $roleOption->label() }}
                                </option>
                            @endforeach
                        </select>
                    </label>

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
                        <span class="text-[11px] font-bold uppercase tracking-wide text-[#2D231E]">Status</span>
                        <select name="status" class="mt-1 w-full h-11 px-3 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] focus:outline-none focus:border-[#2D231E] cursor-pointer">
                            <option value="">Any status</option>
                            <option value="active" @selected(request('status') === 'active')>Active</option>
                            <option value="suspended" @selected(request('status') === 'suspended')>Suspended</option>
                            <option value="deletion-pending" @selected(request('status') === 'deletion-pending')>Deletion pending</option>
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
                        <span class="text-[11px] font-bold uppercase tracking-wide text-[#2D231E]">Sort by</span>
                        <select name="sort" class="mt-1 w-full h-11 px-3 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] focus:outline-none focus:border-[#2D231E] cursor-pointer">
                            <option value="newest" @selected(request('sort', 'newest') === 'newest')>Newest first</option>
                            <option value="oldest" @selected(request('sort') === 'oldest')>Oldest first</option>
                            <option value="name_asc" @selected(request('sort') === 'name_asc')>Nickname A–Z</option>
                            <option value="name_desc" @selected(request('sort') === 'name_desc')>Nickname Z–A</option>
                        </select>
                    </label>
                </x-admin.filter-drawer>

                <a
                    href="{{ route('admin.accounts.export', request()->query()) }}"
                    class="inline-flex items-center justify-center min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors"
                >
                    Export CSV
                </a>
                <a
                    href="{{ route('admin.accounts.create') }}"
                    class="inline-flex items-center justify-center min-h-[44px] px-4 py-2 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700 transition-colors"
                >
                    Add teacher
                </a>
                </div>
            </div>

            <p class="mt-3 text-xs font-semibold text-[#2D231E]">
                {{ $users->total() }} account{{ $users->total() === 1 ? '' : 's' }} found
            </p>
        </x-admin.panel>

        <x-admin.panel>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-[#E6DCCF] text-[11px] uppercase tracking-wider text-[#2D231E]">
                            <th class="px-4 py-3 font-extrabold">Account</th>
                            <th class="px-4 py-3 font-extrabold hidden sm:table-cell">Grade</th>
                            <th class="px-4 py-3 font-extrabold hidden lg:table-cell">Joined</th>
                            <th class="px-4 py-3 font-extrabold hidden lg:table-cell">Last login</th>
                            <th class="px-4 py-3 font-extrabold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#F0E9DD]">
                        @forelse ($users as $account)
                            <tr class="odd:bg-[#FAF6EE] hover:bg-[#F3ECE0] transition-colors">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <x-avatar :user="$account" :size="36" />
                                        <div class="min-w-0">
                                            <a href="{{ route('admin.accounts.show', $account) }}" class="block font-bold text-amber-700 hover:text-amber-800 hover:underline underline-offset-2 truncate">
                                                {{ $account->nickname ?? strtok($account->email, '@') }}
                                            </a>
                                            <p class="text-[11px] font-semibold text-[#2D231E] truncate">
                                                @if ($account->deleted_at)
                                                    Deletion expires {{ $account->deletionExpiresAt()?->format('M j, Y') }}
                                                @else
                                                    {{ $account->email }}
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 hidden sm:table-cell text-xs font-bold text-[#2D231E]">
                                    {{ $account->grade_level ? 'Grade '.$account->effectiveGrade() : '—' }}
                                </td>
                                <td class="px-4 py-3 hidden lg:table-cell text-xs font-semibold text-[#2D231E]">
                                    {{ $account->created_at->format('M j, Y') }}
                                </td>
                                <td class="px-4 py-3 hidden lg:table-cell text-xs font-semibold text-[#2D231E]">
                                    {{ $account->last_login_at?->format('M j, Y') ?? 'Never' }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1.5">
                                        @if ($account->deleted_at)
                                            <x-admin.pill tone="crit" title="Expires {{ $account->deletionExpiresAt()?->format('M j, Y') }}">
                                                Deletion pending
                                            </x-admin.pill>
                                        @elseif ($account->suspended_at)
                                            <x-admin.pill tone="crit">Suspended</x-admin.pill>
                                        @elseif ($account->is_setup_complete)
                                            <x-admin.pill tone="ok">Active</x-admin.pill>
                                        @else
                                            <x-admin.pill>Setup pending</x-admin.pill>
                                        @endif
                                        <x-admin.pill :tone="$account->role === \App\Enums\Role::Admin ? 'warn' : ($account->role === \App\Enums\Role::Teacher ? 'ok' : 'neutral')">
                                            {{ $account->role->value }}
                                        </x-admin.pill>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                    <td colspan="5" class="px-4 py-10 text-center">
                                    <p class="text-sm font-bold text-[#2D231E]">No accounts match these filters.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pt-4">
                {{ $users->links('pagination::teacher') }}
            </div>
        </x-admin.panel>

    </div>
@endsection
