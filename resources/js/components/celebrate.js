/**
 * FX celebration sprites (2026-10-05 asset round - Phase 4).
 *
 * Plays a one-shot pixel-smoke burst (public/image/game/fx/*.png, replayed
 * by the .fx-sprite keyframes in resources/css/app.css) over an anchor
 * element or the viewport centre.
 *
 * Two ways to fire one:
 *   - HTML: tag a server-rendered element with data-celebrate="reward" |
 *           "levelup" | "win". Scanned once at init and again for nodes
 *           added later (MutationObserver). Optional
 *           data-celebrate-guard="name" plays at most once per browser
 *           session per day (sessionStorage, date-stamped) - for content
 *           that re-renders on every page load, e.g. hub goal chips.
 *   - JS:   window.Celebrate('win', element) - used by the arcade games at
 *           endGame(). Safe to call anywhere: honours
 *           prefers-reduced-motion, coalesces overlapping bursts into a
 *           short queue, and never throws when the bundle races.
 *
 * The sprite element is aria-hidden and pointer-events:none; it removes
 * itself on animationend (with a timeout backstop).
 */

const KINDS = {
    reward: 'fx-sprite--reward',
    levelup: 'fx-sprite--levelup',
    win: 'fx-sprite--win',
};

const MAX_QUEUE = 2;

/** Currently playing bursts (used to serialize the queue). */
let active = 0;

/** Pending [kind, anchor] pairs waiting for a slot. */
const queue = [];

function reducedMotion() {
    return typeof window !== 'undefined'
        && window.matchMedia
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function anchorPoint(anchor) {
    const fallback = () => ({
        x: (typeof window !== 'undefined' ? window.innerWidth : 0) / 2,
        y: (typeof window !== 'undefined' ? window.innerHeight : 0) / 2,
    });

    if (anchor && typeof anchor.getBoundingClientRect === 'function') {
        const rect = anchor.getBoundingClientRect();
        if (rect.width > 0 || rect.height > 0) {
            return { x: rect.left + rect.width / 2, y: rect.top + rect.height / 2 };
        }
    }

    if (anchor && typeof anchor.x === 'number' && typeof anchor.y === 'number') {
        return { x: anchor.x, y: anchor.y };
    }

    return fallback();
}

function spawn(kind, anchor) {
    const el = document.createElement('div');
    el.className = `fx-sprite ${KINDS[kind]}`;
    el.setAttribute('aria-hidden', 'true');

    const point = anchorPoint(anchor);
    el.style.left = `${Math.round(point.x)}px`;
    el.style.top = `${Math.round(point.y)}px`;

    let finished = false;
    const finish = () => {
        if (finished) {
            return;
        }
        finished = true;
        active = Math.max(0, active - 1);
        el.remove();
        pump();
    };

    active += 1;
    el.addEventListener('animationend', finish, { once: true });
    // Backstop: animationend never fires if the element is hidden (e.g.
    // reduced motion kicked in after spawn, or the tab was backgrounded).
    setTimeout(finish, 2000);

    document.body.appendChild(el);
}

function pump() {
    if (active > 0 || queue.length === 0) {
        return;
    }
    const [kind, anchor] = queue.shift();
    spawn(kind, anchor);
}

/**
 * Play a celebration burst. `anchor` may be an element, a {x,y} point, or
 * omitted (viewport centre). Returns true when accepted (playing or queued).
 */
export function celebrate(kind, anchor) {
    if (!KINDS[kind] || reducedMotion() || !document.body) {
        return false;
    }
    if (active > 0) {
        if (queue.length >= MAX_QUEUE) {
            return false;
        }
        queue.push([kind, anchor]);
        return true;
    }
    spawn(kind, anchor);
    return true;
}

function playTagged(el) {
    if (!el || el.nodeType !== 1 || el.hasAttribute('data-fx-played')) {
        return;
    }
    const kind = el.getAttribute('data-celebrate');
    if (!kind || !KINDS[kind]) {
        return;
    }

    const guard = el.getAttribute('data-celebrate-guard');
    if (guard) {
        const stamp = new Date().toISOString().slice(0, 10);
        const key = `fx:${guard}:${stamp}`;
        try {
            if (sessionStorage.getItem(key)) {
                // Already celebrated today in this session: mark done so we
                // do not re-check every mutation pass.
                el.setAttribute('data-fx-played', '1');
                return;
            }
            sessionStorage.setItem(key, '1');
        } catch {
            // Storage unavailable (private mode) - play anyway, once per node.
        }
    }

    el.setAttribute('data-fx-played', '1');
    celebrate(kind, el);
}

function scan(root) {
    if (!root || root.nodeType !== 1) {
        return;
    }
    if (root.hasAttribute && root.hasAttribute('data-celebrate')) {
        playTagged(root);
    }
    if (typeof root.querySelectorAll === 'function') {
        root.querySelectorAll('[data-celebrate]').forEach(playTagged);
    }
}

export function initCelebrate() {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return;
    }

    // JS call sites (arcade games) go through the global so they do not
    // need to import this module from their own chunks.
    window.Celebrate = celebrate;

    scan(document);

    if (typeof MutationObserver === 'function' && document.body) {
        const observer = new MutationObserver((records) => {
            for (const record of records) {
                for (const node of record.addedNodes) {
                    scan(node);
                }
            }
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }
}

export default initCelebrate;
