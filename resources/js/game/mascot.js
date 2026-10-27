/**
 * Reading mascot — Alice, the little schoolgirl who lives in the reader's
 * control bar.
 *
 * She is a sprite-sheet player: real drawn frames from
 * /assets/mascot/{idle,walk,attack}.png (CraftPix Schoolgirl 1), stepped by
 * a rAF loop so timing stays honest when the tab hitches. Idle loops
 * forever; walk/attack are one-shot reactions that always settle back to
 * idle, so word saves feel alive without the mascot ever getting stuck
 * mid-pose. She animates regardless of the OS reduced-motion setting (the
 * reader treats her as a companion, not a UI transition).
 *
 * Decorative only: pointer-events none, aria-hidden in the markup.
 */

/* Stock schoolgirl sheets; replaced by the equipped skin (data-skin on the
   element / alice:equip events) when the wardrobe feature is available. */
let SHEETS = {
    idle: { src: '/assets/mascot/idle.png', frames: 6, fps: 8, loop: true },
    walk: { src: '/assets/mascot/walk.png', frames: 6, fps: 10, loop: false },
    attack: { src: '/assets/mascot/attack.png', frames: 4, fps: 12, loop: false },
};

const HOLD_MS = 900;

let els = [];
let anim = 'idle';
let frame = 0;
let lastTick = 0;
let holdUntil = 0;
let rafId = 0;

function paint() {
    const sheet = SHEETS[anim];
    els.forEach((el) => {
        el.style.backgroundImage = `url(${sheet.src})`;
        el.style.backgroundSize = `${sheet.frames * 100}% 100%`;
        // Percentage-position across an N-frame sheet: 0% = frame 0, 100% = last.
        const pct = sheet.frames > 1 ? (frame / (sheet.frames - 1)) * 100 : 0;
        el.style.backgroundPosition = `${pct}% center`;
    });
}

function tick(now) {
    rafId = requestAnimationFrame(tick);

    const sheet = SHEETS[anim];
    const ms = 1000 / sheet.fps;

    if (now - lastTick < ms) return;
    lastTick = now;

    if (!sheet.loop) {
        if (frame >= sheet.frames - 1 && now >= holdUntil) {
            setIdle();
            return;
        }
        if (frame < sheet.frames - 1) frame += 1;
        paint();
        return;
    }

    frame = (frame + 1) % sheet.frames;
    paint();
}

function setIdle() {
    anim = 'idle';
    frame = 0;
    paint();
}

/** Hot-swap to an equipped skin (config/skins.php alice entry slice). */
function applySkin(skin) {
    if (!skin?.anims?.idle) return;
    SHEETS = {
        idle: skin.anims.idle,
        walk: skin.anims.walk ?? skin.anims.idle,
        attack: skin.anims.attack ?? skin.anims.idle,
    };
    els.forEach((el) => {
        if (skin.mascot) {
            el.style.setProperty('--mw', `${skin.mascot.w}px`);
            el.style.setProperty('--mh', `${skin.mascot.h}px`);
        }
    });
    anim = 'idle';
    frame = 0;
    if (els.length) paint();
}

/** Find every mascot mount and start the idle loop. Safe to call once per page. */
export function initMascot() {
    els = [...document.querySelectorAll('[data-mascot]')];
    if (!els.length) return;

    try {
        const skin = JSON.parse(els[0].dataset.skin ?? 'null');
        if (skin) applySkin(skin);
    } catch {
        // keep stock sheets
    }

    // The Field Journal wardrobe equips live: swap without a reload.
    document.addEventListener('alice:equip', (event) => {
        if (!els.length) return;
        applySkin(event.detail?.skin);
    });

    paint();

    lastTick = performance.now();
    cancelAnimationFrame(rafId);
    rafId = requestAnimationFrame(tick);
}

/**
 * One-shot reaction: 'walk' or 'attack' (word saved). Ignored while the
 * same reaction is already playing, so rapid events never stack. Always
 * settles back to idle.
 */
export function mascotReact(name) {
    if (!els.length || !SHEETS[name] || SHEETS[name].loop) return;
    if (anim === name) return;

    anim = name;
    frame = 0;
    holdUntil = performance.now() + HOLD_MS;
    paint();
}
