@props(['target' => '', 'oov' => false])
@php
    // The offline Vosk model is a static public asset; its presence decides
    // whether the dictation widget exists here at all. There is no cloud or
    // browser speech API - this is the only engine, and it never leaves the
    // device.
    $sttOffline = file_exists(public_path('stt/vosk-model-small-en-us-0.15.tar.gz'));
    // A proper noun / word not in the app's dictionary is out-of-vocabulary for
    // the small offline model: it can never be verified at any confidence, so
    // the widget grades it gently ("Hear it" + encouragement) instead of making
    // the student retry a word that can never pass.
    $isOov = (bool) $oov;
@endphp

<div
    data-pronounce-check="{{ $target }}"
    data-stt-offline="{{ $sttOffline ? '1' : '0' }}"
    data-oov="{{ $isOov ? '1' : '0' }}"
    class="hidden flex-col items-center gap-2"
>
    <div class="flex items-center justify-center gap-2.5">
        <button
            type="button"
            data-say-check
            aria-label="Practice saying the word"
            class="relative w-12 h-12 rounded-full bg-[#FDF6E9] border-2 border-[#E6B86A] text-amber-700 shadow-[2px_2px_0px_0px_rgba(230,184,106,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all cursor-pointer"
        >
            <span data-say-check-ping class="absolute -inset-1.5 rounded-full bg-amber-400/70 pointer-events-none"></span>
            <span class="relative flex items-center justify-center"><x-icons.microphone class="w-5 h-5" /></span>
        </button>
        <span data-say-check-label class="font-pixel text-[9px] font-bold uppercase text-[#2D231E]">{{ $isOov ? 'Give it a go' : 'Say it' }}</span>
    </div>

    @if ($isOov)
        <button
            type="button"
            data-say-listen
            class="inline-flex items-center gap-1.5 min-h-[44px] px-4 border-2 border-[#B07D24] rounded-full font-pixel text-[9px] font-bold uppercase text-[#2D231E] bg-[#FDF6E9] hover:text-[#2D231E] transition-colors cursor-pointer"
        >
            <x-icons.speaker class="w-4 h-4" /> Hear it
        </button>
    @endif

    <div data-say-check-level hidden class="say-level-wrap" aria-hidden="true">
        <span class="say-level-bar"></span>
        <span class="say-level-bar"></span>
        <span class="say-level-bar"></span>
        <span class="say-level-bar"></span>
        <span class="say-level-bar"></span>
        <span class="say-level-bar"></span>
        <span class="say-level-bar"></span>
        <span class="say-level-bar"></span>
    </div>

    <div data-say-check-boxes class="flex items-center justify-center gap-1.5 flex-wrap max-w-xs"></div>

    <span
        data-say-check-verdict
        hidden
        class="inline-flex items-center px-2.5 py-1 rounded-full font-pixel text-[8px] font-bold uppercase border-2"
        aria-live="polite"
    ></span>

    <span
        data-say-check-echo
        hidden
        class="block max-w-xs text-center text-[11px] font-semibold text-[#2D231E] leading-snug"
        aria-live="polite"
    ></span>

    <button
        type="button"
        data-say-again
        hidden
        class="inline-flex items-center gap-1.5 min-h-[44px] px-3.5 bg-white border-2 border-[#2D231E] rounded-full font-pixel text-[9px] font-bold uppercase text-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all cursor-pointer"
    >
        Say again
    </button>

    <p data-say-check-msg hidden class="text-xs font-semibold text-[#2D231E] text-center leading-relaxed"></p>
</div>