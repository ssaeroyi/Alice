/**
 * Toast notifications (S4).
 *
 * Two entry points, one rendered look:
 *
 *  - `initToasts(root)`  — server flash toast: binds auto-dismiss + manual
 *    close to a `[data-toast]` rendered by components/toast.blade.php (one-shot,
 *    per page load).
 *  - `showToast(...)`    — runtime toast: builds a `[data-toast]` card on the
 *    fly so any page can raise a notification at the moment something happens
 *    (e.g. a streak freeze shielding a missed day). Falls back to the DOM-ready
 *    flash toast's visual language. Mirrors its accessibility (role=status,
 *    aria-live=polite), 4s auto-dismiss, manual close, and reduced-motion
 *    handling. Newest toast replaces the current one when raised while visible.
 *
 * Tones ride the same palette reuse as the gamification accents, so the look
 * stays consistent with config-driven colours.
 */

const TONES = {
    freeze: {
        border: 'border-sky-400',
        bg: 'bg-sky-50',
        shadow: 'rgba(2,132,199,0.35)',
        iconBg: 'bg-sky-500',
        icon: 'M12 2c.5 2.5-1.5 4-1.5 4s3 .5 3 3c0 .9-.4 1.6-1 2.1m-1 11.9c-2.5-.5-4-2.5-4-4s1.5-3.5 4-4m0 0c-1.9.5-3 1.9-3 3.5s1.1 3 3 3.5m0-7c1.9.5 3 1.9 3 3.5s-1.1 3-3 3.5M12 11v2',
        sfx: 'freeze',
    },
    success: {
        border: 'border-emerald-500',
        bg: 'bg-emerald-50',
        shadow: 'rgba(16,185,129,0.35)',
        iconBg: 'bg-emerald-500',
        icon: 'M5 13l4 4L19 7',
        sfx: null,
    },
    task: {
        border: 'border-violet-500',
        bg: 'bg-violet-50',
        shadow: 'rgba(139,92,246,0.35)',
        iconBg: 'bg-violet-500',
        icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        sfx: null,
    },
    story: {
        border: 'border-rose-400',
        bg: 'bg-rose-50',
        shadow: 'rgba(244,63,94,0.35)',
        iconBg: 'bg-rose-500',
        icon: 'M12 6.25c-2.5-1.8-6-1.8-8.5 0V19a6.5 6.5 0 018.5 0 6.5 6.5 0 018.5-0V6.25c-2.5-1.8-6-1.8-8.5 0z',
        sfx: null,
    },
    stamp: {
        border: 'border-amber-500',
        bg: 'bg-amber-50',
        shadow: 'rgba(245,158,11,0.35)',
        iconBg: 'bg-amber-500',
        icon: 'M5 3h14a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1V4a1 1 0 011-1zm0 8h10a1 1 0 010 2H5a1 1 0 010-2zm0 4h10a1 1 0 010 2H5a1 1 0 010-2zm13 0h1m-1 4h1',
        sfx: null,
    },
};

function reduceMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function buildToast(message, tone) {
    const t = TONES[tone] ?? TONES.freeze;

    const root = document.createElement('div');
    root.className = 'fixed inset-x-0 bottom-20 sm:inset-x-auto sm:bottom-6 sm:right-6 z-[100] px-4 sm:px-0 pointer-events-none';
    root.dataset.toast = '';
    root.setAttribute('role', 'status');
    root.setAttribute('aria-live', 'polite');
    root.innerHTML = `
        <div class="mx-auto sm:mx-0 sm:max-w-sm pointer-events-auto ${t.border} ${t.bg} border-2 rounded-2xl px-4 py-3 shadow-[4px_4px_0px_0px_${t.shadow}] flex items-center gap-3">
            <span class="shrink-0 w-6 h-6 rounded-full ${t.iconBg} text-white flex items-center justify-center" aria-hidden="true">
                <svg class="w-4 h-4 stroke-current" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="${t.icon}"/>
                </svg>
            </span>
            <p class="flex-1 text-xs font-bold text-[#2D231E] leading-relaxed"></p>
            <button type="button" data-toast-close aria-label="Dismiss notification" class="shrink-0 min-h-[44px] min-w-[44px] -my-1 -mr-2 flex items-center justify-center text-[#2D231E] hover:text-[#2D231E] transition-colors">
                <svg class="w-4 h-4 stroke-current" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 6l12 12M18 6L6 18"/>
                </svg>
            </button>
        </div>
    `;
    root.querySelector('p').textContent = message;

    return { root, tone: t.sfx };
}

/**
 * Raise a runtime toast.
 *
 * @param {string}   message
 * @param {object}   [options]
 * @param {string}   [options.tone='freeze']  'freeze' (sky/snowflake), 'success' (emerald),
 *                   'task' (violet), 'story' (rose), 'stamp' (amber).
 * @param {number}   [options.duration=4000]  Auto-dismiss delay in ms.
 * @param {boolean}  [options.sfx=true]       Play the tone's audio cue (mute/reduced-motion aware).
 */
export function showToast(message, { tone = 'freeze', duration = 4000, sfx = true } = {}) {
    if (!message) return;

    const existing = document.querySelector('[data-toast]');
    if (existing) existing.remove();

    const { root, tone: sfxCue } = buildToast(message, tone);
    document.body.appendChild(root);

    const dismiss = () => {
        if (reduceMotion()) {
            root.remove();
            return;
        }
        root.classList.add('toast-hide');
        root.addEventListener('transitionend', () => root.remove(), { once: true });
    };

    root.querySelector('[data-toast-close]').addEventListener('click', () => {
        clearTimeout(timer);
        dismiss();
    });

    const timer = window.setTimeout(dismiss, duration);

    if (sfx && sfxCue && window.SFX?.play) {
        window.SFX.play(sfxCue);
    }
}

export function initToasts(root = document) {
    const toast = root.querySelector('[data-toast]');

    if (!toast) {
        return;
    }

    const dismiss = () => {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            toast.remove();

            return;
        }

        toast.classList.add('toast-hide');
        toast.addEventListener('transitionend', () => toast.remove(), { once: true });
    };

    root.querySelector('[data-toast-close]')?.addEventListener('click', dismiss);
    window.setTimeout(dismiss, 4000);
}
