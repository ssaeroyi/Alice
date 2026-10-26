@extends('layouts.authenticated')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8 space-y-6">

    <!-- PROFILE INFO -->
    <section class="bg-white border-4 border-[#2D231E] rounded-3xl p-5 sm:p-6 shadow-[5px_5px_0px_0px_rgba(45,35,30,1)] space-y-5">
        <div class="border-b-2 border-dashed border-[#E6DCCF] pb-3">
            <h2 class="font-pixel text-xs sm:text-sm font-bold text-[#2D231E] uppercase tracking-wider">
                PROFILE INFO
            </h2>
        </div>

        <div class="flex items-center justify-between gap-6">
            <div class="space-y-1 min-w-0">
                <p class="font-pixel text-[10px] sm:text-xs font-bold text-[#2D231E] uppercase tracking-wider">
                    Display Name
                </p>
                <p class="text-sm font-bold text-[#2D231E] truncate">
                    {{ $user->nickname }}
                </p>
            </div>

            <button
                type="button"
                data-modal-open="name-modal"
                class="shrink-0 min-h-[44px] font-pixel text-[10px] px-4 py-2 rounded-xl bg-[#FAF6EE] border-2 border-[#2D231E] text-[#2D231E] font-bold flex items-center justify-center shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] transition-all cursor-pointer uppercase"
            >
                <span>Edit</span>
            </button>
        </div>

        <div class="space-y-1.5">
            <p class="font-pixel text-[10px] sm:text-xs font-bold text-[#2D231E] uppercase tracking-wider">
                Grade Level
            </p>
            <div class="inline-flex items-center gap-2 font-pixel text-[9px] sm:text-[10px] bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] px-2.5 py-1 rounded-full font-bold uppercase tracking-wider">
                Grade {{ $user->effectiveGrade() }}
            </div>
            <p class="text-[11px] font-semibold text-[#2D231E]">
                Set during your reading check. Moves up each school year. Not editable here.
            </p>
        </div>
    </section>

    <!-- AVATAR -->
    <section class="bg-white border-4 border-[#2D231E] rounded-3xl p-5 sm:p-6 shadow-[5px_5px_0px_0px_rgba(45,35,30,1)] space-y-5">
        <div class="border-b-2 border-dashed border-[#E6DCCF] pb-3">
            <h2 class="font-pixel text-xs sm:text-sm font-bold text-[#2D231E] uppercase tracking-wider">
                AVATAR
            </h2>
        </div>

        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-5 min-w-0">
                <div class="shrink-0 w-20 h-20 rounded-2xl border-4 border-[#2D231E] overflow-hidden bg-[#FAF6EE] shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]">
                    @if (filled($user->avatar))
                        <img src="{{ asset('image/avatar/'.$user->avatar) }}" alt="Your avatar" class="w-full h-full object-cover">
                    @else
                        <div class="w-full h-full flex items-center justify-center font-pixel text-2xl text-[#2D231E]">
                            {{ strtoupper(substr($user->nickname, 0, 1)) }}
                        </div>
                    @endif
                </div>
                <p class="font-pixel text-[10px] sm:text-xs font-bold text-[#2D231E] uppercase tracking-wider">
                    Choose your avatar
                </p>
            </div>

            <button
                type="button"
                data-modal-open="avatar-modal"
                class="shrink-0 min-h-[44px] font-pixel text-[10px] px-4 py-2 rounded-xl bg-[#FAF6EE] border-2 border-[#2D231E] text-[#2D231E] font-bold flex items-center justify-center shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] transition-all cursor-pointer uppercase"
            >
                <span>Change</span>
            </button>
        </div>
    </section>

    <!-- ACCOUNT INFO -->
    <section class="bg-white border-4 border-[#2D231E] rounded-3xl p-5 sm:p-6 shadow-[5px_5px_0px_0px_rgba(45,35,30,1)] space-y-5">
        <div class="border-b-2 border-dashed border-[#E6DCCF] pb-3">
            <h2 class="font-pixel text-xs sm:text-sm font-bold text-[#2D231E] uppercase tracking-wider">
                ACCOUNT INFO
            </h2>
        </div>

        <div class="flex items-center justify-between gap-4">
            <div class="space-y-1 min-w-0">
                <p class="font-pixel text-[10px] sm:text-xs font-bold text-[#2D231E] uppercase tracking-wider">
                    Email
                </p>
                <p class="text-sm font-bold text-[#2D231E] truncate">
                    {{ $user->email }}
                </p>
            </div>

            <button
                type="button"
                data-modal-open="email-modal"
                class="shrink-0 min-h-[44px] font-pixel text-[10px] px-4 py-2 rounded-xl bg-[#FAF6EE] border-2 border-[#2D231E] text-[#2D231E] font-bold flex items-center justify-center shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] transition-all cursor-pointer uppercase"
            >
                <span>Edit</span>
            </button>
        </div>
    </section>

    <!-- PASSWORD & SECURITY -->
    <section class="bg-white border-4 border-[#2D231E] rounded-3xl p-5 sm:p-6 shadow-[5px_5px_0px_0px_rgba(45,35,30,1)] space-y-5">
        <div class="border-b-2 border-dashed border-[#E6DCCF] pb-3">
            <h2 class="font-pixel text-xs sm:text-sm font-bold text-[#2D231E] uppercase tracking-wider">
                PASSWORD AND SECURITY
            </h2>
        </div>

        <div class="flex items-center justify-between gap-6 pt-1">
            <div class="space-y-1.5 min-w-0">
                <p class="font-pixel text-[10px] sm:text-xs font-bold text-[#2D231E] uppercase tracking-wider">
                    Password
                </p>
                <p class="text-sm font-bold text-[#2D231E]">
                    ••••••••
                </p>
            </div>

            <div class="shrink-0 flex flex-col items-end gap-1.5">
                <button
                    type="button"
                    data-modal-open="password-modal"
                    class="min-h-[44px] font-pixel text-[10px] px-4 py-2 rounded-xl bg-[#FAF6EE] border-2 border-[#2D231E] text-[#2D231E] font-bold flex items-center justify-center shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] transition-all cursor-pointer uppercase"
                >
                    <span>Edit</span>
                </button>
                @if ($user->password_changed_at)
                    <p class="text-[10px] sm:text-xs font-bold text-emerald-700 text-right">
                        Last changed {{ $user->password_changed_at->diffForHumans() }}
                    </p>
                @endif
            </div>
        </div>
    </section>

    <!-- APP SETTINGS -->
    <section class="bg-white border-4 border-[#2D231E] rounded-3xl p-5 sm:p-6 shadow-[5px_5px_0px_0px_rgba(45,35,30,1)] space-y-5">
        <div class="border-b-2 border-dashed border-[#E6DCCF] pb-3">
            <h2 class="font-pixel text-xs sm:text-sm font-bold text-[#2D231E] uppercase tracking-wider">
                APP SETTINGS
            </h2>
        </div>

        <div class="flex items-center justify-between gap-4">
            <div class="space-y-1">
                <p class="font-pixel text-[10px] sm:text-xs font-bold text-[#2D231E] uppercase tracking-wider">
                    Dark Mode
                </p>
                <p class="text-xs font-medium text-[#2D231E]">Switch between light and dark theme.</p>
            </div>

            <button
                type="button"
                data-theme-toggle
                role="switch"
                aria-checked="false"
                class="shrink-0 flex items-center gap-2 cursor-pointer group"
                aria-label="Dark mode"
            >
                <span class="theme-switch shrink-0" aria-hidden="true">
                    <span class="theme-switch-thumb">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path data-switch-icon="sun" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                            <path data-switch-icon="moon" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                        </svg>
                    </span>
                </span>
            </button>
        </div>
    </section>

    <!-- AUDIO -->
    <section class="bg-white border-4 border-[#2D231E] rounded-3xl p-5 sm:p-6 shadow-[5px_5px_0px_0px_rgba(45,35,30,1)] space-y-5">
        <div class="border-b-2 border-dashed border-[#E6DCCF] pb-3">
            <h2 class="font-pixel text-xs sm:text-sm font-bold text-[#2D231E] uppercase tracking-wider">
                AUDIO
            </h2>
        </div>

        <div class="flex items-center justify-between gap-4">
            <div class="space-y-1">
                <p class="font-pixel text-[10px] sm:text-xs font-bold text-[#2D231E] uppercase tracking-wider">
                    Sound Effects
                </p>
                <p class="text-xs font-medium text-[#2D231E]">Play sounds for actions, rewards and games. The same toggle ALICE uses everywhere.</p>
            </div>

            <button
                type="button"
                data-sound-toggle
                class="shrink-0 w-14 h-14 bg-white border-2 border-[#2D231E] text-[#2D231E] rounded-2xl flex items-center justify-center shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] transition-all cursor-pointer"
                title="Sound: on. Tap to mute"
            >
                <span class="w-7 h-7 text-[#2D231E] [&>svg]:h-full [&>svg]:w-full" data-sound-on>{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'sound-on') !!}</span>
                <span class="w-7 h-7 text-[#8C7B70] [&>svg]:h-full [&>svg]:w-full hidden" data-sound-off>{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'sound-off') !!}</span>
            </button>
        </div>
    </section>

    <!-- DELETE ACCOUNT -->
    <section class="bg-white border-4 border-[#2D231E] rounded-3xl p-5 sm:p-6 shadow-[5px_5px_0px_0px_rgba(45,35,30,1)] space-y-5">
        <div class="border-b-2 border-dashed border-[#E6DCCF] pb-3">
            <h2 class="font-pixel text-xs sm:text-sm font-bold text-rose-600 uppercase tracking-wider">
                DELETE ACCOUNT
            </h2>
        </div>

        <p class="text-xs sm:text-sm font-medium text-[#2D231E] leading-relaxed">
            Schedule your account for deletion. You'll have a {{ (int) config('auth.deletion_grace_days', 7) }}-day grace
            period to change your mind, then your library collection and progress are permanently erased.
        </p>

        <div class="pt-1">
            <button
                type="button"
                data-modal-open="delete-account-modal"
                class="w-full sm:w-auto min-h-[44px] font-pixel text-xs px-6 py-3 rounded-2xl bg-white border-2 border-rose-600 text-rose-600 font-bold flex items-center justify-center shadow-[4px_4px_0px_0px_rgba(225,29,72,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0px_0px_rgba(225,29,72,1)] transition-all cursor-pointer uppercase"
            >
                <span>SCHEDULE DELETION</span>
            </button>
        </div>
    </section>

</div>

<!-- CHANGE AVATAR MODAL -->
<x-modal id="avatar-modal" title="Choose your avatar" pixel>
    <form action="{{ route('settings.avatar') }}" method="POST" data-submit-guard class="space-y-4">
        @csrf

        <div class="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 gap-3">
            @foreach (config('avatars', []) as $av)
                <label class="group relative aspect-square rounded-2xl border-2 border-[#2D231E] overflow-hidden bg-[#FAF6EE] shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] cursor-pointer transition-all hover:-translate-y-0.5 has-[:checked]:border-amber-500 has-[:checked]:ring-2 has-[:checked]:ring-amber-400 has-[:checked]:outline-2 has-[:checked]:outline-amber-400 has-[:checked]:outline-offset-2">
                    <input type="radio" name="avatar" value="{{ $av['file'] }}" class="sr-only" @checked(old('avatar', $user->avatar) === $av['file'])>
                    <img src="{{ asset('image/avatar/'.$av['file']) }}" alt="{{ $av['name'] }}" class="w-full h-full object-cover">
                </label>
            @endforeach
        </div>

        <x-input-error field="avatar" />

        <div class="flex gap-2 pt-1">
            <button
                type="button"
                data-modal-close
                class="flex-1 min-h-[44px] bg-white border-2 border-[#2D231E] rounded-2xl font-pixel text-[11px] font-bold text-[#2D231E] shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-none transition-all cursor-pointer uppercase"
            >
                Cancel
            </button>
            <button
                type="submit"
                class="flex-1 min-h-[44px] bg-amber-400 border-2 border-[#2D231E] rounded-2xl font-pixel text-[11px] font-bold text-[#2D231E] shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-none transition-all cursor-pointer uppercase flex items-center justify-center gap-2"
            >
                <span data-submit-idle>Save</span>
                <span data-submit-active class="hidden"><x-spinner label="Saving..." /></span>
            </button>
        </div>
    </form>
</x-modal>

<!-- CHANGE DISPLAY NAME MODAL -->
<x-modal id="name-modal" title="Change Display Name" pixel>
    <form action="{{ route('settings.name') }}" method="POST" data-submit-guard class="space-y-4">
        @csrf
        <input type="hidden" name="modal" value="name-modal">

        <div class="space-y-1.5">
            <label for="name" class="block font-pixel text-[10px] sm:text-xs font-bold text-[#2D231E] uppercase tracking-wider">
                Display Name
            </label>
            <input
                type="text"
                id="name"
                name="name"
                value="{{ old('name', $user->nickname) }}"
                placeholder="Wanderer"
                required
                maxlength="20"
                class="w-full min-h-[44px] px-4 py-2.5 bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl text-sm font-bold text-[#2D231E] placeholder-[#8C7B70] focus:outline-none focus:ring-2 focus:ring-amber-400 transition-all"
            >
            <p class="text-[11px] font-semibold text-[#2D231E]">
                This will act as your display name to Alice and to others.
            </p>
            <x-input-error field="name" />
        </div>

        <div class="pt-2">
            <button
                type="submit"
                class="w-full min-h-[44px] font-pixel text-xs px-6 py-3.5 rounded-2xl bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] font-bold flex items-center justify-center gap-2 shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all cursor-pointer uppercase"
            >
                <span data-submit-idle>UPDATE NAME</span>
                <span data-submit-active class="hidden"><x-spinner label="Updating..." /></span>
            </button>
        </div>
    </form>
</x-modal>

<!-- CHANGE EMAIL MODAL -->
<x-modal id="email-modal" title="Change Email" pixel>
    <form action="{{ route('settings.email') }}" method="POST" data-submit-guard class="space-y-5">
        @csrf
        <input type="hidden" name="modal" value="email-modal">

        <div class="space-y-1.5">
            <label for="email" class="block font-pixel text-[10px] sm:text-xs font-bold text-[#2D231E] uppercase tracking-wider">
                New Email
            </label>
            <input
                type="email"
                id="email"
                name="email"
                data-no-spaces
                data-live-validate="email"
                value="{{ old('email') }}"
                required
                class="w-full min-h-[44px] px-4 py-2.5 bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl text-sm font-bold text-[#2D231E] placeholder-[#8C7B70] focus:outline-none focus:ring-2 focus:ring-amber-400 transition-all"
            >
            <x-input-error field="email" />
            <p data-live-status="email" aria-live="polite" class="hidden mt-1.5 text-xs font-bold"></p>
        </div>

        <div class="space-y-1.5">
            <label for="email_current_password" class="block font-pixel text-[10px] sm:text-xs font-bold text-[#2D231E] uppercase tracking-wider">
                Current Password
            </label>
            <div class="relative flex items-center">
                <input
                    type="password"
                    id="email_current_password"
                    name="current_password"
                    data-no-spaces
                    required
                    class="w-full min-h-[44px] pl-4 pr-12 py-2.5 bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl text-sm font-bold text-[#2D231E] placeholder-[#8C7B70] focus:outline-none focus:ring-2 focus:ring-amber-400 transition-all"
                >
                <button
                    type="button"
                    data-password-toggle="#email_current_password"
                    aria-label="Show password"
                    class="absolute right-3 text-[#8C7B70] hover:text-[#2D231E] transition-colors"
                >
                    <svg data-icon="eye" class="w-5 h-5 stroke-current" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    <svg data-icon="eye-off" class="w-5 h-5 stroke-current hidden" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4l16 16"/></svg>
                </button>
            </div>
            <x-input-error field="current_password" />
        </div>

        <div class="pt-2">
            <button
                type="submit"
                class="w-full min-h-[44px] font-pixel text-xs px-6 py-3.5 rounded-2xl bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] font-bold flex items-center justify-center gap-2 shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all cursor-pointer uppercase"
            >
                <span data-submit-idle>UPDATE EMAIL</span>
                <span data-submit-active class="hidden"><x-spinner label="Updating..." /></span>
            </button>
        </div>
    </form>
</x-modal>

<!-- CHANGE PASSWORD MODAL -->
<x-modal id="password-modal" title="Change Password" pixel>
    <form action="{{ route('settings.password') }}" method="POST" data-submit-guard class="space-y-4">
        @csrf
        <input type="hidden" name="modal" value="password-modal">

        <div class="space-y-1.5">
            <label for="password_current_password" class="block font-pixel text-[10px] sm:text-xs font-bold text-[#2D231E] uppercase tracking-wider">
                Current Password
            </label>
            <div class="relative flex items-center">
                <input
                    type="password"
                    id="password_current_password"
                    name="current_password"
                    data-no-spaces
                    required
                    class="w-full min-h-[44px] pl-4 pr-12 py-2.5 bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl text-sm font-bold text-[#2D231E] placeholder-[#8C7B70] focus:outline-none focus:ring-2 focus:ring-amber-400 transition-all"
                >
                <button
                    type="button"
                    data-password-toggle="#password_current_password"
                    aria-label="Show password"
                    class="absolute right-3 text-[#8C7B70] hover:text-[#2D231E] transition-colors"
                >
                    <svg data-icon="eye" class="w-5 h-5 stroke-current" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    <svg data-icon="eye-off" class="w-5 h-5 stroke-current hidden" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4l16 16"/></svg>
                </button>
            </div>
            <x-input-error field="current_password" />
        </div>

        <div class="space-y-1.5">
            <label for="password" class="block font-pixel text-[10px] sm:text-xs font-bold text-[#2D231E] uppercase tracking-wider">
                New Password
            </label>
            <div class="relative flex items-center">
                <input
                    type="password"
                    id="password"
                    name="password"
                    data-no-spaces
                    data-live-validate="register-password"
                    required
                    class="w-full min-h-[44px] pl-4 pr-12 py-2.5 bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl text-sm font-bold text-[#2D231E] placeholder-[#8C7B70] focus:outline-none focus:ring-2 focus:ring-amber-400 transition-all"
                >
                <button
                    type="button"
                    data-password-toggle="#password"
                    aria-label="Show password"
                    class="absolute right-3 text-[#8C7B70] hover:text-[#2D231E] transition-colors"
                >
                    <svg data-icon="eye" class="w-5 h-5 stroke-current" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    <svg data-icon="eye-off" class="w-5 h-5 stroke-current hidden" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4l16 16"/></svg>
                </button>
            </div>
            <x-input-error field="password" />
            <div data-live-status="password" aria-live="polite" class="hidden mt-1.5 space-y-1"></div>
        </div>

        <div class="space-y-1.5">
            <label for="password_confirmation" class="block font-pixel text-[10px] sm:text-xs font-bold text-[#2D231E] uppercase tracking-wider">
                Confirm New Password
            </label>
            <div class="relative flex items-center">
                <input
                    type="password"
                    id="password_confirmation"
                    name="password_confirmation"
                    data-no-spaces
                    data-live-validate="confirm"
                    required
                    class="w-full min-h-[44px] pl-4 pr-12 py-2.5 bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl text-sm font-bold text-[#2D231E] placeholder-[#8C7B70] focus:outline-none focus:ring-2 focus:ring-amber-400 transition-all"
                >
                <button
                    type="button"
                    data-password-toggle="#password_confirmation"
                    aria-label="Show password"
                    class="absolute right-3 text-[#8C7B70] hover:text-[#2D231E] transition-colors"
                >
                    <svg data-icon="eye" class="w-5 h-5 stroke-current" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    <svg data-icon="eye-off" class="w-5 h-5 stroke-current hidden" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4l16 16"/></svg>
                </button>
            </div>
            <p data-live-status="password_confirmation" aria-live="polite" class="hidden text-right text-xs font-bold"></p>
            <x-input-error field="password_confirmation" />
        </div>

        <div class="pt-3">
            <button
                type="submit"
                class="w-full min-h-[44px] font-pixel text-xs px-6 py-3.5 rounded-2xl bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] font-bold flex items-center justify-center gap-2 shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all cursor-pointer uppercase"
            >
                <span data-submit-idle>UPDATE PASSWORD</span>
                <span data-submit-active class="hidden"><x-spinner label="Updating..." /></span>
            </button>
        </div>
    </form>
</x-modal>

<!-- SCHEDULE DELETION CONFIRM MODAL -->
<x-modal id="delete-account-modal" title="Schedule Deletion" pixel>
    <form action="{{ route('settings.delete.request') }}" method="POST" data-submit-guard class="space-y-4">
        @csrf
        <input type="hidden" name="modal" value="delete-account-modal">

        <div class="bg-rose-50 border-2 border-rose-600 rounded-2xl p-4 space-y-2">
            <p class="font-pixel text-[10px] font-bold text-rose-700 uppercase tracking-wider">
                What happens next
            </p>
            <ul class="text-xs font-medium text-[#2D231E] leading-relaxed list-disc pl-4 space-y-1">
                <li>Your account is locked immediately — sign-in is blocked.</li>
                <li>You'll keep the ability to undo for <strong>{{ (int) config('auth.deletion_grace_days', 7) }} days</strong>.</li>
                <li>After that, your library collection and progress will be permanently erased from our system.</li>
            </ul>
        </div>

        <div class="space-y-1.5">
            <label for="delete_password" class="block font-pixel text-[10px] sm:text-xs font-bold text-[#2D231E] uppercase tracking-wider">
                Current Password
            </label>
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
                    class="absolute right-3 text-[#8C7B70] hover:text-[#2D231E] transition-colors"
                >
                    <svg data-icon="eye" class="w-5 h-5 stroke-current" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    <svg data-icon="eye-off" class="w-5 h-5 stroke-current hidden" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4l16 16"/></svg>
                </button>
            </div>
            <x-input-error field="password" />
        </div>

        <div class="space-y-1.5">
            <label for="delete_nickname" class="block font-pixel text-[10px] sm:text-xs font-bold text-[#2D231E] uppercase tracking-wider">
                Type your display name to confirm
            </label>
            <input
                type="text"
                id="delete_nickname"
                name="nickname_confirm"
                data-no-spaces
                placeholder="{{ $user->nickname }}"
                required
                class="w-full min-h-[44px] px-4 py-2.5 bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl text-sm font-bold text-[#2D231E] placeholder-[#8C7B70] focus:outline-none focus:ring-2 focus:ring-amber-400 transition-all"
            >
            <p class="text-[11px] font-semibold text-[#2D231E]">Must match your display name ({{ $user->nickname }}).</p>
            <x-input-error field="nickname_confirm" />
        </div>

        <div class="flex items-center justify-between gap-3 pt-2">
            <button
                type="button"
                data-modal-close="delete-account-modal"
                class="min-h-[44px] font-pixel text-xs px-5 py-3 rounded-2xl bg-[#FAF6EE] border-2 border-[#2D231E] text-[#2D231E] font-bold flex items-center justify-center shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] transition-all cursor-pointer uppercase"
            >
                <span>Cancel</span>
            </button>
            <button
                type="submit"
                class="flex-1 min-h-[44px] font-pixel text-xs px-5 py-3 rounded-2xl bg-rose-600 border-2 border-[#2D231E] text-white font-bold flex items-center justify-center gap-2 shadow-[3px_3px_0px_0px_rgba(225,29,72,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-[1px_1px_0px_0px_rgba(225,29,72,1)] transition-all cursor-pointer uppercase"
            >
                <span data-submit-idle>Schedule Deletion</span>
                <span data-submit-active class="hidden"><x-spinner label="Deleting..." /></span>
            </button>
        </div>
    </form>
</x-modal>

@endsection
