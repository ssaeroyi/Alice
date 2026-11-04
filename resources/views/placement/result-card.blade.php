{{--
    Placement result card — rendered by the placement result page
    (step-5-result). Pass $previewMode=true to hide the personalized
    "start here" strip and the Enter-Library CTA (nothing on a preview
    should navigate a real student flow).
--}}
<!-- Completion Badge -->
<div class="inline-flex items-center gap-2 font-pixel text-[10px] sm:text-xs bg-emerald-400 border-2 border-[#2D231E] text-[#2D231E] px-4 py-1.5 rounded-full font-bold shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] uppercase tracking-wider">
    <span>CHECK COMPLETE</span>
</div>

<!-- Starting Shelf Hero Box -->
<div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-6 space-y-3 shadow-[4px_4px_0px_0px_rgba(45,35,30,1)]">
    <span class="font-pixel text-[10px] font-bold text-[#8C7B70] uppercase tracking-wider">YOUR STARTING SHELF</span>
    <div class="font-pixel text-2xl sm:text-3xl font-bold text-amber-600 uppercase">
        Grade {{ $serveGrade }}
    </div>
    <p class="text-xs font-bold text-[#2D231E]">
        You'll start on the Grade {{ $serveGrade }} shelf in the {{ $bandLabel }} band.
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
        <span class="font-pixel text-[8px] font-bold text-[#8C7B70] uppercase leading-tight text-center break-words" title="Vocabulary">Vocabulary</span>
        <span class="text-sm font-extrabold text-[#2D231E] tabular-nums">{{ $result['vocab']['correct'] }}/{{ $result['vocab']['total'] }}</span>
    </div>
    <div class="min-h-[64px] flex flex-col min-w-0 items-center justify-center gap-1 bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl px-2 py-3">
        <span class="font-pixel text-[8px] font-bold text-[#8C7B70] uppercase leading-tight text-center break-words" title="Cloze">Cloze</span>
        <span class="text-sm font-extrabold text-[#2D231E] tabular-nums">{{ $result['cloze']['correct'] }}/{{ $result['cloze']['total'] }}</span>
    </div>
    <div class="min-h-[64px] flex flex-col min-w-0 items-center justify-center gap-1 bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl px-2 py-3">
        <span class="font-pixel text-[8px] font-bold text-[#8C7B70] uppercase leading-tight text-center break-words" title="Comprehension">Comprehension</span>
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
        @foreach (['vocab' => 'Vocabulary', 'cloze' => 'Cloze', 'comprehension' => 'Comprehension'] as $section => $label)
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
            href="{{ route('library') }}"
            class="w-full min-h-[44px] font-pixel text-xs sm:text-sm px-6 py-3.5 rounded-2xl bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] font-bold flex items-center justify-center shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all cursor-pointer uppercase"
        >
            <span>ENTER THE LIBRARY</span>
        </a>
    </div>
@endif
