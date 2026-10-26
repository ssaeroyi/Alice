/**
 * Game juice — lightweight animation helpers.
 * Pure CSS animations — no anime.js dependency.
 * Respects prefers-reduced-motion.
 */

const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/**
 * Score popup float.
 * @param {boolean} force - bypass prefers-reduced-motion AND escape the
 *   caller's overflow clipping. The reader's word popup has overflow:hidden,
 *   which would clip a float rising past its top edge; forcing pins the float
 *   to the viewport (fixed) just above the anchor so it stays fully visible.
 *   Games keep the normal in-place float.
 */
export function scorePopup(el, text, color = '#10B981', force = false) {
    if (prefersReduced && !force) return;

    const popup = document.createElement('div');
    popup.textContent = text;
    popup.className = 'game-score-popup' + (force ? ' game-score-popup-forced' : '');
    popup.style.color = color;

    if (force) {
        // Anchor to the viewport above the target element so the popup's
        // overflow:hidden cannot clip it. Centered horizontally (the forced
        // CSS pairs this with translateX(-50%) so the float-up animation
        // keeps the horizontal centering).
        const rect = el.getBoundingClientRect();
        popup.style.position = 'fixed';
        popup.style.left = `${Math.round(rect.left + rect.width / 2)}px`;
        popup.style.top = `${Math.round(rect.top - 36)}px`;
        document.body.appendChild(popup);
    } else {
        el.appendChild(popup);
    }

    requestAnimationFrame(() => {
        popup.classList.add('game-score-popup-active');
    });

    setTimeout(() => popup.remove(), 800);
}

export function shake(el) {
    if (prefersReduced) return;
    el.classList.add('game-shake');
    setTimeout(() => el.classList.remove('game-shake'), 400);
}

export function flash(el, color = 'emerald') {
    if (prefersReduced) return;
    const cls = `game-flash-${color}`;
    el.classList.add(cls);
    setTimeout(() => el.classList.remove(cls), 500);
}

export function comboFlash(el, combo) {
    if (prefersReduced || combo < 2) return;
    el.classList.add('game-combo');
    el.dataset.combo = combo;
    setTimeout(() => el.classList.remove('game-combo'), 600);
}
