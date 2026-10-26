@extends('layouts.authenticated')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-10 space-y-6">

    <div class="bg-white border-4 border-[#2D231E] rounded-3xl p-6 sm:p-8 shadow-[6px_6px_0px_0px_rgba(45,35,30,1)] space-y-4 text-center">
        <h1 class="font-pixel text-base sm:text-lg font-bold text-rose-600 uppercase tracking-wider">
            Deletion Scheduled
        </h1>

        <div class="mx-auto w-16 h-16 rounded-2xl border-4 border-[#2D231E] bg-amber-400 flex items-center justify-center text-[#2D231E]">
            <svg class="w-8 h-8 stroke-current" fill="none" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
            </svg>
        </div>

        <p class="text-sm font-semibold text-[#2D231E] leading-relaxed">
            Your account will be permanently deleted on
            <span class="font-pixel text-rose-600 font-bold">{{ $expires?->format('M j, Y') }}</span>.
        </p>

        <p class="text-xs font-medium text-[#8C7B70] leading-relaxed">
            Within these {{ (int) config('auth.deletion_grace_days', 7) }} days you can change your mind any time —
            just below. After that date, all your saved words, progress, keys and badges are erased for good.
        </p>

        @if ($errors->any())
            <div class="bg-rose-50 border-2 border-rose-600 rounded-xl p-3 text-left space-y-1">
                @foreach ($errors->all() as $error)
                    <p class="text-xs font-bold text-rose-700">{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form action="{{ route('settings.delete.cancel') }}" method="POST" class="space-y-4 pt-2">
            @csrf

            <div class="space-y-1.5 text-left">
                <label class="block font-pixel text-[10px] sm:text-xs font-bold text-[#2D231E] uppercase tracking-wider">
                    Your password
                </label>
                <div class="relative">
                    <input
                        id="password"
                        type="password"
                        name="password"
                        data-no-spaces
                        required
                        class="w-full min-h-[44px] px-4 pr-12 py-2.5 bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl text-sm font-bold text-[#2D231E] placeholder-[#8C7B70] focus:outline-none focus:ring-2 focus:ring-amber-400 transition-all"
                    >
                    <button type="button" data-password-toggle="#password" aria-label="Show password" class="absolute right-3 top-1/2 -translate-y-1/2 text-[#8C7B70] hover:text-[#2D231E] transition-colors">
                        <svg data-icon="eye" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        <svg data-icon="eye-off" class="w-5 h-5 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                    </button>
                </div>
                <x-input-error field="password" />
            </div>

            <div class="space-y-1.5 text-left">
                <label class="block font-pixel text-[10px] sm:text-xs font-bold text-[#2D231E] uppercase tracking-wider">
                    A fresh email to restore
                </label>
                <input
                    type="email"
                    name="email"
                    data-no-spaces
                    data-live-validate="email"
                    placeholder="wanderer@example.com"
                    required
                    class="w-full min-h-[44px] px-4 py-2.5 bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl text-sm font-bold text-[#2D231E] placeholder-[#8C7B70] focus:outline-none focus:ring-2 focus:ring-amber-400 transition-all"
                >
                <p class="text-[11px] font-semibold text-[#8C7B70]">Your original email may already be in use again — any available address works.</p>
                <x-input-error field="email" />
            </div>

            <button
                type="submit"
                class="w-full min-h-[48px] font-pixel text-xs px-6 py-3 rounded-2xl bg-emerald-500 border-2 border-[#2D231E] text-white font-bold flex items-center justify-center shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all cursor-pointer uppercase"
            >
                Keep My Account
            </button>
        </form>
    </div>

    <p class="text-center text-[11px] font-semibold text-[#8C7B70]">
        Need help? Contact support.
    </p>
</div>
@endsection