@if (config('devtools.enabled') && \App\Support\ViewAs::isActive() && \App\Support\ViewAs::isScratchTarget())
<div
    data-dev-tools
    data-state-url="{{ route('dev.state') }}"
    data-run-url="{{ route('dev.run') }}"
    class="relative shrink-0"
>
    <button
        type="button"
        data-dev-tools-toggle
        aria-expanded="false"
        class="shrink-0 text-xs font-pixel font-bold px-3 py-1.5 rounded-lg border border-amber-400 bg-[#1F1A17] text-amber-300 hover:bg-[#3A2F29] transition-colors cursor-pointer"
    >DEV TOOLS</button>

    <div data-dev-tools-panel class="hidden fixed bottom-4 right-4 z-[60] w-[330px] max-w-[calc(100vw-1rem)] max-h-[calc(100vh-1rem)] overflow-y-auto bg-[#1F1A17] border-2 border-amber-400 rounded-2xl shadow-2xl text-white">
        <div class="px-4 pt-3.5 pb-3 space-y-3">
            <div class="flex items-center justify-between">
                <p class="font-pixel text-[9px] font-bold text-amber-300 uppercase tracking-wider">Dev Tools</p>
                <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-rose-500/20 text-rose-300 border border-rose-500 uppercase">Scratch only</span>
            </div>

            <!-- Live readout -->
            <div class="grid grid-cols-2 gap-1.5" data-dev-tools-readout>
                <div class="flex items-center justify-between bg-white/5 border border-white/10 rounded-lg px-2.5 py-1.5">
                    <span class="text-[10px] font-bold text-white/60 uppercase">Keys</span>
                    <span data-k="total_keys" class="text-[11px] font-pixel font-bold text-amber-300">-</span>
                </div>
                <div class="flex items-center justify-between bg-white/5 border border-white/10 rounded-lg px-2.5 py-1.5">
                    <span class="text-[10px] font-bold text-white/60 uppercase">Points</span>
                    <span data-k="points" class="text-[11px] font-pixel font-bold text-emerald-300">-</span>
                </div>
                <div class="flex items-center justify-between bg-white/5 border border-white/10 rounded-lg px-2.5 py-1.5">
                    <span class="text-[10px] font-bold text-white/60 uppercase">Streak</span>
                    <span data-k="streak_display" class="text-[11px] font-pixel font-bold text-rose-300">-</span>
                </div>
                <div class="flex items-center justify-between bg-white/5 border border-white/10 rounded-lg px-2.5 py-1.5">
                    <span class="text-[10px] font-bold text-white/60 uppercase">Restores</span>
                    <span data-k="streak_freezes" class="text-[11px] font-pixel font-bold text-sky-300">-</span>
                </div>
                <div class="flex items-center justify-between bg-white/5 border border-white/10 rounded-lg px-2.5 py-1.5">
                    <span class="text-[10px] font-bold text-white/60 uppercase">Mins today</span>
                    <span data-k="minutes_display" class="text-[11px] font-pixel font-bold text-cyan-300">-</span>
                </div>
                <div class="flex items-center justify-between bg-white/5 border border-white/10 rounded-lg px-2.5 py-1.5">
                    <span class="text-[10px] font-bold text-white/60 uppercase">Farm.</span>
                    <span data-k="farmable_today" class="text-[11px] font-pixel font-bold text-amber-200">-</span>
                </div>
            </div>

            <p data-dev-tools-message class="min-h-[16px] text-[10px] font-bold text-emerald-300 leading-4"></p>

            <!-- Economy -->
            <p class="text-[9px] font-extrabold text-white/40 uppercase tracking-wider">Economy</p>
            <div class="grid grid-cols-3 gap-1.5">
                <button type="button" data-dev-action="add_keys" data-amount="25" class="min-h-[36px] text-[10px] font-bold px-2 py-1.5 rounded-lg bg-emerald-500 text-[#2D231E] hover:bg-emerald-400 transition-colors cursor-pointer">+25 keys</button>
                <button type="button" data-dev-action="add_points" data-amount="100" class="min-h-[36px] text-[10px] font-bold px-2 py-1.5 rounded-lg bg-emerald-500 text-[#2D231E] hover:bg-emerald-400 transition-colors cursor-pointer">+100 pts</button>
                <button type="button" data-dev-action="spend_keys" data-amount="5" class="min-h-[36px] text-[10px] font-bold px-2 py-1.5 rounded-lg bg-amber-600 text-white hover:bg-amber-500 transition-colors cursor-pointer">-5 keys</button>
            </div>

            <!-- Reading & streak -->
            <p class="text-[9px] font-extrabold text-white/40 uppercase tracking-wider">Reading & streak</p>
            <div class="grid grid-cols-3 gap-1.5">
                <button type="button" data-dev-action="reading_minutes" data-minutes="5" class="min-h-[36px] text-[10px] font-bold px-2 py-1.5 rounded-lg bg-cyan-500 text-[#2D231E] hover:bg-cyan-400 transition-colors cursor-pointer">+5 min</button>
                <button type="button" data-dev-action="stamp_today" class="min-h-[36px] text-[10px] font-bold px-2 py-1.5 rounded-lg bg-neutral-500 text-white hover:bg-neutral-400 transition-colors cursor-pointer">Stamp</button>
                <button type="button" data-dev-action="add_freeze" class="min-h-[36px] text-[10px] font-bold px-2 py-1.5 rounded-lg bg-sky-500 text-[#2D231E] hover:bg-sky-400 transition-colors cursor-pointer">+1 restore</button>
            </div>

            <!-- Scenarios -->
            <p class="text-[9px] font-extrabold text-white/40 uppercase tracking-wider">Real-path scenarios</p>
            <div class="grid grid-cols-2 gap-1.5">
                <button type="button" data-dev-action="scenario_quiz" data-kind="perfect" class="min-h-[36px] text-[10px] font-bold px-2 py-1.5 rounded-lg bg-violet-500 text-white hover:bg-violet-400 transition-colors cursor-pointer">Quiz perfect</button>
                <button type="button" data-dev-action="scenario_game" data-game="wordsearch" data-score="excellent" class="min-h-[36px] text-[10px] font-bold px-2 py-1.5 rounded-lg bg-violet-500 text-white hover:bg-violet-400 transition-colors cursor-pointer">Game excellent</button>
                <button type="button" data-dev-action="scenario_drill" class="min-h-[36px] text-[10px] font-bold px-2 py-1.5 rounded-lg bg-violet-500 text-white hover:bg-violet-400 transition-colors cursor-pointer">Drill</button>
                <button type="button" data-dev-action="scenario_task" class="min-h-[36px] text-[10px] font-bold px-2 py-1.5 rounded-lg bg-violet-500 text-white hover:bg-violet-400 transition-colors cursor-pointer">Task claim</button>
            </div>

            <div class="pt-2 border-t border-white/10">
                <button type="button" data-dev-action="reset" class="w-full min-h-[32px] text-[10px] font-bold px-2 py-1.5 rounded-lg bg-rose-600 text-white hover:bg-rose-500 transition-colors cursor-pointer">Reset scratch</button>
            </div>
            <p class="text-[9px] font-semibold text-white/40 text-center truncate" data-k="email">-</p>
        </div>

        <!-- Reset confirm modal -->
        <div data-dev-reset-modal class="hidden fixed inset-0 z-[70] flex items-center justify-center p-4">
            <div data-dev-reset-backdrop class="absolute inset-0 bg-black/60"></div>
            <div class="relative w-full max-w-[300px] bg-[#1F1A17] border-2 border-rose-500 rounded-2xl shadow-2xl p-4 space-y-3">
                <p class="font-pixel text-[10px] font-bold text-rose-300 uppercase tracking-wider">Reset scratch student?</p>
                <p class="text-[11px] font-semibold text-white/70 leading-4">All preview data is wiped and the economy is zeroed. Audited.</p>
                <div class="flex gap-2">
                    <button type="button" data-dev-reset-cancel class="flex-1 min-h-[32px] text-[10px] font-bold px-2 py-1.5 rounded-lg border border-white/20 text-white/70 hover:bg-white/10 transition-colors cursor-pointer">Cancel</button>
                    <button type="button" data-dev-reset-confirm class="flex-1 min-h-[32px] text-[10px] font-bold px-2 py-1.5 rounded-lg bg-rose-600 text-white hover:bg-rose-500 transition-colors cursor-pointer">Reset</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endif