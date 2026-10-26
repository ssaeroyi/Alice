@extends('layouts.guest')

@section('js')
    @vite(['resources/js/app.js'])
@endsection

@section('content')
<!-- Retro Dot/Grid Background Canvas (Bounded to max-w-6xl like Landing & Login) -->
<div class="max-w-6xl mx-auto px-4 py-12 min-h-[75vh] flex items-center justify-center relative">

    <!-- Centered Registration Box -->
    <div class="w-full max-w-md bg-white border-4 border-[#2D231E] rounded-3xl p-6 sm:p-8 shadow-[6px_6px_0px_0px_rgba(45,35,30,1)] space-y-6">
        
        <!-- Header / Greeting Badge -->
        <div class="space-y-3 text-center">
            <div class="inline-flex items-center gap-2 font-pixel text-[10px] sm:text-xs bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] px-3.5 py-1.5 rounded-full font-bold shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] uppercase tracking-wider">
                <!-- SVG Sparkle / User Plus Icon -->
                <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                <span>REGISTER</span>
            </div>

            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-[#2D231E]">
                Start Your Journey
            </h1>
            <p class="text-xs sm:text-sm font-medium text-[#2D231E]">
                Create an account to begin learning at your own pace.
            </p>
        </div>

        <!-- Registration Form -->
        <form action="{{ route('register.submit') }}" method="POST" data-submit-guard class="space-y-4">
            @csrf

            <!-- Username Field -->
            <div class="space-y-1.5">
                <div class="flex items-center gap-1.5">
                    <label for="nickname" class="font-pixel text-[10px] sm:text-xs font-bold text-[#2D231E] uppercase tracking-wider">
                        Username
                    </label>
                    <x-info label="What's my username?">
                        This should be your unique username you can use to make your account unique.
                    </x-info>
                </div>
                <div class="relative">
                    <input
                        type="text"
                        id="nickname"
                        name="nickname"
                        value="{{ old('nickname') }}"
                        required
                        maxlength="20"
                        class="w-full min-h-[44px] px-4 py-2.5 bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl text-sm font-bold text-[#2D231E] placeholder-[#8C7B70] focus:outline-none focus:ring-2 focus:ring-amber-400 transition-all"
                    >
                </div>
                <x-input-error field="nickname" />
            </div>

            <!-- Email Field -->
            <div class="space-y-1.5">
                <div class="flex items-center gap-1.5">
                    <label for="email" class="font-pixel text-[10px] sm:text-xs font-bold text-[#2D231E] uppercase tracking-wider">
                        Email Address
                    </label>
                    <x-info label="Why do you need my email?">
                        This should be a functioning email you can actually use for ALICE.
                    </x-info>
                </div>
                <div class="relative">
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
                </div>
                <x-input-error field="email" />
                <p data-live-status="email" aria-live="polite" class="hidden mt-1.5 text-xs font-bold"></p>
            </div>

            <!-- Password Field -->
            <div class="space-y-1.5">
                <div class="flex items-center gap-1.5">
                    <label for="password" class="font-pixel text-[10px] sm:text-xs font-bold text-[#2D231E] uppercase tracking-wider">
                        Password
                    </label>
                    <x-info label="How do I make a strong password?">
                        Your password should contain letters, numbers, and symbols to make it safe.
                    </x-info>
                </div>

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

            <!-- Confirm Password Field -->
            <div class="space-y-1.5">
                <label for="password_confirmation" class="block font-pixel text-[10px] sm:text-xs font-bold text-[#2D231E] uppercase tracking-wider">
                    Confirm Password
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

                <!-- Live password match indicator (right-aligned) -->
                <p data-live-status="password_confirmation" aria-live="polite" class="hidden text-right text-xs font-bold"></p>

                <x-input-error field="password_confirmation" />
            </div>

            <!-- Terms Consent -->
            <div class="pt-1">
                <label for="terms" class="inline-flex items-start gap-2 cursor-pointer">
                    <input 
                        type="checkbox" 
                        id="terms" 
                        name="terms" 
                        required 
                        class="w-4 h-4 accent-amber-400 mt-0.5"
                    >
                    <span class="text-xs font-bold text-[#2D231E] leading-relaxed">
                        I agree to the
                        <button type="button" data-modal-open="terms-modal" class="font-bold text-amber-700 hover:text-[#2D231E] hover:underline cursor-pointer">
                            Terms & Conditions
                        </button>
                    </span>
                </label>
                <x-input-error field="terms" />
            </div>

            <!-- Submit Button (Tactile 3D Button) -->
            <div class="pt-3">
                <button 
                    type="submit" 
                    class="w-full min-h-[44px] font-pixel text-xs sm:text-sm px-6 py-3.5 rounded-2xl bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] font-bold flex items-center justify-center gap-2 shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all cursor-pointer"
                >
                    <span data-submit-idle>CREATE ACCOUNT</span>
                    <span data-submit-active class="hidden"><x-spinner label="Creating account..." /></span>
                </button>
            </div>
        </form>

        <!-- Terms & Conditions Overlay -->
        <x-modal id="terms-modal" title="TERMS & CONDITIONS" pixel wide sticky>
            <div class="flex items-center justify-between border-b-2 border-dashed border-[#E6DCCF] pb-3">
                <span class="font-pixel text-[9px] sm:text-[10px] bg-[#E6DCCF] border border-[#2D231E] text-[#2D231E] px-2 py-1 rounded-md font-bold uppercase">
                    LAST UPDATED: SEP 2026
                </span>
            </div>
            @include('footer.partials.terms-sections')
        </x-modal>

        <!-- Footer Link (Login Prompt) -->
        <div class="border-t-2 border-dashed border-[#E6DCCF] pt-4 text-center">
            <p class="text-xs font-semibold text-[#2D231E]">
                Already have an account? 
                <a href="{{ route('login') }}" class="font-bold text-[#2D231E] hover:underline">
                    Sign in here
                </a>
            </p>
        </div>

    </div>

</div>
@endsection
