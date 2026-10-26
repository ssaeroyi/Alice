/**
 * ALICE minimal skeleton helper (FRONTEND_GUIDELINES "skeleton loading states").
 *
 * Contract:
 *  - A skeleton may appear only after SHOW_DEBOUNCE_MS so fast local responses
 *    never flash one.
 *  - Every settle path (success / abort / catch) MUST call the returned
 *    settle(token); a stuck skeleton is a bug.
 *  - Tokens guard out-of-order AJAX: settling with a token that no longer owns
 *    the skeleton is a no-op, so a late stale response can't clear a newer one.
 *  - Skeletons are aria-hidden; the hosting region owns role="status" + sr-only
 *    copy so screen readers announce exactly once.
 */

export const SHOW_DEBOUNCE_MS = 150;

let pendingTimer = 0;

/**
 * Begin a busy skeleton over `region`, owned by `token`. The skeleton markup is
 * drawn after a short debounce so fast round-trips don't flash it. Returns
 * settle(token). If this region already has a newer skeleton owner, the call is
 * a no-op (the latest request always wins).
 */
export function busy(region, token) {
    if (!region || region.dataset.aliceSkel) {
        return () => {};
    }

    region.dataset.aliceToken = String(token);

    const draw = () => {
        if (region.dataset.aliceToken !== String(token)) {
            return;
        }
        region.setAttribute('aria-busy', 'true');
        region.dataset.aliceSkel = '1';
        region.innerHTML =
            '<div role="status" class="sr-only">Loading…</div>' +
            '<div aria-hidden="true" class="space-y-3">' +
            Array.from({ length: 4 }, () => '<span class="alice-skeleton block w-full h-16 rounded-xl"></span>').join('') +
            '</div>';
    };

    window.clearTimeout(pendingTimer);
    pendingTimer = window.setTimeout(draw, SHOW_DEBOUNCE_MS);

    // settle([finishToken]): only the current owner may tear the skeleton down.
    // Calling with its own token (or without one) clears; a mismatched token
    // (a superseded request settling late) is a safe no-op.
    return (finishToken) => {
        if (finishToken !== undefined && region.dataset.aliceToken !== String(finishToken)) {
            return;
        }
        window.clearTimeout(pendingTimer);
        delete region.dataset.aliceToken;
        delete region.dataset.aliceSkel;
        region.removeAttribute('aria-busy');
    };
}