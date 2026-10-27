/**
 * ALICE "Meet Alice" autopilot tour (FRONTEND_GUIDELINES "Meet Alice tour"
 * note, SYSTEM_DESIGN §45). Student-area only. Drives a scripted, narratable
 * spotlight walk of the app: an RPG-style dialogue box fixed at the viewport
 * bottom + a semi-blocking dim shield with a pulsing ring on the target.
 *
 * - Trigger: the Help modal's GET STARTED button ([data-tour-start]) writes a
 *   step index to sessionStorage and loads /hub.
 * - Sessions are per-tab (sessionStorage), auto-resume where left off; an
 *   unexpected URL shows a gentle "Tour paused" with Resume / End instead of a
 *   redirect fight.
 * - QUIT (button or Escape) cleans up instantly, including restoring any
 *   raised target styles and closing leftover demo overlays.
 * - Semi-blocking: the dim shield swallows stray taps (with a hint line); the
 *   ringed target is temporarily raised above the shield so it stays tappable.
 *   Scripted taps always use element.click() (immune to the shield).
 * - Ending: the tour drops the student back on the Hub (the home base) with a
 *   farewell from Alice, then hides itself.
 * - Voice: captions-first. When public/audio/tour/manifest.json exists
 *   (generate_narration.py --tour, af_heart), Alice actually speaks: each
 *   say(text, key) pairs with {key}.mp3 whose real duration drives the
 *   typewriter speed (~90% of the clip to finish typing) and the hold. Voice
 *   is additive - missing files, a muted browser, or an autoplay block just
 *   fall back to the heuristic pacing, never stall the tour.
 * - Safety: tour only visits GET pages and toggles harmless UI (hub tabs,
 *   filter drawer, game help modal). It never submits quizzes/games/finish and
 *   never enters the reader (auto-scrolling its paper would trip the reading
 *   time heartbeat). Reduced motion: no glide, no typewriter, static ring.
 */

import { closeModal } from '../components/modal.js';
import { getMuted, onMuteChange } from '../game/sfx.js';

const KEY = 'alice.tour.v1';
const SHELL_ID = 'alice-tour';
const MANIFEST_URL = '/audio/tour/manifest.json';
const AREA_OK = () => document.body?.dataset?.area === 'student';

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const sleep = (ms) => new Promise((resolve) => window.setTimeout(resolve, ms));
const holdMs = (text) => Math.min(2600, Math.max(1400, text.length * 38));

const getState = () => {
    try { return JSON.parse(sessionStorage.getItem(KEY) || 'null'); } catch { return null; }
};
const setState = (state) => {
    try { sessionStorage.setItem(KEY, JSON.stringify(state)); } catch { /* noop */ }
};
const clearState = () => {
    try { sessionStorage.removeItem(KEY); } catch { /* noop */ }
};

const pick = (...selectors) => selectors.map((s) => document.querySelector(s)).find(Boolean) || null;

/* ------------------------------------------------------------------ *
 *  Tour script — one entry per stop. `run` orchestrates its own       *
 *  typewriter lines (await say(...)) and spotlights; the loop moves   *
 *  on automatically when run() resolves. The second arg of say() is    *
 *  the audio key (public/audio/tour/{key}.mp3), kept in sync with      *
 *  TOUR_LINES in tools/narration/generate_narration.py (--check).      *
 * ------------------------------------------------------------------ */

const STEPS = [
    {
        name: 'hub-intro',
        url: /^\/hub$/,
        canonical: '/hub',
        run: async () => {
            await say("Hi! I'm Alice. This is the Hub. It's your home base on ALICE.", 'hub-intro-0');
            await sleep(300);
        },
    },
    {
        name: 'hub-tabs',
        url: /^\/hub$/,
        canonical: '/hub',
        run: async () => {
            await say("These are your five tabs. Watch them light up as I tap them.", 'hub-tabs-0');
            for (const name of ['today', 'tasks', 'stats', 'badges', 'inbox']) {
                const tab = document.querySelector(`[data-hub-tab="${name}"]`);
                if (!tab || disposed) continue;
                await spot(tab);
                await sleep(360);
                if (disposed) return;
                tab.click();
                await unspot();
            }
            // Leave the address bar clean (the sweep visited every hash).
            history.replaceState(null, '', location.pathname + location.search);
        },
    },
    {
        name: 'classwork',
        url: /^\/classwork$/,
        canonical: '/classwork',
        run: async () => {
            await say("This is Classwork. Your teacher's assignments and your classes all live on this page.", 'classwork-0');
            await sleep(300);
        },
    },
    {
        name: 'hub-go',
        url: /^\/(hub|classwork)$/,
        canonical: '/hub',
        run: async () => {
            await say("Now let's find you a story. Off to the Library!", 'hub-go-0');
            await sleep(400);
        },
    },
    {
        name: 'library-shelves',
        url: /^\/library$/,
        canonical: '/library',
        run: async () => {
            await say("Stories here are sorted just for you. Search up top for a title or an author, or you can filter by a specific genre that you like.", 'library-shelves-0');
            const search = pick('input[name="q"]');
            if (search) {
                await spot(search);
                await sleep(420);
                await unspot();
            }
            const drawerOpen = pick('[data-filter-drawer-open]');
            if (drawerOpen) {
                await spot(drawerOpen);
                await sleep(350);
                if (!disposed) drawerOpen.click();
            }
            await sleep(500);
            shieldOff();
        },
    },
    {
        name: 'library-drawer',
        url: /^\/library$/,
        canonical: '/library',
        run: async () => {
            await say("These are the Filters. Feel free to explore what specific story you would like with the options here.", 'library-drawer-0');
            await sleep(750);
            const drawerClose = pick('[data-filter-drawer-close]');
            if (drawerClose) drawerClose.click();
            await sleep(450);
            shieldOn();
        },
    },
    {
        name: 'study',
        url: /^\/study$/,
        canonical: '/study',
        run: async () => {
            await say("This is your Study corner. You can look up words here with our dictionary, save them and practice them with our flashcard feature. Or you can practice saved words from your past readings.", 'study-0');
            const dict = pick('a[href*="dictionary"]');
            if (dict) {
                await spot(dict);
                await sleep(400);
                await unspot();
            }
            const flashcards = pick('section[data-caught-up]');
            if (flashcards) {
                await spot(flashcards);
                await sleep(450);
                await unspot();
            }
        },
    },
    {
        name: 'games',
        url: /^\/game$/,
        canonical: '/game',
        run: async () => {
            await say("Here is the mini game section. Currently we have three games which are Word Search, Memory and Sentence Builder.", 'games-0');
            const helpBtn = pick('#btn-help');
            if (helpBtn) {
                await spot(helpBtn);
                await sleep(400);
                if (!disposed) helpBtn.click();
                await sleep(500);
                shieldOff();
                await say("Each game has its own help button to help you understand how each game's mechanics work.", 'games-1');
                await sleep(750);
                const gameHelp = document.getElementById('game-help-modal');
                if (gameHelp) {
                    try { closeModal(gameHelp); } catch { /* noop */ }
                }
                await sleep(400);
                shieldOn();
            }
        },
    },
    {
        name: 'farewell',
        url: /^\/hub$/,
        canonical: '/hub',
        run: async () => {
            await say("That's the whole tour. You know your way around now.", 'farewell-0');
            await say("Tap Library up top to start a story and have fun. I'll be here any time you tap the question mark.", 'farewell-1');
            await sleep(1200);
        },
    },
];

/* ------------------------------------------------------------------ *
 *  Elements & wiring                                                   *
 * ------------------------------------------------------------------ */

let shell = null;
let shield = null;
let dim = null;
let ring = null;
let hint = null;
let box = null;
let textEl = null;
let arrow = null;
let progress = null;
let pausedRow = null;

let active = false;
let disposed = false;    // this page-level session is over (quit/end/error)
let typingToken = 0;
let typingStop = null;

const raisedStack = [];  // {el, position, zIndex} for targets lifted above the shield
let ringTarget = null;

function cacheElements() {
    shell = document.getElementById(SHELL_ID);
    if (!shell) return;
    shield = shell.querySelector('[data-tour="shield"]');
    dim = shell.querySelector('[data-tour="dim"]');
    ring = shell.querySelector('[data-tour="ring"]');
    hint = shell.querySelector('[data-tour="hint"]');
    box = shell.querySelector('[data-tour="box"]');
    textEl = shell.querySelector('[data-tour="text"]');
    arrow = shell.querySelector('[data-tour="arrow"]');
    progress = shell.querySelector('[data-tour="progress"]');
    pausedRow = shell.querySelector('[data-tour="paused"]');
}

function showShell() {
    shell.classList.remove('hidden');
    shell.setAttribute('aria-hidden', 'false');
}

function hideShell() {
    const focused = document.activeElement;
    if (focused && shell && shell.contains(focused)) focused.blur();
    shell.classList.add('hidden');
    shell.setAttribute('aria-hidden', 'true');
}

/* --------------------------- spotlight --------------------------- */

function computeRing(el) {
    const rect = el.getBoundingClientRect();
    ring.style.left = `${rect.left - 5}px`;
    ring.style.top = `${rect.top - 5}px`;
    ring.style.width = `${rect.width + 10}px`;
    ring.style.height = `${rect.height + 10}px`;
}

function ringLoop() {
    if (!ringTarget || disposed || ring.hidden) return;
    computeRing(ringTarget);
    window.requestAnimationFrame(ringLoop);
}

async function spot(el) {
    if (!el || disposed) return false;
    const computed = window.getComputedStyle(el);
    raisedStack.push({ el, position: computed.position, zIndex: computed.zIndex });
    if (computed.position === 'static') el.style.position = 'relative';
    el.style.zIndex = '70';
    try {
        el.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'center', inline: 'nearest' });
    } catch { /* noop */ }
    if (!reduceMotion) await sleep(90); // let the glide begin before first measure
    if (disposed) return false;
    ringTarget = el;
    ring.hidden = false;
    computeRing(el);
    ringLoop();
    return true;
}

function unspot() {
    for (const entry of raisedStack.splice(0)) {
        entry.el.style.position = entry.position;
        entry.el.style.zIndex = entry.zIndex === 'auto' ? '' : entry.zIndex;
    }
    ringTarget = null;
    ring.hidden = true;
}

/* --------------------------- shield --------------------------- */

function shieldOn() {
    dim.style.opacity = '1';
    shield.style.pointerEvents = 'auto';
}

function shieldOff() {
    dim.style.opacity = '0';
    shield.style.pointerEvents = 'none';
    unspot();
}

let hintTimer = 0;
function showHint() {
    hint.style.opacity = '1';
    window.clearTimeout(hintTimer);
    hintTimer = window.setTimeout(() => { hint.style.opacity = '0'; }, 950);
}

/* --------------------------- voice ---------------------------
   Optional. manifest.json is fetched once; if it is absent the tour simply
   runs captions with heuristic pacing. When present, each say() pairs with a
   clip and the typewriter is paced to the real audio duration so Alice's
   voice and her text land together. */

let voiceConfig = null;      // { voice, model, lines: { key: {mp3, duration_ms, ...} } }
// Tour-only voice mute (localStorage 'alice-tour-voice-mute'). Deliberately
// PRIVATE: muting Alice's tour narration must not touch the app-wide sound
// switch that games and the reader share (game/sfx.js owns that one).
let tourVoiceMuted = false;
let manifestPromise = null;  // cached fetch
let voiceAudio = null;       // one shared <audio>

function getVoiceAudio() {
    if (!voiceAudio) {
        voiceAudio = new Audio();
        voiceAudio.preload = 'auto';
    }
    return voiceAudio;
}

function stopVoice() {
    const audio = voiceAudio;
    if (audio) {
        audio.onended = null;
        audio.onerror = null;
        audio.pause();
        audio.currentTime = 0;
    }
}

function fetchVoiceManifest() {
    if (!manifestPromise) {
        manifestPromise = fetch(MANIFEST_URL, { cache: 'no-cache' })
            .then((res) => (res.ok ? res.json() : null))
            .catch(() => null)
            .then((cfg) => {
                voiceConfig = (cfg && typeof cfg.lines === 'object' && Object.keys(cfg.lines).length) ? cfg : null;
                if (!voiceConfig) return null;
                // Prime the first clip so later plays start fast.
                const first = voiceConfig.lines[Object.keys(voiceConfig.lines)[0]];
                try { getVoiceAudio().src = voiceUrl(first); getVoiceAudio().load(); } catch { /* noop */ }
                renderVoiceButton();
                return voiceConfig;
            });
    }
    return manifestPromise;
}

/** Manifest {key}.mp3 values are bare filenames; always resolve against the
 *  static asset root so a relative URL can't resolve against /hub or /library. */
function voiceUrl(meta) {
    return '/audio/tour/' + meta.mp3;
}

/** The tour's private voice mute: shown when tour audio ships. Toggling it
 *  touches ONLY the tour narration — the app-wide sound switch (games,
 *  reader) is owned by components/sound.js and stays untouched. */
function renderVoiceButton() {
    const btn = shell?.querySelector('[data-tour="mute"]');
    if (!btn) return;
    btn.hidden = !voiceConfig;

    const on = btn.querySelector('[data-tour-voice-on]');
    const off = btn.querySelector('[data-tour-voice-off]');
    if (on && off) {
        on.classList.toggle('hidden', tourVoiceMuted);
        off.classList.toggle('hidden', !tourVoiceMuted);
    }
    btn.title = tourVoiceMuted ? "Alice's voice: muted. Tap to unmute" : "Alice's voice: on. Tap to mute";
    btn.setAttribute('aria-pressed', String(tourVoiceMuted));
}

function bindTourMute() {
    const btn = shell?.querySelector('[data-tour="mute"]');
    btn?.addEventListener('click', () => {
        tourVoiceMuted = !tourVoiceMuted;
        try { localStorage.setItem('alice-tour-voice-mute', tourVoiceMuted ? '1' : '0'); } catch { /* storage off */ }
        renderVoiceButton();
        if (tourVoiceMuted) stopVoice();
    });
}

/** Browsers block unmuted autoplay without a gesture. Prime playback with a
 *  silent muted tick on the tour's first pointer-down so later lines speak. */
function unlockVoiceOnGesture() {
    document.addEventListener('pointerdown', () => {
        if (!voiceConfig) return;
        const audio = getVoiceAudio();
        const wasMuted = audio.muted;
        audio.muted = true;
        const attempt = audio.play();
        if (attempt) {
            attempt.then(() => audio.pause()).catch(() => { /* autoplay still blocked */ })
                .finally(() => { audio.muted = wasMuted; audio.currentTime = 0; });
        }
    }, { capture: true, once: true });
}

/* --------------------------- typewriter --------------------------- */

function charIntervalFor(durMs, len) {
    // Finish typing around 90% through the clip so the tail feels like speech.
    const target = Math.floor((durMs * 0.9) / Math.max(1, len));
    return Math.min(60, Math.max(13, target));
}

function say(text, key) {
    return new Promise((resolve) => {
        const token = ++typingToken;
        arrow.style.opacity = '0';
        textEl.textContent = '';

        let finishTimer = 0;
        let guardTimer = 0;
        let iv = 0;

        const finish = () => {
            clearTimeout(finishTimer);
            clearTimeout(guardTimer);
            if (iv) { clearInterval(iv); iv = 0; }
            stopVoice();
            textEl.textContent = text;
            arrow.style.opacity = '1';
            if (typingToken === token) resolve();
        };

        const meta = (key && voiceConfig && !tourVoiceMuted) ? voiceConfig.lines[key] : null;

        if (meta && !getMuted()) {
            const dur = Number(meta.duration_ms) || 0;
            if (reduceMotion) {
                textEl.textContent = text;
                finishTimer = window.setTimeout(finish, dur + 400);
            } else if (text.length > 0) {
                const hold = Math.max(700, dur * 0.1 + 150);
                const charMs = charIntervalFor(dur, text.length);
                const typedOut = () => {
                    iv = 0;
                    finishTimer = window.setTimeout(finish, hold);
                };
                let i = 0;
                if (charMs <= 1) {
                    textEl.textContent = text;
                    typedOut();
                } else {
                    iv = window.setInterval(() => {
                        i += 1;
                        textEl.textContent = text.slice(0, i);
                        if (i >= text.length) { clearInterval(iv); typedOut(); }
                    }, charMs);
                }
            } else {
                finishTimer = window.setTimeout(finish, Math.max(700, dur * 0.1 + 150));
            }
            // Safety net: a stalled clip must never freeze the tour.
            guardTimer = window.setTimeout(finish, Math.max(1800, dur + 1000));
            const audio = getVoiceAudio();
            audio.onended = finish;
            audio.onerror = finish;
            audio.pause();
            audio.currentTime = 0;
            try {
                audio.src = voiceUrl(meta);
                const attempt = audio.play();
                if (attempt) attempt.catch(finish);
            } catch { finish(); }
        } else if (reduceMotion) {
            // No voice (or muted): instant caption + heuristic hold.
            textEl.textContent = text;
            finishTimer = window.setTimeout(finish, holdMs(text));
        } else {
            let i = 0;
            const typedOut = () => {
                iv = 0;
                clearTimeout(finishTimer);
                finishTimer = window.setTimeout(finish, holdMs(text));
            };
            iv = window.setInterval(() => {
                i += 1;
                textEl.textContent = text.slice(0, i);
                if (i >= text.length) { clearInterval(iv); typedOut(); }
            }, 22);
        }

        typingStop = () => {
            if (typingToken !== token) return;
            typingToken += 1;
            finish();
        };
    });
}

/* --------------------------- pause / resume --------------------------- */

function showPaused() {
    if (disposed) return;
    active = true;
    arrow.style.opacity = '0';
    progress.textContent = '';
    pausedRow.classList.remove('hidden');
    textEl.textContent = "We went somewhere new. No problem. Keep going, or end here?";
}

/* --------------------------- lifecycle --------------------------- */

function startTour() {
    if (!AREA_OK()) return;
    setState({ step: 0 });
    const helpModal = document.getElementById('help-modal');
    if (helpModal) {
        try { closeModal(helpModal); } catch { /* noop */ }
    }
    if (location.pathname === '/hub') {
        active = true;
        disposed = false;
        runLoop();
    } else {
        location.assign('/hub');
    }
}

async function runLoop() {
    if (disposed) return;
    if (!manifestPromise) fetchVoiceManifest();
    // Don't let a slow manifest fetch stall the tour start (voice is optional).
    await Promise.race([manifestPromise || Promise.resolve(null), sleep(1200)]);

    const state = getState();
    if (!state) { endTour(true); return; }

    const step = state.step;
    const entry = STEPS[step];
    if (!entry) { endTour(true); return; }

    if (!entry.url.test(location.pathname)) {
        showPaused();
        return;
    }

    active = true;
    showShell();
    progress.textContent = `${step + 1} / ${STEPS.length}`;
    await sleep(350);

    try {
        await entry.run();
    } catch (error) {
        // A failed beat must never kill the tour: pause instead (resumable).
        showPaused();
        return;
    }

    if (disposed) return;
    unspot();

    const next = step + 1;
    if (next >= STEPS.length) { endTour(true); return; }

    setState({ step: next });
    const nextEntry = STEPS[next];
    if (nextEntry.url.test(location.pathname)) {
        runLoop();
        return;
    }
    location.assign(nextEntry.canonical);
}

function endTour(finished) {
    clearState();
    dispose(finished);
}

function dispose() {
    disposed = true;
    typingStop?.();
    typingStop = null;
    stopVoice();
    unspot();
    closeOpenDemos();
    shieldOn();
    hint.style.opacity = '0';
    hideShell();
    active = false;
}

/** Best-effort cleanup of demo overlays the tour may have opened. */
function closeOpenDemos() {
    try {
        const drawerClose = document.querySelector('[data-filter-drawer]:not(.hidden) [data-filter-drawer-close]');
        if (drawerClose) drawerClose.click();
    } catch { /* noop */ }
    try {
        const modalClose = document.querySelector('[data-modal]:not(.hidden) [data-modal-close]');
        if (modalClose) modalClose.click();
    } catch { /* noop */ }
}

/* --------------------------- init --------------------------- */

export function initTourDriver() {
    // The GET STARTED trigger must bind on every student page.
    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-tour-start]');
        if (!trigger || !AREA_OK()) return;
        trigger.disabled = true;
        startTour();
    });

    cacheElements();
    if (!shell) return;

    try { tourVoiceMuted = localStorage.getItem('alice-tour-voice-mute') === '1'; } catch { /* storage off */ }
    bindTourMute();

    box.addEventListener('click', (event) => {
        if (event.target.closest('[data-tour="quit"], [data-tour="resume"], [data-tour="end"], [data-tour="mute"]')) {
            return; // controls handle themselves
        }
        typingStop?.(); // tap anywhere else advances (skip typing + hold + clip)
    });

    const quiet = (el) => { if (el) el.setAttribute('aria-label', ''); };
    quiet(pick('[data-tour="progress"]'));

    shell.querySelector('[data-tour="quit"]').addEventListener('click', () => endTour(false));
    shell.querySelector('[data-tour="resume"]').addEventListener('click', () => {
        const state = getState();
        const entry = state && STEPS[state.step];
        if (entry) location.assign(entry.canonical);
        else endTour(false);
    });
    shell.querySelector('[data-tour="end"]').addEventListener('click', () => endTour(false));

    // The button click is handled by components/sound.js (it owns the
    // [data-sound-toggle] contract). All the tour does is react: a clip that
    // is already speaking stops the moment sound is turned off.
    onMuteChange((muted) => {
        if (muted) stopVoice();
    });

    shield.addEventListener('pointerdown', () => {
        if (ringTarget) showHint(); // only hint when there's actually something to tap
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && active) endTour(false);
    });

    unlockVoiceOnGesture();

    if (getState() && AREA_OK() && !disposed) {
        active = true;
        runLoop();
    }
}