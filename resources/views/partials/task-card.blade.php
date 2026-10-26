@php
    $rerollCost = (int) config('gamification.sinks.task_reroll');
    $keysNow = (int) ($gamer['keys'] ?? 0);
@endphp
@foreach ($tasks as $t)
    <div class="card-hover bg-white border-4 {{ $t['can_claim'] ? 'border-emerald-500 task-card-pulse' : 'border-[#2D231E]' }} rounded-2xl p-4 shadow-[5px_5px_0px_0px_rgba(45,35,30,1)]">
        <div class="flex items-start gap-3">
            <span class="font-semibold w-12 h-12 shrink-0 border-2 border-[#2D231E] rounded-xl flex items-center justify-center {{ $t['claimed'] ? 'bg-[#E6DCCF] text-[#2D231E]' : 'bg-emerald-100 text-emerald-700' }} [&>svg]:h-6 [&>svg]:w-6">
                {!! \App\Support\LibraryPresentation::iconGlyph('ui', $t['type']->icon()) !!}
            </span>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-1.5">
                    <h3 class="font-pixel text-xs font-bold text-[#2D231E] uppercase">{{ $t['title'] }}</h3>
                    <x-info label="{{ 'About this task' }}">Finish this task to earn {{ $t['reward_keys'] }} key{{ $t['reward_keys'] === 1 ? '' : 's' }} and {{ $t['reward_xp'] }} points. You can claim it once a day.</x-info>
                </div>
                <p class="text-[11px] text-[#2D231E] font-semibold">{{ $t['description'] }}</p>
            </div>
        </div>

        <div class="mt-2.5 flex items-center justify-between gap-2">
            <span class="font-pixel text-[8px] font-bold uppercase tracking-wider text-[#2D231E]">Reward</span>
            <span class="flex items-center gap-1.5">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-amber-100 border-2 border-[#2D231E] rounded-md text-amber-700 text-[10px] font-bold [&>svg]:h-3.5 [&>svg]:w-3.5" title="{{ $t['reward_keys'] }} key{{ $t['reward_keys'] === 1 ? '' : 's' }}">
                    {!! \App\Support\LibraryPresentation::iconGlyph('ui', 'key') !!} +{{ $t['reward_keys'] }}
                </span>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-emerald-100 border-2 border-[#2D231E] rounded-md text-emerald-700 text-[10px] font-bold [&>svg]:h-3.5 [&>svg]:w-3.5" title="{{ $t['reward_xp'] }} points">
                    {!! \App\Support\LibraryPresentation::iconGlyph('ui', 'laurel-crown') !!} +{{ $t['reward_xp'] }} points
                </span>
            </span>
        </div>

        @if (! $t['claimed'])
        <div class="mt-4">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[11px] font-bold text-[#2D231E]"><span data-count-up="{{ min($t['progress'], $t['target']) }}">{{ min($t['progress'], $t['target']) }}</span> / {{ $t['target'] }}</span>
                @if ($t['completed'])
                    <span class="inline-flex items-center gap-1.5 text-[11px] font-bold text-emerald-600">Complete!</span>
                @elseif ($t['in_progress'])
                    <span class="inline-flex items-center gap-1.5 text-[11px] font-bold text-[#2D231E]">
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-400 border border-[#2D231E]" aria-hidden="true"></span>
                        In progress
                    </span>
                @else
                    <span class="text-[11px] font-bold text-[#2D231E]">Not started</span>
                @endif
            </div>
            <div class="h-3 w-full bg-[#E6DCCF] border-2 border-[#2D231E] rounded-full overflow-hidden">
                <div class="h-full {{ $t['completed'] ? 'bg-emerald-500' : 'bg-amber-400' }} bar-grow" style="--bar-w: {{ (int) round(min(100, ($t['target'] ? $t['progress'] / $t['target'] : 0) * 100)) }}%"></div>
            </div>
        </div>
        @endif

        <div class="mt-4 flex items-center gap-2">
            @unless ($t['claimed'])
                <div class="flex items-center gap-2" data-task-actions data-task-id="{{ $t['id'] }}" data-reward-keys="{{ $t['reward_keys'] }}"{{ $t['can_claim'] ? ' data-can-claim' : '' }}>
                    @if ($t['can_claim'])
                        <form method="POST" action="{{ route('tasks.claim') }}" class="inline" data-task-claim-form>
                            @csrf
                            <input type="hidden" name="task" value="{{ $t['id'] }}">
                            @if (! empty($fromHub))
                                <input type="hidden" name="origin" value="hub">
                            @endif
                            <button type="submit"
                                class="min-w-[44px] min-h-[44px] px-4 bg-emerald-500 hover:bg-emerald-600 cursor-pointer text-white font-pixel text-[11px] font-bold rounded-xl border-2 border-[#2D231E] shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all uppercase goal-claim-pulse">
                                Claim
                            </button>
                        </form>
                    @elseif (! $t['completed'])
                        <a href="{{ route($t['type']->routeName()) }}"
                            class="inline-flex items-center min-h-[44px] px-4 bg-amber-400 hover:bg-amber-300 text-[#2D231E] font-pixel text-[11px] font-bold rounded-xl border-2 border-[#2D231E] shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all uppercase">
                            Go
                        </a>
                    @endif
                    @if ($rerollsRemaining > 0)
                        <form method="POST" action="{{ route('tasks.reroll') }}" class="inline">
                            @csrf
                            <input type="hidden" name="task" value="{{ $t['id'] }}">
                            @if (! empty($fromHub))
                                <input type="hidden" name="origin" value="hub">
                            @endif
                            <button type="submit" data-confirm-open="reroll-confirm-{{ $t['id'] }}"
                                class="min-w-[44px] min-h-[44px] px-4 bg-white hover:bg-[#FAF6EE] text-[#2D231E] font-pixel text-[11px] font-bold rounded-xl border-2 border-[#2D231E] shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all uppercase">
                                Reroll
                            </button>
                        </form>
                    @endif
                </div>
            @endunless
            <span data-task-claimed-chip class="items-center gap-1.5 px-3 py-2 bg-[#D8CDBF] text-[#5B4A3F] font-pixel text-[11px] font-bold rounded-xl border-2 border-[#2D231E] uppercase [&>svg]:h-4 [&>svg]:w-4 {{ $t['claimed'] ? 'inline-flex' : 'hidden' }}">
                {!! \App\Support\LibraryPresentation::iconGlyph('ui', 'trophy') !!} Claimed
            </span>
        </div>
    </div>

    <x-modal id="reroll-confirm-{{ $t['id'] }}" title="Swap this task?" :pixel="true">
        <p class="text-sm font-semibold text-[#2D231E]/70 text-center">Swap <b>{{ $t['title'] }}</b> for a different task? It costs <b class="text-amber-600">{{ $rerollCost }}</b> key{{ $rerollCost === 1 ? '' : 's' }}. You have <b class="text-emerald-700">{{ $keysNow }}</b>.</p>
        <div class="flex items-center justify-center gap-3 pt-1">
            <button type="button" data-modal-close class="min-h-[44px] px-5 font-pixel text-[9px] font-bold uppercase bg-white border-2 border-[#2D231E] rounded-xl shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all">KEEP TASK</button>
            <button type="button" data-confirm-approve {{ $keysNow >= $rerollCost ? '' : 'disabled' }} class="min-h-[44px] px-5 inline-flex items-center gap-2 font-pixel text-[9px] font-bold uppercase text-white bg-emerald-600 hover:bg-emerald-700 border-2 border-[#2D231E] rounded-xl shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all disabled:opacity-40 disabled:pointer-events-none">
                {{ $keysNow >= $rerollCost ? 'SWAP IT' : 'NEED MORE KEYS' }}
            </button>
        </div>
    </x-modal>
@endforeach
