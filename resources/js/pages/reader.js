/**
 * Student reader — continuous-scroll mode (webnovel style).
 * Reading works without any of this; the module adds:
 *
 *  - Scroll-depth tracking with thin progress bar + FINISH activation at 95%.
 *  - Aa slide-panel: font size, font family.
 *  - ⋮ menu slide panel: mini story card + report-issue button (opens modal).
 *  - Multi-segment narration with sentence-level highlight follow-along.
 *  - Preferences persistence: localStorage immediately, debounced PATCH.
 *  - Word-definition pane with offline lookups, LRU memo, circuit breaker,
 *    and two actions: save to Study, or open the word in the full dictionary
 *    (returning to this exact reading spot afterwards).
 *  - Heartbeat: 20s cadence while visible, clamped deltas, keepalive flush.
 *    FINISH waits for one final flush beat so the last reading window is
 *    banked before the summary page settles the story-clear reward.
 *  - Live task/stamp notifications: raised as the shared bottom toast, fed by
 *    heartbeat deltas (E).
 */

import { play } from '../game/sfx.js';
import { scorePopup } from '../game/juice.js';
import { initMascot, mascotReact } from '../game/mascot.js';
import { initJournal } from '../game/journal.js';

const BEAT_INTERVAL_MS = 20000;
const MAX_BEAT_SECONDS = 60;
const PREFS_KEY = 'alice.reader.prefs';
const BEAT_ORIGIN_KEY = 'alice.reader.beatOrigin';
const WORDS_CACHE_KEY = 'alice.words.memo';
const BREAKER_FAILS = 5;
const BREAKER_COOLDOWN_MS = 30000;
const HOVER_OPEN_MS = 250;
const PRESS_OPEN_MS = 420;
const FINISH_THRESHOLD = 95;

const FONT_SIZES = [15, 16, 17, 18, 19];

const DEFAULT_PREFS = { font: 'default', fontSize: 2 };

let csrf = '';
let heartbeatUrl = '';
let prefsUrl = '';
let storySlug = '';
let savedWords = new Set();
let beatOrigin = Date.now();
let readingActive = false;
let timer = null;

let memo = new Map();
let consecutiveFails = 0;
let cooldownUntil = 0;

/* Stage state */
let stageEl = null;
let paperEl = null;

/* Scroll tracking */
let lastScrollPct = 0;
let scrollSaveTimer = null;

/* Preferences */
let prefs = { ...DEFAULT_PREFS };
let prefsDebounce = null;

/* Definition pane state */
let openedBy = null;
let hoverTimer = null;
let hoverCloseTimer = null;
let pressTimer = null;

const POPUP_GUARD = '#word-popup, #aa-panel, #aa-backdrop, #menu-panel, #menu-backdrop';

/* ========================= multi-segment narration ======================= */

const NARRATION_SPEEDS = [0.75, 1, 1.25];
let narrationSegments = [];
let narrationAudio = null;
let narrSpeedIndex = NARRATION_SPEEDS.indexOf(1);
let narrRafId = 0;

export default function init() {
    const shell = document.getElementById('reader-shell');
    const main = document.getElementById('reader-main');

    if (!main) {
        return;
    }

    csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    prefsUrl = shell?.dataset.prefsUrl ?? '';
    storySlug = shell?.dataset.storySlug ?? '';

    readState(main);
    markSavedWordsInText();
    bindAaPanel();
    bindMenuPanel();
    bindReportModal();
    bind();
    startHeartbeat();
    setupStage(main);
    restorePrefs();
    applyPrefs();
    initNarration(main);
    restoreScrollPosition();
    setupReaderDebug(shell);
    initMascot();
    initJournal();

    if (paperEl) {
        paperEl.addEventListener('scroll', onScroll, { passive: true });
    }

    if (window.history && window.history.replaceState) {
        window.history.replaceState({}, '', window.location.href);
    }
}

function readState(main) {
    heartbeatUrl = main.dataset.heartbeatUrl ?? '';

    try {
        savedWords = new Set(JSON.parse(main.dataset.savedWords ?? '[]'));
        memo = new Map(JSON.parse(sessionStorage.getItem(WORDS_CACHE_KEY) ?? '[]'));
    } catch {
        savedWords = new Set();
        memo = new Map();
    }

    // A fresh load is a new reading visit: always start timing from "now" so a
    // stale beatOrigin left over from a previous story / earlier trip through
    // this tab can never grant a lump of time on the very first beat. In-page
    // pause/resume is handled by the focus/visibility handlers, which reset
    // beatOrigin to Date.now() on the way back in.
    beatOrigin = Date.now();
    persistBeatOrigin();
}

/* ====================== scroll tracking + persistence ==================== */

function scrollKey() {
    return `alice.reader.scroll:${storySlug}`;
}

function onScroll() {
    updateScrollProgress();
    saveScrollPosition();
}

function updateScrollProgress() {
    if (!paperEl) return;

    // Actually scrolling into the story is proof the reader is reading.
    if (paperEl.scrollTop > 0) {
        readingActive = true;
    }

    const maxScroll = paperEl.scrollHeight - paperEl.clientHeight;
    if (maxScroll <= 0) {
        setProgressBar(100);
        return;
    }

    const pct = Math.round((paperEl.scrollTop / maxScroll) * 100);
    lastScrollPct = Math.max(lastScrollPct, pct);
    setProgressBar(pct);
    updateFinishButton(pct);
    checkMilestones(pct);
}

/* Reading milestones: one celebration each at 25/50/75%. Alice reacts and a
   bubble appears above the bar, then everything settles back. The restored
   scroll position seeds lastMilestone so a resume never spams old bubbles. */
let lastMilestone = 0;
const MILESTONES = [[75, 'Almost done!'], [50, 'Halfway there!'], [25, 'Good start!']];

function checkMilestones(pct) {
    const hit = MILESTONES.find(([m]) => pct >= m && lastMilestone < m);
    if (!hit) return;
    lastMilestone = hit[0];
    showMascotBubble(hit[1]);
    mascotReact('walk');
}

let bubbleTimer = 0;

function showMascotBubble(text) {
    const bubble = document.getElementById('reader-mascot-bubble');
    if (!bubble) return;
    bubble.textContent = text;
    bubble.classList.add('show');
    clearTimeout(bubbleTimer);
    bubbleTimer = setTimeout(() => bubble.classList.remove('show'), 3000);
}

function setProgressBar(pct) {
    const bar = document.getElementById('scroll-progress');
    if (bar) bar.style.width = pct + '%';
}

function updateFinishButton(pct) {
    const btn = document.querySelector('[data-finish-btn]');
    if (!btn) return;

    if (pct >= FINISH_THRESHOLD) {
        btn.classList.remove('opacity-30', 'pointer-events-none');
    } else {
        btn.classList.add('opacity-30', 'pointer-events-none');
    }
}

function saveScrollPosition() {
    if (!paperEl) return;
    clearTimeout(scrollSaveTimer);
    scrollSaveTimer = setTimeout(() => {
        try {
            localStorage.setItem(scrollKey(), String(Math.round(paperEl.scrollTop)));
        } catch { /* */ }
    }, 300);
}

function restoreScrollPosition() {
    if (!paperEl) return;

    // Server-authoritative resume (F): story_progress.current_page is a scroll
    // percentage (0-100) tracked by the heartbeat, so "Continue reading" and a
    // cross-device reopen both resume exactly where the student left off.
    const initialPct = parseInt(document.getElementById('reader-main')?.dataset.initialPct ?? '0', 10);
    lastMilestone = initialPct;
    const maxScroll = paperEl.scrollHeight - paperEl.clientHeight;
    let target = 0;

    if (initialPct > 0 && maxScroll > 0) {
        target = Math.min(maxScroll, Math.round((initialPct / 100) * maxScroll));
    } else {
        // Fallback for older sessions without a server pct: local bookmark.
        try {
            const saved = parseInt(localStorage.getItem(scrollKey()) ?? '0', 10);
            if (saved > 0) target = Math.min(maxScroll, saved);
        } catch { /* */ }
    }

    if (target > 0) {
        paperEl.scrollTop = target;
    }
    // Sync progress bar + finish button after restore.
    updateScrollProgress();
}

function persistBeatOrigin() {
    sessionStorage.setItem(BEAT_ORIGIN_KEY, String(beatOrigin));
}

/* ============================== heartbeat =============================== */

function startHeartbeat() {
    if (!heartbeatUrl) {
        return;
    }

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            sendBeat(true);
        } else {
            beatOrigin = Date.now();
            persistBeatOrigin();
            readingActive = true;
        }
    });

    window.addEventListener('pagehide', () => sendBeat(true));

    // Reading time is "time the reader is visible in the foreground". If the
    // whole window loses focus (switch app / click elsewhere) pause the timer
    // so an unfocused-but-tab-visible reader does not keep accruing minutes.
    window.addEventListener('blur', () => sendBeat(true));
    window.addEventListener('focus', () => {
        beatOrigin = Date.now();
        persistBeatOrigin();
        readingActive = true;
    });

    timer = setInterval(tickBeat, BEAT_INTERVAL_MS);
}

function tickBeat() {
    if (document.visibilityState !== 'visible') {
        return;
    }
    // Don't bank time before real reading has begun (loading the page / sitting
    // on it without reading). readingActive flips true on the first scroll, word
    // tap, or on returning to a focused/visible tab.
    if (!readingActive) {
        return;
    }
    sendBeat(false);
}

function sendBeat(flush) {
    const now = Date.now();

    if (!heartbeatUrl) {
        return Promise.resolve();
    }

    // Reading time = elapsed time since the last beat while the reader was
    // active and visible (foreground). A single beat only ever reports the
    // window since the previous beat, capped at one beat interval, so long idle
    // gaps or a frozen tab can never be granted as one lump on the next beat —
    // time accrues in real seconds as the reader actually reads. MAX_BEAT_SECONDS
    // remains the absolute upper bound a beat may ever claim.
    const elapsedSeconds = Math.min(
        Math.max((now - beatOrigin) / 1000, 0),
        BEAT_INTERVAL_MS / 1000,
    );

    if (!flush && elapsedSeconds < 1) {
        return Promise.resolve();
    }

    beatOrigin = now;
    persistBeatOrigin();

    // Resolves with the server payload so callers (FINISH flush below) can
    // await the beat before navigating away.
    return fetch(heartbeatUrl, {
        method: 'POST',
        keepalive: flush,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrf,
        },
        body: JSON.stringify({
            scroll_pct: lastScrollPct,
            seconds_delta: Math.round(elapsedSeconds),
        }),
    })
        .then((response) => response.json().catch(() => ({})))
        .then((data) => {
            if (!flush) handleLiveNotifications(data);
            return data;
        })
        .catch(() => { /* a dropped beat costs at most one capped window */ });
}

/* ======================= admin view-as timer debug ======================= */

/**
 * Admin-only live readout of the reading timer. Only mounted when the server
 * marks this reader with data-debug-actor="admin" — i.e. an administrator is
 * previewing this session via "View As". A normal student never sees it.
 *
 * It exposes the module's real timer state (not a re-implementation) so the
 * admin can watch reading time accrue in real seconds and verify the
 * heartbeat reports only actual elapsed time. "BEAT NOW" issues a flush
 * heartbeat (same code path as leaving / marking finished) to observe the
 * small delta it sends.
 */
function setupReaderDebug(shell) {
    if (!shell || shell.dataset.debugActor !== 'admin') {
        return;
    }

    window.__readerDebug = () => ({
        readingActive,
        beatOrigin,
        elapsed: Math.min(Math.max((Date.now() - beatOrigin) / 1000, 0), BEAT_INTERVAL_MS / 1000),
        lastScrollPct,
        visibility: document.visibilityState,
        hasFocus: document.hasFocus(),
    });

    const panel = document.createElement('div');
    panel.setAttribute('data-reader-debug', '');
    panel.style.cssText = 'position:fixed;left:8px;bottom:8px;z-index:80;padding:10px 12px;'
        + 'background:#2D231E;color:#FBBF24;border:1px solid #FBBF24;border-radius:6px;'
        + 'font-family:"Quicksand",sans-serif;font-size:11px;line-height:1.55;'
        + 'box-shadow:0 1px 3px rgba(0,0,0,0.35);max-width:240px;';
    panel.innerHTML = ''
        + '<div style="font-size:9px;font-weight:700;letter-spacing:0.02em;color:#fff;margin-bottom:4px;">READER TIMER DEBUG</div>'
        + '<div data-k="active">ACTIVE -</div>'
        + '<div data-k="delta">DELTA 0.0s</div>'
        + '<div data-k="scroll">SCROLL 0%</div>'
        + '<div data-k="vis">VIS visible</div>'
        + '<button type="button" data-k="beat" style="margin-top:6px;width:100%;padding:6px;'
        + 'background:#FBBF24;color:#2D231E;border:0;border-radius:4px;font:inherit;font-weight:700;cursor:pointer;">BEAT NOW</button>';
    document.body.appendChild(panel);

    const active = panel.querySelector('[data-k="active"]');
    const delta = panel.querySelector('[data-k="delta"]');
    const scroll = panel.querySelector('[data-k="scroll"]');
    const vis = panel.querySelector('[data-k="vis"]');
    const beat = panel.querySelector('[data-k="beat"]');

    const repaint = () => {
        const s = window.__readerDebug();
        active.textContent = 'ACTIVE ' + (s.readingActive ? 'YES' : 'NO');
        delta.textContent = 'DELTA ' + s.elapsed.toFixed(1) + 's';
        scroll.textContent = 'SCROLL ' + Math.round(s.lastScrollPct) + '%';
        vis.textContent = 'VIS ' + s.visibility + ' FOCUS ' + (s.hasFocus ? 'Y' : 'N');
    };

    repaint();
    const interval = setInterval(repaint, 200);

    beat.addEventListener('click', () => {
        sendBeat(true);
        setTimeout(repaint, 60);
    });

    window.addEventListener('pagehide', () => clearInterval(interval), { once: true });
}

/* ========================= live notifications =========================== */

/**
 * Raise a live heartbeat notification (Phase E) as the shared bottom toast.
 * Priority: a task crossing into claimable outranks the daily stamp. Works on
 * the same audio-aware rules as the rest of the app: SFX through the global
 * core (mute/reduced-motion aware), auto-dismiss, never blocking the reading
 * area. One toast at a time (showToast replaces any visible one).
 */
function handleLiveNotifications(data) {
    if (!data || typeof data !== 'object') return;

    const completedNow = Array.isArray(data.completed_now) ? data.completed_now : [];
    const stampedNow = data.stamped_now === true;
    const freezeUsed = data.freeze_used === true;
    const storyCleared = data.story_cleared === true;

    if (freezeUsed && window.showToast) {
        window.showToast('A Streak Restore shielded your streak after a missed day.', { tone: 'freeze' });
    }

    let tone = 'freeze';
    let title = '';
    let cue = '';

    if (completedNow.length > 0) {
        tone = 'task';
        title = 'Task complete — claim your reward on the hub.';
        cue = 'claim';
    } else if (storyCleared) {
        tone = 'story';
        title = 'Story finish! You earned 3 keys.';
        cue = 'coin';
    } else if (stampedNow && !freezeUsed) {
        tone = 'stamp';
        title = 'Your reading streak is safe for today.';
        cue = 'stamp';
    } else {
        return;
    }

    // Unified notification surface: the shared bottom toast (same look, tone
    // and accessibility as the quiz/games/rewards), replacing the old top slot.
    if (window.showToast) {
        window.showToast(title, { tone, duration: 4500 });
    }

    if (cue) play(cue);
}

/* ======================== stage setup =================================== */

function setupStage(scope) {
    stageEl = scope.querySelector('#reader-stage');
    paperEl = scope.querySelector('#reader-paper');

    if (!stageEl || !paperEl) {
        return;
    }

    // Press-and-hold for dictionary (desktop hover, mobile hold).
    stageEl.addEventListener('pointerdown', onPressStart);
    for (const type of ['pointerup', 'pointercancel', 'pointerleave']) {
        stageEl.addEventListener(type, cancelPress);
    }
    stageEl.addEventListener('pointermove', (event) => {
        if (pressTimer === null) return;
        if (event.movementX !== undefined && Math.abs(event.movementX) + Math.abs(event.movementY) > 6) {
            cancelPress();
        }
    });
    stageEl.addEventListener('contextmenu', (event) => event.preventDefault());

    if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
        stageEl.addEventListener('mouseover', onWordHover);
        stageEl.addEventListener('mouseout', onWordOut);
    }
}

/* ====================== press-and-hold dictionary ====================== */

function onPressStart(event) {
    const word = event.target.closest?.('.tap-word');
    if (!word || event.button !== 0) return;

    clearTimeout(hoverCloseTimer);
    pressTimer = setTimeout(() => {
        pressTimer = null;
        openPopup(word, 'press');
    }, PRESS_OPEN_MS);
}

function cancelPress() {
    clearTimeout(pressTimer);
    pressTimer = null;
}

function onWordHover(event) {
    const word = event.target.closest?.('.tap-word');
    if (!word) return;

    clearTimeout(hoverTimer);
    hoverTimer = setTimeout(() => openPopup(word, 'hover'), HOVER_OPEN_MS);
}

function onWordOut(event) {
    if (!event.target.closest?.('.tap-word')) return;

    clearTimeout(hoverTimer);
    if (openedBy === 'hover') {
        clearTimeout(hoverCloseTimer);
        hoverCloseTimer = setTimeout(() => hidePopup(), 180);
    }
}

/* ========================= Aa slide panel =============================== */

function bindAaPanel() {
    const backdrop = document.getElementById('aa-backdrop');
    const panel = document.getElementById('aa-panel');
    if (!backdrop || !panel) return;

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-aa-open]')) {
            openAaPanel();
            return;
        }
        if (event.target.closest('[data-aa-close]') || event.target === backdrop) {
            closeAaPanel();
            return;
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.classList.contains('pointer-events-none')) {
            closeAaPanel();
        }
    });

    initAaPanel(panel);
}

function openAaPanel() {
    const backdrop = document.getElementById('aa-backdrop');
    const panel = document.getElementById('aa-panel');
    if (!backdrop || !panel) return;

    closeMenuPanel();

    backdrop.classList.remove('pointer-events-none');
    backdrop.classList.add('pointer-events-auto');
    void backdrop.offsetHeight;
    backdrop.classList.remove('opacity-0');
    backdrop.classList.add('opacity-100');

    panel.classList.remove('pointer-events-none');
    panel.classList.add('pointer-events-auto');
    void panel.offsetHeight;
    panel.classList.remove('translate-x-full');
    panel.classList.add('translate-x-0');

    syncAaPanelStates();
}

function closeAaPanel() {
    const backdrop = document.getElementById('aa-backdrop');
    const panel = document.getElementById('aa-panel');
    if (!backdrop || !panel) return;

    backdrop.classList.remove('opacity-100');
    backdrop.classList.add('opacity-0');
    backdrop.classList.remove('pointer-events-auto');
    backdrop.classList.add('pointer-events-none');

    panel.classList.remove('translate-x-0');
    panel.classList.add('translate-x-full');
    panel.classList.remove('pointer-events-auto');
    panel.classList.add('pointer-events-none');
}

/* ========================= ⋮ menu slide panel ========================== */

function bindMenuPanel() {
    const backdrop = document.getElementById('menu-backdrop');
    const panel = document.getElementById('menu-panel');
    if (!backdrop || !panel) return;

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-menu-open]')) {
            openMenuPanel();
            return;
        }
        if (event.target.closest('[data-menu-close]') || event.target === backdrop) {
            closeMenuPanel();
            return;
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.classList.contains('pointer-events-none')) {
            closeMenuPanel();
        }
    });
}

function openMenuPanel() {
    const backdrop = document.getElementById('menu-backdrop');
    const panel = document.getElementById('menu-panel');
    if (!backdrop || !panel) return;

    closeAaPanel();

    backdrop.classList.remove('pointer-events-none');
    backdrop.classList.add('pointer-events-auto');
    void backdrop.offsetHeight;
    backdrop.classList.remove('opacity-0');
    backdrop.classList.add('opacity-100');

    panel.classList.remove('pointer-events-none');
    panel.classList.add('pointer-events-auto');
    void panel.offsetHeight;
    panel.classList.remove('translate-x-full');
    panel.classList.add('translate-x-0');
}

function closeMenuPanel() {
    const backdrop = document.getElementById('menu-backdrop');
    const panel = document.getElementById('menu-panel');
    if (!backdrop || !panel) return;

    backdrop.classList.remove('opacity-100');
    backdrop.classList.add('opacity-0');
    backdrop.classList.remove('pointer-events-auto');
    backdrop.classList.add('pointer-events-none');

    panel.classList.remove('translate-x-0');
    panel.classList.add('translate-x-full');
    panel.classList.remove('pointer-events-auto');
    panel.classList.add('pointer-events-none');
}

/* ========================= report issue modal =========================== */

function reportModalEl() {
    return document.getElementById('report-modal');
}

function bindReportModal() {
    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-report-open]')) {
            openReportModal();
            return;
        }
        if (event.target.closest('[data-report-close]')) {
            closeReportModal();
            return;
        }
        if (event.target === reportModalEl()) {
            closeReportModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && reportModalOpen()) {
            closeReportModal();
        }
    });

    reportModalEl()?.querySelector('[data-report-form]')?.addEventListener('submit', (event) => {
        event.preventDefault();
        submitReport(event.currentTarget);
    });
}

function reportModalOpen() {
    const modal = reportModalEl();
    return !!modal && !modal.classList.contains('hidden');
}

function openReportModal() {
    const modal = reportModalEl();
    if (!modal) return;

    closeMenuPanel();
    closeAaPanel();

    modal.querySelector('[data-report-form]')?.classList.remove('hidden');
    modal.querySelector('[data-report-success]')?.classList.add('hidden');
    modal.querySelector('[data-report-error]')?.classList.add('hidden');

    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeReportModal() {
    const modal = reportModalEl();
    if (!modal) return;

    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

async function submitReport(form) {
    const shell = document.getElementById('reader-shell');
    const url = shell?.dataset.reportUrl ?? '';
    if (!url) return;

    const modal = reportModalEl();
    const errEl = modal?.querySelector('[data-report-error]');
    const submitBtn = form.querySelector('[type="submit"]');
    errEl?.classList.add('hidden');
    if (submitBtn) submitBtn.disabled = true;

    const body = {
        category: form.querySelector('[name="category"]')?.value ?? '',
        description: form.querySelector('[name="description"]')?.value ?? '',
    };

    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify(body),
        });

        if (res.ok) {
            form.classList.add('hidden');
            modal?.querySelector('[data-report-success]')?.classList.remove('hidden');
            return;
        }

        let message = 'Something went wrong. Please try again.';
        if (res.status === 422) {
            const data = await res.json().catch(() => ({}));
            message = Object.values(data.errors ?? {})[0]?.[0] ?? 'Please check your answers.';
        }
        if (errEl) {
            errEl.textContent = message;
            errEl.classList.remove('hidden');
        }
    } catch {
        if (errEl) {
            errEl.textContent = 'Something went wrong. Please try again.';
            errEl.classList.remove('hidden');
        }
    } finally {
        if (submitBtn) submitBtn.disabled = false;
    }
}

/* ======================== Aa panel internals ============================ */

function initAaPanel(panel) {
    const sizeSlider = panel.querySelector('[data-pref-fontsize-slider]');
    if (sizeSlider) {
        sizeSlider.addEventListener('input', () => {
            prefs.fontSize = Math.max(0, Math.min(FONT_SIZES.length - 1, parseInt(sizeSlider.value, 10) || 0));
            applyPrefs();
            syncAaPanelStates();
            persistPrefs();
        });
    }

    panel.querySelectorAll('[data-pref-font]').forEach((btn) => {
        btn.addEventListener('click', () => {
            prefs.font = btn.dataset.prefFont;
            applyPrefs();
            syncAaPanelStates();
            persistPrefs();
        });
    });
}

function syncAaPanelStates() {
    const panel = document.getElementById('aa-panel');
    if (!panel) return;

    const sizeLabel = panel.querySelector('[data-pref-fontsize-label]');
    if (sizeLabel) {
        sizeLabel.style.fontSize = FONT_SIZES[prefs.fontSize] + 'px';
    }

    const sizeSlider = panel.querySelector('[data-pref-fontsize-slider]');
    if (sizeSlider) {
        sizeSlider.value = String(prefs.fontSize);
    }

    panel.querySelectorAll('[data-pref-font]').forEach((btn) => {
        const isActive = btn.dataset.prefFont === prefs.font;
        btn.classList.toggle('bg-amber-400', isActive);
        btn.classList.toggle('border-[#2D231E]', isActive);
        btn.classList.toggle('border-transparent', !isActive);
    });
}

/* ======================== preferences persistence ======================= */

function restorePrefs() {
    try {
        const stored = JSON.parse(localStorage.getItem(PREFS_KEY) ?? '{}');
        prefs = { ...DEFAULT_PREFS, ...stored };
        prefs.fontSize = Math.max(0, Math.min(FONT_SIZES.length - 1, prefs.fontSize));
    } catch {
        prefs = { ...DEFAULT_PREFS };
    }
}

function applyPrefs() {
    const paper = document.getElementById('reader-paper');
    if (!paper) return;

    const FONT_CLASSES = ['font-reading-default', 'font-reading-lexend', 'font-reading-atkinson', 'font-reading-opendyslexic', 'font-reading-literata', 'font-reading-patrick'];
    const FONT_MAP = { default: 'font-reading-default', lexend: 'font-reading-lexend', atkinson: 'font-reading-atkinson', opendyslexic: 'font-reading-opendyslexic', literata: 'font-reading-literata', patrick: 'font-reading-patrick' };
    paper.classList.remove(...FONT_CLASSES);
    paper.classList.add(FONT_MAP[prefs.font] ?? 'font-reading-default');

    paper.style.fontSize = FONT_SIZES[prefs.fontSize] + 'px';
}

function persistPrefs() {
    try {
        localStorage.setItem(PREFS_KEY, JSON.stringify(prefs));
    } catch { /* private mode */ }

    clearTimeout(prefsDebounce);
    prefsDebounce = setTimeout(() => {
        if (!prefsUrl) return;
        fetch(prefsUrl, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({ preferences: prefs }),
        }).catch(() => { /* non-critical */ });
    }, 800);
}

/* =============================== binding ================================ */

function bind() {
    document.addEventListener('click', onClick);
    document.addEventListener('keydown', onKeydown);

    const box = popup();
    box?.addEventListener('mouseenter', () => clearTimeout(hoverCloseTimer));
    box?.addEventListener('mouseleave', () => {
        if (openedBy === 'hover') hidePopup();
    });

    document.addEventListener('pointerdown', (event) => {
        if (!popup()?.hidden && !event.target.closest('#word-popup, .tap-word, #aa-panel, #aa-backdrop, #menu-panel, #menu-backdrop')) {
            hidePopup();
        }
    });

    bindFinishFlush();

    // Battle-win sync: the journal dispatches this on victory. The bottom bar
    // stays stable (no FINISH appears there on battle stories); Alice points
    // to the journal's own FINISH STORY button instead.
    document.addEventListener('alice:battle-won', () => {
        showMascotBubble("Bruno's down! Finish the story inside the journal.");
        mascotReact('attack');
    });

    // First visit to a mission story: auto-open HOW TO FINISH once so the
    // rule lands before they start. Every other visit stays quiet; the ?
    // button still opens it anytime.
    try {
        if (document.querySelector('[data-journal-open]') && localStorage.getItem('alice-reader-goal-seen') !== '1') {
            localStorage.setItem('alice-reader-goal-seen', '1');
            setTimeout(() => document.querySelector('[data-modal-open="journal-help-modal"]')?.click(), 700);
        }
    } catch { /* storage off */ }
}

/**
 * Bank the last reading window before the FINISH form navigates away.
 *
 * Time is measured between heartbeats, so the seconds between the previous
 * beat and the FINISH tap used to be lost — a fast 30s read could settle as
 * near-zero time and trip the (now removed) session gate. FINISH now sends one
 * final flush beat (same keepalive path as pagehide) and only submits once it
 * resolves, bounded by a 1.5s race so navigation never hangs on a dead
 * network. A dropped flush costs at most one capped beat window.
 */
function bindFinishFlush() {
    if (!heartbeatUrl) {
        return;
    }

    const finishForm = document.querySelector('[data-finish-btn]')?.closest('form');
    if (!finishForm) {
        return;
    }

    finishForm.addEventListener('submit', (event) => {
        if (finishForm.dataset.flushing) {
            return;
        }

        event.preventDefault();
        finishForm.dataset.flushing = '1';

        Promise.race([
            sendBeat(true),
            new Promise((resolve) => window.setTimeout(resolve, 1500)),
        ])
            .catch(() => { /* dropped flush costs at most one capped window */ })
            .finally(() => finishForm.submit());
    });
}

function onClick(event) {
    const saveButton = event.target.closest?.('[data-slot="save"]');
    if (saveButton instanceof HTMLButtonElement && !saveButton.disabled) {
        saveWord(saveButton);
        return;
    }

    const dictButton = event.target.closest?.('[data-slot="dictionary"]');
    if (dictButton instanceof HTMLButtonElement && !dictButton.disabled) {
        openDictionary();
    }
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        hidePopup();
        closeAaPanel();
        closeMenuPanel();
        return;
    }

    if (event.key === 'Enter' || event.key === ' ') {
        const word = event.target.closest?.('.tap-word');
        if (word) {
            event.preventDefault();
            openPopup(word, 'key');
        }
    }
}

/* ======================== narration (multi-segment) ==================== */

/**
 * Build a sentence index over tap-word elements WITHOUT moving or wrapping
 * any DOM nodes.  Returns a 2-D array:
 *   sectionSentences[sectionIdx] = [{ words: [el…], charStart, charEnd }, …]
 *
 * charStart/charEnd are 0-based character offsets within the section text,
 * matching the `sentences` array in the narration manifest.  They weight each
 * sentence's share of playback time under the proportional model.
 *
 * The DOM wraps only word characters (`[\p{L}\p{N}'’–]+`) in each `tap-word`
 * span's `data-w`, so punctuation never lives on a span.  The manifest
 * `sentences` offsets (computed on the raw section text) are therefore the
 * authoritative boundary source: we split the raw text into word tokens with
 * their char positions, then assign each DOM `tap-word` to the sentence whose
 * char span covers that token.  DOM words and raw tokens line up 1:1 in order.
 */
const NARR_WORD_TOKEN = /[\p{L}\p{N}'’–]+/gu;
const NARR_TERMINAL = /[.!?…]\s*/g;

function splitSectionWords(rawText) {
    const tokens = [];
    NARR_WORD_TOKEN.lastIndex = 0;
    let m;
    while ((m = NARR_WORD_TOKEN.exec(rawText))) {
        tokens.push({ start: m.index, end: m.index + m[0].length, text: m[0] });
    }
    return tokens;
}

/** Mirror generate_narraration.py build_sentence_offsets ([s,e) char spans). */
function buildSentenceSpans(rawText) {
    const spans = [];
    NARR_TERMINAL.lastIndex = 0;
    let start = 0;
    let m;
    while ((m = NARR_TERMINAL.exec(rawText))) {
        const endPos = m.index + m[0].length;
        const segment = rawText.slice(start, endPos).replace(/\s+$/, '');
        if (segment.length) {
            spans.push({ s: start, e: start + segment.length });
        }
        start = endPos;
    }
    const tail = rawText.slice(start).replace(/\s+$/, '');
    if (tail.length) {
        spans.push({ s: start, e: start + tail.length });
    }
    return spans;
}

function buildSentenceIndex(paperScope, segSentences, segTexts) {
    const sections = paperScope.querySelectorAll('[data-narr-section]');
    const all = [];

    for (let s = 0; s < sections.length; s++) {
        const section = sections[s];
        const rawText = segTexts[s] ?? '';
        const manifestSents = segSentences[s] || [];
        const spans = manifestSents.length
            ? manifestSents.map((x) => ({ s: x.s ?? x[0] ?? 0, e: x.e ?? x[1] ?? 0 }))
            : buildSentenceSpans(rawText);

        if (!spans.length) {
            all.push([]);
            continue;
        }

        const tokens = splitSectionWords(rawText);
        const wordEls = [];
        for (const para of section.querySelectorAll('p')) {
            wordEls.push(...para.querySelectorAll('.tap-word'));
        }

        // Cover the last span even when it has no terminal punctuation.
        const last = spans[spans.length - 1];
        if (last.e <= last.s) last.e = last.s + tokens.reduce((n, t) => (t.start >= last.s ? n + t.end - t.start : n), 0);

        const sentData = [];
        let tokenIdx = 0;
        for (let i = 0; i < spans.length; i++) {
            const span = spans[i];
            const words = [];
            while (tokenIdx < tokens.length && tokens[tokenIdx].end <= span.e) {
                words.push(wordEls[tokenIdx] ?? null);
                tokenIdx++;
            }
            sentData.push({
                words: words.filter(Boolean),
                charStart: span.s,
                charEnd: span.e,
            });
        }

        all.push(sentData);
    }

    return all;
}

function initNarration(scope) {
    const wrap = scope.querySelector('[data-narration]');
    if (!wrap) return;

    try {
        narrationSegments = JSON.parse(scope.dataset.narrationSegments ?? '[]');
    } catch {
        narrationSegments = [];
    }

    if (!narrationSegments.length) return;

    narrationAudio = document.createElement('audio');
    narrationAudio.preload = 'none';
    wrap.appendChild(narrationAudio);

    const toggle = wrap.querySelector('[data-narr-toggle]');
    const icon = wrap.querySelector('[data-narr-icon]');
    const speedButton = wrap.querySelector('[data-narr-speed]');

    try {
        const savedSpeed = parseFloat(localStorage.getItem('narration-speed') ?? '');
        if (NARRATION_SPEEDS.includes(savedSpeed)) {
            narrSpeedIndex = NARRATION_SPEEDS.indexOf(savedSpeed);
        }
    } catch { /* */ }

    applyNarrSpeed(speedButton);

    // Narration options menu: speeds as explicit rows + replay. Replaces the
    // blind 1x cycle chip; same storage key and playback path underneath.
    const menuWrap = wrap.querySelector('[data-narr-menu-wrap]');
    const menuBtn = wrap.querySelector('[data-narr-menu]');
    const menuPanel = wrap.querySelector('[data-narr-menu-panel]');
    const closeNarrMenu = () => {
        menuPanel?.classList.add('hidden');
        menuBtn?.setAttribute('aria-expanded', 'false');
    };

    menuBtn?.addEventListener('click', () => {
        const open = menuPanel?.classList.toggle('hidden') === false;
        menuBtn?.setAttribute('aria-expanded', String(open));
    });

    document.addEventListener('click', (e) => {
        if (menuPanel && !menuPanel.classList.contains('hidden') && !menuWrap?.contains(e.target)) closeNarrMenu();
    });

    menuPanel?.querySelectorAll('[data-narr-speed-option]').forEach((opt) => {
        opt.addEventListener('click', () => {
            narrSpeedIndex = NARRATION_SPEEDS.indexOf(parseFloat(opt.dataset.speed));
            if (narrSpeedIndex < 0) narrSpeedIndex = 1;
            applyNarrSpeed(speedButton);
            try { localStorage.setItem('narration-speed', String(NARRATION_SPEEDS[narrSpeedIndex])); } catch { /* */ }
            closeNarrMenu();
        });
    });

    menuPanel?.querySelector('[data-narr-replay]')?.addEventListener('click', () => {
        closeNarrMenu();
        if (!narrationAudio) return;
        // Replay must actually narrate: reset to segment 0, load it, play it.
        narrationAudio.pause();
        currentSegmentIdx = 0;
        lastSentIdx = -1;
        setNarrationState(toggle, icon, true);
        toggle?.setAttribute('aria-busy', 'true');
        loadSegment(0, toggle, icon, speedButton, offsets);
        narrationAudio.play()
            .then(() => toggle?.removeAttribute('aria-busy'))
            .catch(() => {
                toggle?.removeAttribute('aria-busy');
                setNarrationState(toggle, icon, false);
                stopTracking();
            });
        startTracking();
        mascotReact('walk');
    });

    let currentSegmentIdx = 0;
    let lastSentIdx = -1;

    const offsets = [];
    let cumulative = 0;
    for (const seg of narrationSegments) {
        offsets.push(cumulative);
        cumulative += seg.seconds;
    }

    // Build sentence-level targets for highlighting using manifest char offsets.
    const segSentences = narrationSegments.map(seg => seg.sentences || []);
    const segTexts = narrationSegments.map(seg => seg.text || '');
    const sectionSentences = buildSentenceIndex(paperEl ?? scope, segSentences, segTexts);

    // RAF-based tracking — smoother than timeupdate (~4 Hz).
    function trackHighlight() {
        if (!narrationAudio || narrationAudio.paused && narrationAudio.ended) {
            narrRafId = 0;
            return;
        }

        if (!narrationAudio.paused) {
            const t = narrationAudio.currentTime;

            // Find which segment we're in.
            let idx = 0;
            for (let i = narrationSegments.length - 1; i >= 0; i--) {
                if (t >= offsets[i]) { idx = i; break; }
            }
            currentSegmentIdx = idx;

            // Map progress within the segment to a character position.
            const seg = narrationSegments[idx];
            const sents = sectionSentences[idx] || [];
            if (sents.length) {
                const totalChars = sents.reduce((s, x) => s + (x.charEnd - x.charStart), 0);
                const progress = (t - offsets[idx]) / (seg.seconds || 1);
                const spokenChars = Math.floor(Math.min(progress, 1) * totalChars);

                let cum = 0;
                let sentIdx = sents.length - 1;
                for (let i = 0; i < sents.length; i++) {
                    cum += sents[i].charEnd - sents[i].charStart;
                    if (spokenChars < cum) { sentIdx = i; break; }
                }

                // Only update DOM when the active sentence changes.
                if (sentIdx !== lastSentIdx) {
                    lastSentIdx = sentIdx;

                    paperEl?.querySelectorAll('.sentence-active').forEach(el => el.classList.remove('sentence-active'));

                    const active = sents[sentIdx];
                    if (active) {
                        for (const w of active.words) w.classList.add('sentence-active');
                    }
                }
            }
        }

        narrRafId = requestAnimationFrame(trackHighlight);
    }

    function startTracking() {
        if (narrRafId) cancelAnimationFrame(narrRafId);
        lastSentIdx = -1;
        narrRafId = requestAnimationFrame(trackHighlight);
    }

    function stopTracking() {
        if (narrRafId) {
            cancelAnimationFrame(narrRafId);
            narrRafId = 0;
        }
    }

    narrationAudio.addEventListener('ended', () => {
        setNarrationState(toggle, icon, false);
        currentSegmentIdx = 0;
        lastSentIdx = -1;
        stopTracking();
        paperEl?.querySelectorAll('.sentence-active').forEach(el => el.classList.remove('sentence-active'));
    });

    toggle?.addEventListener('click', () => {
        if (narrationAudio.paused) {
            if (narrationAudio.ended || !narrationAudio.src) {
                currentSegmentIdx = 0;
                lastSentIdx = -1;
                loadSegment(0, toggle, icon, speedButton, offsets);
            }
            // Buffering state: mark busy until play actually resumes, so a slow
            // segment load reads as loading rather than broken.
            toggle?.setAttribute('aria-busy', 'true');
            narrationAudio.play()
                .then(() => toggle?.removeAttribute('aria-busy'))
                .catch(() => {
                    toggle?.removeAttribute('aria-busy');
                    setNarrationState(toggle, icon, false);
                    stopTracking();
                });
            setNarrationState(toggle, icon, true);
            startTracking();
            mascotReact('walk');
        } else {
            narrationAudio.pause();
            setNarrationState(toggle, icon, false);
            stopTracking();
        }
    });

    loadSegment(0, toggle, icon, speedButton, offsets);
}

function loadSegment(idx, toggle, icon, speedButton, offsets) {
    if (idx >= narrationSegments.length || !narrationAudio) return;

    const seg = narrationSegments[idx];
    narrationAudio.src = seg.url;
    narrationAudio.playbackRate = NARRATION_SPEEDS[narrSpeedIndex];

    const onEnded = () => {
        narrationAudio.removeEventListener('ended', onEnded);
        if (idx + 1 < narrationSegments.length) {
            loadSegment(idx + 1, toggle, icon, speedButton, offsets);
            narrationAudio.play().catch(() => setNarrationState(toggle, icon, false));
        } else {
            setNarrationState(toggle, icon, false);
        }
    };

    narrationAudio.addEventListener('ended', onEnded);
}

function applyNarrSpeed(speedButton) {
    if (narrationAudio) {
        narrationAudio.playbackRate = NARRATION_SPEEDS[narrSpeedIndex];
    }
    // Active speed row inside the options menu (the button itself is a dots glyph).
    document.querySelectorAll('[data-narr-speed-option]').forEach((opt) => {
        const active = parseFloat(opt.dataset.speed) === NARRATION_SPEEDS[narrSpeedIndex];
        opt.classList.toggle('bg-amber-400', active);
        opt.classList.toggle('bg-white', !active);
    });
}

function setNarrationState(toggle, icon, playing) {
    toggle?.setAttribute('aria-pressed', String(playing));
    if (!icon) return;
    const playIcon = icon.querySelector('[data-icon-play]');
    const pauseIcon = icon.querySelector('[data-icon-pause]');
    if (!playIcon || !pauseIcon) return;
    playIcon.classList.toggle('hidden', playing);
    pauseIcon.classList.toggle('hidden', !playing);
}

/* =================------------- word popup ============================== */

const popup = () => document.getElementById('word-popup');
let currentPopupWord = null;
let currentPopupData = null;
let popupAnchorEl = null;
let popupRepositionBound = false;

/**
 * Anchor the quick-action popover at the tapped/hovered word, clamped to the
 * viewport: preferred below the word, flipped above it near the bottom edge.
 */
function positionPopupAt(wordEl) {
    const box = popup();
    if (!box || box.hidden || !wordEl) return;

    const rect = wordEl.getBoundingClientRect();
    const pad = 8;
    const boxWidth = box.offsetWidth;
    const boxHeight = box.offsetHeight;

    let left = rect.left + rect.width / 2 - boxWidth / 2;
    left = Math.max(pad, Math.min(left, window.innerWidth - boxWidth - pad));

    let top = rect.bottom + pad;
    if (top + boxHeight > window.innerHeight - pad) {
        top = rect.top - boxHeight - pad;
    }
    top = Math.max(pad, top);

    box.style.left = `${Math.round(left)}px`;
    box.style.top = `${Math.round(top)}px`;
}

function bindPopupRepositioning() {
    if (popupRepositionBound) return;
    popupRepositionBound = true;

    window.addEventListener('scroll', () => {
        if (popupAnchorEl) positionPopupAt(popupAnchorEl);
    }, true);

    window.addEventListener('resize', () => {
        if (popupAnchorEl && !popup()?.hidden) positionPopupAt(popupAnchorEl);
    });
}

function openPopup(wordEl, source) {
    // Field Journal game mode owns word taps; the dictionary stays out.
    if (document.body.dataset.gameMode === 'on') return;

    const tapped = wordEl.dataset.w ?? '';
    readingActive = true;
    currentPopupWord = tapped;
    openedBy = source ?? null;
    popupAnchorEl = wordEl;
    showPopupShell(tapped);
    bindPopupRepositioning();
    positionPopupAt(wordEl);

    const cached = memo.get(tapped);
    if (cached) {
        renderLookup(cached);
        return;
    }

    if (Date.now() < cooldownUntil) {
        renderMissLine('The dictionary is catching its breath — try again in a moment.');
        return;
    }

    renderMissLine('Looking it up\u2026');

    fetch(`/words/${encodeURIComponent(tapped)}`, { headers: { Accept: 'application/json' } })
        .then((response) => {
            if (!response.ok) throw new Error(String(response.status));
            return response.json();
        })
        .then((data) => {
            consecutiveFails = 0;
            remember(tapped, data);
            renderLookup(data);
        })
        .catch(() => {
            consecutiveFails++;
            if (consecutiveFails >= BREAKER_FAILS) {
                cooldownUntil = Date.now() + BREAKER_COOLDOWN_MS;
            }
            renderMissLine("We couldn't reach the dictionary. Check the connection.");
        });
}

function remember(key, data) {
    memo.delete(key);
    memo.set(key, data);
    while (memo.size > 40) {
        memo.delete(memo.keys().next().value);
    }
    sessionStorage.setItem(WORDS_CACHE_KEY, JSON.stringify([...memo]));
}

function showPopupShell(tapped) {
    const box = popup();
    if (!box) return;

    box.hidden = false;
    setStatusLine(box, '', false);

    const saveButton = box.querySelector('[data-slot="save"]');
    saveButton.disabled = false;
    paintSaveButton(saveButton, savedWords.has(tapped));

    const dictButton = box.querySelector('[data-slot="dictionary"]');
    dictButton.disabled = false;
}

function hidePopup() {
    const box = popup();
    if (box) box.hidden = true;

    currentPopupWord = null;
    currentPopupData = null;
    popupAnchorEl = null;
    openedBy = null;
    clearTimeout(hoverTimer);
    clearTimeout(hoverCloseTimer);
}

function setStatusLine(box, message, show) {
    const status = box?.querySelector('[data-slot="status"]');
    if (!status) return;
    status.textContent = message;
    status.hidden = !show;
}

function setSaveLabel(button, label) {
    const el = button.querySelector('[data-slot="save-label"]');
    if (el) el.textContent = label;
}

function paintSaveButton(button, saved) {
    setSaveLabel(button, saved ? 'Saved' : 'Save');
    if (saved) {
        button.dataset.state = 'saved';
    } else {
        delete button.dataset.state;
    }
}

/* ===================== already-saved word marks ========================== */

/**
 * Amber underline on story words the student has already saved. The saved set
 * stores dictionary lemmas, so matching is exact-form only (saved "run" will
 * not mark "running"); the popup button stays the authority for inflected
 * taps once the lookup resolves.
 */
function normalizeWord(word) {
    return word.toLowerCase().replace(/[\u2019]/g, "'");
}

function markSavedWordsInText() {
    if (savedWords.size === 0) return;

    const lemmas = new Set([...savedWords].map((w) => normalizeWord(w)));
    document.querySelectorAll('.tap-word[data-w]').forEach((el) => {
        if (lemmas.has(normalizeWord(el.dataset.w ?? ''))) {
            el.classList.add('tap-word-saved');
        }
    });
}

function markWordInText(word, saved) {
    const key = normalizeWord(word);
    document.querySelectorAll('.tap-word[data-w]').forEach((el) => {
        if (normalizeWord(el.dataset.w ?? '') === key) {
            el.classList.toggle('tap-word-saved', saved);
        }
    });
}

function renderLookup(data) {
    const box = popup();
    if (!box || box.hidden) return;

    currentPopupData = data;

    if (!data.found) {
        renderMissLine(data.looks_like_name ? 'Looks like a name in this story.' : 'No definition found for this one.');
        setSaveDisabled();
        return;
    }

    setStatusLine(box, '', false);
    syncSaveButton(data);
}

function renderMissLine(message) {
    setStatusLine(popup(), message, true);
}

function setSaveDisabled() {
    const box = popup();
    const saveButton = box?.querySelector('[data-slot="save"]');
    if (saveButton instanceof HTMLButtonElement) {
        saveButton.disabled = true;
        setSaveLabel(saveButton, '\u00b7');
    }
    const dictButton = box?.querySelector('[data-slot="dictionary"]');
    if (dictButton instanceof HTMLButtonElement) {
        dictButton.disabled = true;
    }
}

function syncSaveButton(data) {
    const saveButton = popup()?.querySelector('[data-slot="save"]');
    if (!(saveButton instanceof HTMLButtonElement)) return;

    const lemma = data.resolved_to ?? data.word;
    saveButton.disabled = false;
    paintSaveButton(saveButton, savedWords.has(lemma) || savedWords.has(data.word));
}

async function saveWord(button) {
    const word = currentPopupData?.resolved_to ?? currentPopupWord;
    if (!word) return;

    // The button is a toggle: a filed word unsaves instead of re-saving (the
    // old re-save played the +1 juice again while the server just bumped
    // lookup_count).
    if (savedWords.has(word) || savedWords.has(currentPopupWord)) {
        await unsaveWord(button, word);
        return;
    }

    paintSaveButton(button, true);

    try {
        const response = await fetch('/words', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({ word }),
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok || data.saved !== true) {
            paintSaveButton(button, false);
            renderMissLine(data.message ?? 'Saving failed. Try again.');
            return;
        }

        savedWords.add(data.word);
        markWordInText(data.word, true);
        paintSaveButton(button, true);

        // Only a genuinely new filing celebrates; a race with another tab
        // (already_saved) settles the button silently.
        if (data.already_saved !== true) {
            wordSaveJuice(button);
        }
    } catch {
        paintSaveButton(button, false);
        renderMissLine('Saving failed. Check the connection.');
    }
}

async function unsaveWord(button, word) {
    try {
        const response = await fetch(`/words/${encodeURIComponent(word)}`, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok || data.deleted !== true) {
            renderMissLine(data.message ?? 'Removing failed. Try again.');
            return;
        }

        savedWords.delete(data.word ?? word);
        savedWords.delete(word);
        savedWords.delete(currentPopupWord);
        markWordInText(word, false);
        paintSaveButton(button, false);
    } catch {
        renderMissLine('Removing failed. Check the connection.');
    }
}

/**
 * Word-save juice: the save used to flip a label and stop there. Now it
 * coins a blips, floats "+1 WORD" off the button, and gets Alice to swing
 * her attack. Optimistic path only (real success). The float is forced so
 * it still animates for students with OS animations off.
 */
function wordSaveJuice(button) {
    play('coin');
    scorePopup(button, '+1 WORD', '#B45309', true);
    mascotReact('attack');
}

/* ============================== dictionary ============================== */

/**
 * Open the Study dictionary for the tapped word, carrying the current reader
 * URL as the `back` target so the dictionary's back button returns to exactly
 * this spot in the story. The server only accepts same-host back targets, so
 * this can never be an open redirect.
 */
function openDictionary() {
    const word = currentPopupData?.resolved_to ?? currentPopupWord;
    if (!word) return;

    const dictUrl = document.getElementById('reader-shell')?.dataset.dictionaryUrl ?? '';
    if (!dictUrl) return;

    const url = new URL(dictUrl, window.location.origin);
    url.searchParams.set('word', word);
    url.searchParams.set('back', window.location.href);
    window.location.href = url.toString();
}
