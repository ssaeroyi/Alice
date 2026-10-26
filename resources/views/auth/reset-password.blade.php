@extends('layouts.guest')

@section('js')
    @vite(['resources/js/app.js'])
@endsection

@section('content')
<!-- Retro Dot/Grid Background Canvas -->
<div class="max-w-6xl mx-auto px-4 py-12 min-h-[75vh] flex items-center justify-center relative">

    <!-- Centered One-Time Reset Box -->
    <div class="w-full max-w-md bg-white border-4 border-[#2D231E] rounded-3xl p-6 sm:p-8 shadow-[6px_6px_0px_0px_rgba(45,35,30,1)] space-y-6">

        <!-- Header / Status Badge -->
        <div class="space-y-3 text-center">
            <div class="inline-flex items-center gap-2 font-pixel text-[10px] sm:text-xs bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] px-3.5 py-1.5 rounded-full font-bold shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] uppercase tracking-wider">
                <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
                <span>RESET PASSWORD</span>
            </div>

            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-[#2D231E]">
                Set New Password
            </h1>
            <p class="text-xs sm:text-sm font-medium text-[#2D231E] leading-relaxed">
                @if ($email)
                    Resetting the password for <span class="font-bold text-[#2D231E]">{{ $email }}</span>. Please enter and confirm your new password below.
                @else
                    Please enter and confirm your new password below.
                @endif
            </p>
        </div>

        <!-- Form -->
        <form action="{{ route('password.update') }}" method="POST" data-submit-guard class="space-y-4">
            @csrf

            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="email" value="{{ $email }}">

            <x-input-error field="email" />

            <!-- New Password Field -->
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

            <!-- Re-enter New Password Field -->
            <div class="space-y-1.5">
                <label for="password_confirmation" class="block font-pixel text-[10px] sm:text-xs font-bold text-[#2D231E] uppercase tracking-wider">
                    Re-enter New Password
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

            <!-- Submit Button (3D Tactile) -->
            <div class="pt-3">
                <button
                    type="submit"
                    class="w-full min-h-[44px] font-pixel text-xs sm:text-sm px-6 py-3.5 rounded-2xl bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] font-bold flex items-center justify-center gap-2 shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all cursor-pointer"
                >
                    <span data-submit-idle>CONFIRM RESET</span>
                    <span data-submit-active class="hidden"><x-spinner label="Resetting..." /></span>
                </button>
            </div>
        </form>

        <!-- Footer Note -->
        <div class="border-t-2 border-dashed border-[#E6DCCF] pt-4 text-center">
            <p class="text-xs font-semibold text-[#2D231E]">
                Link expired?
                <a href="{{ route('password.request') }}" class="font-bold text-[#2D231E] hover:underline">
                    Request a new one
                </a>
            </p>
        </div>

    </div>

</div>
@endsection
