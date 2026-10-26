{{-- Journal toolbar + move pool (swapped in place by the AJAX mutate handler). --}}
<div class="flex flex-wrap items-center gap-3">
    <form method="POST" action="{{ route('teacher.stories.missions.toggle', $story) }}" data-journal-mutate>
        @csrf
        <button
            type="submit"
            data-submit-guard
            class="{{ $story->missions_enabled ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'bg-[#F3ECE0] border border-[#E6DCCF] text-[#2D231E] hover:border-[#2D231E]' }} inline-flex items-center justify-center min-h-[44px] px-4 rounded-lg text-xs font-bold transition-colors cursor-pointer"
        >
            <span data-submit-idle>Journal: {{ $story->missions_enabled ? 'ON' : 'OFF' }}</span>
            <span data-submit-active class="hidden"><x-spinner label="Saving..." /></span>
        </button>
    </form>
    <p class="flex-1 min-w-[200px] text-xs font-semibold text-[#2D231E]">
        Panel shows the student journal on the reading page. Action swaps the plain Finish button for a boss battle.
    </p>
    <span class="shrink-0 inline-flex items-center px-2 py-0.5 bg-white border border-[#E6DCCF] rounded-full text-[10px] font-extrabold text-[#2D231E] tabular-nums">
        {{ $missionActions->count() }} of {{ config('missions.authored_max') }} moves
    </span>
    @if ($missionActions->count() < (int) config('missions.authored_max'))
        <button
            type="button"
            data-modal-open="mission-action-modal"
            class="shrink-0 inline-flex items-center justify-center min-h-[44px] px-4 py-2 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700 transition-colors cursor-pointer"
        >+ Add action</button>
    @endif
</div>

<div class="relative space-y-2" data-journal-list data-reorder-url="{{ route('teacher.stories.mission-actions.reorder', $story) }}">
    @forelse ($missionActions as $action)
        @php
            $actionSummary = \App\Support\MissionActionWriter::summary($action);
            $actionEditType = $action->config['rule']['type'] ?? ($action->config['type'] ?? '');
            if ($actionEditType === 'prefix') {
                $actionEditType = 'initial';
            }
        @endphp
        <div class="group p-4 bg-white border border-[#E6DCCF] rounded-lg" data-journal-card="{{ $action->id }}">
            <div class="flex items-start gap-2">
                <button
                    type="button"
                    data-drag-handle
                    aria-label="Reorder this journal move. Drag, or focus and press Arrow Up or Arrow Down"
                    class="shrink-0 min-h-[44px] min-w-[28px] inline-flex items-center justify-center text-[#2D231E] cursor-grab active:cursor-grabbing touch-none"
                >
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" class="w-4 h-4">
                        <circle cx="9" cy="6" r="1.6"/><circle cx="15" cy="6" r="1.6"/>
                        <circle cx="9" cy="12" r="1.6"/><circle cx="15" cy="12" r="1.6"/>
                        <circle cx="9" cy="18" r="1.6"/><circle cx="15" cy="18" r="1.6"/>
                    </svg>
                </button>
                <span class="shrink-0 w-8 h-8 flex items-center justify-center bg-[#F3ECE0] rounded-lg text-[11px] font-extrabold text-[#2D231E] tabular-nums">
                    {{ str_pad((string) ($loop->index + 1), 2, '0', STR_PAD_LEFT) }}
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-bold text-[#2D231E]">{{ $actionSummary }}</p>
                </div>
                <x-admin.pill tone="ok">{{ strtoupper($action->kind) }}</x-admin.pill>
            </div>
            <div class="mt-3 flex flex-wrap items-center justify-end gap-1 border-t border-[#F3ECE0] pt-3 opacity-100 transition-opacity [@media(hover:hover)]:opacity-0 [@media(hover:hover)]:group-hover:opacity-100 [@media(hover:hover)]:group-focus-within:opacity-100">
                <button
                    type="button"
                    data-modal-open="mission-action-modal"
                    data-mission-edit="{{ json_encode(['id' => $action->id, 'type' => $actionEditType, 'value' => $action->config['rule']['value'] ?? null, 'need' => $action->config['need'] ?? 1]) }}"
                    class="min-h-[44px] inline-flex items-center px-3 text-[11px] font-bold text-[#2D231E] hover:text-[#3D3029] hover:underline cursor-pointer"
                >Edit</button>
                <form method="POST" action="{{ route('teacher.stories.mission-actions.destroy', $action) }}" data-journal-mutate>
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="action_preview" value="{{ $actionSummary }}">
                    <button
                        type="button"
                        data-confirm-open="mission-action-delete-modal"
                        class="min-h-[44px] inline-flex items-center px-3 text-[11px] font-bold bg-rose-50 border border-rose-200 text-rose-700 rounded-lg hover:bg-rose-600 hover:border-rose-600 hover:text-white transition-colors cursor-pointer"
                    >Delete</button>
                </form>
            </div>
        </div>
    @empty
        <div class="p-5 bg-[#FAF6EE] border border-dashed border-[#E6DCCF] rounded-lg text-center">
            <p class="text-sm font-bold text-[#2D231E]">No moves authored yet</p>
            <p class="mt-1 text-xs font-semibold text-[#2D231E]">The battle runs on auto moves until you add some. Store up to {{ config('missions.authored_max') }} so every attempt shuffles a fresh 3.</p>
        </div>
    @endforelse
</div>
