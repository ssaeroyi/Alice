@extends('layouts.teacher')

@section('title', 'Settings')
@section('eyebrow', 'Your account')

@section('content')
    <div class="max-w-3xl mx-auto space-y-5">

        <x-admin.panel title="Profile">
            <div class="divide-y divide-[#F0E9DD]">
                <div class="flex flex-wrap items-center justify-between gap-3 pb-4">
                    <div class="min-w-0">
                        <p class="text-xs font-bold text-[#2D231E]">Username</p>
                        <p class="text-sm font-semibold text-[#2D231E] truncate">{{ $user->nickname }}</p>
                    </div>
                    <button
                        type="button"
                        data-modal-open="nickname-modal"
                        class="shrink-0 min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
                    >
                        Edit
                    </button>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 pt-4">
                    <div class="min-w-0">
                        <p class="text-xs font-bold text-[#2D231E]">Name</p>
                        <p class="text-sm font-semibold text-[#2D231E] truncate">
                            {{ trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: '—' }}
                        </p>
                    </div>
                    <button
                        type="button"
                        data-modal-open="name-modal"
                        class="shrink-0 min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
                    >
                        Edit
                    </button>
                </div>
            </div>
        </x-admin.panel>

        <x-admin.panel title="Password & security">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-bold text-[#2D231E]">Password</p>
                    <p class="text-sm font-semibold text-[#2D231E]">&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;</p>
                    @if ($user->password_changed_at)
                    <p class="mt-1 text-[11px] font-semibold text-emerald-700">
                        Last changed {{ $user->password_changed_at->diffForHumans() }}
                    </p>
                    @endif
                </div>
                <button
                    type="button"
                    data-modal-open="password-modal"
                    class="shrink-0 min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
                >
                    Edit
                </button>
            </div>
        </x-admin.panel>

        <section class="bg-rose-50 border border-rose-600 rounded-xl">
            <div class="px-4 sm:px-5 pt-4 sm:pt-5 pb-4 border-b border-rose-600">
                <h2 class="text-sm font-extrabold text-rose-700 flex items-center gap-1.5">Danger zone</h2>
            </div>
            <div class="p-4 sm:p-5 space-y-4">
                <div class="space-y-1">
                    <p class="text-xs font-bold text-[#2D231E]">Schedule account deletion</p>
                    <p class="text-sm font-semibold text-[#2D231E] leading-relaxed">
                        Locks your account immediately. Inside a {{ (int) config('auth.deletion_grace_days', 7) }}-day
                        grace period you can undo. After that your identity is erased — your classes and assignments
                        are reassigned or archived by an administrator.
                    </p>
                </div>
                <button
                    type="button"
                    data-modal-open="delete-account-modal"
                    class="w-full sm:w-auto min-h-[44px] px-5 py-2 bg-white border-2 border-rose-600 text-rose-600 rounded-lg text-xs font-bold hover:bg-rose-100 transition-colors cursor-pointer"
                >
                    Schedule deletion
                </button>
            </div>
        </section>

    </div>

    <!-- DISPLAY NAME MODAL -->
    <x-modal id="nickname-modal" title="Edit username">
        <form method="POST" action="{{ route('teacher.settings.profile') }}" class="space-y-4">
            @csrf
            @method('PUT')
            <input type="hidden" name="modal" value="nickname-modal">

            <div>
                <label for="nickname" class="block text-xs font-bold text-[#2D231E] mb-1.5">Username</label>
                <input
                    id="nickname"
                    type="text"
                    name="nickname"
                    value="{{ old('nickname', $user->nickname) }}"
                    autocomplete="username"
                    class="w-full h-11 px-3 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] focus:outline-none focus:border-[#2D231E] @error('nickname') border-rose-400 @enderror"
                >
                @error('nickname')
                    <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-[11px] font-semibold text-[#2D231E]">
                    Letters, numbers, spaces, hyphens, and underscores. 20 characters or fewer.
                </p>
            </div>

            <button
                type="submit"
                data-submit-guard
                class="w-full inline-flex items-center justify-center min-h-[44px] px-5 py-2 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700 transition-colors cursor-pointer"
            >
                <span data-submit-idle>Save username</span>
                <span data-submit-active class="hidden"><x-spinner label="Saving..." /></span>
            </button>
        </form>
    </x-modal>

    <!-- NAME MODAL -->
    <x-modal id="name-modal" title="Edit name">
        <form method="POST" action="{{ route('teacher.settings.profile') }}" class="space-y-4">
            @csrf
            @method('PUT')
            <input type="hidden" name="modal" value="name-modal">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="first_name" class="block text-xs font-bold text-[#2D231E] mb-1.5">First name</label>
                    <input
                        id="first_name"
                        type="text"
                        name="first_name"
                        value="{{ old('first_name', $user->first_name) }}"
                        autocomplete="given-name"
                        class="w-full h-11 px-3 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] focus:outline-none focus:border-[#2D231E] @error('first_name') border-rose-400 @enderror"
                    >
                    @error('first_name')
                        <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="last_name" class="block text-xs font-bold text-[#2D231E] mb-1.5">Last name</label>
                    <input
                        id="last_name"
                        type="text"
                        name="last_name"
                        value="{{ old('last_name', $user->last_name) }}"
                        autocomplete="family-name"
                        class="w-full h-11 px-3 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] focus:outline-none focus:border-[#2D231E] @error('last_name') border-rose-400 @enderror"
                    >
                    @error('last_name')
                        <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <button
                type="submit"
                data-submit-guard
                class="w-full inline-flex items-center justify-center min-h-[44px] px-5 py-2 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700 transition-colors cursor-pointer"
            >
                <span data-submit-idle>Save name</span>
                <span data-submit-active class="hidden"><x-spinner label="Saving..." /></span>
            </button>
        </form>
    </x-modal>

    <!-- PASSWORD MODAL -->
    <x-modal id="password-modal" title="Change password">
        <form method="POST" action="{{ route('teacher.settings.password') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="modal" value="password-modal">

            <div>
                <label for="current_password" class="block text-xs font-bold text-[#2D231E] mb-1.5">Current password</label>
                <input
                    id="current_password"
                    type="password"
                    name="current_password"
                    autocomplete="current-password"
                    class="w-full h-11 px-3 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] focus:outline-none focus:border-[#2D231E] @error('current_password') border-rose-400 @enderror"
                >
                @error('current_password')
                    <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="password" class="block text-xs font-bold text-[#2D231E] mb-1.5">New password</label>
                    <div class="relative">
                        <input
                            id="password"
                            type="password"
                            name="password"
                            autocomplete="new-password"
                            data-no-spaces
                            data-live-validate="register-password"
                            class="w-full h-11 px-3 pr-10 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] focus:outline-none focus:border-[#2D231E] @error('password') border-rose-400 @enderror"
                        >
                        <button type="button" data-password-toggle="#password" aria-label="Show password" class="absolute right-3 top-1/2 -translate-y-1/2 text-[#8C7B70] hover:text-[#2D231E] transition-colors">
                            <svg data-icon="eye" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg data-icon="eye-off" class="w-5 h-5 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                        </button>
                    </div>
                    <div data-live-status="password" aria-live="polite" class="hidden mt-1.5 space-y-1"></div>
                    @error('password')
                        <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-xs font-bold text-[#2D231E] mb-1.5">Confirm new password</label>
                    <input
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        autocomplete="new-password"
                        class="w-full h-11 px-3 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] focus:outline-none focus:border-[#2D231E] @error('password_confirmation') border-rose-400 @enderror"
                    >
                    @error('password_confirmation')
                        <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <button
                type="submit"
                data-submit-guard
                class="w-full inline-flex items-center justify-center min-h-[44px] px-5 py-2 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700 transition-colors cursor-pointer"
            >
                <span data-submit-idle>Update password</span>
                <span data-submit-active class="hidden"><x-spinner label="Updating..." /></span>
            </button>
        </form>
    </x-modal>

<x-modal id="delete-account-modal" title="Schedule deletion" pixel>
    <form action="{{ route('teacher.settings.delete.request') }}" method="POST" data-submit-guard class="space-y-4">
        @csrf
        <input type="hidden" name="modal" value="delete-account-modal">

        <div class="bg-rose-50 border border-rose-600 rounded-xl p-4 space-y-2">
            <p class="text-[10px] font-bold text-rose-700 uppercase tracking-wider">What happens next</p>
            <ul class="text-xs font-medium text-[#2D231E] leading-relaxed list-disc pl-4 space-y-1">
                <li>Your account locks immediately — sign-in is blocked.</li>
                <li>You can undo for {{ (int) config('auth.deletion_grace_days', 7) }} days.</li>
                <li>After that, your identity is erased. Your classes, assignments and stories are reassigned or archived by an admin.</li>
                <li>Your email address becomes available for a new account right away.</li>
            </ul>
        </div>

        <div class="space-y-1.5">
            <label for="delete_password" class="block text-[10px] sm:text-xs font-bold text-[#2D231E] uppercase tracking-wider">Current password</label>
            <div class="relative flex items-center">
                <input
                    type="password"
                    id="delete_password"
                    name="password"
                    data-no-spaces
                    required
                    class="w-full min-h-[44px] pl-4 pr-12 py-2.5 bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl text-sm font-bold text-[#2D231E] placeholder-[#8C7B70] focus:outline-none focus:ring-2 focus:ring-amber-400 transition-all"
                >
                <button
                    type="button"
                    data-password-toggle="#delete_password"
                    aria-label="Show password"
                    class="absolute right-3 text-[#2D231E] transition-colors"
                >
                    <svg data-icon="eye" class="w-5 h-5 stroke-current" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    <svg data-icon="eye-off" class="w-5 h-5 stroke-current hidden" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4l16 16"/></svg>
                </button>
            </div>
            <x-input-error field="password" />
        </div>

        <div class="space-y-1.5">
            <label for="delete_nickname" class="block text-[10px] sm:text-xs font-bold text-[#2D231E] uppercase tracking-wider">Type your display name to confirm</label>
            <input
                type="text"
                id="delete_nickname"
                name="nickname_confirm"
                data-no-spaces
                placeholder="{{ $user->nickname }}"
                required
                class="w-full min-h-[44px] px-4 py-2.5 bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl text-sm font-bold text-[#2D231E] placeholder-[#8C7B70] focus:outline-none focus:ring-2 focus:ring-amber-400 transition-all"
            >
            <p class="text-[11px] font-semibold text-[#2D231E]">Must match your username ({{ $user->nickname }}).</p>
            <x-input-error field="nickname_confirm" />
        </div>

        <div class="flex items-center justify-between gap-3 pt-1">
            <button
                type="button"
                data-modal-close="delete-account-modal"
                class="min-h-[44px] px-5 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors cursor-pointer"
            >
                Cancel
            </button>
            <button
                type="submit"
                class="flex-1 min-h-[44px] px-5 py-2 bg-rose-600 text-white rounded-lg text-xs font-bold hover:bg-rose-700 transition-colors cursor-pointer inline-flex items-center justify-center gap-2"
            >
                <span data-submit-idle>Schedule deletion</span>
                <span data-submit-active class="hidden"><x-spinner label="Deleting..." /></span>
            </button>
        </div>
    </form>
</x-modal>
@endsection
