@props(['message' => '', 'type' => 'success'])

@php
    $typeClasses = match ($type) {
        default => 'border-emerald-500 bg-emerald-50',
    };
@endphp

<div
    data-toast
    role="status"
    aria-live="polite"
    class="fixed inset-x-0 bottom-20 sm:inset-x-auto sm:bottom-6 sm:right-6 z-[100] px-4 sm:px-0 pointer-events-none"
>
    <div class="mx-auto sm:mx-0 sm:max-w-sm pointer-events-auto {{ $typeClasses }} border-2 rounded-2xl px-4 py-3 shadow-[4px_4px_0px_0px_rgba(16,185,129,0.35)] flex items-center gap-3">
        <span class="shrink-0 w-6 h-6 rounded-full bg-emerald-500 text-white flex items-center justify-center" aria-hidden="true">
            <svg class="w-4 h-4 stroke-current" fill="none" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
            </svg>
        </span>
        <p class="flex-1 text-xs font-bold text-[#2D231E] leading-relaxed">{{ $message }}</p>
        <button
            type="button"
            data-toast-close
            aria-label="Dismiss notification"
            class="font-semibold shrink-0 min-h-[44px] min-w-[44px] -my-1 -mr-2 flex items-center justify-center text-[#2D231E] hover:text-[#2D231E] transition-colors"
        >
            <svg class="w-4 h-4 stroke-current" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 6l12 12M18 6L6 18"/>
            </svg>
        </button>
    </div>
</div>