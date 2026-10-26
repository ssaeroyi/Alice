@php
    // Single top-center "game banner" notification slot (notification taxonomy).
    // Renders at most ONE banner per page load from the highest-priority flash
    // that is present, so reward / badge / greeting never stack.
    //
    //   reward   (emerald)  — earned keys/points (task claim, quiz/game bank)
    //   badge    (rarity)   — badge unlocked
    //   assignment (sky)    — an assignment just became complete (schoolwork)
    //   greeting (amber)    — welcome back / new account (shown on the hub)
    //
    // Visual feedback ("+N keys") is rendered unconditionally; audio is
    // best-effort behind the existing mute + reduced-motion guards (browser
    // autoplay blocks sound on a fresh POST-redirect page, so the cue fires on
    // the student's first interaction once).

    $tcKeys = (int) (session('task_claim')['keys'] ?? 0);
    $tcPoints = (int) (session('task_claim')['points'] ?? 0);

    // Badge flash is a plain-array summary (see BadgeService::summarize), but
    // tolerate any legacy shape (Collection / array / null) so a stale session
    // value can never crash the banner with "Call to a member function ...".
    $badges = collect(session('badges_awarded'));
    $badge = $badges->first();
    $badgeMore = max(0, $badges->count() - 1);
    $badgeName = $badge ? (is_array($badge) ? ($badge['name'] ?? '') : ($badge->name ?? '')) : '';
    $badgeGlyph = $badge ? str_replace('ui/', '', (string) (is_array($badge) ? ($badge['asset_path'] ?? '') : ($badge->asset_path ?? ''))) : '';
    $badgeRarityName = $badge ? (is_array($badge) ? ($badge['rarity'] ?? 'common') : ($badge->rarity->value ?? 'common')) : 'common';
    $badgeRarity = $badge
        ? config('badges.rarity.'.$badgeRarityName, ['text' => 'text-stone-700', 'bg' => 'bg-stone-100', 'border' => 'border-stone-400'])
        : null;

    $welcome = session('welcome');

    // Assignment-complete flash shape: ['title' => ..., 'story_id' => ...]
    // (see ReaderController::finish and QuizController::submit). Shown on the
    // reader summary / quiz results / assignment detail PRG target once.
    $assignmentComplete = session('assignment_complete');

    $tone = 'none';
    $icon = 'trophy';
    $eyebrow = '';
    $title = '';
    $sub = '';
    $cue = '';

    if ($tcKeys > 0 || $tcPoints > 0) {
        $tone = 'reward';
        $icon = 'key';
        $eyebrow = 'Task complete!';
        $title = '+'.$tcKeys.' keys and +'.$tcPoints.' points';
        $sub = '';
        $cue = 'claim';
    } elseif ($badge) {
        $tone = 'badge';
        $icon = $badgeGlyph;
        $eyebrow = 'Badge unlocked!';
        $title = $badgeName.($badgeMore > 0 ? ' +'.$badgeMore.' more' : '');
        $sub = '';
        $cue = 'badge';
    } elseif ($assignmentComplete) {
        $tone = 'assignment';
        $icon = 'book-aura';
        $eyebrow = 'Assignment complete!';
        $title = $assignmentComplete['title'] ?? '';
        $sub = 'You completed it.';
        $cue = 'badge';
    } elseif ($welcome) {
        $tone = 'greeting';
        $icon = 'crown';
        $eyebrow = $welcome['eyebrow'] ?? 'Welcome';
        $title = $welcome['title'] ?? 'Welcome to ALICE!';
        $sub = $welcome['sub'] ?? '';
        $cue = 'select';
    }

    $toneClasses = match ($tone) {
        'reward' => 'bg-emerald-600',
        'badge' => 'bg-white',
        'assignment' => 'bg-sky-600',
        'greeting' => 'bg-amber-500',
        default => '',
    };
    $iconWrap = match ($tone) {
        'reward' => 'bg-white border-[#2D231E] text-emerald-600',
        'badge' => ($badgeRarity['bg'] ?? 'bg-stone-100').' border-[#2D231E] '.($badgeRarity['text'] ?? 'text-stone-700'),
        'assignment' => 'bg-white border-[#2D231E] text-sky-600',
        'greeting' => 'bg-white border-[#2D231E] text-[#2D231E]',
        default => '',
    };
    $textColor = match ($tone) {
        'badge' => 'text-[#2D231E]',
        default => 'text-white',
    };
    $titleColor = match ($tone) {
        'badge' => 'text-[#2D231E]',
        default => 'text-white',
    };
@endphp

@if ($tone !== 'none')
    <div data-game-banner role="status" aria-live="polite"{{ $tone === 'reward' ? ' data-celebrate="reward"' : '' }}
         class="fixed top-20 sm:top-24 left-0 right-0 z-[60] flex justify-center px-4 pointer-events-none">
        <div class="pointer-events-auto w-full max-w-sm {{ $toneClasses }} {{ $tone === 'badge' ? ($badgeRarity['border'] ?? 'border-stone-400') : 'border-[#2D231E]' }} border-4 rounded-2xl shadow-[6px_6px_0px_0px_rgba(45,35,30,1)] p-4 {{ $textColor }}">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 shrink-0 {{ $iconWrap }} border-2 rounded-xl flex items-center justify-center [&>svg]:h-6 [&>svg]:w-6">
                    {!! \App\Support\LibraryPresentation::iconGlyph('ui', $icon) !!}
                </span>
                <div class="min-w-0 flex-1">
                    <p class="font-pixel text-[11px] font-bold uppercase tracking-wider">{{ $eyebrow }}</p>
                    <p class="text-xs font-bold mt-0.5 {{ $titleColor }}">{{ $title }}</p>
                    @if ($sub)
                        <p class="text-[11px] font-semibold mt-0.5 opacity-90">{{ $sub }}</p>
                    @endif
                </div>
                <button type="button" data-game-banner-close aria-label="Dismiss notification"
                        class="font-semibold shrink-0 min-h-[44px] min-w-[44px] -my-1 -mr-2 flex items-center justify-center {{ $tone === 'badge' ? 'text-[#2D231E] hover:text-[#2D231E]' : 'text-white/80 hover:text-white' }} transition-colors">
                    <svg class="w-4 h-4 stroke-current" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 6l12 12M18 6L6 18"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <style>
        @keyframes game-banner-in {
            from { opacity: 0; transform: translateY(-12px); }
            to { opacity: 1; transform: translateY(0); }
        }
        [data-game-banner] {
            animation: game-banner-in 0.4s ease-out;
            transition: opacity 0.25s ease, transform 0.25s ease;
        }
        [data-game-banner].toast-hide {
            opacity: 0;
            transform: translateY(-12px);
            pointer-events: none;
        }
    </style>

    <script>
        (function () {
            var banner = document.querySelector('[data-game-banner]');
            if (!banner) return;
            var dismissed = false;
            function dismiss() {
                if (dismissed) return;
                dismissed = true;
                if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                    banner.remove();
                } else {
                    banner.classList.add('toast-hide');
                    banner.addEventListener('transitionend', function () { banner.remove(); }, { once: true });
                }
            }
            banner.querySelector('[data-game-banner-close]')?.addEventListener('click', dismiss);
            window.setTimeout(dismiss, 5000);
            // Audio is best-effort: the browser may block it until the first
            // user gesture (autoplay policy on a POST-redirect page). Fire once
            // on the first interaction so the reward is heard without double-cueing.
            var played = false;
            function fire() {
                if (played || dismissed) return;
                played = true;
                if (window.SFX && typeof window.SFX.play === 'function') {
                    window.SFX.play({!! Js::from($cue) !!});
                }
            }
            document.addEventListener('pointerdown', fire, { once: true, passive: true });
            document.addEventListener('keydown', fire, { once: true });
        })();
    </script>
@endif
