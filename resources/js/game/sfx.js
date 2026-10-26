/**
 * Sound effects module — plays pre-encoded MP3 cues from /audio/ui/ (built
 * from the itch.io SFX packs, see public/image/game/ATTRIBUTION.md), falling
 * back to Web Audio oscillator bursts whenever a file is missing or playback
 * is rejected by the autoplay policy. Mute state persists to localStorage.
 *
 * ALICE-wide sound core (S3). The module is imported both by the global
 * bundle (resources/js/components/sound.js binds the header mute toggle and
 * exposes window.SFX) and by code-split page chunks (games, quiz). Because a
 * code-split build can instantiate this module more than once, ALL shared
 * state (AudioContext, muted flag, file pool, listeners) lives on a single
 * window singleton so every copy reads/writes the same reality — the header
 * toggle always drives the games and vice-versa.
 */

const MUTE_KEY = 'alice-sound-mute';
// Back-compat (S3): the mini-games historically persisted under
// 'alice-game-mute'. Read it as a fallback so an existing mute survives;
// we always write the generalized key from here on.
const LEGACY_MUTE_KEY = 'alice-game-mute';

const GLOBAL = '__ALICE_SFX_STATE__';

function state() {
    if (!window[GLOBAL]) {
        let v = localStorage.getItem(MUTE_KEY);
        if (v === null) {
            v = localStorage.getItem(LEGACY_MUTE_KEY);
            if (v !== null) {
                localStorage.setItem(MUTE_KEY, v);
            }
        }
        window[GLOBAL] = {
            ctx: null,
            muted: v === '1',
            listeners: [],
        };
    }
    return window[GLOBAL];
}

function audioContext() {
    const s = state();
    if (s.ctx) return s.ctx;

    const AC = window.AudioContext || window.webkitAudioContext;
    if (!AC) return null;

    s.ctx = new AC();

    // Resume on first user gesture (autoplay policy).
    const resume = () => {
        if (s.ctx && s.ctx.state === 'suspended') s.ctx.resume();
        window.removeEventListener('pointerdown', resume);
        window.removeEventListener('keydown', resume);
    };
    window.addEventListener('pointerdown', resume);
    window.addEventListener('keydown', resume);

    return s.ctx;
}

/**
 * Play a short tone. `type` is an oscillator waveform; `freq` can be a
 * number or [start, end] glide; `dur` seconds; `vol` 0-1.
 */
function tone(type, freq, dur, vol = 0.4, delay = 0) {
    const ac = audioContext();
    if (!ac) return;

    // Autoplay policy: a freshly created context starts suspended, and any
    // tone scheduled against the frozen clock is silently dropped. Resume
    // first, then fire from the live clock so the FIRST interaction on a page
    // is audible — not just the second one.
    if (ac.state === 'suspended') {
        const run = () => renderTone(ac, type, freq, dur, vol, delay);
        const resume = typeof ac.resume === 'function' ? ac.resume() : null;
        if (resume && typeof resume.then === 'function') {
            resume.then(run).catch(() => run());
        } else {
            run();
        }
        return;
    }

    renderTone(ac, type, freq, dur, vol, delay);
}

function renderTone(ac, type, freq, dur, vol, delay) {
    const t0 = ac.currentTime + delay;
    const osc = ac.createOscillator();
    const gain = ac.createGain();

    if (Array.isArray(freq)) {
        osc.frequency.setValueAtTime(freq[0], t0);
        osc.frequency.exponentialRampToValueAtTime(Math.max(1, freq[1]), t0 + dur);
    } else {
        osc.frequency.setValueAtTime(freq, t0);
    }

    osc.type = type;

    gain.gain.setValueAtTime(0.0001, t0);
    gain.gain.exponentialRampToValueAtTime(vol, t0 + 0.01);
    gain.gain.exponentialRampToValueAtTime(0.0001, t0 + dur);

    osc.connect(gain);
    gain.connect(ac.destination);

    osc.start(t0);
    osc.stop(t0 + dur + 0.02);
}

/**
 * Eagerly create + resume the shared context on the first user gesture, so
 * page-level cues start from an already-running context and the first cue a
 * student hears on any page is never dropped by the autoplay policy.
 */
export function prime() {
    const ac = audioContext();
    if (ac && ac.state === 'suspended' && typeof ac.resume === 'function') {
        ac.resume().catch(() => {});
    }
    // Warm the file pool so the first cue never stalls on a network fetch.
    Object.keys(FILES).forEach((k) => {
        try {
            baseSound(FILES[k]);
        } catch {
            // Audio element unavailable — synth fallback still works.
        }
    });
    return ac !== null;
}

const CUES = {
    // --- retro-DS / game lexicon (existing) ---
    pop: () => tone('triangle', [400, 700], 0.09, 0.35),
    flip: () => tone('triangle', [500, 800], 0.06, 0.25),
    match: () => {
        tone('triangle', 660, 0.1, 0.35);
        tone('triangle', 880, 0.14, 0.35, 0.09);
    },
    wrong: () => tone('sawtooth', [220, 140], 0.22, 0.25),
    fanfare: () => {
        tone('square', 523, 0.12, 0.3);
        tone('square', 659, 0.12, 0.3, 0.12);
        tone('square', 784, 0.2, 0.35, 0.24);
    },
    tick: () => tone('square', 1000, 0.03, 0.15),
    whoosh: () => tone('sine', [900, 200], 0.25, 0.2),
    go: () => {
        tone('square', 523, 0.08, 0.3);
        tone('square', 784, 0.1, 0.3, 0.08);
        tone('triangle', 1046, 0.18, 0.32, 0.16);
    },

    // --- economy / rewards (S3) ---
    coin: () => {
        tone('triangle', 880, 0.08, 0.35);
        tone('triangle', 1174, 0.12, 0.3, 0.07);
    },
    claim: () => {
        tone('triangle', 660, 0.09, 0.35);
        tone('triangle', 880, 0.16, 0.35, 0.08);
    },
    stamp: () => {
        tone('sine', 180, 0.09, 0.5);
        tone('sine', 120, 0.12, 0.45, 0.04);
    },
    freeze: () => {
        tone('sine', 900, 0.15, 0.2);
        tone('sine', 1300, 0.2, 0.15, 0.1);
    },
    badge: () => {
        tone('square', 784, 0.1, 0.24);
        tone('square', 988, 0.1, 0.24, 0.1);
        tone('triangle', 1318, 0.2, 0.3, 0.2);
    },
    select: () => tone('square', 520, 0.045, 0.18),
    correct: () => tone('triangle', [600, 840], 0.12, 0.32),
    turn: () => tone('sine', [600, 260], 0.16, 0.16),
    open: () => tone('triangle', [300, 520], 0.09, 0.3),
    close: () => tone('triangle', [520, 320], 0.08, 0.24),
    nav: () => tone('square', 440, 0.04, 0.14),

    // --- Field Journal boss battle (item 43-46) ---
    journalHit: () => {
        tone('square', 220, 0.06, 0.4);
        tone('sawtooth', [440, 180], 0.16, 0.28, 0.04);
    },
    journalLose: () => {
        tone('sawtooth', [320, 120], 0.3, 0.3);
        tone('square', 90, 0.24, 0.28, 0.2);
    },
    journalVictory: () => {
        tone('square', 523, 0.1, 0.3);
        tone('square', 659, 0.1, 0.3, 0.1);
        tone('square', 784, 0.1, 0.3, 0.2);
        tone('triangle', 1046, 0.3, 0.35, 0.3);
    },
    // Defeat reveal: the RESTARTING overlay lifts and the battle returns.
    journalRestart: () => {
        tone('sine', [220, 880], 0.5, 0.3);
        tone('triangle', 440, 0.4, 0.25, 0.2);
    },
};

// Pre-encoded cue files (19 cues have a file — synth above is the
// fallback; the 5 cues without a call site were retired in the
// 2026-10-05 cleanup). Paths are absolute from the web root; loudness was
// normalized at build time (LUFS ≈ -16, sub-400ms blips peak-normalized to
// -1.5dB).
const FILES = {
    pop: '/audio/ui/pop.mp3',
    flip: '/audio/ui/flip.mp3',
    match: '/audio/ui/match.mp3',
    wrong: '/audio/ui/wrong.mp3',
    fanfare: '/audio/ui/fanfare.mp3',
    tick: '/audio/ui/tick.mp3',
    whoosh: '/audio/ui/whoosh.mp3',
    go: '/audio/ui/go.mp3',
    coin: '/audio/ui/coin.mp3',
    claim: '/audio/ui/claim.mp3',
    stamp: '/audio/ui/stamp.mp3',
    freeze: '/audio/ui/freeze.mp3',
    badge: '/audio/ui/badge.mp3',
    select: '/audio/ui/select.mp3',
    correct: '/audio/ui/correct.mp3',
    turn: '/audio/ui/turn.mp3',
    open: '/audio/ui/open.mp3',
    close: '/audio/ui/close.mp3',
    nav: '/audio/ui/nav.mp3',
    journalHit: '/audio/journal/hit.ogg',
    journalLose: '/audio/journal/lose.ogg',
    journalVictory: '/audio/journal/victory.ogg',
    journalRestart: '/audio/journal/return-by-death.mp3',
};

// Per-cue start offset (seconds) — skips lead-in on cues that need it.
const SEEK = {
    journalRestart: 0,
};

const FILE_VOLUME = 0.55;

/** Cached base element per URL (held on the window singleton, fetched once). */
function baseSound(url) {
    const s = state();
    if (!s.pool) s.pool = {};
    let base = s.pool[url];
    if (!base) {
        base = new Audio(url);
        base.preload = 'auto';
        s.pool[url] = base;
    }
    return base;
}

/** Fresh clone per shot so rapid cues overlap instead of cutting each other. */
function fileSound(url) {
    const a = baseSound(url).cloneNode();
    a.volume = FILE_VOLUME;
    return a;
}

function synth(cue) {
    try {
        cue();
    } catch {
        // Audio context unavailable — stay silent.
    }
}

export function play(name) {
    if (state().muted) return;
    const cue = CUES[name];
    if (!cue) return;

    const url = FILES[name];
    if (url) {
        try {
            const a = fileSound(url);
            const seek = SEEK[name];
            if (seek) {
                const apply = () => {
                    try {
                        a.currentTime = seek;
                    } catch {
                        // Metadata not ready — play from the top instead.
                    }
                };
                if (a.readyState >= 1) apply();
                else a.addEventListener('loadedmetadata', apply, { once: true });
            }
            const p = a.play();
            if (p && typeof p.then === 'function') {
                // Autoplay rejected before first gesture: defer via the
                // synth path, which queues against the context resume.
                p.catch(() => synth(cue));
            }
            return;
        } catch {
            // fall through to synthesis
        }
    }
    synth(cue);
}

export function toggleMute() {
    return setMuted(!state().muted);
}

export function setMuted(value) {
    const s = state();
    s.muted = Boolean(value);
    localStorage.setItem(MUTE_KEY, s.muted ? '1' : '0');
    emit(s);
    return s.muted;
}

export function isMuted() {
    return state().muted;
}

export function getMuted() {
    return state().muted;
}

/** Subscribe to mute changes; returns an unsubscribe fn. */
export function onMuteChange(cb) {
    const s = state();
    s.listeners.push(cb);
    return () => {
        s.listeners = s.listeners.filter((l) => l !== cb);
    };
}

function emit(s) {
    s.listeners.forEach((cb) => {
        try {
            cb(s.muted);
        } catch {
            // Listener error must never break the sound core.
        }
    });
    window.dispatchEvent(new CustomEvent('alice:soundchange', { detail: { muted: s.muted } }));
}
