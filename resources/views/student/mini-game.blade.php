@extends('layouts.authenticated')

@section('page', 'mini-game')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8 sm:py-6 space-y-6" data-mini-game-hub>

    <!-- Header -->
    <div class="flex items-center gap-3">
        <a href="{{ route('hub') }}" aria-label="Back to hub"
           class="w-10 h-10 flex items-center justify-center bg-white border-2 border-[#2D231E] rounded-xl text-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all">
            <x-icons.arrow-left class="w-4 h-4" />
        </a>
        <h1 class="font-pixel text-base font-bold text-[#2D231E] uppercase">Mini Games</h1>
        <div class="ml-auto flex items-center gap-2">
            <button type="button" data-modal-open="leaderboard-modal" title="Global leaderboard"
                    class="w-11 h-11 flex items-center justify-center bg-white border-2 border-[#2D231E] rounded-xl text-amber-600 shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all [&>svg]:h-5 [&>svg]:w-5">
                {!! \App\Support\LibraryPresentation::iconGlyph('ui', 'trophy') !!}
            </button>
            <button type="button" id="btn-help" data-modal-open="game-help-modal"
                    class="w-11 h-11 flex items-center justify-center bg-white border-2 border-[#2D231E] rounded-xl font-pixel text-xs font-bold text-[#2D231E] shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all uppercase">
                ?
            </button>
        </div>
    </div>

    @if(session('error'))
        <div class="bg-rose-100 border-2 border-rose-400 text-rose-700 text-sm font-bold px-4 py-3 rounded-2xl text-center">
            {{ session('error') }}
        </div>
    @endif

    <!-- Game Cards -->
    <div class="space-y-3">
        <p class="font-pixel text-[11px] font-bold text-[#2D231E] uppercase tracking-wider text-center">
            Pick a game to play
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

            <!-- Word Search -->
            <a href="{{ route('game.setup', ['game' => 'wordsearch']) }}"
                class="group bg-white border-4 border-[#2D231E] rounded-2xl overflow-hidden shadow-[5px_5px_0px_0px_rgba(45,35,30,1)] hover:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] hover:translate-x-[3px] hover:translate-y-[3px] active:translate-x-[3px] active:translate-y-[3px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all block">
                <div class="bg-emerald-100 border-b-2 border-[#2D231E] px-4 py-6 flex items-center justify-center relative overflow-hidden" style="background-image: radial-gradient(rgba(45,35,30,0.08) 1px, transparent 1px); background-size: 8px 8px;">
                    <div class="w-16 h-16 rounded-2xl bg-white border-2 border-[#2D231E] flex items-center justify-center shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] group-hover:scale-105 transition-transform text-emerald-600 [&>svg]:h-8 [&>svg]:w-8">
                        {!! \App\Support\LibraryPresentation::iconGlyph('ui', 'magnifying-glass') !!}
                    </div>
                </div>
                <div class="p-4 text-center space-y-1">
                    <h3 class="font-pixel text-xs font-bold text-[#2D231E] uppercase">Word Search</h3>
                    <p class="text-[11px] text-[#2D231E] font-semibold">Find hidden words in the grid</p>
                    <div class="inline-flex items-center gap-1.5 mt-2 px-2.5 py-1 bg-amber-100 border-2 border-[#2D231E] rounded-lg text-amber-700 text-[11px] font-bold [&>svg]:h-4 [&>svg]:w-4">
                        {!! \App\Support\LibraryPresentation::iconGlyph('ui', 'trophy') !!}
                        <span>Best: {{ isset($bestScores['wordsearch']) && $bestScores['wordsearch'] ? number_format((int) $bestScores['wordsearch']) : '-' }}</span>
                    </div>
                </div>
            </a>

            <!-- Memory -->
            <a href="{{ route('game.setup', ['game' => 'match']) }}"
                class="group bg-white border-4 border-[#2D231E] rounded-2xl overflow-hidden shadow-[5px_5px_0px_0px_rgba(45,35,30,1)] hover:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] hover:translate-x-[3px] hover:translate-y-[3px] active:translate-x-[3px] active:translate-y-[3px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all block">
                <div class="bg-sky-100 border-b-2 border-[#2D231E] px-4 py-6 flex items-center justify-center relative overflow-hidden" style="background-image: radial-gradient(rgba(45,35,30,0.08) 1px, transparent 1px); background-size: 8px 8px;">
                    <div class="w-16 h-16 rounded-2xl bg-white border-2 border-[#2D231E] flex items-center justify-center shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] group-hover:scale-105 transition-transform text-sky-600 [&>svg]:h-8 [&>svg]:w-8">
                        {!! \App\Support\LibraryPresentation::iconGlyph('ui', 'cards') !!}
                    </div>
                </div>
                <div class="p-4 text-center space-y-1">
                    <h3 class="font-pixel text-xs font-bold text-[#2D231E] uppercase">Memory</h3>
                    <p class="text-[11px] text-[#2D231E] font-semibold">Memorize the pairs, then match</p>
                    <div class="inline-flex items-center gap-1.5 mt-2 px-2.5 py-1 bg-amber-100 border-2 border-[#2D231E] rounded-lg text-amber-700 text-[11px] font-bold [&>svg]:h-4 [&>svg]:w-4">
                        {!! \App\Support\LibraryPresentation::iconGlyph('ui', 'trophy') !!}
                        <span>Best: {{ isset($bestScores['match']) && $bestScores['match'] ? number_format((int) $bestScores['match']) : '-' }}</span>
                    </div>
                </div>
            </a>

            <!-- Sentence Builder -->
            <a href="{{ route('game.setup', ['game' => 'sentence']) }}"
                class="group bg-white border-4 border-[#2D231E] rounded-2xl overflow-hidden shadow-[5px_5px_0px_0px_rgba(45,35,30,1)] hover:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] hover:translate-x-[3px] hover:translate-y-[3px] active:translate-x-[3px] active:translate-y-[3px] active:shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] transition-all block">
                <div class="bg-amber-100 border-b-2 border-[#2D231E] px-4 py-6 flex items-center justify-center relative overflow-hidden" style="background-image: radial-gradient(rgba(45,35,30,0.08) 1px, transparent 1px); background-size: 8px 8px;">
                    <div class="w-16 h-16 rounded-2xl bg-white border-2 border-[#2D231E] flex items-center justify-center shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] group-hover:scale-105 transition-transform text-amber-600 [&>svg]:h-8 [&>svg]:w-8">
                        {!! \App\Support\LibraryPresentation::iconGlyph('ui', 'book-pile') !!}
                    </div>
                </div>
                <div class="p-4 text-center space-y-1">
                    <h3 class="font-pixel text-xs font-bold text-[#2D231E] uppercase">Sentence</h3>
                    <p class="text-[11px] text-[#2D231E] font-semibold">Complete the missing words</p>
                    <div class="inline-flex items-center gap-1.5 mt-2 px-2.5 py-1 bg-amber-100 border-2 border-[#2D231E] rounded-lg text-amber-700 text-[11px] font-bold [&>svg]:h-4 [&>svg]:w-4">
                        {!! \App\Support\LibraryPresentation::iconGlyph('ui', 'trophy') !!}
                        <span>Best: {{ isset($bestScores['sentence']) && $bestScores['sentence'] ? number_format((int) $bestScores['sentence']) : '-' }}</span>
                    </div>
                </div>
            </a>

        </div>
    </div>

    <!-- Leaderboard Modal -->
    <x-modal id="leaderboard-modal" title="Weekly Leaderboards" :pixel="true">
        <div data-help-pager class="space-y-4">

            <!-- Pager head: dots -->
            <div class="flex items-center gap-1.5" role="group" aria-label="Leaderboards">
                <button type="button" class="help-pager-dot is-active" data-help-dot="0" aria-label="Word Search leaderboard" aria-current="true"></button>
                <button type="button" class="help-pager-dot" data-help-dot="1" aria-label="Memory leaderboard"></button>
                <button type="button" class="help-pager-dot" data-help-dot="2" aria-label="Sentence Builder leaderboard"></button>
            </div>

            @foreach (['wordsearch' => ['Word Search', 'bg-emerald-100'], 'match' => ['Memory', 'bg-sky-100'], 'sentence' => ['Sentence Builder', 'bg-amber-100']] as $gameKey => $meta)
                @php $board = $leaderboard[$gameKey] ?? ['current' => ['entries' => [], 'selfRank' => null, 'selfBest' => null], 'lastWeek' => []]; @endphp
                <div data-help-page class="{{ $loop->first ? '' : 'hidden' }}">
                    <div class="bg-white border-4 border-[#2D231E] rounded-2xl overflow-hidden shadow-[5px_5px_0px_0px_rgba(45,35,30,1)]">
                        <div class="{{ $meta[1] }} border-b-2 border-[#2D231E] px-4 py-3 flex items-center gap-2">
                            <span class="text-[#2D231E] [&>svg]:h-5 [&>svg]:w-5">{!! \App\Support\LibraryPresentation::iconGlyph('ui', $gameKey === 'wordsearch' ? 'magnifying-glass' : ($gameKey === 'match' ? 'cards' : 'book-pile')) !!}</span>
                            <p class="font-pixel text-[10px] font-bold text-[#2D231E] uppercase text-left">{{ $meta[0] }}</p>
                        </div>

                        <div class="p-3 pb-2 space-y-1.5">
                            <p class="font-pixel text-[9px] font-bold uppercase tracking-wider text-[#2D231E]">Last week</p>
                            @forelse ($board['lastWeek'] as $row)
                                <div class="flex items-center gap-2 px-2 py-1.5 rounded-xl bg-[#FAF6EE] border-2 border-[#2D231E]/10">
                                    <span class="w-6 text-center font-pixel text-[10px] font-bold {{ $row['rank'] === 1 ? 'text-amber-500' : ($row['rank'] === 2 ? 'text-slate-400' : 'text-orange-400') }}">{{ $row['rank'] }}</span>
                                    <x-avatar :name="$row['name']" :avatar="$row['avatar'] ?? null" :medal="$row['rank']" :size="24" />
                                    <span class="text-sm font-bold text-[#2D231E] truncate flex-1">{{ $row['name'] }}</span>
                                    <span class="text-xs font-extrabold text-[#2D231E]">{{ $row['score'] }}</span>
                                </div>
                            @empty
                                <p class="text-xs font-bold text-[#2D231E] text-center py-2">No scores yet</p>
                            @endforelse
                        </div>

                        <div class="px-3 pb-3 pt-2 space-y-1.5 max-h-52 overflow-y-auto border-t-2 border-dashed border-[#2D231E]/20">
                            <p class="font-pixel text-[9px] font-bold uppercase tracking-wider text-[#2D231E]">This week</p>
                            @forelse ($board['current']['entries'] as $row)
                                <div class="flex items-center gap-2 px-2 py-1.5 rounded-xl {{ $row['isSelf'] ? 'bg-amber-100 border-2 border-amber-400' : ($row['rank'] <= 3 ? 'bg-[#FAF6EE] border-2 border-[#2D231E]/10' : '') }} {{ $row['isSelf'] ? '' : 'border-2 border-transparent' }}">
                                    <span class="w-6 text-center font-pixel text-[10px] font-bold {{ $row['rank'] === 1 ? 'text-amber-500' : ($row['rank'] === 2 ? 'text-slate-400' : ($row['rank'] === 3 ? 'text-orange-400' : 'text-[#2D231E]')) }}">{{ $row['rank'] }}</span>
                                    <x-avatar :name="$row['name']" :avatar="$row['avatar'] ?? null" :medal="$row['rank'] <= 3 ? $row['rank'] : null" :size="24" />
                                    <span class="text-sm font-bold text-[#2D231E] truncate flex-1">{{ $row['name'] }}</span>
                                    <span class="text-xs font-extrabold text-[#2D231E]">{{ $row['score'] }}</span>
                                </div>
                            @empty
                                <p class="text-xs font-bold text-[#2D231E] text-center py-3">No rounds yet</p>
                            @endforelse
                        </div>

                        <div class="border-t-2 border-[#2D231E] px-4 py-2 bg-[#FAF6EE] text-center">
                            @if ($board['current']['selfRank'] !== null)
                                <p class="text-[11px] text-[#2D231E] font-bold">
                                    You're #{{ $board['current']['selfRank'] }}{{ $board['current']['selfBest'] !== null ? ' ('.$board['current']['selfBest'].')' : '' }}
                                </p>
                            @else
                                <p class="text-[11px] text-[#2D231E] font-bold">Play a round to join!</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach

            <!-- Prev / Next nav -->
            <div class="flex items-center justify-between gap-3">
                <button
                    type="button"
                    data-help-prev
                    class="hidden min-h-[40px] px-4 font-pixel text-[9px] font-bold uppercase bg-white border-2 border-[#2D231E] text-[#2D231E] rounded-xl shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all cursor-pointer"
                >
                    Previous
                </button>
                <button
                    type="button"
                    data-help-next
                    class="ml-auto min-h-[40px] px-4 font-pixel text-[9px] font-bold uppercase bg-white border-2 border-[#2D231E] text-[#2D231E] rounded-xl shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all cursor-pointer"
                >
                    Next
                </button>
            </div>
        </div>
        <p class="text-[11px] text-[#2D231E] font-semibold text-center -mt-2">Resets every Monday.</p>
    </x-modal>

    <!-- Help Modal -->
    <x-modal id="game-help-modal" title="How Games Work" :pixel="true">
        <div class="space-y-4 text-sm text-[#2D231E] font-medium leading-relaxed">
            <div>
                <h4 class="font-pixel text-[10px] font-bold text-emerald-600 uppercase mb-1">Word Search</h4>
                <p>Pick a story (or let Alice surprise you), then swipe the grid to find its hidden words.</p>
            </div>
            <div>
                <h4 class="font-pixel text-[10px] font-bold text-sky-600 uppercase mb-1">Memory</h4>
                <p>Memorize the pairs while every card is face-up, then flip two at a time to match words with the same meaning.</p>
            </div>
            <div>
                <h4 class="font-pixel text-[10px] font-bold text-amber-600 uppercase mb-1">Sentence Builder</h4>
                <p>Fill in the missing words to complete the sentence.</p>
            </div>
            <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl p-3">
                <h4 class="font-pixel text-[10px] font-bold text-[#2D231E] uppercase mb-1">Word Source</h4>
                <p>Word Search lets you <span class="font-bold">Pick a Story</span>. <span class="font-bold">Saved</span> uses words you saved yourself.</p>
            </div>
        </div>
    </x-modal>

</div>
@endsection
