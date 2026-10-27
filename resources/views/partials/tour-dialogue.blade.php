{{-- ALICE TOUR: RPG-style dialogue shell + spotlight shield (resources/js/tour/tour.js) --}}
{{-- Hidden by default; the driver reveals it only for an active session. --}}
<div id="alice-tour" class="hidden" data-tour-root aria-hidden="true">

    {{-- Semi-blocking dim + pulsing ring + stray-tap hint --}}
    <div class="fixed inset-0 z-[60]" data-tour="shield">
        <div class="absolute inset-0 bg-[#2D231E]/55 transition-opacity duration-300" data-tour="dim"></div>
        <div class="absolute top-0 left-0 pointer-events-none rounded-2xl border-4 border-amber-400 shadow-[0_0_0_4px_rgba(251,191,36,0.35)] alice-tour-ring" data-tour="ring" hidden></div>
        <p
            class="pointer-events-none absolute left-1/2 -translate-x-1/2 top-6 max-w-[240px] opacity-0 transition-opacity duration-200 text-center text-[11px] font-semibold text-[#FAF6EE] bg-[#2D231E]/85 border-2 border-[#2D231E] rounded-xl px-3 py-1.5"
            data-tour="hint"
        >Tap the glowing thing</p>
    </div>

    {{-- RPG-style dialogue box --}}
    <div class="fixed bottom-0 inset-x-0 z-[80] p-3 sm:p-4" data-tour="box">
        <div class="relative mx-auto max-w-xl bg-[#FFF8E7] border-2 border-[#2D231E] rounded-lg px-2.5 pt-2 pb-2.5 sm:px-4 sm:pt-3 sm:pb-3 shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] select-none">

            {{-- Name tag badge: straddles the top-left outer border --}}
            <div class="absolute -top-[11px] left-2.5 sm:left-4 inline-flex items-center bg-[#FFF8E7] border-2 border-[#2D231E] rounded-md px-2 py-0.5 shadow-[2px_2px_0px_0px_rgba(45,35,30,1)]">
                <span class="font-pixel text-[9px] font-bold text-[#2D231E]">ALICE</span>
            </div>

            {{-- Top-right controls: counter, tour voice mute, pause/play, QUIT --}}
            <div class="flex items-center justify-end gap-1.5 pb-1 pt-0.5">
                <span class="font-pixel text-[8px] text-[#2D231E]" data-tour="progress"></span>

                {{-- Tour-only voice mute: this is NOT the app-wide sound switch.
                     It mutes only Alice's tour narration (private flag owned by
                     tour.js); game and system sounds stay untouched. --}}
                <button
                    type="button"
                    hidden
                    data-tour="mute"
                    class="w-[30px] h-[30px] bg-white border-2 border-[#2D231E] text-[#2D231E] rounded-md flex items-center justify-center cursor-pointer shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all"
                    title="Alice's voice: on. Tap to mute"
                >
                    <span class="w-4 h-4 [&>svg]:h-full [&>svg]:w-full" data-tour-voice-on>{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'sound-on') !!}</span>
                    <span class="w-4 h-4 [&>svg]:h-full [&>svg]:w-full hidden" data-tour-voice-off>{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'sound-off') !!}</span>
                </button>

                {{-- Pause / play: any interruption lands here; play continues --}}
                <button
                    type="button"
                    data-tour="pause"
                    aria-pressed="false"
                    class="w-[30px] h-[30px] bg-white border-2 border-[#2D231E] text-[#2D231E] rounded-md flex items-center justify-center cursor-pointer shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all"
                    title="Pause the tour"
                >
                    <span class="w-3.5 h-3.5" data-tour-pause-icon><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 5h4v14H6zM14 5h4v14h-4z"/></svg></span>
                    <span class="w-3.5 h-3.5 hidden" data-tour-play-icon><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg></span>
                </button>

                <button
                    type="button"
                    data-tour="quit"
                    aria-label="End the tour"
                    class="h-[30px] font-pixel text-[8px] font-bold text-[#2D231E] bg-white border-2 border-[#2D231E] rounded-md flex items-center justify-center px-2.5 shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all cursor-pointer"
                >QUIT</button>
            </div>

            {{-- Portrait LEFT + caption right: classic RPG dialogue --}}
            <div class="flex items-center gap-3">
                <div class="w-16 h-16 shrink-0 bg-amber-400 border-2 border-[#2D231E] rounded-lg overflow-hidden flex items-center justify-center">
                    <img
                        class="w-full h-full object-cover"
                        src="{{ asset('image/Alice/Light mode/alice-lt-casual.png') }}"
                        alt="Alice"
                    >
                </div>

                <div class="min-w-0 flex-1">
                    <p
                        class="text-xs sm:text-sm font-semibold text-[#2D231E] leading-relaxed min-h-[2.75rem] sm:min-h-[3.25rem]"
                        data-tour="text"
                    ></p>

                    {{-- Pause note + end, shown while the tour is paused. --}}
                    <div class="hidden flex items-center gap-2 mt-1" data-tour="paused">
                        <span class="font-pixel text-[8px] font-bold text-[#8C7B70] uppercase">Paused</span>
                        <button
                            type="button"
                            data-tour="end"
                            class="min-h-[30px] font-pixel text-[8px] font-bold text-[#2D231E] bg-white border-2 border-[#2D231E] rounded-lg px-2.5 shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all cursor-pointer"
                        >END TOUR</button>
                    </div>
                </div>
            </div>

            {{-- Pixel "tap to continue" indicator, anchored bottom-right --}}
            <svg
                data-tour="arrow"
                aria-hidden="true"
                viewBox="0 0 12 12"
                class="alice-tour-arrow absolute bottom-2 right-2.5 sm:right-4 h-4 w-4 text-[#2D231E] pointer-events-none"
            >
                <path fill="currentColor" shape-rendering="crispEdges" d="M5 1h2v1h-2zM4 2h4v1h-4zM3 3h6v1h-6zM2 4h8v2h-8zM3 6h6v1h-6zM4 7h4v1h-4zM5 8h2v1h-2z"/>
            </svg>
        </div>
    </div>
</div>