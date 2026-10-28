@extends('layouts.admin')

@section('title', $account->nickname ?? strtok($account->email, '@'))
@section('eyebrow', 'Accounts')

@section('content')
    <div class="space-y-5">

        <a href="{{ route('admin.accounts.index', request()->query()) }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-[#2D231E] hover:text-amber-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path></svg>
            Back to accounts
        </a>

        <!-- HEADER -->
        <div class="bg-white border border-[#E6DCCF] rounded-xl p-5 sm:p-6">
            <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                <x-avatar :user="$account" :alt="$account->nickname ?? 'Account'" :size="56" />
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        @php $adminFullName = trim(($account->first_name ?? '').' '.($account->last_name ?? '')); @endphp
                        <h1 class="text-base font-extrabold text-[#2D231E] truncate">
                            {{ $adminFullName ?: ($account->nickname ?? strtok($account->email, '@')) }}
                        </h1>
                        @if ($account->nickname && $adminFullName)
                            <x-admin.pill>{{ $account->nickname }}</x-admin.pill>
                        @endif
                        <x-admin.pill :tone="$account->role === \App\Enums\Role::Admin ? 'warn' : ($account->role === \App\Enums\Role::Teacher ? 'ok' : 'neutral')">
                            {{ $account->role->value }}
                        </x-admin.pill>
                        @if ($account->suspended_at)
                            <x-admin.pill tone="crit">Suspended</x-admin.pill>
                        @endif
                        @if ($account->deleted_at)
                            <x-admin.pill tone="crit" title="Expires {{ $account->deletionExpiresAt()?->format('M j, Y') }}">
                                Deletion pending
                            </x-admin.pill>
                        @endif
                    </div>
                    <p class="text-sm font-semibold text-[#2D231E] mt-0.5 truncate">{{ $account->email }}</p>
                    <p class="text-[11px] font-semibold text-[#2D231E] mt-1">
                        Joined {{ $account->created_at->format('M j, Y') }},
                        last login {{ $account->last_login_at?->format('M j, Y g:i A') ?? 'never' }}.
                    </p>
                </div>
            </div>
        </div>

        <!-- ACCOUNT DETAILS -->
        <x-admin.panel title="Account details" subtitle="Setup and placement data only.">
            <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <dt class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E]">Full name</dt>
                    <dd class="mt-1 text-sm font-bold text-[#2D231E]">
                        {{ trim(($account->first_name ?? '').' '.($account->last_name ?? '')) ?: ($account->nickname ?? strtok($account->email, '@')) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E]">Username</dt>
                    <dd class="mt-1 text-sm font-bold text-[#2D231E]">{{ $account->nickname ?? 'Not set' }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E]">Grade</dt>
                    <dd class="mt-1 text-sm font-bold text-[#2D231E]">
                        {{ $account->grade_level ? 'Grade '.$account->effectiveGrade() : 'Not set' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E]">Placement</dt>
                    <dd class="mt-1">
                        @if ($account->is_setup_complete)
                            <x-admin.pill tone="ok">Complete</x-admin.pill>
                        @else
                            <x-admin.pill>Pending</x-admin.pill>
                        @endif
                    </dd>
                </div>
                @if ($account->suspended_at)
                    <div>
                        <dt class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E]">Suspended since</dt>
                        <dd class="mt-1 text-sm font-bold text-rose-600">{{ $account->suspended_at->format('M j, Y g:i A') }}</dd>
                    </div>
                @endif
                @if ($account->deleted_at)
                    <div>
                        <dt class="text-[11px] font-extrabold uppercase tracking-wider text-[#2D231E]">Deletion expires</dt>
                        <dd class="mt-1 text-sm font-bold text-rose-600">
                            {{ $account->deletionExpiresAt()?->format('M j, Y g:i A') ?? 'Now' }}
                        </dd>
                    </div>
                @endif
            </dl>
        </x-admin.panel>

        <!-- ACTIONS -->
        @if (auth()->id() === $account->id)
            <x-admin.panel title="Account actions">
                <p class="text-xs font-semibold text-[#2D231E]">
                    This is your own account — it cannot be changed from the web (no self-suspension, no self-reset,
                    no self role change). Manage it from the command line:
                </p>
                <p class="mt-3 px-3 py-2 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] font-mono">
                    php artisan admin:manage list&nbsp;&middot;&nbsp;reset-password&nbsp;&middot;&nbsp;set-role&nbsp;&middot;&nbsp;create
                </p>
            </x-admin.panel>
        @else
            <x-admin.panel title="Account actions">
                <div class="flex flex-wrap items-center gap-3">
                    @if ($account->suspended_at === null)
                    <form method="POST" action="{{ route('admin.accounts.suspend', $account) }}">
                        @csrf
                        <x-admin.button tone="danger" type="button" data-modal-open="suspend-modal">
                            Suspend account
                        </x-admin.button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.accounts.reactivate', $account) }}">
                        @csrf
                        <x-admin.button tone="safe" type="button" data-confirm-open="reactivate-modal">
                            Reactivate account
                        </x-admin.button>
                    </form>
                @endif

                <form method="POST" action="{{ route('admin.accounts.reset-link', $account) }}">
                    @csrf
                    <x-admin.button tone="outline" type="button" data-confirm-open="reset-link-modal">
                        Send reset link
                    </x-admin.button>
                </form>

                <form method="POST" action="{{ route('admin.accounts.sign-out', $account) }}">
                    @csrf
                    <x-admin.button tone="outline" type="button" data-confirm-open="sign-out-modal">
                        Sign out all sessions
                    </x-admin.button>
                </form>

                <x-admin.button tone="outline" type="button" data-modal-open="role-modal">
                    Change role
                </x-admin.button>
            </div>
            </x-admin.panel>

            @if ($account->deleted_at === null && $account->role !== \App\Enums\Role::Admin)
                <div class="bg-white border border-rose-200 rounded-xl overflow-hidden">
                    <div class="flex items-start justify-between gap-4 px-4 sm:px-5 pt-4 sm:pt-5 pb-4 border-b border-rose-100">
                        <div class="min-w-0">
                            <h2 class="text-sm font-extrabold text-rose-700 flex items-center gap-1.5">Danger zone</h2>
                            <p class="text-xs font-semibold text-[#2D231E] mt-0.5">Irreversible actions. Read the confirm prompt carefully.</p>
                        </div>
                    </div>
                    <div class="p-4 sm:p-5">
                        <form method="POST" action="{{ route('admin.accounts.destroy', $account) }}">
                            @csrf
                            <x-admin.button tone="danger" type="button" data-confirm-open="delete-modal">
                                Schedule deletion
                            </x-admin.button>
                        </form>
                    </div>
                </div>
            @endif
        @endif

        <!-- DELETION PENDING -->
        @if ($account->deleted_at && auth()->id() !== $account->id)
            <x-admin.panel title="Deletion in progress">
                <div class="space-y-3">
                    <p class="text-xs font-semibold text-[#2D231E] leading-relaxed">
                        This account requested deletion and has been locked. Its identity (email, name, avatar) has already
                        been scrambled and is available for reuse. Restore it before the grace window
                        (expires {{ $account->deletionExpiresAt()?->format('M j, Y g:i A') }}) to let the holder sign in again;
                        students past that date are permanently erased by the nightly purge.
                    </p>
                    <form method="POST" action="{{ route('admin.accounts.restore', $account) }}">
                        @csrf
                        <div class="flex flex-wrap items-end gap-2">
                            <div class="min-w-0 flex-1">
                                <label for="restore_email" class="block text-xs font-bold text-[#2D231E] mb-1.5">New sign-in email</label>
                                <input
                                    type="email"
                                    id="restore_email"
                                    name="email"
                                    data-no-spaces
                                    required
                                    placeholder="student@example.com"
                                    value="{{ old('email') }}"
                                    class="w-full h-11 px-3 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] placeholder:text-[#B5A89C] focus:outline-none focus:border-[#2D231E]"
                                >
                                @error('email')
                                    <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                        <div class="mt-3 flex items-center justify-end">
                            <x-admin.button tone="safe" type="button" data-confirm-open="restore-modal">
                                Restore account
                            </x-admin.button>
                        </div>
                    </form>
                </div>
            </x-admin.panel>
        @endif



        <!-- SESSIONS -->
        <x-admin.panel title="Active sessions" subtitle="Sessions are revoked when the user signs out or when you sign them out here.">
            @if ($sessions->isEmpty())
                <p class="text-sm font-semibold text-[#2D231E]">No active sessions.</p>
            @else
                <div class="overflow-x-auto table-scroll">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-[#E6DCCF] text-[11px] uppercase tracking-wider text-[#2D231E]">
                                <th class="px-4 py-3 font-extrabold">IP address</th>
                                <th class="px-4 py-3 font-extrabold hidden sm:table-cell">Device</th>
                                <th class="px-4 py-3 font-extrabold">Last activity</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#F0E9DD]">
                            @foreach ($sessions as $session)
                                <tr class="odd:bg-[#FAF6EE] hover:bg-[#F3ECE0] transition-colors">
                                    <td class="px-4 py-3 text-xs font-bold text-[#2D231E]">{{ $session->ip_address }}</td>
                                    <td class="px-4 py-3 hidden sm:table-cell text-xs font-semibold text-[#2D231E] max-w-[260px] truncate">{{ $session->user_agent }}</td>
                                    <td class="px-4 py-3 text-xs font-semibold text-[#2D231E]">
                                        {{ \Carbon\Carbon::createFromTimestamp($session->last_activity)->diffForHumans() }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-admin.panel>

        <!-- CONSENT LOG -->
        <x-admin.panel title="Consent log" subtitle="Every policy the account has accepted.">
            @if ($consents->isEmpty())
                <p class="text-sm font-semibold text-[#2D231E]">No consent records yet.</p>
            @else
                <div class="overflow-x-auto table-scroll">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-[#E6DCCF] text-[11px] uppercase tracking-wider text-[#2D231E]">
                                <th class="px-4 py-3 font-extrabold">Type</th>
                                <th class="px-4 py-3 font-extrabold">Document version</th>
                                <th class="px-4 py-3 font-extrabold">Accepted</th>
                                <th class="px-4 py-3 font-extrabold hidden sm:table-cell">IP address</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#F0E9DD]">
                            @foreach ($consents as $consent)
                                <tr class="odd:bg-[#FAF6EE] hover:bg-[#F3ECE0] transition-colors">
                                    <td class="px-4 py-3 text-xs font-bold text-[#2D231E]">{{ $consent->type }}</td>
                                    <td class="px-4 py-3 text-xs font-semibold text-[#2D231E]">{{ $consent->document_version }}</td>
                                    <td class="px-4 py-3 text-xs font-semibold text-[#2D231E]">{{ $consent->accepted_at->format('M j, Y g:i A') }}</td>
                                    <td class="px-4 py-3 hidden sm:table-cell text-xs font-semibold text-[#2D231E]">{{ $consent->ip_address }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-admin.panel>

        <!-- AUDIT TRAIL -->
        <x-admin.panel title="Audit trail" subtitle="Account-related actions, most recent first.">
            @if ($audit->isEmpty())
                <p class="text-sm font-semibold text-[#2D231E]">No audit events for this account yet.</p>
            @else
                <div class="overflow-x-auto table-scroll">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-[#E6DCCF] text-[11px] uppercase tracking-wider text-[#2D231E]">
                                <th class="px-4 py-3 font-extrabold">Action</th>
                                <th class="px-4 py-3 font-extrabold">By</th>
                                <th class="px-4 py-3 font-extrabold">When</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#F0E9DD]">
                            @foreach ($audit as $entry)
                                <tr class="odd:bg-[#FAF6EE] hover:bg-[#F3ECE0] transition-colors">
                                    <td class="px-4 py-3">
                                        <p class="text-xs font-bold text-[#2D231E]">{{ $entry->action }}</p>
                                        @if ($entry->meta)
                                            <p class="text-[11px] font-semibold text-[#2D231E]">{{ json_encode($entry->meta) }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-xs font-semibold text-[#2D231E]">
                                        {{ $entry->actor?->nickname ?? $entry->actor?->email ?? 'System' }}
                                    </td>
                                    <td class="px-4 py-3 text-xs font-semibold text-[#2D231E]">{{ $entry->created_at->format('M j, Y g:i A') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-admin.panel>

    </div>

    @if (auth()->id() !== $account->id)

    <!-- SUSPEND MODAL -->
    <x-modal id="suspend-modal" title="Suspend {{ $account->nickname ?? strtok($account->email, '@') }}?">
        <p class="text-xs font-semibold text-[#2D231E]">
            The account is locked out immediately. Every sign-in is blocked until an admin reactivates it. This action is recorded in the audit log.
        </p>
        <form method="POST" action="{{ route('admin.accounts.suspend', $account) }}" class="space-y-4">
            @csrf
            <div>
                <label for="reason" class="block text-xs font-bold text-[#2D231E] mb-1.5">Reason (required)</label>
                <textarea
                    id="reason"
                    name="reason"
                    rows="3"
                    maxlength="200"
                    required
                    placeholder="e.g. Account flagged for review"
                    class="w-full px-3 py-2 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] placeholder:text-[#B5A89C] focus:outline-none focus:border-[#2D231E]"
                ></textarea>
            </div>
            <div class="flex items-center justify-end gap-2">
                <button type="button" data-modal-close class="min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer">Cancel</button>
                <button
                    type="submit"
                    data-submit-guard
                    class="inline-flex items-center justify-center min-h-[44px] px-5 py-2 bg-rose-600 text-white rounded-lg text-xs font-bold hover:bg-rose-700 transition-colors cursor-pointer"
                >
                    <span data-submit-idle>Suspend account</span>
                    <span data-submit-active class="hidden"><x-spinner label="Suspending..." /></span>
                </button>
            </div>
        </form>
    </x-modal>

    <!-- ROLE MODAL -->
    <x-modal id="role-modal" title="Change role">
        <p class="text-xs font-semibold text-[#2D231E]">
            Roles gate what {{ $account->nickname ?? strtok($account->email, '@') }} sees: students use the learning
            app, teachers get the learning tools. Moving someone in or out of the administrator role is done from the
            command line instead (<span class="font-mono font-bold text-[#2D231E]">php artisan admin:manage set-role</span>).
        </p>
        <form method="POST" action="{{ route('admin.accounts.role', $account) }}" class="space-y-4">
            @csrf
            <div>
                <label for="role" class="block text-xs font-bold text-[#2D231E] mb-1.5">New role</label>
                <select id="role" name="role" class="w-full h-11 px-3 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] focus:outline-none focus:border-[#2D231E] cursor-pointer">
                    @foreach ([
                        \App\Enums\Role::Student,
                        \App\Enums\Role::Teacher,
                    ] as $roleOption)
                        <option value="{{ $roleOption->value }}" @selected($account->role === $roleOption)>
                            {{ $roleOption->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center justify-end gap-2">
                <button type="button" data-modal-close class="min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer">Cancel</button>
                <button
                    type="submit"
                    data-submit-guard
                    class="inline-flex items-center justify-center min-h-[44px] px-5 py-2 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700 transition-colors cursor-pointer"
                >
                    <span data-submit-idle>Save role</span>
                    <span data-submit-active class="hidden"><x-spinner label="Saving..." /></span>
                </button>
            </div>
        </form>
    </x-modal>

    <!-- REACTIVATE CONFIRM -->
    <x-modal id="reactivate-modal" title="Reactivate {{ $account->nickname ?? strtok($account->email, '@') }}?">
        <p class="text-xs font-semibold text-[#2D231E]">Sign-in is allowed again immediately. This action is recorded in the audit log.</p>
        <div class="flex items-center justify-end gap-2">
            <button type="button" data-modal-close class="min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer">Cancel</button>
            <button type="button" data-confirm-approve class="inline-flex items-center justify-center min-h-[44px] px-5 py-2 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700 transition-colors cursor-pointer">
                Reactivate account
            </button>
        </div>
    </x-modal>

    <!-- RESET LINK CONFIRM -->
    <x-modal id="reset-link-modal" title="Send a password reset link?">
        <p class="text-xs font-semibold text-[#2D231E]">
            A reset email goes to <span class="font-bold text-[#2D231E]">{{ $account->email }}</span>. The current password stops working once the account holder sets a new one. Recorded in the audit log.
        </p>
        <div class="flex items-center justify-end gap-2">
            <button type="button" data-modal-close class="min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer">Cancel</button>
            <button type="button" data-confirm-approve class="inline-flex items-center justify-center min-h-[44px] px-5 py-2 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700 transition-colors cursor-pointer">
                Send link
            </button>
        </div>
    </x-modal>

    <!-- SIGN OUT CONFIRM -->
    <x-modal id="sign-out-modal" title="Sign out all sessions?">
        <p class="text-xs font-semibold text-[#2D231E]">
            Every device {{ $account->nickname ?? strtok($account->email, '@') }} is signed in on gets logged out and must sign in again. Recorded in the audit log.
        </p>
        <div class="flex items-center justify-end gap-2">
            <button type="button" data-modal-close class="min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer">Cancel</button>
            <button type="button" data-confirm-approve class="inline-flex items-center justify-center min-h-[44px] px-5 py-2 bg-rose-600 text-white rounded-lg text-xs font-bold hover:bg-rose-700 transition-colors cursor-pointer">
                Sign out sessions
            </button>
        </div>
    </x-modal>

    <!-- RESTORE CONFIRM -->
    <x-modal id="restore-modal" title="Restore this account?">
        <p class="text-xs font-semibold text-[#2D231E]">
            The account is unlocked instantly with the new email you typed. The holder can sign in again and keeps their
            saved progress. The deletion schedule is cancelled. Recorded in the audit log.
        </p>
        <div class="flex items-center justify-end gap-2">
            <button type="button" data-modal-close class="min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer">Cancel</button>
            <button type="button" data-confirm-approve class="inline-flex items-center justify-center min-h-[44px] px-5 py-2 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700 transition-colors cursor-pointer">
                Restore account
            </button>
        </div>
    </x-modal>

    <!-- DELETE CONFIRM -->
    @if ($account->deleted_at === null && $account->role !== \App\Enums\Role::Admin)
        <x-modal id="delete-modal" title="Schedule deletion for {{ $account->nickname ?? strtok($account->email, '@') }}?">
            <p class="text-xs font-semibold text-[#2D231E]">
                This schedules the account for deletion. The email, name and avatar are scrambled immediately and every session is signed out. The account is purged after the grace window ({{ (int) config('auth.deletion_grace_days', 7) }} days). Restore it from this page before then to undo. Recorded in the audit log.
            </p>
            <div class="flex items-center justify-end gap-2">
                <button type="button" data-modal-close class="min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer">Cancel</button>
                <button type="button" data-confirm-approve class="inline-flex items-center justify-center min-h-[44px] px-5 py-2 bg-rose-600 text-white rounded-lg text-xs font-bold hover:bg-rose-700 transition-colors cursor-pointer">
                    Schedule deletion
                </button>
            </div>
        </x-modal>
    @endif
    @endif
@endsection
