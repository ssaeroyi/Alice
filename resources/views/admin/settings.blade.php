@extends('layouts.admin')

@section('title', 'Settings')
@section('eyebrow', 'Your account')
@section('page', 'admin-settings')

@section('content')
    <div class="max-w-3xl mx-auto space-y-5">

        <x-admin.panel title="Profile">
            <div class="divide-y divide-[#F0E9DD]">
                <div class="flex flex-wrap items-center justify-between gap-3 pb-4">
                    <div class="min-w-0">
                        <p class="text-xs font-bold text-[#2D231E]">Display name</p>
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

                <div class="flex flex-wrap items-center justify-between gap-3 py-4">
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

                <div class="flex flex-wrap items-center justify-between gap-3 pt-4">
                    <div class="min-w-0">
                        <p class="text-xs font-bold text-[#2D231E]">Email</p>
                        <p class="text-sm font-semibold text-[#2D231E] truncate">{{ $user->email }}</p>
                    </div>
                    <button
                        type="button"
                        data-modal-open="email-modal"
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

    </div>

    <!-- DISPLAY NAME MODAL -->
    <x-modal id="nickname-modal" title="Edit display name">
        <form method="POST" action="{{ route('admin.settings.profile') }}" class="space-y-4">
            @csrf
            @method('PUT')
            <input type="hidden" name="modal" value="nickname-modal">

            <div>
                <label for="nickname" class="block text-xs font-bold text-[#2D231E] mb-1.5">Display name</label>
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
                <span data-submit-idle>Save display name</span>
                <span data-submit-active class="hidden"><x-spinner label="Saving..." /></span>
            </button>
        </form>
    </x-modal>

    <!-- NAME MODAL -->
    <x-modal id="name-modal" title="Edit name">
        <form method="POST" action="{{ route('admin.settings.profile') }}" class="space-y-4">
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

    <!-- EMAIL MODAL -->
    <x-modal id="email-modal" title="Change email">
        <form method="POST" action="{{ route('admin.settings.email') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="modal" value="email-modal">

            <div>
                <label for="email" class="block text-xs font-bold text-[#2D231E] mb-1.5">New email address</label>
                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email', $user->email) }}"
                    autocomplete="email"
                    class="w-full h-11 px-3 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] focus:outline-none focus:border-[#2D231E] @error('email') border-rose-400 @enderror"
                >
                @error('email')
                    <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="email_password" class="block text-xs font-bold text-[#2D231E] mb-1.5">Current password</label>
                <input
                    id="email_password"
                    type="password"
                    name="current_password"
                    autocomplete="current-password"
                    class="w-full h-11 px-3 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] focus:outline-none focus:border-[#2D231E] @error('current_password') border-rose-400 @enderror"
                >
                @error('current_password')
                    <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-[11px] font-semibold text-[#2D231E]">
                    Confirm with your current password to protect the change.
                </p>
            </div>

            <button
                type="submit"
                data-submit-guard
                class="w-full inline-flex items-center justify-center min-h-[44px] px-5 py-2 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700 transition-colors cursor-pointer"
            >
                <span data-submit-idle>Update email</span>
                <span data-submit-active class="hidden"><x-spinner label="Saving..." /></span>
            </button>
        </form>
    </x-modal>

    <!-- PASSWORD MODAL -->
    <x-modal id="password-modal" title="Change password">
        <form method="POST" action="{{ route('admin.settings.password') }}" class="space-y-4">
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
@endsection
