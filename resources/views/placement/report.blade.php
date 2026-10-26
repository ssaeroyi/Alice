@extends('layouts.placement')

@section('js')
    @vite(['resources/js/app.js'])
@endsection

@section('content')
<div class="max-w-4xl mx-auto px-4 py-10">
    <div class="bg-white border-4 border-[#2D231E] rounded-3xl p-6 sm:p-8 shadow-[6px_6px_0px_0px_rgba(45,35,30,1)] space-y-6">

        <div>
            <span class="font-pixel text-[10px] font-bold text-[#8C7B70] uppercase tracking-wider">DIAGNOSTIC REPORT</span>
            <h1 class="font-pixel text-xl sm:text-2xl font-bold text-[#2D231E] uppercase mt-1">Placement Scoring Report</h1>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs font-bold">
            <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl p-3">
                <span class="block text-[9px] font-pixel text-[#8C7B70] uppercase">STUDENT</span>
                <span class="text-[#2D231E]">{{ $attempt->user->nickname }}</span>
            </div>
            <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl p-3">
                <span class="block text-[9px] font-pixel text-[#8C7B70] uppercase">GRADE</span>
                <span class="text-[#2D231E]">{{ $grade }}</span>
            </div>
            <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl p-3">
                <span class="block text-[9px] font-pixel text-[#8C7B70] uppercase">ATTEMPT #</span>
                <span class="text-[#2D231E]">{{ $attempt->id }}</span>
            </div>
            <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl p-3">
                <span class="block text-[9px] font-pixel text-[#8C7B70] uppercase">SET</span>
                <span class="text-[#2D231E]">{{ $attempt->set->title }} ({{ $attempt->set->slug }})</span>
            </div>
            <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl p-3">
                <span class="block text-[9px] font-pixel text-[#8C7B70] uppercase">COMPLETED</span>
                <span class="text-[#2D231E]">{{ $attempt->completed_at }}</span>
            </div>
            <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl p-3">
                <span class="block text-[9px] font-pixel text-[#8C7B70] uppercase">SCORE FROZEN</span>
                <span class="text-[#2D231E]">Yes (snapshot)</span>
            </div>
        </div>

        @foreach (['vocab' => 'Vocabulary', 'cloze' => 'Cloze', 'comprehension' => 'Comprehension'] as $section => $label)
            <div>
                <span class="font-pixel text-[10px] font-bold text-[#8C7B70] uppercase tracking-wider">{{ $label }}</span>
                <table class="mt-2 w-full text-xs font-semibold border-2 border-[#2D231E] rounded-xl overflow-hidden">
                    <thead>
                        <tr class="bg-[#FAF6EE] text-left text-[10px] font-pixel uppercase text-[#8C7B70]">
                            <th class="px-3 py-2">#</th>
                            <th class="px-3 py-2">Question</th>
                            <th class="px-3 py-2">Your answer</th>
                            <th class="px-3 py-2">Correct answer</th>
                            <th class="px-3 py-2">Result</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($review[$section] as $i => $item)
                            <tr class="border-t border-[#E6DCCF]">
                                <td class="px-3 py-2">{{ $i + 1 }}</td>
                                <td class="px-3 py-2">{{ $item['question'] }}</td>
                                <td class="px-3 py-2">{{ $item['answer'] ?? 'not answered' }}</td>
                                <td class="px-3 py-2">{{ $item['expected'] }}</td>
                                <td class="px-3 py-2 {{ $item['correct'] ? 'text-emerald-700' : 'text-rose-700' }}">{{ $item['correct'] ? 'CORRECT' : 'WRONG' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="mt-1.5 text-xs font-bold text-[#2D231E]">{{ $result[$section]['correct'] }} / {{ $result[$section]['total'] }} correct</p>
            </div>
        @endforeach

        <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-4 space-y-2 text-xs font-bold">
            <p class="text-[#2D231E]">
                Overall: <span class="text-amber-600">{{ $result['correct'] }} / {{ $result['total'] }} ({{ $result['percentage'] }}%)</span>
            </p>
            <p class="text-[#2D231E]">
                Threshold mapping:
                <span class="text-amber-600">
                    @php
                        $band = $result['level']->value;
                        $threshold = match ($band) {
                            'independent' => 'at or above '.config('placement.independent_threshold').'%',
                            'instructional' => 'at or above '.config('placement.instructional_threshold').'%',
                            'frustration' => 'at or above '.config('placement.frustration_threshold').'%',
                            default => 'below '.config('placement.frustration_threshold').'%',
                        };
                    @endphp
                    {{ $result['percentage'] }}% is {{ $threshold }} &rarr; {{ ucfirst($band) }}
                </span>
            </p>
            <p class="text-[#2D231E]">
                Diagnosed reading level: Grade {{ $grade }} - {{ $offset }} ({{ $band }} offset) &rarr;
                <span class="text-amber-600">Grade {{ $attempt->user->reading_level }}</span>
                @if (($grade - $offset) < (int) config('placement.reading_level_floor'))
                    <span class="text-[#8C7B70]">(floor raised to Grade {{ config('placement.reading_level_floor') }})</span>
                @endif
            </p>
            <p class="text-[#8C7B70]">
                The library serves grades {{ config('placement.reading_level_floor') }}-10, so the number reported above is the served grade — the shelf the student starts on. A diagnosed level below the grade-{{ config('placement.reading_level_floor') }} floor is expected for younger or struggling readers and reads <em>at their diagnosed level as the label of record</em>; the served shelf never goes below {{ config('placement.reading_level_floor') }} by design, not by deficit.
            </p>
            <p class="text-[#8C7B70]">
                This score is a snapshot frozen when the attempt was completed; later changes to saved answers cannot alter it.
            </p>
        </div>

        <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-4 space-y-3 text-xs font-bold">
            <span class="block font-pixel text-[10px] font-bold text-[#8C7B70] uppercase tracking-wider">PASSAGE READABILITY</span>
            <p class="text-[#8C7B70] text-[11px] font-semibold">
                Standard formulas (Flesch Reading Ease and Flesch-Kincaid Grade Level) computed on the passages this student read.
            </p>
            <table class="w-full text-xs font-semibold border-2 border-[#2D231E] rounded-xl overflow-hidden">
                <thead>
                    <tr class="bg-white text-left text-[10px] font-pixel uppercase text-[#8C7B70]">
                        <th class="px-3 py-2">Passage</th>
                        <th class="px-3 py-2">Flesch Reading Ease</th>
                        <th class="px-3 py-2">Flesch-Kincaid Grade</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-t border-[#E6DCCF]">
                        <td class="px-3 py-2">Cloze</td>
                        <td class="px-3 py-2">{{ $readability['cloze']['flesch'] }} / 100</td>
                        <td class="px-3 py-2">Grade {{ $readability['cloze']['grade'] }}</td>
                    </tr>
                    <tr class="border-t border-[#E6DCCF]">
                        <td class="px-3 py-2">Comprehension</td>
                        <td class="px-3 py-2">{{ $readability['comprehension']['flesch'] }} / 100</td>
                        <td class="px-3 py-2">Grade {{ $readability['comprehension']['grade'] }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-4 space-y-2 text-xs font-bold">
            <span class="block font-pixel text-[10px] font-bold text-[#8C7B70] uppercase tracking-wider">METHODOLOGY</span>
            <table class="w-full text-xs font-semibold border-2 border-[#2D231E] rounded-xl overflow-hidden">
                <thead>
                    <tr class="bg-white text-left text-[10px] font-pixel uppercase text-[#8C7B70]">
                        <th class="px-3 py-2">Band</th>
                        <th class="px-3 py-2">Accuracy</th>
                        <th class="px-3 py-2">Grade offset</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-t border-[#E6DCCF]">
                        <td class="px-3 py-2">Independent</td>
                        <td class="px-3 py-2">at or above {{ config('placement.independent_threshold') }}%</td>
                        <td class="px-3 py-2">0</td>
                    </tr>
                    <tr class="border-t border-[#E6DCCF]">
                        <td class="px-3 py-2">Instructional</td>
                        <td class="px-3 py-2">at or above {{ config('placement.instructional_threshold') }}%</td>
                        <td class="px-3 py-2">1</td>
                    </tr>
                    <tr class="border-t border-[#E6DCCF]">
                        <td class="px-3 py-2">Frustration</td>
                        <td class="px-3 py-2">at or above {{ config('placement.frustration_threshold') }}%</td>
                        <td class="px-3 py-2">2</td>
                    </tr>
                    <tr class="border-t border-[#E6DCCF]">
                        <td class="px-3 py-2">Critical</td>
                        <td class="px-3 py-2">below {{ config('placement.frustration_threshold') }}%</td>
                        <td class="px-3 py-2">3</td>
                    </tr>
                </tbody>
            </table>
            <p class="text-[#2D231E]">
                Bands follow the informal reading inventory (IRI) tradition (Betts, 1946), recalibrated 2026-08-26 for a short mixed-format check: Independent means a student reads comfortably alone, Instructional means reading with light support, Frustration means more practice is needed, Critical sits below that. This implementation measures comprehension accuracy only (25 items: 10 vocab, 10 cloze, 5 comprehension); forgiving bands keep normal test variance and single mistakes from demoting a reader.
            </p>
            <p class="text-[#2D231E]">
                The diagnosed reading level is the student's self-reported grade minus the band offset; the served shelf is that value floored at Grade {{ config('placement.reading_level_floor') }} (see footnote above). It is a transparent first-pass heuristic, not a normed scale; refinement toward adaptive nudging is planned.
            </p>
        </div>

        <a href="{{ route('hub') }}"
           class="inline-block min-h-[44px] font-pixel text-xs sm:text-sm px-6 py-3.5 rounded-2xl bg-amber-400 border-2 border-[#2D231E] text-[#2D231E] font-bold shadow-[4px_4px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all cursor-pointer uppercase">
            BACK TO HUB
        </a>

    </div>
</div>
@endsection