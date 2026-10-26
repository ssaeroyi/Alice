@extends('layouts.authenticated')

@section('page', 'reader')

@php
    $coverUrl = $story->coverUrl();
    $narrationSegmentsJson = json_encode($narrationSegments);

    // Build tappable HTML from ALL pages with data-narr-section markers.
    $storyHtml = '';
    foreach ($pageList as $idx => $pageText) {
        $pageNum = $idx + 1;
        $paragraphs = collect(preg_split('/\n{2,}/', (string) $pageText))
            ->map(fn ($p) => trim($p))
            ->filter()
            ->map(fn ($paragraph) => '<p><span class="para-text">'.preg_replace_callback(
                // Same pattern as StoryProfile: one span per token, so a
                // tap's normalize() output always equals one profile word.
                "/[\p{L}\p{N}]+(?:['\x{2019}][\p{L}\p{N}]+)*/u",
                fn ($m) => '<span class="tap-word" tabindex="0" role="button" data-w="'.e($m[0]).'">'.e($m[0]).'</span>',
                $paragraph,
            ).'</span></p>')
            ->implode('');

        $storyHtml .= '<div data-narr-section="'.$pageNum.'"'
            .(($pageContinues[$idx] ?? false) ? ' class="is-continuation"' : '')
            .'>'.$paragraphs.'</div>';
    }
@endphp

@section('content')
<div
    class="max-w-3xl mx-auto w-full px-3 py-2 h-[100dvh] flex flex-col gap-2"
    id="reader-shell"
    data-story-slug="{{ $story->slug }}"
    data-story-title="{{ $story->title }}"
    data-story-author="{{ $story->author ?? 'Unknown' }}"
    data-story-cover="{{ $coverUrl }}"
    data-prefs-url="{{ route('reader.preferences', $story) }}"
    data-finish-url="{{ route('reader.finish', $story) }}"
    data-report-url="{{ route('reader.report.store', $story) }}"
    data-preview-url="{{ route('story.preview', $story) }}"
    data-dictionary-url="{{ route('study.dictionary') }}"
    data-debug-actor="{{ \App\Support\ViewAs::actor()?->role->value ?? '' }}"
    data-debug="{{ app()->hasDebugModeEnabled() ? '1' : '0' }}"
    data-stt-debug="{{ session('alice.stt_debug') ? '1' : '0' }}"
>

    {{-- Thin scroll-progress bar --}}
    <div class="shrink-0 h-[2px] bg-[#2D231E]/10 rounded-full overflow-hidden">
        <div id="scroll-progress" class="h-full bg-emerald-500 rounded-full transition-[width] duration-150" style="width:0%"></div>
    </div>

    {{-- Top bar: back · Aa · ⋮ — swaps to the battle strip during a move --}}
    <div
        class="reader-surface flex items-center gap-2 shrink-0 bg-white border-2 border-[#2D231E] rounded-xl shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] px-3 py-1.5"
        id="reader-topbar"
    >
        <div class="topbar-normal">
            <a
                href="{{ route('story.preview', $story) }}"
                class="w-10 h-10 shrink-0 bg-white border-2 border-[#2D231E] text-[#2D231E] rounded-xl font-bold flex items-center justify-center shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-none transition-all"
                aria-label="Back to the story preview"
            >
                <x-icons.arrow-left class="w-4 h-4" />
            </a>

            <div class="flex-1"></div>

            {{-- Aa: reading settings --}}
            <button
                type="button"
                data-aa-open
                aria-label="Reading settings"
                class="min-h-[40px] min-w-[40px] px-2 inline-flex items-center justify-center font-pixel text-xs font-bold bg-white border-2 border-[#2D231E] rounded-xl shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all cursor-pointer"
            >Aa</button>

            {{-- ⋮: story menu --}}
            <button
                type="button"
                data-menu-open
                aria-label="Story options"
                class="min-h-[40px] min-w-[40px] inline-flex items-center justify-center bg-white border-2 border-[#2D231E] rounded-xl shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all cursor-pointer"
            ><x-icons.ellipsis-vertical class="w-5 h-5 text-[#2D231E]" /></button>
        </div>

        {{-- Battle strip: hidden normally, replaces the bar above while a
             move is running (body[data-game-mode="on"]). --}}
        <div class="gm-strip">
            <span class="gm-task font-pixel" data-gm-task></span>
            <span class="gm-timer font-pixel" data-gm-timer></span>
            <span class="gm-hearts" data-gm-hearts></span>
            <button type="button" data-gm-quit class="gm-quit font-pixel" aria-label="Quit action">X</button>
        </div>
    </div>

    {{-- Alice: decorative companion. position:fixed (CSS) so she is out of
         flow and never affects layout — on desktop she sits in the left
         margin outside the card, on narrow/mobile she overlays the control
         bar. pointer-events:none keeps every control tappable. --}}
    <div id="reader-mascot" data-anim="idle" data-skin='@json($equippedAlice)' aria-hidden="true"></div>

    <div
        id="reader-main"
        data-heartbeat-url="{{ route('reader.heartbeat', $story) }}"
        data-initial-pct="{{ (int) $progress->current_page }}"
        data-saved-words='@json($savedWords)'
        data-narration-segments="{{ $narrationSegmentsJson }}"
        class="flex-1 min-h-0 flex flex-col gap-2"
    >

        {{-- Stage card --}}
        <div
            id="reader-stage"
            class="reader-surface flex-1 min-w-0 bg-white rounded-2xl overflow-hidden"
        >
            <article
                id="reader-paper"
                class="reader-paper h-full overflow-y-auto px-3 py-5"
            >
                {!! $storyHtml !!}
            </article>
        </div>

        {{-- Bottom bar --}}
        <div class="reader-surface shrink-0 flex items-center justify-between gap-2 bg-white border-2 border-[#2D231E] rounded-xl shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] px-3 py-1.5">
            {{-- Center: narration --}}
            @if ($narrationSegments)
                <div
                    data-narration
                    data-slug="{{ $story->slug }}"
                    class="flex items-center gap-1.5 min-w-0"
                >
                    <button
                        type="button"
                        data-narr-toggle
                        data-cue="turn"
                        aria-pressed="false"
                        aria-label="LISTEN"
                        class="min-h-[40px] px-3 inline-flex items-center gap-1.5 font-pixel text-[9px] font-bold uppercase text-white bg-emerald-600 hover:bg-emerald-700 border-2 border-[#2D231E] rounded-xl shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all"
                    ><span data-narr-icon>▶</span></button>
                    <button
                        type="button"
                        data-narr-speed
                        data-cue="select"
                        aria-label="Change reading speed"
                        class="min-h-[40px] px-2 font-pixel text-[8px] font-bold uppercase bg-white border-2 border-[#2D231E] rounded-lg shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all"
                    >1×</button>
                </div>
            @else
                <span></span>
            @endif

            {{-- Right: JOURNAL (mission stories) + FINISH --}}
            <div class="shrink-0 flex items-center gap-2">
                @if ($missionsEnabled)
                    <button
                        type="button"
                        data-journal-open
                        data-cue="open"
                        aria-label="Open field journal"
                        class="min-h-[40px] px-3 inline-flex items-center gap-1.5 font-pixel text-[9px] font-bold uppercase text-white bg-amber-500 hover:bg-amber-600 border-2 border-[#2D231E] rounded-xl shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all{{ $battleComplete ? '' : ' journal-pulse' }}"
                    >JOURNAL</button>
                @endif
                @if (!$missionsEnabled || $battleComplete)
                <form method="POST" action="{{ route('reader.finish', $story) }}">
                    @csrf
                    <button
                        type="submit"
                        data-finish-btn
                        class="min-h-[40px] px-3 inline-flex items-center gap-1.5 font-pixel text-[9px] font-bold uppercase text-white bg-emerald-600 hover:bg-emerald-700 border-2 border-[#2D231E] rounded-xl shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all opacity-30 pointer-events-none"
                    >FINISH</button>
                </form>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Field Journal battle overlay (mission stories only) --}}
@if ($missionsEnabled)
<div
    id="journal-root"
    data-battle='@json($battleState)'
    data-state-url="{{ route('reader.battle.state', $story) }}"
    data-start-url="{{ route('reader.battle.start', $story) }}"
    data-complete-url="{{ route('reader.battle.complete', $story) }}"
    data-fail-url="{{ route('reader.battle.fail', $story) }}"
    data-reset-url="{{ route('reader.battle.reset', $story) }}"
    data-finish-url="{{ route('reader.finish', $story) }}"
    data-hearts-src="{{ asset('assets/ui/hearts.png') }}"
    data-skins='@json($skinsPayload)'
    data-skins-base-url="{{ route('skins.index') }}"
    data-boss-name="{{ $equippedBoss['name'] }}"
>
    <div id="journal-backdrop" hidden></div>
    <div id="journal-panel" hidden role="dialog" aria-modal="true" aria-label="JOURNAL">
            <div class="journal-frame">
                <div class="journal-top">
                    <div class="journal-top-row">
                        <span class="journal-title font-pixel">JOURNAL</span>
                        <div class="journal-top-btns">
                            <button type="button" data-modal-open="journal-help-modal" class="journal-icon-btn font-pixel" aria-label="How to finish this journal">?</button>
                            <button type="button" data-journal-close class="journal-icon-btn font-pixel" aria-label="Close journal">X</button>
                        </div>
                    </div>
                    <div class="journal-tabs" role="tablist" aria-label="Journal sections">
                        <button type="button" class="journal-tab font-pixel is-active" data-journal-tab="battle" role="tab" aria-selected="true">BATTLE</button>
                        <button type="button" class="journal-tab font-pixel" data-journal-tab="missions" role="tab" aria-selected="false">MISSIONS</button>
                        <button type="button" class="journal-tab font-pixel" data-journal-tab="skins" role="tab" aria-selected="false">SKINS</button>
                    </div>
                </div>
            <div class="journal-spread">
                <div class="journal-page journal-arena" style="background-image: url('{{ asset($skinsPayload['catalog']['background'][$skinsPayload['state']['equipped']['background']]['file']) }}')">
                    <div class="arena-overlay">
                        <div class="arena-hearts" data-arena-hearts aria-label="Alice hearts left"></div>
                        <div class="arena-hearts arena-hearts--boss" data-arena-boss-hearts aria-label="Bruno hearts left"></div>
                    </div>
                    <div class="arena-scene">
                        <div class="arena-boss" data-arena-boss aria-hidden="true"></div>
                        <div class="arena-alice" data-arena-alice aria-hidden="true"></div>
                    </div>
                </div>
                <div class="journal-page journal-actions-page">
                    <div class="journal-actions-head">
                        <h3 class="font-pixel">ACTIONS</h3>
                        <button type="button" data-journal-restart class="journal-icon-btn" aria-label="Restart battle">
                            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square" stroke-linejoin="miter" aria-hidden="true">
                                <path d="M15.3 2.7v4h-4"/>
                                <path d="M0.7 13.3v-4h4"/>
                                <path d="M2.34 6a6 6 0 0 1 9.9-2.24L15.3 6.7"/>
                                <path d="M0.7 9.3l3.1 2.9a6 6 0 0 0 9.9-2.2"/>
                            </svg>
                        </button>
                    </div>
                    <ul class="journal-action-list" data-action-list></ul>
                    <div class="journal-win" data-journal-win hidden>
                        <p class="journal-win-banner font-pixel" data-win-banner>BRUNO DEFEATED!</p>
                        <form method="POST" action="{{ route('reader.finish', $story) }}" data-win-finish>
                            @csrf
                            <button type="submit" class="journal-finish-btn font-pixel">FINISH STORY</button>
                        </form>
                        <button type="button" data-journal-replay class="journal-replay-btn font-pixel">REPLAY</button>
                    </div>
                </div>
            </div>

            {{-- MISSIONS tab: the active move's answer sheet (no overlays) --}}
            <div class="journal-missions" data-missions-pane hidden>
                <div class="missions-empty" data-missions-empty>
                    <p class="missions-empty-title font-pixel">NO MISSIONS IN PROGRESS</p>
                    <p class="missions-empty-sub">Start a move from the BATTLE tab. Its answers land here.</p>
                </div>
                <div class="missions-empty" data-missions-resume hidden>
                    <p class="missions-empty-title font-pixel">MOVE IN PROGRESS</p>
                    <p class="missions-empty-sub">Resume it from the BATTLE tab to keep answering.</p>
                </div>
                <div class="missions-sheet" data-missions-sheet hidden></div>
            </div>

            {{-- SKINS tab: Alice / Boss wardrobe (live, AJAX) --}}
            <div class="journal-skins" data-skins-pane hidden>
                <div class="skins-head">
                    <div class="skins-sections" role="tablist" aria-label="Skin type">
                        <button type="button" class="skins-section font-pixel is-active" data-skins-section="alice" role="tab" aria-selected="true">ALICE</button>
                        <button type="button" class="skins-section font-pixel" data-skins-section="boss" role="tab" aria-selected="false">BOSS</button>
                        <button type="button" class="skins-section font-pixel" data-skins-section="background" role="tab" aria-selected="false">BACKGROUND</button>
                    </div>
                </div>
                <div class="skins-grid" data-skins-grid></div>
            </div>
        </div>
    </div>

    {{-- Skin preview dialog (nested above the journal panel) --}}
    <div id="skin-preview" hidden role="dialog" aria-modal="true" aria-label="SKIN PREVIEW">
        <div class="skin-preview-frame">
            <div class="skin-preview-top">
                <span class="journal-keys" data-keys-chip title="Your keys">
                    <span class="journal-keys-glyph">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'key') !!}</span>
                    <span class="journal-keys-count font-pixel" data-keys-count>{{ number_format($skinsPayload['state']['keys']) }}</span>
                </span>
                <button type="button" data-preview-close class="journal-icon-btn font-pixel" aria-label="Close preview">X</button>
            </div>
            <div class="skin-preview-stage" data-preview-stage style="background-image: url('{{ asset($skinsPayload['catalog']['background'][$skinsPayload['state']['equipped']['background']]['file']) }}')"></div>
            <p class="skin-preview-name font-pixel" data-preview-name></p>
            <p class="skin-preview-sub" data-preview-sub></p>
            <button type="button" class="skin-preview-action font-pixel" data-preview-action></button>
        </div>
    </div>

    {{-- Restart confirmation (header restart button, battle tab only) --}}
    <div id="restart-confirm" hidden role="dialog" aria-modal="true" aria-labelledby="restart-confirm-title">
        <div class="restart-confirm-frame">
            <div class="restart-confirm-top">
                <span class="restart-confirm-title font-pixel" id="restart-confirm-title">RESTART BATTLE?</span>
            </div>
            <div class="restart-confirm-actions">
                <button type="button" data-restart-cancel class="restart-confirm-btn font-pixel">NOT NOW</button>
                <button type="button" data-restart-confirm class="restart-confirm-btn restart-confirm-btn--go font-pixel">RESTART</button>
            </div>
        </div>
    </div>

</div>

{{-- Journal help: HOW TO FINISH? (sidebar sections, ACTIONS paginated) --}}
<x-modal id="journal-help-modal" title="HOW TO FINISH?" pixel wide>
    <div class="flex flex-col sm:flex-row gap-3" data-journal-help>
        <nav class="flex gap-1.5 pr-1 pb-1 overflow-x-auto no-scrollbar shrink-0 sm:w-40 sm:flex-col sm:overflow-visible sm:pr-0 sm:pb-0" aria-label="Help sections">
            <button type="button" class="jh-tab font-pixel is-active" data-jh-tab="overview" aria-selected="true">OVERVIEW</button>
            <button type="button" class="jh-tab font-pixel" data-jh-tab="actions" aria-selected="false">ACTIONS</button>
            <button type="button" class="jh-tab font-pixel" data-jh-tab="missions" aria-selected="false">MISSIONS</button>
            <button type="button" class="jh-tab font-pixel" data-jh-tab="skins" aria-selected="false">SKINS</button>
        </nav>

        <div class="flex-1 min-w-0">
            {{-- Overview --}}
            <section data-jh-panel="overview" class="space-y-2.5 text-xs font-medium text-[#2D231E] leading-relaxed">
                <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-3 space-y-1">
                    <p class="font-pixel text-[10px] font-bold text-amber-600 uppercase">THE JOURNAL</p>
                    <p>This is your field journal. Every mission story hides a boss in the arena, and you beat it by finishing the tasks in the ACTIONS list.</p>
                </div>
                <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-3 space-y-1">
                    <p class="font-pixel text-[10px] font-bold text-amber-600 uppercase">WINNING</p>
                    <p>Press GO on a move to start it. Every finished move damages the boss. Empty the boss hearts, then press FINISH STORY to clear the story.</p>
                </div>
                <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-3 space-y-1">
                    <p class="font-pixel text-[10px] font-bold text-amber-600 uppercase">TABS</p>
                    <p>BATTLE is the arena and your moves. MISSIONS holds your answer sheets. SKINS is your wardrobe.</p>
                </div>
            </section>

            {{-- Actions (paginated: one action type per page) --}}
            <section data-jh-panel="actions">
                <div data-jh-pager class="space-y-3">
                    <div class="flex items-center gap-1.5" role="group" aria-label="Action pages">
                        <button type="button" class="help-pager-dot is-active" data-jh-dot="0" aria-label="Page 1: Spell Strike" aria-current="true"></button>
                        <button type="button" class="help-pager-dot" data-jh-dot="1" aria-label="Page 2: Grammar Strike"></button>
                        <button type="button" class="help-pager-dot" data-jh-dot="2" aria-label="Page 3: Prefix and Suffix Puzzle"></button>
                        <button type="button" class="help-pager-dot" data-jh-dot="3" aria-label="Page 4: Missing Words"></button>
                        <button type="button" class="help-pager-dot" data-jh-dot="4" aria-label="Page 5: Letter Scramble"></button>
                    </div>

                    <div data-jh-page class="space-y-2.5 text-xs font-medium text-[#2D231E] leading-relaxed">
                        <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-3 space-y-1">
                            <p class="font-pixel text-[10px] font-bold text-amber-600 uppercase">SPELL STRIKE</p>
                            <p class="font-pixel text-[9px] font-bold uppercase text-[#2D231E]">Answer in the story</p>
                            <p>Skim the story and tap the words that match the rule, like double letters or a suffix. Wrong taps flash red. The journal closes so you can read, and the move scores itself once the counter is full.</p>
                        </div>
                    </div>

                    <div data-jh-page class="space-y-2.5 text-xs font-medium text-[#2D231E] leading-relaxed">
                        <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-3 space-y-1">
                            <p class="font-pixel text-[10px] font-bold text-amber-600 uppercase">GRAMMAR STRIKE</p>
                            <p class="font-pixel text-[9px] font-bold uppercase text-[#2D231E]">Answer in the story</p>
                            <p>Same hunt with a grammar rule. Tap the verbs, nouns, adjectives or adverbs the move asks for inside the story. Keep tapping until the count is full and the move lands.</p>
                        </div>
                    </div>

                    <div data-jh-page class="space-y-2.5 text-xs font-medium text-[#2D231E] leading-relaxed">
                        <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-3 space-y-1">
                            <p class="font-pixel text-[10px] font-bold text-amber-600 uppercase">PREFIX AND SUFFIX PUZZLE</p>
                            <p class="font-pixel text-[9px] font-bold uppercase text-[#2D231E]">Answer on the missions sheet</p>
                            <p>Type the missing prefix or suffix into each blank, then press CHECK. One piece per blank and spelling counts.</p>
                        </div>
                    </div>

                    <div data-jh-page class="space-y-2.5 text-xs font-medium text-[#2D231E] leading-relaxed">
                        <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-3 space-y-1">
                            <p class="font-pixel text-[10px] font-bold text-amber-600 uppercase">MISSING WORDS</p>
                            <p class="font-pixel text-[9px] font-bold uppercase text-[#2D231E]">Answer on the missions sheet</p>
                            <p>Read each sentence, type the word that completes it, then press CHECK. One word per blank and spelling counts.</p>
                        </div>
                    </div>

                    <div data-jh-page class="space-y-2.5 text-xs font-medium text-[#2D231E] leading-relaxed">
                        <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-3 space-y-1">
                            <p class="font-pixel text-[10px] font-bold text-amber-600 uppercase">LETTER SCRAMBLE</p>
                            <p class="font-pixel text-[9px] font-bold uppercase text-[#2D231E]">Answer on the missions sheet</p>
                            <p>Tap the letter tiles into the boxes, left to right, to rebuild the word. Tap a placed tile to take it back, then press CHECK. Every tile has to be used.</p>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <button
                            type="button"
                            data-jh-prev
                            class="hidden min-h-[40px] px-4 font-pixel text-[9px] font-bold uppercase bg-white border-2 border-[#2D231E] text-[#2D231E] rounded-xl shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all cursor-pointer"
                        >
                            Previous
                        </button>
                        <button
                            type="button"
                            data-jh-next
                            class="ml-auto min-h-[40px] px-4 font-pixel text-[9px] font-bold uppercase bg-white border-2 border-[#2D231E] text-[#2D231E] rounded-xl shadow-[2px_2px_0px_0px_rgba(45,35,30,1)] active:translate-x-[1px] active:translate-y-[1px] active:shadow-none transition-all cursor-pointer"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </section>

            {{-- Missions --}}
            <section data-jh-panel="missions" class="space-y-2.5 text-xs font-medium text-[#2D231E] leading-relaxed">
                <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-3 space-y-1">
                    <p class="font-pixel text-[10px] font-bold text-amber-600 uppercase">WHERE MOVES GO</p>
                    <p>Every move you start from BATTLE lands here as an answer sheet.</p>
                </div>
                <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-3 space-y-1">
                    <p class="font-pixel text-[10px] font-bold text-amber-600 uppercase">STORY MOVES</p>
                    <p>Spell Strike and Grammar Strike are answered in the story itself. This tab only mirrors the rule and your live count.</p>
                </div>
                <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-3 space-y-1">
                    <p class="font-pixel text-[10px] font-bold text-amber-600 uppercase">SHEET MOVES</p>
                    <p>Puzzles and scrambles stay in the journal. Fill the sheet and press CHECK to land the hit.</p>
                </div>
                <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-3 space-y-1">
                    <p class="font-pixel text-[10px] font-bold text-amber-600 uppercase">PAUSED</p>
                    <p>Switch back to BATTLE and press RESUME to pick a move up again. Finished and failed moves clear out on their own.</p>
                </div>
            </section>

            {{-- Skins --}}
            <section data-jh-panel="skins" class="space-y-2.5 text-xs font-medium text-[#2D231E] leading-relaxed">
                <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-3 space-y-1">
                    <p class="font-pixel text-[10px] font-bold text-amber-600 uppercase">THREE WARDROBES</p>
                    <p>ALICE, BOSS and BACKGROUND. Pick a section to browse what you own.</p>
                </div>
                <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-3 space-y-1">
                    <p class="font-pixel text-[10px] font-bold text-amber-600 uppercase">PREVIEW</p>
                    <p>Tap any card to see it on the arena before you spend anything.</p>
                </div>
                <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-3 space-y-1">
                    <p class="font-pixel text-[10px] font-bold text-amber-600 uppercase">UNLOCK AND EQUIP</p>
                    <p>Locked cards show their key price. Press UNLOCK, then YES to spend the keys. Owned cards show EQUIP, and the equipped card gets a green border.</p>
                </div>
                <div class="bg-[#FAF6EE] border-2 border-[#2D231E] rounded-2xl p-3 space-y-1">
                    <p class="font-pixel text-[10px] font-bold text-amber-600 uppercase">KEYS</p>
                    <p>The key chip in the preview header tracks the keys you have left. Win moves to earn more.</p>
                </div>
            </section>
        </div>
    </div>
</x-modal>
@endif

{{-- Aa slide panel: reading settings --}}
<div id="aa-backdrop" class="fixed inset-0 z-50 bg-[#2D231E]/40 backdrop-blur-sm opacity-0 pointer-events-none transition-opacity duration-200"></div>
<div
    id="aa-panel"
    class="fixed z-50 top-0 right-0 h-full w-80 max-w-[85vw] bg-white border-l-4 border-[#2D231E] shadow-[-6px_0_0_0_rgba(45,35,30,1)] flex flex-col translate-x-full transition-transform duration-200 ease-out pointer-events-none"
>
    {{-- Header --}}
    <div class="flex items-center justify-between px-4 py-3 border-b-2 border-[#2D231E]/10 shrink-0">
        <p class="font-pixel text-[10px] font-bold uppercase tracking-wider text-[#2D231E]">Reading Settings</p>
        <button
            type="button"
            data-aa-close
            aria-label="Close settings"
            class="w-8 h-8 flex items-center justify-center rounded-lg border-2 border-[#2D231E] text-[#2D231E] font-bold text-sm hover:bg-[#FAF6EE] transition-colors cursor-pointer"
        >✕</button>
    </div>

    {{-- Scrollable content --}}
    <div class="flex-1 overflow-y-auto px-4 py-4 space-y-5">

        {{-- Font Size --}}
        <div>
            <p class="font-pixel text-[8px] font-bold uppercase text-[#8C7B70] tracking-wider mb-2">FONT SIZE</p>
            <div class="flex justify-center mb-2">
                <span data-pref-fontsize-label class="font-quicksand text-lg font-bold text-[#2D231E]">Aa</span>
            </div>
            <div class="flex items-center gap-3">
                <span class="font-quicksand text-xs font-bold text-[#8C7B70] select-none">A</span>
                <input type="range" min="0" max="4" step="1" value="2" data-pref-fontsize-slider aria-label="Font size" class="flex-1 accent-amber-500 cursor-pointer">
                <span class="font-quicksand text-base font-bold text-[#8C7B70] select-none">A+</span>
            </div>
        </div>

        {{-- Font Family --}}
        <div>
            <p class="font-pixel text-[8px] font-bold uppercase text-[#8C7B70] tracking-wider mb-2">FONT</p>
            <div class="space-y-1.5" data-pref-font-list>
                <button type="button" data-pref-font="default" class="w-full px-3 py-2.5 text-left text-sm font-semibold rounded-xl border-2 transition-colors cursor-pointer" style="font-family:'Quicksand',sans-serif">Default</button>
                <button type="button" data-pref-font="lexend" class="w-full px-3 py-2.5 text-left text-sm rounded-xl border-2 transition-colors cursor-pointer" style="font-family:'Lexend',sans-serif"><strong>Lexend</strong>. Reading fluency.</button>
                <button type="button" data-pref-font="atkinson" class="w-full px-3 py-2.5 text-left text-sm rounded-xl border-2 transition-colors cursor-pointer" style="font-family:'Atkinson Hyperlegible',sans-serif"><strong>Atkinson</strong>. Maximum legibility.</button>
                <button type="button" data-pref-font="opendyslexic" class="w-full px-3 py-2.5 text-left text-sm rounded-xl border-2 transition-colors cursor-pointer" style="font-family:'OpenDyslexic',sans-serif"><strong>OpenDyslexic</strong>. For dyslexia.</button>
                <button type="button" data-pref-font="literata" class="w-full px-3 py-2.5 text-left text-sm rounded-xl border-2 transition-colors cursor-pointer" style="font-family:'Literata',serif"><strong>Literata</strong>. Literary reading.</button>
                <button type="button" data-pref-font="patrick" class="w-full px-3 py-2.5 text-left text-sm rounded-xl border-2 transition-colors cursor-pointer" style="font-family:'Patrick Hand',cursive"><strong>Patrick Hand</strong>. Handwritten fun.</button>
            </div>
        </div>

    </div>
</div>

{{-- ⋮ slide panel: story menu --}}
<div id="menu-backdrop" class="fixed inset-0 z-50 bg-[#2D231E]/40 backdrop-blur-sm opacity-0 pointer-events-none transition-opacity duration-200"></div>
<div
    id="menu-panel"
    class="fixed z-50 top-0 right-0 h-full w-80 max-w-[85vw] bg-white border-l-2 border-[#2D231E] shadow-[-4px_0_0_0_rgba(45,35,30,1)] flex flex-col translate-x-full transition-transform duration-200 ease-out pointer-events-none"
>
    {{-- Header --}}
    <div class="flex items-center justify-between px-4 py-3 border-b-2 border-[#2D231E]/10 shrink-0">
        <p class="font-pixel text-[10px] font-bold uppercase tracking-wider text-[#2D231E]">Story Menu</p>
        <button
            type="button"
            data-menu-close
            aria-label="Close menu"
            class="w-8 h-8 flex items-center justify-center rounded-lg border-2 border-[#2D231E] text-[#2D231E] font-bold text-sm hover:bg-[#FAF6EE] transition-colors cursor-pointer"
        >✕</button>
    </div>

    {{-- Scrollable content --}}
    <div class="flex-1 overflow-y-auto px-4 py-4 space-y-4">

        {{-- Story preview --}}
        <div class="flex items-start gap-3">
            @if ($coverUrl)
                <img src="{{ $coverUrl }}" alt="" class="w-14 h-14 rounded-lg object-cover border-2 border-[#2D231E] shrink-0" loading="lazy">
            @else
                <div class="w-14 h-14 rounded-lg border-2 border-[#2D231E] bg-[#FAF6EE] shrink-0 flex items-center justify-center">
                    <x-icons.book-open class="w-6 h-6 text-[#2D231E]/30" />
                </div>
            @endif
            <div class="min-w-0">
                <p class="text-sm font-bold leading-tight">{{ $story->title }}</p>
                <p class="text-xs text-[#2D231E]/60">{{ $story->author ?? 'Unknown' }}</p>
            </div>
        </div>

        {{-- Report issue --}}
        <button
            type="button"
            data-report-open
            class="w-full flex items-center gap-2 px-3 py-2.5 text-sm font-semibold text-[#2D231E] hover:bg-[#FAF6EE] border-2 border-[#2D231E]/10 rounded-xl transition-colors cursor-pointer"
        >
            <x-icons.book-open class="w-4 h-4 text-[#2D231E]/60" />
            Report an issue
        </button>

    </div>
</div>

{{-- Report an issue modal (opens from the story menu) --}}
<div
    id="report-modal"
    class="hidden fixed inset-0 z-[60] items-center justify-center bg-[#2D231E]/40 backdrop-blur-sm p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="report-modal-title"
>
    <div class="w-full max-w-md bg-white border-4 border-[#2D231E] rounded-3xl p-5 shadow-[6px_6px_0px_0px_rgba(45,35,30,1)] max-h-[85vh] overflow-y-auto space-y-4">
        <div class="flex items-center justify-between border-b-2 border-dashed border-[#E6DCCF] pb-3">
            <h2 id="report-modal-title" class="font-pixel text-xs font-bold text-[#2D231E] uppercase tracking-wider">Report an issue</h2>
            <button
                type="button"
                data-report-close
                aria-label="Close"
                class="min-w-[44px] min-h-[44px] font-pixel text-xs font-bold text-[#2D231E] bg-white border-2 border-[#2D231E] rounded-xl flex items-center justify-center px-2 shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-[1px_1px_0px_0px_rgba(45,35,30,1)] focus:ring-2 focus:ring-amber-400 transition-all cursor-pointer"
            >[X]</button>
        </div>

        <form data-report-form class="space-y-4">
            <div>
                <label for="report-category" class="block font-pixel text-[8px] font-bold uppercase text-[#8C7B70] tracking-wider mb-2">Category</label>
                <select
                    id="report-category"
                    name="category"
                    required
                    class="w-full px-3 py-3 text-sm font-semibold bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl text-[#2D231E] focus:ring-2 focus:ring-amber-400 focus:outline-none cursor-pointer"
                >
                    <option value="">Select a category...</option>
                    <option value="typo">Typo or spelling error</option>
                    <option value="content">Content error</option>
                    <option value="other">Other issue</option>
                </select>
            </div>

            <div>
                <label for="report-description" class="block font-pixel text-[8px] font-bold uppercase text-[#8C7B70] tracking-wider mb-2">Description</label>
                <textarea
                    id="report-description"
                    name="description"
                    rows="4"
                    required
                    placeholder="Describe the issue you found..."
                    class="w-full px-3 py-3 text-sm font-semibold bg-[#FAF6EE] border-2 border-[#2D231E] rounded-xl text-[#2D231E] placeholder:text-[#8C7B70]/70 placeholder:font-normal focus:ring-2 focus:ring-amber-400 focus:outline-none resize-none"
                ></textarea>
            </div>

            <p data-report-error class="hidden text-xs font-bold text-rose-600"></p>

            <button
                type="submit"
                class="w-full min-h-[48px] bg-rose-600 hover:bg-rose-700 text-white font-pixel text-[11px] font-bold uppercase tracking-wider border-2 border-[#2D231E] rounded-xl shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-none transition-all cursor-pointer"
            >Submit report</button>
        </form>

        <div data-report-success hidden class="text-center py-4 space-y-3">
            <p class="font-pixel text-sm font-bold text-emerald-600 uppercase">Report sent!</p>
            <p class="text-sm font-semibold text-[#2D231E]">Thank you! Your report has been submitted.</p>
            <button
                type="button"
                data-report-close
                class="min-h-[44px] px-6 bg-amber-400 hover:bg-amber-500 text-[#2D231E] font-pixel text-[10px] font-bold uppercase border-2 border-[#2D231E] rounded-xl shadow-[3px_3px_0px_0px_rgba(45,35,30,1)] active:translate-x-[2px] active:translate-y-[2px] active:shadow-none transition-all cursor-pointer"
            >Close</button>
        </div>
    </div>
</div>

{{-- Quick-action popover for a tapped word: save above, look up below. --}}
<div id="word-popup" hidden class="fixed z-50 w-[19rem] max-w-[calc(100vw-2rem)] bg-white border-4 border-[#2D231E] rounded-2xl shadow-[5px_5px_0px_0px_rgba(45,35,30,1)] overflow-hidden">
    <p class="hidden px-4 py-2 text-xs font-semibold text-[#8C7B70] leading-relaxed border-b-2 border-[#2D231E]" data-slot="status"></p>
    <div class="flex flex-col gap-1.5 p-1.5">
        <button type="button" data-slot="save" disabled class="relative min-h-[38px] rounded-lg inline-flex items-center justify-center gap-1.5 font-pixel text-[9px] font-bold uppercase bg-emerald-600 text-white hover:bg-emerald-700 disabled:bg-[#2D231E]/20 disabled:text-[#2D231E]/50 transition-colors cursor-pointer">
            <span class="w-3.5 h-3.5 [&>svg]:h-full [&>svg]:w-full">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'bookmark') !!}</span>
            <span data-slot="save-label">Save</span>
        </button>
        <button type="button" data-slot="dictionary" class="min-h-[38px] rounded-lg inline-flex items-center justify-center gap-1.5 font-pixel text-[9px] font-bold uppercase bg-white hover:bg-amber-100 disabled:bg-[#2D231E]/20 disabled:text-[#2D231E]/50 transition-colors cursor-pointer">
            <span class="w-3.5 h-3.5 text-[#2D231E]/60 [&>svg]:h-full [&>svg]:w-full">{!! \App\Support\LibraryPresentation::iconGlyph('ui', 'open-book') !!}</span>
            <span>Look up</span>
        </button>
    </div>
</div>
@endsection
