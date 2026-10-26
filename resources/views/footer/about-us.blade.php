@extends(auth()->check() && auth()->user()->role === \App\Enums\Role::Student ? 'layouts.authenticated' : 'layouts.guest')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-8 sm:py-12">

    <!-- Main Panel -->
    <div class="bg-white border-4 border-[#2D231E] rounded-3xl p-6 sm:p-8 shadow-[6px_6px_0px_0px_rgba(45,35,30,1)] space-y-6">

        <!-- Header -->
        <div class="flex items-center justify-between border-b-2 border-dashed border-[#E6DCCF] pb-3">
            <span class="font-pixel text-xs sm:text-sm font-bold text-[#2D231E] uppercase tracking-wider">
                ABOUT ALICE
            </span>
        </div>

        <!-- Mascot + speech box (stays beside the sprite on mobile too) -->
        <div class="flex flex-row items-center gap-3 sm:gap-4">
            <div class="shrink-0 flex items-center justify-center relative">
                <div class="absolute w-16 h-16 sm:w-20 sm:h-20 rounded-full bg-[#2D231E]/20 blur-md"></div>
                <img
                    src="{{ asset('image/Alice/Light mode/alice-lt-talking.png') }}"
                    width="1280" height="1280"
                    alt="ALICE"
                    class="relative z-10 w-20 h-20 sm:w-24 sm:h-24 object-contain scale-110 drop-shadow-[3px_3px_0px_rgba(45,35,30,1)]"
                >
            </div>
            <div class="relative flex-1 min-w-0 bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-3 sm:p-5 shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]">
                <div class="absolute -left-2.5 sm:-left-3 top-1/2 -translate-y-1/2 w-4 h-4 sm:w-5 sm:h-5 bg-[#FAF6EE] border-l-2 border-b-2 border-[#2D231E] rotate-45"></div>

                <p class="text-xs sm:text-sm font-bold text-[#2D231E] leading-relaxed text-left">
                    ALICE is a helper tool for reading and understanding. It is made for students of Dasmariñas East Integrated High School, and it uses light game features so practice feels more fun.
                </p>
            </div>
        </div>

        <!-- What you can do -->
        <div class="space-y-3">
            <p class="font-pixel text-[10px] font-bold text-[#8C7B70] uppercase tracking-wider">WHAT YOU CAN DO</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs sm:text-sm font-medium text-[#2D231E] leading-relaxed">
                <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-3 flex items-start gap-3">
                    <span class="w-8 h-8 shrink-0 flex items-center justify-center rounded-xl bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] [&>svg]:w-5 [&>svg]:h-5">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'open-book') !!}</span>
                    <div class="space-y-0.5">
                        <p class="font-pixel text-[9px] font-bold text-amber-600 uppercase">READ STORIES</p>
                        <p>Pick stories that fit your reading level.</p>
                    </div>
                </div>

                <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-3 flex items-start gap-3">
                    <span class="w-8 h-8 shrink-0 flex items-center justify-center rounded-xl bg-sky-300 border-2 border-[#2D231E] text-[#2D231E] [&>svg]:w-5 [&>svg]:h-5">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'magnifying-glass') !!}</span>
                    <div class="space-y-0.5">
                        <p class="font-pixel text-[9px] font-bold text-sky-700 uppercase">LEARN WORDS</p>
                        <p>Tap a word you do not know and study it later.</p>
                    </div>
                </div>

                <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-3 flex items-start gap-3">
                    <span class="w-8 h-8 shrink-0 flex items-center justify-center rounded-xl bg-violet-400 border-2 border-[#2D231E] text-[#2D231E] [&>svg]:w-5 [&>svg]:h-5">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'scroll-quill') !!}</span>
                    <div class="space-y-0.5">
                        <p class="font-pixel text-[9px] font-bold text-violet-700 uppercase">ANSWER QUIZZES</p>
                        <p>Check what you understand at the end of a story.</p>
                    </div>
                </div>

                <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-3 flex items-start gap-3">
                    <span class="w-8 h-8 shrink-0 flex items-center justify-center rounded-xl bg-emerald-400 border-2 border-[#2D231E] text-[#2D231E] [&>svg]:w-5 [&>svg]:h-5">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'cards') !!}</span>
                    <div class="space-y-0.5">
                        <p class="font-pixel text-[9px] font-bold text-emerald-700 uppercase">PLAY WORD GAMES</p>
                        <p>Try word search, matching, and sentence builder.</p>
                    </div>
                </div>

                <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-3 flex items-start gap-3">
                    <span class="w-8 h-8 shrink-0 flex items-center justify-center rounded-xl bg-rose-400 border-2 border-[#2D231E] text-[#2D231E] [&>svg]:w-5 [&>svg]:h-5">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'key') !!}</span>
                    <div class="space-y-0.5">
                        <p class="font-pixel text-[9px] font-bold text-rose-700 uppercase">EARN POINTS</p>
                        <p>Gain points, keys, streaks, and badges as you learn.</p>
                    </div>
                </div>

                <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-3 flex items-start gap-3">
                    <span class="w-8 h-8 shrink-0 flex items-center justify-center rounded-xl bg-cyan-300 border-2 border-[#2D231E] text-[#2D231E] [&>svg]:w-5 [&>svg]:h-5">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'sound-on') !!}</span>
                    <div class="space-y-0.5">
                        <p class="font-pixel text-[9px] font-bold text-cyan-700 uppercase">HEAR AND SPEAK</p>
                        <p>Listen to words and practice saying them out loud.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Built with -->
        <div class="space-y-3">
            <div class="space-y-2">
                <p class="font-pixel text-[10px] font-bold text-[#8C7B70] uppercase tracking-wider">FRAMEWORK &amp; UI</p>
                <div class="flex flex-wrap gap-2">
                    <span class="font-pixel text-[9px] sm:text-[10px] bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] px-3 py-1.5 rounded-full font-bold shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] uppercase">LARAVEL</span>
                    <span class="font-pixel text-[9px] sm:text-[10px] bg-emerald-400 border-2 border-[#2D231E] text-[#2D231E] px-3 py-1.5 rounded-full font-bold shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] uppercase">TAILWIND CSS</span>
                    <span class="font-pixel text-[9px] sm:text-[10px] bg-sky-300 border-2 border-[#2D231E] text-[#2D231E] px-3 py-1.5 rounded-full font-bold shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] uppercase">JAVASCRIPT</span>
                    <span class="font-pixel text-[9px] sm:text-[10px] bg-violet-400 border-2 border-[#2D231E] text-[#2D231E] px-3 py-1.5 rounded-full font-bold shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] uppercase">CHART.JS</span>
                    <span class="font-pixel text-[9px] sm:text-[10px] bg-rose-400 border-2 border-[#2D231E] text-[#2D231E] px-3 py-1.5 rounded-full font-bold shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] uppercase">ANIME.JS</span>
                </div>
            </div>

            <div class="space-y-2">
                <p class="font-pixel text-[10px] font-bold text-[#8C7B70] uppercase tracking-wider">LANGUAGE TOOLS</p>
                <div class="flex flex-wrap gap-2">
                    <span class="font-pixel text-[9px] sm:text-[10px] bg-cyan-300 border-2 border-[#2D231E] text-[#2D231E] px-3 py-1.5 rounded-full font-bold shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] uppercase">VOSK SPEECH</span>
                    <span class="font-pixel text-[9px] sm:text-[10px] bg-rose-300 border-2 border-[#2D231E] text-[#2D231E] px-3 py-1.5 rounded-full font-bold shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] uppercase">KOKORO TTS</span>
                    <span class="font-pixel text-[9px] sm:text-[10px] bg-amber-300 border-2 border-[#2D231E] text-[#2D231E] px-3 py-1.5 rounded-full font-bold shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] uppercase">LANGUAGETOOL</span>
                    <span class="font-pixel text-[9px] sm:text-[10px] bg-emerald-300 border-2 border-[#2D231E] text-[#2D231E] px-3 py-1.5 rounded-full font-bold shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] uppercase">WORDNET</span>
                    <span class="font-pixel text-[9px] sm:text-[10px] bg-sky-200 border-2 border-[#2D231E] text-[#2D231E] px-3 py-1.5 rounded-full font-bold shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] uppercase">FLESCH-KINCAID</span>
                </div>
            </div>
        </div>

        <!-- Media & assets -->
        <div class="space-y-2">
            <p class="font-pixel text-[10px] font-bold text-[#8C7B70] uppercase tracking-wider">MEDIA &amp; ASSETS</p>
            <div class="flex flex-wrap gap-x-4 gap-y-1 text-[10px] sm:text-xs font-medium text-[#8C7B70]">
                <span>game-icons.net <span class="text-[#bbb]">(CC BY 3.0)</span></span>
                <span>Kenney <span class="text-[#bbb]">(CC0)</span></span>
                <span>Nieobie <span class="text-[#bbb]">(CC0)</span></span>
                <span>Google Fonts <span class="text-[#bbb]">(OFL)</span></span>
            </div>
            <p class="text-[10px] sm:text-xs text-[#8C7B70] leading-relaxed">Reading materials adapted from public-domain classics.</p>
        </div>

        <!-- Character credit -->
        <div class="flex items-center gap-2 bg-[#FAF6EE] border-2 border-[#E6DCCF] rounded-2xl px-4 py-3">
            <x-icons.sparkle class="w-4 h-4 text-amber-500 shrink-0" />
            <p class="text-xs font-semibold text-[#8C7B70]">ALICE character art made by <span class="font-bold text-[#2D231E]">Arañez John Ray</span></p>
        </div>

        <!-- CTA -->
        <div class="pt-2">
            <a
                href="/"
                class="w-full min-h-[44px] font-pixel text-xs px-6 py-3 rounded-2xl bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] font-bold flex items-center justify-center shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] focus:ring-2 focus:ring-amber-400 transition-all uppercase"
            >
                <span>CLOSE</span>
            </a>
        </div>

    </div>

</div>
@endsection
