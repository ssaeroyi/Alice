{{-- Streak Restore prompt: shown once per broken streak from the shared
     student layout. Grace window (1 missed day) offers Yes/No; once the
     window is gone, a single OK notice fires (marked seen at render). --}}
@php
    $restoreState = auth()->check() ? \App\Support\StreakRestore::state(auth()->user()) : null;

    if ($restoreState !== null && $restoreState['mode'] === 'missed') {
        \App\Support\StreakRestore::markMissedSeen(auth()->user());
    }
@endphp

@if ($restoreState !== null)
    <x-modal
        id="streak-restore-modal"
        :title="$restoreState['mode'] === 'missed' ? 'STREAK RESTORE' : 'STREAK AT RISK'"
        :open="true"
        :closeable="false"
        pixel
    >
        <div class="space-y-4 text-center">
            @if ($restoreState['mode'] === 'missed')
                <span class="w-14 h-14 mx-auto rounded-2xl border-2 border-[#2D231E] bg-rose-100 text-rose-600 flex items-center justify-center">
                    <x-icons.face-sad class="w-8 h-8" />
                </span>
            @else
                <span class="w-14 h-14 mx-auto rounded-2xl border-2 border-[#2D231E] bg-sky-100 text-sky-600 flex items-center justify-center [&>svg]:h-8 [&>svg]:w-8">
                    {!! \App\Support\LibraryPresentation::iconGlyph('ui', 'snowflake') !!}
                </span>
            @endif

            @if ($restoreState['mode'] === 'missed')
                <p class="text-xs font-semibold text-[#2D231E] leading-relaxed">
                    You broke your streak and missed the 1 day grace period to restore it.
                </p>
            @else
                <p class="text-xs font-semibold text-[#2D231E] leading-relaxed">
                    Hey, you were here the other day and your streak broke. Would you like to restore it?
                </p>
                <p class="font-pixel text-[11px] text-[#2D231E] uppercase">
                    @if ($restoreState['mode'] === 'restore')
                        You have {{ $restoreState['holds'] }} Streak Restore{{ $restoreState['holds'] === 1 ? '' : 's' }}.
                    @else
                        You have 0 Streak Restores.
                    @endif
                </p>
            @endif
        </div>

        @if ($restoreState['mode'] === 'missed')
            <button
                type="button"
                data-modal-close
                class="w-full min-h-[46px] font-pixel text-xs px-6 py-3 rounded-2xl bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] font-bold flex items-center justify-center shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all cursor-pointer uppercase"
            >
                OK
            </button>
        @elseif ($restoreState['mode'] === 'restore')
            <form method="POST" action="{{ route('rewards.streak.restore') }}" class="space-y-3">
                @csrf
                <button
                    type="submit"
                    name="answer"
                    value="accept"
                    class="w-full min-h-[46px] font-pixel text-xs px-6 py-3 rounded-2xl bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] font-bold flex items-center justify-center shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all cursor-pointer uppercase"
                >
                    YES, RESTORE
                </button>
                <button
                    type="submit"
                    name="answer"
                    value="decline"
                    class="w-full min-h-[44px] font-pixel text-xs px-6 py-3 rounded-2xl bg-white border-2 border-[#2D231E] text-[#2D231E] font-bold uppercase shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all cursor-pointer"
                >
                    NO
                </button>
            </form>
        @else
            <form method="POST" action="{{ route('rewards.streak.restore') }}">
                @csrf
                <button
                    type="submit"
                    name="answer"
                    value="unavailable"
                    class="w-full min-h-[46px] font-pixel text-xs px-6 py-3 rounded-2xl bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] font-bold flex items-center justify-center shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all cursor-pointer uppercase"
                >
                    OK
                </button>
            </form>
        @endif
    </x-modal>
@endif
