@extends('layouts.guest')

@section('js')
    @vite(['resources/js/app.js'])
@endsection

@section('content')
<!-- Retro Dot/Grid Background Canvas (Bounded to max-w-6xl like Landing) -->
<div class="max-w-6xl mx-auto px-4 py-12 min-h-[75vh] flex items-center justify-center relative">

    <!-- Centered Minimal Login Box (AWS Style Layout + DS Aesthetics) -->
    <div class="w-full max-w-md bg-white border-4 border-[#2D231E] rounded-3xl p-6 sm:p-8 shadow-[6px_6px_0px_0px_rgba(45,35,30,1)] space-y-6">
        
        <!-- Header / Greeting Badge -->
        <div class="space-y-3 text-center">
            <div class="inline-flex items-center gap-2 font-pixel text-[10px] sm:text-xs bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] px-3.5 py-1.5 rounded-full font-bold shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] uppercase tracking-wider">
                <!-- SVG Key/Sparkle Icon -->
                <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M12.65 10C11.83 7.67 9.61 6 7 6c-3.31 0-6 2.69-6 6s2.69 6 6 6c2.61 0 4.83-1.67 5.65-4H17v2h2v-2h2v-2h-8.35zM7 14c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z"/></svg>
                <span>LOGIN</span>
            </div>

            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-[#2D231E]">
                Welcome Back!
            </h1>
            <p class="text-xs sm:text-sm font-medium text-[#2D231E]">
                Enter your credentials to continue your journey.
            </p>
        </div>

        <!-- Login Form -->
        <form action="{{ route('login.submit') }}" method="POST" data-submit-guard class="space-y-5">
            @csrf

            <!-- Email Field -->
            <div class="space-y-1.5">
                <label for="email" class="block font-pixel text-[10px] sm:text-xs font-bold text-[#2D231E] uppercase tracking-wider">
                    Email Address
                </label>
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
                <label for="password" class="block font-pixel text-[10px] sm:text-xs font-bold text-[#2D231E] uppercase tracking-wider">
                    Password
                </label>

                <div class="relative flex items-center">
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        data-no-spaces 
                        data-live-validate="login-password" 
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
                <p data-live-status="password" aria-live="polite" class="hidden mt-1.5 text-xs font-bold"></p>
            </div>

            <!-- Remember Me + Forgot Password -->
            <div class="flex items-start justify-between gap-4 pt-1">
                <div class="space-y-1">
                    <label for="remember" class="inline-flex items-center gap-2 cursor-pointer">
                        <input 
                            type="checkbox" 
                            id="remember" 
                            name="remember" 
                            value="1" 
                            @checked(old('remember')) 
                            class="w-4 h-4 accent-amber-400"
                        >
                        <span class="text-xs font-bold text-[#2D231E]">Remember me</span>
                    </label>
                    <p class="text-[10px] font-medium text-[#2D231E] pl-6">
                        Only check this on your own device.
                    </p>
                </div>
                <a href="{{ route('password.request') }}" class="text-xs font-bold text-amber-700 hover:text-[#2D231E] hover:underline transition-colors shrink-0">
                    Forgot Password?
                </a>
            </div>

            <!-- Submit Button (Tactile 3D Button) -->
            <div class="pt-2">
                <button 
                    type="submit" 
                    class="w-full min-h-[44px] font-pixel text-xs sm:text-sm px-6 py-3.5 rounded-2xl bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] font-bold flex items-center justify-center gap-2 shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all cursor-pointer"
                >
                    <span data-submit-idle>SIGN IN</span>
                    <span data-submit-active class="hidden"><x-spinner label="Signing in..." /></span>
                </button>
            </div>
        </form>

        <!-- Footer Link (Register Prompt) -->
        <div class="border-t-2 border-dashed border-[#E6DCCF] pt-4 text-center">
            <p class="text-xs font-semibold text-[#2D231E]">
                Don't have an account yet? 
                <a href="{{ route('register') }}" class="font-bold text-[#2D231E] hover:underline">
                    Create an account
                </a>
            </p>
        </div>

    </div>

    <!-- Account-scheduled-for-deletion recovery funnel (opened when a failed
         login's password matched a soft-deleted account still in grace) -->
    <x-modal
        id="deletion-restore"
        pixel
        :open="session()->has('deletion_pending')"
        title="Restore account"
    >
        <div id="restore-step-1" class="space-y-6 py-1 {{ $errors->any() ? 'hidden' : '' }}">
            <div class="text-center">
                <p class="text-sm font-bold text-[#2D231E]">We found an account matching your details that is scheduled for deletion.</p>
            </div>

            <button
                type="button"
                data-restore-next
                class="w-full min-h-[44px] font-pixel text-xs px-6 py-3.5 rounded-2xl bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] font-bold flex items-center justify-center gap-2 shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all cursor-pointer uppercase"
            >
                Next
            </button>
        </div>

        <div id="restore-step-2" class="space-y-5 {{ $errors->any() ? '' : 'hidden' }}">
            <p class="text-xs leading-5 font-medium text-[#2D231E]">
                Your account is scheduled for deletion on {{ session('deletion_pending_expires') }}.
                If this was a mistake, you can restore it right now. Nothing is lost until that date.
            </p>

            <div class="bg-[#FAF6EE] border-2 border-[#E6DCCF] rounded-xl p-4 space-y-3">
                <form action="{{ route('login.restore') }}" method="POST" data-submit-guard class="space-y-4">
                    @csrf

                    <div class="space-y-1.5">
                        <label for="restore-email" class="block font-pixel text-[10px] font-bold text-[#2D231E] uppercase tracking-wider">
                            New email address
                        </label>
                        <input
                            type="email"
                            id="restore-email"
                            name="email"
                            data-no-spaces
                            value="{{ old('email') }}"
                            required
                            class="w-full min-h-[44px] px-4 py-2.5 bg-white border-2 border-[#2D231E] rounded-xl text-sm font-bold text-[#2D231E] placeholder-[#B5A89C] focus:outline-none focus:ring-2 focus:ring-amber-400 transition-all"
                        >
                        <x-input-error field="email" />
                    </div>

                    <div class="space-y-1.5">
                        <label for="restore-password" class="block font-pixel text-[10px] font-bold text-[#2D231E] uppercase tracking-wider">
                            Your password
                        </label>
                        <input
                            type="password"
                            id="restore-password"
                            name="password"
                            data-no-spaces
                            required
                            class="w-full min-h-[44px] px-4 py-2.5 bg-white border-2 border-[#2D231E] rounded-xl text-sm font-bold text-[#2D231E] placeholder-[#B5A89C] focus:outline-none focus:ring-2 focus:ring-amber-400 transition-all"
                        >
                        <x-input-error field="password" />
                    </div>

                    <button
                        type="submit"
                        class="w-full min-h-[44px] font-pixel text-xs px-6 py-3.5 rounded-2xl bg-rose-500 border-2 border-[#2D231E] text-white font-bold flex items-center justify-center gap-2 shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all cursor-pointer"
                    >
                        <span data-submit-idle>RESTORE MY ACCOUNT</span>
                        <span data-submit-active class="hidden"><x-spinner label="Restoring..." /></span>
                    </button>
                </form>
            </div>

            <button
                type="button"
                data-restore-back
                class="w-full min-h-[44px] font-pixel text-xs px-6 py-3.5 rounded-2xl bg-white border-2 border-[#2D231E] text-[#2D231E] font-bold flex items-center justify-center gap-2 shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all cursor-pointer uppercase"
            >
                Back
            </button>
        </div>

        <script>
            (function () {
                var step1 = document.getElementById('restore-step-1');
                var step2 = document.getElementById('restore-step-2');
                var next = document.querySelector('[data-restore-next]');
                var back = document.querySelector('[data-restore-back]');
                if (!step1 || !step2) return;
                if (next) next.addEventListener('click', function () {
                    step1.classList.add('hidden');
                    step2.classList.remove('hidden');
                    var email = document.getElementById('restore-email');
                    if (email) email.focus();
                });
                if (back) back.addEventListener('click', function () {
                    step2.classList.add('hidden');
                    step1.classList.remove('hidden');
                });
            })();
        </script>
    </x-modal>

</div>
@endsection
