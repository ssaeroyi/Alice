/**
 * Pronunciation check - fully local dictation behind a reusable widget.
 * Press the mic, say the target word out loud, and the widget scores the
 * attempt with instant visual feedback:
 *
 *   - the mic glows with a pulsing ring while it listens,
 *   - per-letter boxes light up as the recognized word grows,
 *   - a verdict chip lands when speech ends: Perfect / Close / Try again,
 *   - a ghost "Say again" button appears under the chip so a student can
 *     retry as many times as they like; every new attempt starts clean.
 *
 * Honesty: a verdict is only ever produced from a transcript that came with
 * real speech energy AND word confidence. Silence, background noise, a muted
 * mic and low-confidence decoder garbage all surface as a neutral "Nothing
 * heard" / "Mic seems off" — never as a fabricated Perfect.
 *
 * Feedback: after a verdict the letter boxes reveal exactly which letters
 * matched (green), differed (rose) or were missed (ghost), with "+" tags for
 * letters the mic added; a live pulse meter proves the mic hears you while
 * listening; on a Close or Try-again the widget echoes the word it heard
 * ("Heard "much"").
 *
 * The only engine is Vosk (WASM) running in a Web Worker with the small
 * en-US model served as a static asset beside the widget. It never touches
 * the network - there is no Google relay, so no shield or ad-blocker can
 * intercept it and no audio ever leaves the device. The model is warmed in
 * the background as soon as the widget is on screen, so the first tap is
 * immediate instead of paying a long fetch.
 *
 * Robustness/UX:
 *   - tapping the mic again while listening stops early and scores what was
 *     heard instead of waiting for the full window,
 *   - interim speech is only trusted when the voice-activity gate measured
 *     genuine speech and no final result landed (e.g. tap-to-stop); a
 *     transcript without real speech behind it is never scored,
 *   - raw mic errors are surfaced in the helper line so environment
 *     failures (mic blocked, no device) are visible instead of an opaque
 *     "try again",
 *   - stopPronounceCheck() lets hosts (flashcards) abort listening when the
 *     card changes or the hosting modal closes.
 */

import { play } from '../game/sfx.js';

const NON_LETTERS = /[^\p{L}\p{N}]+/gu;

const LISTEN_MS = 7000;

const OFFLINE_MODEL_PATH = '/stt/vosk-model-small-en-us-0.15.tar.gz';

// Honest-verdict gates. No grammar hint is used: a single-token grammar can
// only ever decode the target word, so silence, noise and even a
// mispronunciation get forced to it and everything looks "Perfect" (and a
// [target,[unk]] grammar rejects a slightly-off utterance as "Nothing heard").
// Instead every verdict requires real speech energy AND word confidence:
//   - CONF_FLOOR        below this per-word confidence a matching final is
//                       distrusted and the attempt reads as "Nothing heard"
//   - MIN_SPEECH_MS     genuine speech must span this long before a transcript
//                       may be scored at all
//   - OVER_FLOOR_DB     a frame must sit this many dB above the adaptive
//                       ambient floor to count as speech
//   - ABS_ROOT_FLOOR    ...and never below this absolute RMS floor (≈ -54 dBFS)
//   - MIC_MUTED_RMS     an attempt peaking under this is a muted/dead mic
//   - STARTUP_DISCARD_MS  the first ~150ms after the stream opens is ignored
//                       (mic start pop / a trailing TTS must not light boxes)
//   - REEFLOOR_MS       if no speech is found by then, creep the floor upward
//                       so a genuinely loud room can still trigger speech
// Round-12: 0.35 (was 0.50). A 40MB isolated-word model recognises casual,
// accented, real-mic human speech at lower confidence than clean synthetic
// TTS audio, so 0.50 rejected many correct words as "Nothing heard". 0.35
// lets a just-below-typical, clearly-spoken word through the gate; the fuzzy
// grader (below) still tells Perfect from Close from Wrong so honesty holds.
const CONF_FLOOR = 0.35;
const MIN_SPEECH_MS = 250;
const OVER_FLOOR_DB = 6;
const OVER_FLOOR_RATIO = 10 ** (OVER_FLOOR_DB / 20);
const ABS_ROOT_FLOOR = 0.002;
const MIC_MUTED_RMS = 0.0015;
const STARTUP_DISCARD_MS = 150;
const REEFLOOR_MS = 2500;

// Round-10 decode tuning: "I said it right but it hears another word".
// The drift ("match" -> "much", "hello" -> "hell no") is attacked in the
// DECISION layer only - the recognizer always gets the raw float frame:
//   - TAIL_TRIM_MS/CONF      a final whose last word begins well past where
//                            real speech ended AND sits below this confidence
//                            is decoder fluff glued after the word - drop it
//   - EXACT_PARTIAL_MIN_OBSERVATIONS  an exact partial that reads the target
//                            twice in a row beats a single drifted final
//
// A hand-rolled DSP front-end (60 Hz Butterworth HPF + slow AGC + tanh soft
// clip) was implemented for this round and is intentionally NOT here: a
// hand-tuned in-browser filter twice silently muted the mic (seen as "not
// detecting anything"), so the signal path is exactly the round-6..9 one that
// decoded reliably. If quiet-vs-loud equality is ever needed again, tighten
// the VAD/confidence gates - never reintroduce custom signal conditioning.
const TAIL_TRIM_MS = 150;
const TAIL_TRIM_CONF = 0.45;
const EXACT_PARTIAL_MIN_OBSERVATIONS = 2;

// Round-10 live feedback refresh rates: the pulse-meter bars and the STT
// debug pane's level/source readout update on these throttles. METER_REF_RMS
// is the raw-frame RMS that lights all eight bars.
const METER_LEVELS = 8;
const METER_REF_RMS = 0.25;
const METER_PUBLISH_MS = 120;
const DIAG_PUBLISH_MS = 200;

function normalize(text) {
    return (text || '').toLowerCase().replace(NON_LETTERS, '');
}

function lettersOf(text) {
    return Array.from(normalize(text));
}

function levenshtein(a, b) {
    if (a === b) return 0;
    if (!a.length) return b.length;
    if (!b.length) return a.length;

    let previous = Array.from({ length: b.length + 1 }, (_, i) => i);
    for (let i = 1; i <= a.length; i++) {
        const row = [i];
        for (let j = 1; j <= b.length; j++) {
            row[j] = Math.min(
                previous[j] + 1,
                row[j - 1] + 1,
                previous[j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1),
            );
        }
        previous = row;
    }
    return previous[b.length];
}

function truncateText(text, max) {
    const chars = Array.from(String(text ?? '').trim());
    if (chars.length <= max) return chars.join('');
    return chars.slice(0, Math.max(1, max - 1)).join('') + '…';
}

/**
 * The consonant skeleton of a word (all vowels stripped): "match" -> "mtch",
 * "much" -> "mch". "I said it right, it heard a vowel shift" hits this test:
 * the two skeletons differ by a single consonant ("mtch" vs "mch"), so the
 * attempt reads as a Close instead of a flat miss - the learner pronounced
 * the consonant structure, the decoder just sang the wrong vowel.
 */
function consonantSkeleton(text) {
    return Array.from(normalize(text)).filter((ch) => !'aeiouy'.includes(ch)).join('');
}

function skeletonClose(a, b) {
    const sa = consonantSkeleton(a);
    const sb = consonantSkeleton(b);
    if (!sa || !sb) return false;
    // Round-12: tolerance raised 1 -> 2 so drift like "stroll"->"strong"
    // (skeletons "strll" vs "strng") still reads Close. Two consonants off is
    // a near-miss, three is a different word.
    return levenshtein(sa, sb) <= 2 && Math.abs(sa.length - sb.length) <= 2;
}

/**
 * Align the recognized letters against the target letters (Levenshtein DP
 * traceback) so every target position gets a verdict:
 *   ok    that letter matched,
 *   miss  a different letter was heard in its place,
 *   gone  the letter was never heard at all.
 * Extra letters the mic added come back as a separate count (rendered as a
 * "+N" tag beside the boxes).
 */
function alignDiff(expected, got) {
    const m = expected.length;
    const n = got.length;
    const d = Array.from({ length: m + 1 }, () => new Array(n + 1).fill(0));
    for (let i = 0; i <= m; i++) d[i][0] = i;
    for (let j = 0; j <= n; j++) d[0][j] = j;
    for (let i = 1; i <= m; i++) {
        for (let j = 1; j <= n; j++) {
            const cost = expected[i - 1] === got[j - 1] ? 0 : 1;
            d[i][j] = Math.min(d[i - 1][j - 1] + cost, d[i - 1][j] + 1, d[i][j - 1] + 1);
        }
    }
    const states = new Array(m).fill('gone');
    let extras = 0;
    let i = m;
    let j = n;
    while (i > 0 && j > 0) {
        const diagCost = expected[i - 1] === got[j - 1] ? 0 : 1;
        if (d[i][j] === d[i - 1][j - 1] + diagCost) {
            states[i - 1] = diagCost === 0 ? 'ok' : 'miss';
            i -= 1;
            j -= 1;
        } else if (d[i][j] === d[i - 1][j] + 1) {
            i -= 1; // letter never heard
        } else {
            extras += 1;
            j -= 1; // extra letter the decoder added
        }
    }
    extras += j;
    return { states, extras };
}

/**
 * A Vosk attempt can end with several finals (one per endpoint). The last one
 * is often decoder fluff: an extra word glued after the real one, beginning
 * after speech already ended and carrying a weak confidence. Drop just that
 * tail word when it fails both tests (VAD already placed speech and the word
 * is honestly unsure) and mark the item so the verdict source reads
 * "final·trimmed" in the STT debug pane.
 */
function trimTrailingNoise(finals, vad, target) {
    if (!finals.length) return finals;
    return finals.map((item) => {
        const words = item.words ?? [];
        if (words.length <= 1) return item;
        const tail = words[words.length - 1];
        const tailMs = Number(tail?.start) * 1000;
        const pastSpeechEnd = vad.speechEndRecMs > 0 && tailMs > vad.speechEndRecMs + TAIL_TRIM_MS;
        const weakTail = Number.isFinite(Number(tail?.conf)) && tail.conf < TAIL_TRIM_CONF;
        if (!(pastSpeechEnd && weakTail)) return item;
        const kept = words.slice(0, -1);
        const text = kept.map((w) => w.word).join(' ').trim();
        if (!text) return item;
        const confs = kept.map((w) => Number(w?.conf)).filter((c) => Number.isFinite(c));
        diagLogEvent('trim', `${tail.word} · ${Number(tail.conf).toFixed(2)} conf · ${tailMs.toFixed(0)}ms after speech ended`);
        return {
            text,
            words: kept,
            trimmed: true,
            avgConf: confs.length ? confs.reduce((s, c) => s + c, 0) / confs.length : null,
        };
    });
}

function setMeterHidden(root, hidden) {
    const meter = root?.querySelector('[data-say-check-level]');
    if (meter) meter.hidden = hidden;
}

function setMeterLevel(root, level) {
    const meter = root?.querySelector('[data-say-check-level]');
    if (!meter) return;
    meter.querySelectorAll('.say-level-bar').forEach((bar, i) => {
        bar.classList.toggle('active', i < level);
    });
}

/**
 * Round-10 verdict reveal: after speech ends the letter boxes switch from the
 * growing-prefix highlight (.on) to a full alignment against the target -
 * green for letters that matched, rose for letters the mic swapped, ghost for
 * letters that were never heard, and a "+N" tag when the decoder added extra
 * letters. The container gets a spoken summary via aria-label.
 */
function renderVerdictBoxes(root, target, heard) {
    const box = root.querySelector('[data-say-check-boxes]');
    if (!box) return;
    const expected = normalize(target);
    const got = normalize(heard);
    let okCount = 0;
    if (got && expected) {
        const { states, extras } = alignDiff(expected, got);
        box.querySelectorAll('.say-letter-box').forEach((cell, i) => {
            if (states[i] === 'ok') {
                cell.classList.add('matched');
                okCount += 1;
            } else if (states[i] === 'miss') {
                cell.classList.add('mismatch');
            } else {
                cell.classList.add('missing');
            }
        });
        if (extras > 0) {
            const tag = document.createElement('span');
            tag.className = 'say-extra-tag';
            tag.dataset['extra'] = '1';
            tag.textContent = `+${extras}`;
            box.appendChild(tag);
        }
    }
    box.setAttribute('aria-label', `${okCount} of ${expected.length} letters correct`);
}

/**
 * Neutral verdicts (Mic seems off / Nothing heard / low confidence) leave the
 * page exactly as they found it: no lit boxes, no heard-echo, no stale meter
 * bars - a dead attempt must never masquerade as a scored one.
 */
function clearAttemptVisuals(root) {
    resetBoxes(root);
    const echo = root?.querySelector('[data-say-check-echo]');
    if (echo) {
        echo.textContent = '';
        echo.hidden = true;
    }
    setMeterHidden(root, true);
}

let activeTimeout = null;

// --- offline (Vosk) engine module state ------------------------------------
let offlineModelPromise = null;    // cached createModel() promise
let offlineStreamPromise = null;   // cached getUserMedia() promise; tracks stopped between attempts
let offlineState = 'idle';         // idle | loading | ready | failed
let offlineToken = 0;              // attempt token: invalidates stale recognizer callbacks
let activeOffline = null;          // { token, dispose, finish } of the running offline attempt

// --- diagnostics for the admin STT debug pane -----------------------------
// The pane reads this real module state (never a reimplementation) so the
// admin can watch recognition work or fail in the browser. Only ever set
// here; the debug module just renders it.
let diagStartedAt = null;
let diagRoot = null;
let diagTarget = '';
let diagInterim = '';
let diagFinal = '';
let diagHeard = '';
let diagResults = 0;
let diagFinalSeen = false;
let diagError = null;
let diagVerdict = null;
let diagListens = 0;
let diagFinalWords = null;
let diagSpeechMs = 0;
let diagSpeechRatio = 0;
let diagSpeechPeak = 0;
let diagAmbientRms = 0;
let diagMeterLevel = 0;
let diagSource = null;
const diagLog = [];

function diagLogEvent(kind, detail) {
    diagLog.push({ t: Date.now(), kind, detail });
    if (diagLog.length > 30) {
        diagLog.shift();
    }
}

function diagResetAttempt(root, target) {
    diagStartedAt = Date.now();
    diagRoot = root;
    diagTarget = target;
    diagInterim = '';
    diagFinal = '';
    diagHeard = '';
    diagResults = 0;
    diagFinalSeen = false;
    diagError = null;
    diagVerdict = null;
    diagListens += 1;
    diagFinalWords = null;
    diagSpeechMs = 0;
    diagSpeechRatio = 0;
    diagSpeechPeak = 0;
    diagAmbientRms = 0;
    diagMeterLevel = 0;
    diagSource = null;
    diagLogEvent('start', target);
}

function diagRecordVerdict(label, heard) {
    diagVerdict = { label, heard };
    diagLogEvent('verdict', `${label}: ${heard}`);
}

function setListening(root, listening) {
    root.dataset.listening = listening ? 'true' : 'false';
    const ping = root.querySelector('[data-say-check-ping]');
    if (ping) ping.classList.toggle('animate-ping', listening);
    const label = root.querySelector('[data-say-check-label]');
    if (label) label.textContent = listening ? 'Listening' : 'Say it';
    if (listening) {
        setMeterLevel(root, 0);
    }
    setMeterHidden(root, !listening);
}

// ---------------------------------------------------------------------------
// Engine availability
// ---------------------------------------------------------------------------

/**
 * True when the server shipped the offline Vosk model beside this widget. It
 * is the only engine - without it the widget does not exist at all.
 */
function offlineReadyOnRoot(root) {
    return (root?.dataset?.sttOffline ?? '0') === '1';
}

/**
 * Whether any mic-check widget on the page can run at all: it can only do so
 * when a locally installed Vosk model backs it. Used by hosts (flashcards)
 * to decide whether to show the say-it control.
 */
export function microphoneSupported() {
    return document.querySelector('[data-pronounce-check][data-stt-offline="1"]') !== null;
}

// ---------------------------------------------------------------------------
// Offline (Vosk) engine
// ---------------------------------------------------------------------------

async function loadVosk() {
    // Code-split chunk: pulled in only when the offline engine is first
    // needed, so ordinary pages never pay for the WASM bundle.
    const namespace = await import('vosk-browser');
    const Vosk = namespace.default ?? namespace;
    if (!Vosk || typeof Vosk.createModel !== 'function') {
        throw new Error('vosk-browser did not expose createModel');
    }
    return Vosk;
}

function ensureOfflineModel() {
    if (!offlineModelPromise) {
        offlineModelPromise = (async () => {
            offlineState = 'loading';
            diagLogEvent('offline-model', 'loading');
            const Vosk = await loadVosk();
            const model = await Vosk.createModel(OFFLINE_MODEL_PATH);
            offlineState = 'ready';
            diagLogEvent('offline-model', 'ready');
            return model;
        })();
        // A failed load is not terminal: clear the cache so the next attempt
        // can retry (e.g. after the teacher installs the model file).
        offlineModelPromise.catch(() => {
            offlineState = 'failed';
            diagLogEvent('offline-model', 'failed');
            offlineModelPromise = null;
        });
    }
    return offlineModelPromise;
}

function ensureOfflineStream() {
    if (!offlineStreamPromise) {
        offlineStreamPromise = navigator.mediaDevices.getUserMedia({
            audio: { echoCancellation: true, noiseSuppression: true, channelCount: 1 },
        });
        offlineStreamPromise.catch(() => {
            offlineStreamPromise = null;
        });
    }
    return offlineStreamPromise;
}

// Microphones are released after each attempt so no lingering mic indicator
// stays lit; the next tap reacquires a fresh stream.
function releaseOfflineStream() {
    if (!offlineStreamPromise) {
        return;
    }
    offlineStreamPromise.then((stream) => {
        stream.getTracks().forEach((track) => track.stop());
    }).catch(() => {});
    offlineStreamPromise = null;
}

// ---------------------------------------------------------------------------
// Idle preload
// ---------------------------------------------------------------------------
// The model asset is 41MB, so warm it as soon as a widget is actually on
// screen - never before. A single module-level observer drops each target
// after its first visible trigger and tears down at unload, so fetch-swapped
// pages (dictionary lookups) can't accumulate references.
const offlinePreloadObserver = 'IntersectionObserver' in window
    ? new IntersectionObserver((entries) => {
        for (const entry of entries) {
            if (!entry.isIntersecting) continue;
            const idle = window.requestIdleCallback ?? ((cb) => window.setTimeout(cb, 0));
            offlinePreloadObserver.unobserve(entry.target);
            idle(() => ensureOfflineModel().catch(() => {}));
        }
    })
    : null;

function observeOfflinePreload(root) {
    if (!offlineReadyOnRoot(root)) {
        return;
    }
    if (offlinePreloadObserver) {
        offlinePreloadObserver.observe(root);
    } else {
        // Very old browsers: warm eagerly so the first tap stays quick.
        ensureOfflineModel().catch(() => {});
    }
}

window.addEventListener('pagehide', () => {
    offlinePreloadObserver?.disconnect();
}, { once: true });

function updateBoxesForHeard(root, target, heard) {
    const expected = normalize(target);
    const got = normalize(heard);
    let matched = 0;
    while (matched < expected.length && got[matched] === expected[matched]) {
        matched++;
    }
    setBoxes(root, matched);
}

function showOfflineMicTrouble(root, error) {
    const name = error?.name ?? '';
    if (name === 'NotAllowedError' || name === 'SecurityError') {
        showVerdict(root, 'info', 'Mic blocked', null);
        showMessage(root, 'Error not-allowed: allow microphone access in the browser address bar, then try again.');
    } else if (name === 'NotFoundError' || name === 'OverconstrainedError') {
        showMessage(root, 'Error audio-capture: no microphone was found on this device.');
    } else {
        showMessage(root, 'Speech check could not start on this device. Try again.');
    }
}

/**
 * Abort any active recognition (optionally for a given widget root) and
 * return the widget to its idle state. Called by hosts whenever a card
 * changes or the hosting modal closes, and internally to steal the mic
 * when a new check starts.
 */
export function stopPronounceCheck(root = null) {
    if (activeTimeout) {
        window.clearTimeout(activeTimeout);
        activeTimeout = null;
    }

    const offline = activeOffline;
    activeOffline = null;
    if (offline) {
        offline.dispose();
    }

    if (root) {
        setListening(root, false);
    }
}

export function initPronounceChecks(root = document) {
    root.querySelectorAll('[data-pronounce-check]').forEach((el) => {
        if (el.dataset.speechBound) {
            return;
        }

        // Nothing can run here without the locally installed Vosk model.
        if (!offlineReadyOnRoot(el)) {
            return;
        }

        el.dataset.speechBound = 'true';

        el.classList.remove('hidden');
        el.classList.add('flex');

        renderBoxes(el, el.dataset.pronounceCheck ?? '');

        el.querySelector('[data-say-check]')?.addEventListener('click', () => handleClick(el));
        el.querySelector('[data-say-again]')?.addEventListener('click', () => handleClick(el));

        // "Hear it": speak the target aloud (browser TTS). Always wired, but
        // only surfaced in the UI for proper-noun / out-of-vocabulary words in
        // a calm, play-as-you-wish box. Never call this while recognising the
        // mic: the App's own audio must not feed back into the recognizer.
        el.querySelector('[data-say-listen]')?.addEventListener('click', () => {
            const word = (el.dataset.pronounceCheck ?? '').trim();
            if (!word || !('speechSynthesis' in window)) return;
            stopPronounceCheck();
            window.speechSynthesis.cancel();
            const utter = new SpeechSynthesisUtterance(word);
            utter.lang = 'en-US';
            utter.rate = 0.85;
            window.speechSynthesis.speak(utter);
        });

        observeOfflinePreload(el);
    });
}

/**
 * Used by pages that swap the target per card (flashcards): repoints the
 * widget and rebuilds its letter boxes. Safe to call before the widget was
 * ever initialized.
 */
export function setPronounceTarget(root, target) {
    if (!root) return;
    root.dataset.pronounceCheck = target;
    renderBoxes(root, target);
    hideTransient(root);
    toggleSayAgain(root, false);
}

function handleClick(root) {
    // Tap-to-stop: a second press while listening ends early and scores
    // whatever was heard instead of waiting out the silence window.
    if (root.dataset.listening === 'true' && activeOffline) {
        activeOffline.finish();
        return;
    }

    start(root);
}

function renderBoxes(root, target) {
    const box = root.querySelector('[data-say-check-boxes]');
    if (!box) return;

    box.innerHTML = '';
    for (const letter of lettersOf(target)) {
        const cell = document.createElement('span');
        cell.className = 'say-letter-box';
        cell.textContent = letter.toUpperCase();
        box.appendChild(cell);
    }
}

function setBoxes(root, matched) {
    root.querySelectorAll('[data-say-check-boxes] .say-letter-box').forEach((cell, i) => {
        cell.classList.toggle('on', i < matched);
    });
}

function resetBoxes(root) {
    root.querySelectorAll('[data-say-check-boxes] .say-letter-box').forEach((cell) => {
        cell.classList.remove('on', 'matched', 'mismatch', 'missing');
    });
    root.querySelectorAll('[data-say-check-boxes] [data-extra]').forEach((tag) => tag.remove());
    const box = root.querySelector('[data-say-check-boxes]');
    if (box) box.removeAttribute('aria-label');
}

function toggleSayAgain(root, on) {
    const again = root?.querySelector('[data-say-again]');
    if (again) {
        again.hidden = !on;
    }
}

function showMessage(root, message) {
    const msg = root.querySelector('[data-say-check-msg]');
    if (!msg) return;
    msg.textContent = message;
    msg.hidden = false;
}

function hideTransient(root) {
    const verdict = root.querySelector('[data-say-check-verdict]');
    if (verdict) verdict.hidden = true;
    const msg = root.querySelector('[data-say-check-msg]');
    if (msg) msg.hidden = true;
    const echo = root.querySelector('[data-say-check-echo]');
    if (echo) {
        echo.textContent = '';
        echo.hidden = true;
    }
}

function showVerdict(root, tone, text, cue, heard) {
    const chip = root.querySelector('[data-say-check-verdict]');
    if (!chip) return;

    const tones = {
        great: 'bg-emerald-400 border-[#2D231E] text-[#2D231E]',
        close: 'bg-amber-400 border-[#2D231E] text-[#2D231E]',
        wrong: 'bg-rose-500 border-[#2D231E] text-white',
        info: 'bg-[#FAF6EE] border-[#E6DCCF] text-[#2D231E]',
    };

    chip.className = 'inline-flex items-center px-2.5 py-1 rounded-full font-pixel text-[8px] font-bold uppercase border-2 '
        + (tones[tone] ?? tones.info);
    chip.textContent = text;
    chip.hidden = false;

    // A verdict replaces the helper line underneath.
    const msg = root.querySelector('[data-say-check-msg]');
    if (msg) {
        msg.textContent = '';
        msg.hidden = true;
    }

    // Round-10 echo: on a non-perfect verdict repeat the word that was heard
    // so the miss is concrete ("Heard "much" - try again!"), never for a
    // clean Perfect or an info chip (mic error, no surprises).
    const echo = root.querySelector('[data-say-check-echo]');
    if (echo) {
        if ((tone === 'close' || tone === 'wrong') && heard) {
            const shown = truncateText(heard, 26);
            echo.textContent = `Heard "${shown}"${tone === 'wrong' ? ' - try again!' : ''}`;
            echo.hidden = false;
        } else {
            echo.textContent = '';
            echo.hidden = true;
        }
    }

    toggleSayAgain(root, true);

    if (cue) play(cue);
}

function evaluate(root, target, heard) {
    const expected = normalize(target);
    const got = normalize(heard);

    if (!got) {
        clearAttemptVisuals(root);
        showMessage(root, 'Nothing heard - try again.');
        toggleSayAgain(root, true);
        diagRecordVerdict('Nothing heard', '');
        return;
    }

    // Defense-in-depth guard: if a decode ever surfaces the literal "[unk]"
    // token (a wrong/muddled word under a grammar, or a bare-unknown from the
    // model), never echo it as a real word the learner can parse — say it
    // plainly. With open-vocab decoding (no grammar) a genuine word decodes to
    // itself, so this is normally inert.
    if (got === 'unk') {
        clearAttemptVisuals(root);
        showMessage(root, 'Heard something unclear - try again, a bit louder or clearer.');
        toggleSayAgain(root, true);
        diagRecordVerdict('Nothing heard (low confidence)', '');
        return;
    }

    // Every verdict reveals the letter-by-letter accounting on the boxes:
    // green for matched, rose for swapped, ghost for never-heard, "+N" when
    // the decoder added letters it shouldn't have.
    renderVerdictBoxes(root, target, heard);

    if (got === expected) {
        showVerdict(root, 'great', 'Perfect', 'correct', heard);
        diagRecordVerdict('Perfect', heard);
        return;
    }

    const dist = levenshtein(got, expected);
    const maxLen = Math.max(got.length, expected.length);
    // Round-12 leniency: a 40MB open-vocab model drifts real speech to a more
    // common sound-alike a couple of letters off ("stroll" -> "strong"). Grade
    // a near-miss as Close - not a flat Wrong - when it keeps the target's
    // onset AND is within ~35% edit distance, OR its consonant skeleton is
    // within two consonants ("match"->"much"). A clearly different word
    // ("kitten"->"elephant") starts on a different letter and still reads Wrong.
    const onsetMatch = got[0] === expected[0];
    const closeEnough = onsetMatch
        && (dist <= 1 || (maxLen > 0 && dist / maxLen <= 0.35))
        || skeletonClose(got, expected);

    if (closeEnough) {
        showVerdict(root, 'close', 'Close', 'select', heard);
        diagRecordVerdict('Close', heard);
    } else {
        showVerdict(root, 'wrong', 'Try again', 'wrong', heard);
        diagRecordVerdict('Try again', heard);
    }
}

function ensureWordsConf(message) {
    const words = Array.isArray(message?.result?.result) ? message.result.result : [];
    const confs = words.map((w) => Number(w?.conf)).filter((c) => Number.isFinite(c));
    return {
        words,
        minConf: confs.length ? Math.min(...confs) : null,
        avgConf: confs.length ? confs.reduce((sum, c) => sum + c, 0) / confs.length : null,
    };
}

// Vosk can emit several final results in one attempt (one per endpoint). Pick
// the final that best matches the target, not the last one - a trailing
// noise-final must never overwrite the word the learner actually said.
function bestFinal(finals, target) {
    const expected = normalize(target);
    let best = null;
    let bestScore = -1;
    for (const item of finals) {
        const got = normalize(item.text);
        if (!got) continue;
        const maxLen = Math.max(expected.length, got.length);
        const score = maxLen ? 1 - levenshtein(expected, got) / maxLen : 0;
        if (score > bestScore || (score === bestScore && (item.minConf ?? -1) > (best?.minConf ?? -1))) {
            best = item;
            bestScore = score;
        }
    }
    return best;
}

function startOffline(root, target) {
    diagLogEvent('offline-start', target);
    setListening(root, true);
    showMessage(root, offlineState === 'ready' ? 'Listening - say the word now.' : 'Loading the speech model…');

    const token = ++offlineToken;
    let disposed = false;
    let ctx = null;
    let processor = null;
    let source = null;
    let mute = null;
    let recognizer = null;

    // Round-11: a proper noun / out-of-vocabulary target cannot be recognised
    // by the small model at any confidence, so the check is graded gently
    // (encouragement + "Hear it") instead of an endless, unfair "Try again".
    const oov = root.dataset.oov === '1';
    const finals = [];
    let partialText = '';
    const exactBlob = { count: 0, text: '' };
    let lastPulseAt = 0;
    let lastDiagAt = 0;
    const vad = {
        startupMs: 0,
        recMs: 0,
        speechMs: 0,
        speechEndRecMs: 0,
        inSpeech: false,
        contiguousMs: 0,
        gapMs: 0,
        floorRms: 0,
        peakRms: 0,
        speechPeakRms: 0,
    };

    // The recognizer always receives the RAW float frame - the exact round-6..9
    // signal path that decoded reliably. A hand-rolled DSP front-end (60 Hz
    // high-pass + AGC + soft clip) was tried here in round 10 to reduce decode
    // drift and equalise quiet/loud speech, and it was REVERTED: the hand-tuned
    // in-browser filter could silently mute the whole microphone, so the only
    // "conditioning" left is a cheap strided frame-RMS read that feeds BOTH
    // the voice-activity gate and the live pulse meter.

    const trackSpeech = (rms, frameMs) => {
        vad.peakRms = Math.max(vad.peakRms, rms);

        if (vad.startupMs < STARTUP_DISCARD_MS) {
            vad.startupMs += frameMs;
            return;
        }

        vad.recMs += frameMs;

        // Adaptive ambient floor: slow decay toward the quietest recent frames
        // while nobody is (yet) talking, so steady hum stays "floor". Re-floor
        // upward in a loud room if no speech has been found in time.
        if (!vad.inSpeech) {
            if (vad.floorRms === 0) {
                vad.floorRms = rms;
            } else {
                vad.floorRms = vad.floorRms * 0.93 + Math.min(rms, vad.floorRms) * 0.07;
            }
            if (vad.recMs > REEFLOOR_MS) {
                vad.floorRms = Math.max(vad.floorRms, rms * 0.6);
            }
        }

        const isSpeechFrame = rms >= ABS_ROOT_FLOOR && rms >= vad.floorRms * OVER_FLOOR_RATIO;

        if (isSpeechFrame) {
            vad.gapMs = 0;
            vad.contiguousMs += frameMs;
            vad.speechPeakRms = Math.max(vad.speechPeakRms, rms);
            if (vad.contiguousMs >= MIN_SPEECH_MS) {
                vad.inSpeech = true;
            }
            if (vad.inSpeech) {
                vad.speechMs += frameMs;
                vad.speechEndRecMs = vad.recMs;
            }
        } else {
            // Micro-pauses inside a word (~140ms) don't reset the run; a real
            // silence gap does.
            vad.gapMs += frameMs;
            if (vad.gapMs > 140) {
                vad.contiguousMs = 0;
            }
        }
    };

    const teardown = () => {
        if (activeTimeout) {
            window.clearTimeout(activeTimeout);
            activeTimeout = null;
        }
        if (recognizer) {
            try { recognizer.remove(); } catch { /* already removed */ }
        }
        if (processor) {
            processor.onaudioprocess = null;
            try { processor.disconnect(); } catch { /* already disconnected */ }
        }
        if (source) {
            try { source.disconnect(); } catch { /* already disconnected */ }
        }
        if (mute) {
            try { mute.disconnect(); } catch { /* already disconnected */ }
        }
        if (ctx && ctx.state !== 'closed') {
            ctx.close().catch(() => {});
        }
        if (activeOffline?.token === token) {
            activeOffline = null;
        }
        setListening(root, false);
        releaseOfflineStream();
    };

    const dispose = () => {
        if (disposed) {
            return;
        }
        disposed = true;
        teardown();
    };

    const recordEnergy = () => {
        diagSpeechMs = Math.round(vad.speechMs);
        diagSpeechRatio = vad.recMs ? vad.speechMs / vad.recMs : 0;
        diagSpeechPeak = vad.speechPeakRms || vad.peakRms;
        diagAmbientRms = vad.floorRms;
    };

    const finish = () => {
        if (disposed) {
            return;
        }
        dispose();

        const expected = normalize(target);
        const trimmed = trimTrailingNoise(finals, vad, target);
        const best = bestFinal(trimmed, target);
        const speechOk = vad.speechMs >= MIN_SPEECH_MS;

        // Resolution order: an exact partial observed twice in a row beats a
        // final that drifted ("hello" heard the whole way, "hell no" in the
        // last endpoint) unless that final already reads the target exactly;
        // otherwise the best final wins, then any energy-backed partial. The
        // source is surfaced in the STT debug pane so decode drift is
        // auditable (final / final·trimmed / partial·exact / partial).
        let heard = '';
        let source = 'none';
        const exactReady = speechOk && exactBlob.count >= EXACT_PARTIAL_MIN_OBSERVATIONS ? exactBlob.text : '';
        if (exactReady && (!best || normalize(best.text) !== expected)) {
            heard = exactBlob.text;
            source = 'partial·exact';
        } else if (best) {
            heard = best.text;
            source = best.trimmed ? 'final·trimmed' : 'final';
        } else if (speechOk && partialText) {
            heard = partialText;
            source = 'partial';
        }

        recordEnergy();
        diagFinalWords = best?.words?.length ? best.words.map((w) => ({ word: w.word, conf: w.conf })) : null;
        diagSource = source;
        diagLogEvent('finish', `speech ${diagSpeechMs}ms · ratio ${diagSpeechRatio.toFixed(2)} · peak ${diagSpeechPeak.toFixed(4)} · floor ${diagAmbientRms.toFixed(4)}`);

        // A muted/dead mic is not a failed attempt - say so in plain words.
        if (!vad.inSpeech && vad.peakRms < MIC_MUTED_RMS) {
            clearAttemptVisuals(root);
            showMessage(root, 'Mic seems off - check the device and try again.');
            toggleSayAgain(root, true);
            diagRecordVerdict('Mic seems off', '');
            return;
        }

        if (!heard) {
            clearAttemptVisuals(root);
            showMessage(root, 'Nothing heard - try again.');
            toggleSayAgain(root, true);
            diagRecordVerdict('Nothing heard', '');
            return;
        }

        // Proper-noun / out-of-vocabulary target: the model physically cannot
        // verify it, so once genuine speech was captured grade it gently and
        // move on. Placed before the confidence gate so a name never leans on
        // the CONF_FLOOR it can never satisfy; `speechOk` still guards against
        // a noise blip dressing up as "Nice". "Mic seems off" (above) wins.
        if (oov && speechOk) {
            clearAttemptVisuals(root);
            showVerdict(root, 'great', 'Nice', 'correct', '');
            diagRecordVerdict('Nice (proper noun)', heard);
            return;
        }

        // A transcript without real speech energy behind it - or a final below
        // the confidence floor - is decoder noise, never a verdict. The floor
        // is measured on the final's mean confidence: min-conf would unfairly
        // penalise multi-word targets where a weak function word scores low.
        // Confidence only exists on finals; an exact-partial read is trusted
        // on its energy alone.
        const finalSource = source === 'final' || source === 'final·trimmed';
        if (!speechOk || (finalSource && best && best.avgConf !== null && best.avgConf < CONF_FLOOR)) {
            clearAttemptVisuals(root);
            showMessage(root, best ? 'Heard something unclear - try again, a bit louder or clearer.' : 'Nothing heard - try again.');
            toggleSayAgain(root, true);
            diagRecordVerdict('Nothing heard (low confidence)', heard);
            return;
        }

        diagLogEvent('end', `heard: ${heard} · source: ${source}`);
        evaluate(root, target, heard);
    };

    activeOffline = { token, dispose, finish };

    (async () => {
        let model;
        try {
            model = await ensureOfflineModel();
        } catch (error) {
            diagLogEvent('offline-error', 'model-unavailable');
            dispose();
            showMessage(root, "Speech isn't ready on this device yet — try again in a moment.");
            return;
        }
        if (disposed) return;
        if (offlineState === 'ready') {
            showMessage(root, 'Listening - say the word now.');
        }

        let stream;
        try {
            stream = await ensureOfflineStream();
        } catch (error) {
            diagLogEvent('offline-error', error?.name ?? 'mic-error');
            dispose();
            showOfflineMicTrouble(root, error);
            return;
        }
        if (disposed) return;

        ctx = new AudioContext({ latencyHint: 'interactive' });
        if (ctx.state === 'suspended') {
            try { await ctx.resume(); } catch { /* stays suspended; nothing more to do */ }
        }
        if (disposed) return;

        recognizer = new model.KaldiRecognizer(ctx.sampleRate);
        recognizer.setWords(true);

        recognizer.on('result', (message) => {
            if (disposed) return;
            const text = (message?.result?.text ?? '').trim();
            if (!text) return;
            const item = ensureWordsConf(message);
            finals.push({ text, words: item.words, minConf: item.minConf, avgConf: item.avgConf });
            diagFinal = text;
            diagHeard = text;
            diagResults += 1;
            diagFinalSeen = true;
            diagLogEvent('result', `${text}${item.minConf != null ? ` · conf ${item.minConf.toFixed(2)}` : ' · conf n/a'}`);
        });

        recognizer.on('partialresult', (message) => {
            if (disposed) return;
            const partial = (message?.result?.partial ?? '').trim();
            if (!partial) return;
            partialText = partial;
            diagInterim = partial;
            diagLogEvent('partial', partial);
            if (!vad || vad.startupMs < STARTUP_DISCARD_MS) {
                return; // ignore anything recognized during the startup discard
            }
            // Consecutive exact partials are strong evidence the target IS
            // being spoken - keep them so a later drifted final ("hell no")
            // cannot overwrite the word the learner actually said.
            if (normalize(partial) === normalize(target)) {
                exactBlob.count += 1;
                exactBlob.text = partial;
            } else {
                exactBlob.count = 0;
                exactBlob.text = '';
            }
            updateBoxesForHeard(root, target, partial);
        });

        processor = ctx.createScriptProcessor(4096, 1, 1);
        processor.onaudioprocess = (event) => {
            if (disposed) return;
            try {
                const buffer = event.inputBuffer;
                const data = buffer.getChannelData(0);
                const frameMs = (data.length / buffer.sampleRate) * 1000;
                const now = performance.now();
                // Raw frame RMS, strided over the block - the same read that
                // drove the VAD through rounds 6-9 and decoded reliably. The
                // recognizer gets the same raw frame, untouched.
                const stride = Math.max(1, Math.floor(data.length / 512));
                let sum = 0;
                for (let i = 0; i < data.length; i += stride) {
                    const v = data[i];
                    sum += v * v;
                }
                const voiceRms = Math.sqrt(sum / Math.max(1, Math.ceil(data.length / stride)));

                recognizer.acceptWaveformFloat(data, buffer.sampleRate);
                trackSpeech(voiceRms, frameMs);
                // Live pulse meter + debug level, throttled independently so
                // the DOM isn't repainted on every 4096-sample frame.
                const level = Math.min(
                    METER_LEVELS,
                    Math.max(0, Math.round(METER_LEVELS * Math.min(1, voiceRms / METER_REF_RMS))),
                );
                if (now - lastPulseAt >= METER_PUBLISH_MS) {
                    lastPulseAt = now;
                    setMeterLevel(root, level);
                }
                if (now - lastDiagAt >= DIAG_PUBLISH_MS) {
                    lastDiagAt = now;
                    diagMeterLevel = level;
                }
            } catch { /* late after dispose */ }
        };
        source = ctx.createMediaStreamSource(stream);
        source.connect(processor);
        // ScriptProcessorNode only ticks when its output is connected, and its
        // output mirrors its input - route it through a silent gain so the
        // student's own voice is never echoed to the speakers.
        mute = ctx.createGain();
        mute.gain.value = 0;
        processor.connect(mute);
        mute.connect(ctx.destination);

        activeTimeout = window.setTimeout(() => finish(), LISTEN_MS);
    })();
}

function start(root) {
    if (!offlineReadyOnRoot(root)) {
        return;
    }

    const target = (root.dataset.pronounceCheck ?? '').trim();
    if (!target) {
        return;
    }

    // One microphone at a time - steal from any older still-running check.
    stopPronounceCheck();

    // Never play TTS into the mic: cancel browser speech AND pause any
    // <audio> narration, or the app's own audio would feed the recognizer.
    if ('speechSynthesis' in window) {
        window.speechSynthesis.cancel();
    }
    document.querySelectorAll('audio').forEach((audio) => {
        if (!audio.paused) {
            try { audio.pause(); } catch { /* already stopped */ }
        }
    });

    diagResetAttempt(root, target);

    // Every new attempt starts clean: drop the old chip, helper line and
    // letter-box highlights so nothing looks stale.
    hideTransient(root);
    resetBoxes(root);
    toggleSayAgain(root, false);

    startOffline(root, target);
}

/**
 * Live snapshot of the module's real STT state, consumed by the admin
 * STT debug pane (`state/stt-debug.js`). Never mutates anything here.
 */
export function speechDebugSnapshot() {
    return {
        api: 'offline',
        secure: window.isSecureContext,
        host: window.location.host,
        engine: 'offline',
        offline: offlineState,
        listening: activeOffline !== null,
        elapsed: activeOffline && diagStartedAt ? (Date.now() - diagStartedAt) / 1000 : 0,
        since: diagStartedAt,
        target: diagTarget,
        transcript: diagInterim,
        final: diagFinal,
        finalWords: diagFinalWords,
        speechMs: diagSpeechMs,
        speechRatio: diagSpeechRatio,
        speechPeak: diagSpeechPeak,
        ambientRms: diagAmbientRms,
        results: diagResults,
        finalSeen: diagFinalSeen,
        listens: diagListens,
        error: diagError,
        verdict: diagVerdict ? { ...diagVerdict } : null,
        meterLevel: diagMeterLevel,
        source: diagSource,
        widgets: document.querySelectorAll('[data-pronounce-check]').length,
        bound: document.querySelectorAll('[data-pronounce-check][data-speech-bound]').length,
        activeRootListening: diagRoot ? diagRoot.dataset.listening === 'true' : false,
        log: diagLog.map((e) => ({ ...e })),
    };
}