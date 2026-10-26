@extends('layouts.guest')

@section('js')
    @vite(['resources/js/app.js'])
@endsection

@section('content')
<!-- Retro Dot/Grid Background Canvas -->
<div class="max-w-6xl mx-auto px-4 py-12 min-h-[75vh] flex items-center justify-center relative">

    <!-- Centered Forgot Password Box -->
    <div class="w-full max-w-md bg-white border-4 border-[#2D231E] rounded-3xl p-6 sm:p-8 shadow-[6px_6px_0px_0px_rgba(45,35,30,1)] space-y-6">

        <!-- Header / Status Badge -->
        <div class="space-y-3 text-center">
            <div class="inline-flex items-center gap-2 font-pixel text-[10px] sm:text-xs bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] px-3.5 py-1.5 rounded-full font-bold shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] uppercase tracking-wider">
                <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
                <span>PASSWORD RESET</span>
            </div>

            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-[#2D231E]">
                Forgot Your Password?
            </h1>
            <p class="text-xs sm:text-sm font-medium text-[#2D231E] leading-relaxed">
                Enter the email address you registered with. If it is in our system, we will send you a link to reset your password.
            </p>
        </div>

        <!-- Form -->
        <form action="{{ route('password.email') }}" method="POST" data-submit-guard class="space-y-5">
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
                        placeholder="yourname@example.com"
                        value="{{ old('email') }}"
                        required
                        class="w-full min-h-[44px] px-4 py-2.5 bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl text-sm font-bold text-[#2D231E] placeholder-[#8C7B70] focus:outline-none focus:ring-2 focus:ring-amber-400 transition-all"
                    >
                </div>
                <x-input-error field="email" />
                <p data-live-status="email" aria-live="polite" class="hidden mt-1.5 text-xs font-bold"></p>
            </div>

            <!-- Submit Button (3D Tactile) -->
            <div class="pt-2">
                <button
                    type="submit"
                    class="w-full min-h-[44px] font-pixel text-xs sm:text-sm px-6 py-3.5 rounded-2xl bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] font-bold flex items-center justify-center gap-2 shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all cursor-pointer"
                >
                    <span data-submit-idle>SEND RESET LINK</span>
                    <span data-submit-active class="hidden"><x-spinner label="Sending link..." /></span>
                </button>
            </div>
        </form>

        <!-- Footer Note -->
        <div class="border-t-2 border-dashed border-[#E6DCCF] pt-4 text-center">
            <p class="text-xs font-semibold text-[#2D231E]">
                Remember your password?
                <a href="{{ route('login') }}" class="font-bold text-[#2D231E] hover:underline">
                    Sign in
                </a>
            </p>
        </div>

    </div>

</div>
@endsection
