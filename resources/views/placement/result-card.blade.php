{{--
    Placement result card — shared by the placement result page (step-5-result)
    and the admin dev-tools placement preview. Variables are inherited from the
    including view; the dev tool passes the same shapes. Pass $previewMode=true
    to hide the personalized "start here" strip and the Enter-Hub CTA (nothing
    on a preview should navigate a real student flow).
--}}
<!-- Completion Badge -->
<div class="inline-flex items-center gap-2 font-pixel text-[10px] sm:text-xs bg-emerald-400 border-2 border-[#2D231E] text-[#2D231E] px-4 py-1.5 rounded-full font-bold shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] uppercase tracking-wider">
    <span>CHECK COMPLETE</span>
</div>

<!-- Conversational Dialogue Box with ALICE Radial Shadow -->
<div class="flex items-center gap-3 sm:gap-4 text-left">

    <!-- ALICE Mascot with Radial Shadow -->
    <div class="shrink-0 flex items-center justify-center relative">
        <!-- Dark Soft Radial Glow -->
        <div class="absolute w-12 h-12 sm:w-20 sm:h-20 rounded-full bg-[#2D231E]/20 blur-md"></div>

        <!-- Character Sprite -->
        <img
            src="{{ asset('image/Alice/Light mode/alice-lt-talking.png') }}"
            width="1280" height="1280"
            alt="ALICE"
            class="relative z-10 w-14 h-14 sm:w-24 sm:h-24 object-contain scale-110 drop-shadow-[3px_3px_0px_rgba(45,35,30,1)]"
        >
    </div>

    <!-- Hovering Speech Bubble (dialogue stays to Alice's right at all sizes) -->
    <div class="relative flex-1 w-full min-w-0 bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-4 sm:p-5 shadow-[3px_3px_0px_0px_rgba(45,35,30,1)]">

        <!-- Left Speech Tail (Always points left to ALICE) -->
        <div class="absolute -left-3 top-1/2 -translate-y-1/2 w-5 h-5 bg-[#FAF6EE] border-l-2 border-b-2 border-[#2D231E] rotate-45"></div>

        <!-- Speech Content -->
        <p class="text-xs sm:text-sm font-bold text-[#2D231E] leading-relaxed text-left">
            You're all set! Here's your starting shelf. {{ $motivation }}
        </p>
    </div>

</div>

<!-- Starting Shelf Hero Box -->
<div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-6 space-y-3 shadow-[4px_4px_0px_0px_rgba(45,35,30,1)]">
    <span class="font-pixel text-[10px] font-bold text-[#8C7B70] uppercase tracking-wider">YOUR STARTING SHELF</span>
    <div class="font-pixel text-2xl sm:text-3xl font-bold text-amber-600 uppercase">
        Grade {{ $serveGrade }}
    </div>
    <p class="text-xs font-bold text-[#2D231E]">
        You'll start on the Grade {{ $serveGrade }} shelf in the {{ $bandLabel }} band. Short stories there are just right for building your streak.
    </p>

    @if ($diagnosedBelowFloor)
        <p class="text-[11px] font-bold text-[#8C7B70]">
            Your answers suggest you're reading a little below your grade, so you'll begin on the Grade {{ \App\Support\AliceLibrary::bandFloor($serveGrade) }} shelf with easier stories. One page at a time, and every story here counts.
        </p>
    @endif

    <!-- Accuracy Bar -->
    <div class="pt-1">
        <div class="flex items-center gap-3">
            <span class="shrink-0 font-pixel text-2xl font-bold text-amber-600 tabular-nums">{{ $result['percentage'] }}%</span>
            <div class="flex-1 bg-white border-2 border-[#2D231E] rounded-full h-5 overflow-hidden">
                <div class="h-full bg-amber-400" style="width: {{ $result['percentage'] }}%"></div>
            </div>
        </div>
        <p class="text-[10px] font-bold text-[#8C7B70] mt-1 uppercase tracking-wide">
            overall accuracy
        </p>
    </div>
</div>

<!-- Score Summary Breakdown Grid -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-center w-full">
    <div class="min-h-[64px] flex flex-col min-w-0 items-center justify-center gap-1 bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl px-2 py-3">
        <span class="font-pixel text-[8px] font-bold text-[#8C7B70] uppercase leading-tight text-center break-words" title="Word meaning questions">Vocabulary</span>
        <span class="text-sm font-extrabold text-[#2D231E] tabular-nums">{{ $result['vocab']['correct'] }}/{{ $result['vocab']['total'] }}</span>
    </div>
    <div class="min-h-[64px] flex flex-col min-w-0 items-center justify-center gap-1 bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl px-2 py-3">
        <span class="font-pixel text-[8px] font-bold text-[#8C7B70] uppercase leading-tight text-center break-words" title="Fill in the blank">Cloze</span>
        <span class="text-sm font-extrabold text-[#2D231E] tabular-nums">{{ $result['cloze']['correct'] }}/{{ $result['cloze']['total'] }}</span>
    </div>
    <div class="min-h-[64px] flex flex-col min-w-0 items-center justify-center gap-1 bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl px-2 py-3">
        <span class="font-pixel text-[8px] font-bold text-[#8C7B70] uppercase leading-tight text-center break-words" title="Story questions">Comprehension</span>
        <span class="text-sm font-extrabold text-[#2D231E] tabular-nums">{{ $result['comprehension']['correct'] }}/{{ $result['comprehension']['total'] }}</span>
    </div>
</div>

<!-- Answer Review Pane -->
<details class="group text-left bg-white border-2 border-[#2D231E] rounded-2xl overflow-hidden">
    <summary class="flex items-center justify-between gap-2 min-h-[44px] px-4 py-3 cursor-pointer bg-[#FAF6EE] hover:bg-[#F3ECE0] transition-colors select-none">
        <span class="font-pixel text-[10px] font-bold uppercase tracking-wider">SEE MY ANSWERS</span>
        <span class="text-xs font-bold text-[#8C7B70] group-open:hidden">tap to open</span>
        <span class="text-xs font-bold text-[#8C7B70] hidden group-open:inline">tap to close</span>
    </summary>

    <div class="px-4 py-4 space-y-5">
        @foreach (['vocab' => 'Word Meanings', 'cloze' => 'Missing Words', 'comprehension' => 'Story Questions'] as $section => $label)
            <div>
                <span class="font-pixel text-[9px] font-bold text-[#8C7B70] uppercase tracking-wider">{{ $label }}</span>
                <ul class="mt-2 space-y-2">
                    @foreach ($review[$section] as $item)
                        <li class="border-2 border-[#E6DCCF] rounded-xl px-3 py-2">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-xs font-bold text-[#2D231E]">{{ $item['question'] }}</p>
                                <span class="shrink-0 font-pixel text-xs {{ $item['correct'] ? 'text-emerald-600' : 'text-rose-600' }}">{{ $item['correct'] ? 'CORRECT' : 'WRONG' }}</span>
                            </div>
                            <div class="mt-1.5 text-xs font-semibold text-[#2D231E]">
                                Your answer: {{ $item['answer'] ?? 'not answered' }}
                            </div>
                            @if (! $item['correct'])
                                <div class="text-xs font-semibold text-emerald-700">
                                    Correct answer: {{ $item['expected'] }}
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
</details>

<!-- Action Button -->
@if (! ($previewMode ?? false))
    <div class="pt-2 border-t-2 border-dashed border-[#E6DCCF]">
        <a
            href="{{ route('hub') }}"
            class="w-full min-h-[44px] font-pixel text-xs sm:text-sm px-6 py-3.5 rounded-2xl bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] font-bold flex items-center justify-center shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all cursor-pointer uppercase"
        >
            <span>ENTER ALICE HUB</span>
        </a>
    </div>
@endif
