@extends('layouts.teacher')

@section('title', 'Deletion scheduled')
@section('eyebrow', 'Your account')

@section('content')
    <div class="max-w-2xl mx-auto space-y-5">

        <x-admin.panel title="Deletion scheduled">
            <div class="space-y-4 text-center">
                <p class="text-sm font-semibold text-[#2D231E] leading-relaxed">
                    Your account will be permanently erased on
                    <span class="font-bold text-rose-600">{{ $expires?->format('M j, Y') }}</span>.
                </p>

                <p class="text-xs font-medium text-[#2D231E] leading-relaxed">
                    Inside these {{ (int) config('auth.deletion_grace_days', 7) }} days you can change your mind below.
                    After that date your identity is removed — your classes, assignments and stories are reassigned or
                    archived by an administrator.
                </p>

                @if ($errors->any())
                    <div class="bg-rose-50 border border-rose-600 rounded-lg p-3 text-left space-y-1">
                        @foreach ($errors->all() as $error)
                            <p class="text-xs font-bold text-rose-700">{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                <form action="{{ route('teacher.settings.delete.cancel') }}" method="POST" class="space-y-4 pt-2 text-left">
                    @csrf

                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-[#2D231E] mb-1.5">Your password</label>
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

                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-[#2D231E] mb-1.5">A fresh email to restore</label>
                        <input
                            type="email"
                            name="email"
                            data-no-spaces
                            data-live-validate="email"
                            placeholder="teacher@example.com"
                            required
                            class="w-full min-h-[44px] px-4 py-2.5 bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl text-sm font-bold text-[#2D231E] placeholder-[#8C7B70] focus:outline-none focus:ring-2 focus:ring-amber-400 transition-all"
                        >
                        <p class="text-[11px] font-semibold text-[#2D231E]">Your original email may already be in use again — any available address works.</p>
                        <x-input-error field="email" />
                    </div>

                    <button
                        type="submit"
                        class="w-full inline-flex items-center justify-center min-h-[44px] px-5 py-2 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700 transition-colors cursor-pointer"
                    >
                        Keep my account
                    </button>
                </form>
            </div>
        </x-admin.panel>

        <p class="text-center text-[11px] font-semibold text-[#2D231E]">Need help? Contact support.</p>
    </div>
@endsection