/**
 * ALICE arcade engine — client-side game-feel scoring + feedback.
 *
 * Computes the 0-100 final score each mini-game reports to /game/submit
 * from three ingredients: accuracy, speed (time left) and combo, minus a
 * wrong-answer penalty. RewardService / the server economy are untouched —
 * this is purely the "feel" layer.
 *
 * Visual feedback uses the vendored Kenney CC0 sprites below
 * (public/image/game/kenney/) resolved through the <meta name="asset-base">
 * tag injected by layouts/authenticated.
 */

const SPRITES = {
    star: 'star_black.png',
    coin: 'coin_black.png',
    cross: 'cross_black.png',
    trophy: 'trophy_black.png',
};

// Kenney ships a *_white.png pair for the lit glyphs. In dark mode the black
// sprites vanish on the near-black HUD, so use the white variant for the glyphs
// that have one (cross has no white counterpart and stays black).
const WHITE_SPRITES = {
    star: 'star_white.png',
    coin: 'coin_white.png',
    trophy: 'trophy_white.png',
};

function darkGlpyh(kind) {
    const name = kind in SPRITES ? kind : 'star';
    const dark = document.documentElement.classList.contains('dark');
    return (dark ? WHITE_SPRITES[name] : null) || SPRITES[name];
}

function clamp01(v) {
    return Math.max(0, Math.min(1, v));
}

function assetPath(file) {
    const base = (document.querySelector('meta[name="asset-base"]')?.content || '').replace(/\/+$/, '');
    return base ? `${base}/image/game/kenney/${file}` : `/image/game/kenney/${file}`;
}

/**
 * Final 0-100 score.
 *
 * @param {object} p
 * @param {number} p.accuracy    0..1  found / matched / correct ratio
 * @param {number} p.timeFraction 0..1  seconds left at end / total seconds
 * @param {number} p.maxCombo     0..   longest correct streak this round
 * @param {number} p.wrongFraction 0..1  wrong attempts relative to round size
 */
export function computeFinalScore({ accuracy, timeFraction, maxCombo = 0, wrongFraction = 0 }) {
    const accuracyPts = 60 * clamp01(accuracy);
    const speedPts = 20 * clamp01(timeFraction);
    // A five-streak grants the full combo pool; longer streaks stay capped.
    const comboPts = 20 * clamp01((Math.max(0, maxCombo) - 1) / 4);
    const wrongPts = 20 * clamp01(wrongFraction);
    return Math.max(0, Math.min(100, Math.round(accuracyPts + speedPts + comboPts - wrongPts)));
}

/**
 * Pop a small sprite burst (Kenney glyphs) at an element's position.
 * Pure decoration; safe on all targets, honors prefers-reduced-motion.
 *
 * @param {HTMLElement} anchor  reference element
 * @param {'star'|'coin'|'cross'|'trophy'} kind
 * @param {number} amount  how many sprites to pop (default 6)
 */
export function burst(anchor, kind = 'star', amount = 6) {
    if (!anchor || !anchor.getBoundingClientRect) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const rect = anchor.getBoundingClientRect();
    const cx = rect.left + rect.width / 2;
    const cy = rect.top + rect.height / 2;

    for (let i = 0; i < amount; i++) {
        const img = document.createElement('img');
        img.src = assetPath(darkGlpyh(kind));
        img.alt = '';
        img.className = 'game-burst-sprite';
        img.style.left = `${cx}px`;
        img.style.top = `${cy}px`;
        img.style.setProperty('--bx', `${Math.round((Math.random() * 140) - 70)}px`);
        img.style.setProperty('--by', `${Math.round(-30 - Math.random() * 90)}px`);
        document.body.appendChild(img);
        img.addEventListener('animationend', () => img.remove(), { once: true });
    }
}

/**
 * Render/refresh a row of star pips into `container` showing how far the
 * current streak goes. `litCombo` stars are filled in amber, the rest dim.
 *
 * @param {HTMLElement} container  target element to hold the pips
 * @param {number} litCombo        current combo value
 * @param {number} total           how many pips to show (default 5)
 */
export function pips(container, litCombo = 0, total = 5) {
    if (!container) return;
    container.classList.remove('hidden');
    container.style.removeProperty('display');
    container.setAttribute('data-combo', litCombo);
    container.innerHTML = '';
    for (let i = 0; i < total; i++) {
        const img = document.createElement('img');
        img.src = assetPath(darkGlpyh('star'));
        img.alt = '';
        img.className = `game-pip${i < litCombo ? ' game-pip-on' : ''}`;
        if (i === litCombo - 1) {
            img.style.animation = 'game-pip-pop 0.25s ease';
        }
        container.appendChild(img);
    }

    syncComboReadout(litCombo);
}

/**
 * Hide the pip row (used on a wrong answer / when the combo resets).
 */
export function clearPips(container) {
    if (!container) return;
    container.classList.add('hidden');
    container.removeAttribute('data-combo');

    const readout = document.querySelector('[data-combo-readout]');
    if (readout) {
        readout.classList.add('hidden');
        readout.classList.remove('game-combo-max');
        const tag = readout.querySelector('[data-combo-max-tag]');
        if (tag) tag.hidden = true;
    }
}

/**
 * The star meter caps at 5 pips (a "full meter"). Once the streak passes that
 * cap the meter can no longer show growth, so the numeric "COMBO ×N" readout
 * becomes the primary signal: it keeps counting and gains a rose "MAX" state
 * (visible overdrive) so a 6+, 7+ streak reads as escalation, not a repeat.
 */
const COMBO_METER_CAP = 5;

/** Reflects the current streak as a "COMBO ×N" readout (hidden below 2). */
function syncComboReadout(litCombo) {
    const readout = document.querySelector('[data-combo-readout]');
    if (!readout) return;

    if (litCombo >= 2) {
        const num = readout.querySelector('b');
        if (num) {
            num.textContent = `×${litCombo}`;
        }
        const over = litCombo > COMBO_METER_CAP;
        readout.classList.toggle('game-combo-max', over);
        const tag = readout.querySelector('[data-combo-max-tag]');
        if (tag) {
            tag.hidden = !over;
            tag.textContent = `MAX ×${litCombo}`;
        }
        readout.classList.remove('hidden');
    } else {
        readout.classList.add('hidden');
        readout.classList.remove('game-combo-max');
        const tag = readout.querySelector('[data-combo-max-tag]');
        if (tag) tag.hidden = true;
    }
}