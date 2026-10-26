@extends('layouts.admin')

@section('title', 'Create teacher')
@section('eyebrow', 'Accounts')

@section('content')
    <div class="max-w-xl">
        <x-admin.panel
            title="Create teacher account"
            subtitle="The teacher gets their sign-in details by email and can sign in right away."
        >
            <form method="POST" action="{{ route('admin.accounts.store') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="nickname" class="block text-xs font-bold text-[#2D231E] mb-1.5">Username</label>
                    <input
                        id="nickname"
                        type="text"
                        name="nickname"
                        value="{{ old('nickname') }}"
                        autocomplete="username"
                        class="w-full h-11 px-3 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] focus:outline-none focus:border-[#2D231E] @error('nickname') border-rose-400 @enderror"
                    >
                    @error('nickname')
                        <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="email" class="block text-xs font-bold text-[#2D231E] mb-1.5">Email address</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        autocomplete="email"
                        class="w-full h-11 px-3 bg-[#FAF6EE] border border-[#E6DCCF] rounded-lg text-sm font-semibold text-[#2D231E] focus:outline-none focus:border-[#2D231E] @error('email') border-rose-400 @enderror"
                    >
                    @error('email')
                        <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-[11px] font-semibold text-[#2D231E]">
                        Already registered as a student? Change their role from the account page instead.
                    </p>
                </div>

                <div>
                    <label for="password" class="block text-xs font-bold text-[#2D231E] mb-1.5">
                        Temporary password
                    </label>
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
                    <p class="mt-1 text-[11px] font-semibold text-[#2D231E]">
                        We email this to the teacher with their sign-in instructions. They set their own password in Settings afterwards.
                    </p>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <a
                        href="{{ route('admin.accounts.index') }}"
                        class="inline-flex items-center justify-center min-h-[44px] px-4 py-2 bg-[#F3ECE0] border border-[#E6DCCF] rounded-lg text-xs font-bold text-[#2D231E] hover:border-[#2D231E] transition-colors"
                    >
                        Cancel
                    </a>
                    <button
                        type="submit"
                        data-submit-guard
                        class="inline-flex items-center justify-center min-h-[44px] px-5 py-2 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700 transition-colors cursor-pointer"
                    >
                        <span data-submit-idle>Create teacher</span>
                        <span data-submit-active class="hidden"><x-spinner label="Creating..." /></span>
                    </button>
                </div>
            </form>
        </x-admin.panel>
    </div>
@endsection
