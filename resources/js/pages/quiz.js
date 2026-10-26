/**
 * Student quiz player (STUDENT_PAGES_PLAN 4.4). Server renders every
 * question panel; this module reveals one per screen, wires the five
 * locked interaction designs, spends hint tokens immediately on use
 * (typed hints: strike / letter clue / revealed pair / pinned first),
 * asks for a hand-in confirmation once the student taps SUBMIT, and
 * settles through the idempotent attempt UUID.
 *
 * Navigation is forward-only (B4): NEXT unlocks when the current panel
 * is complete and PREV does not exist.
 *
 * Refresh safety: the draw + uuid + spent hints live in the server
 * session, so a reload rebuilds the exact same attempt state via the
 * data-hints-applied map.
 */

import { play } from '../game/sfx.js';
import { closeModal, openModal } from '../components/modal.js';

const PAIR_COLORS = ['#F59E0B', '#10B981', '#3B82F6', '#EC4899', '#8B5CF6'];
const IDLE_HINT_MS = 25000;
const IDLE_CHIP_SHOW_MS = 12000;
const HINT_FEEDBACK = {
    strike: 'One wrong answer crossed out.',
    pair: 'A correct pair locked in.',
    first: 'The first event is pinned for you.',
    clue: 'Clue revealed here. Still takes a guess.',
};

let root = null;
let csrf = '';
let config = {};
let panels = [];
let current = 0;
let answers = new Map();
let matchState = new Map();
let seqState = new Map();
let struckByQuestion = new Map();
let submitting = false;
let redirected = false;
let pendingHintPanel = null;
let hintSpending = false;
let submitNagging = false;
let idleHintTimer = null;
let idleChipTimer = null;
let openedAt = Date.now();
let integrity = { focusLosses: 0, focusSeconds: 0, pastes: [] };
let integrityActive = false;

export default function init() {
    root = document.getElementById('quiz-main');

    if (!root) {
        return;
    }

    csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    config = {
        attempt: root.dataset.attempt ?? '',
        submitUrl: root.dataset.submitUrl,
        hintUrl: root.dataset.hintUrl,
        balance: Number(root.dataset.balance ?? 0),
        hintsUsed: Number(root.dataset.hintsUsed ?? 0),
        hintsMax: Number(root.dataset.hintsMax ?? 0),
        hintCost: Number(root.dataset.hintCost ?? 0),
        hintsApplied: parseHintsApplied(root.dataset.hintsApplied),
    };
    openedAt = Date.now();

    panels = [...root.querySelectorAll('[data-qpanel]')];

    if (panels.length === 0) {
        return;
    }

    // Restore server-known hints so refreshes keep them.
    for (const panel of panels) {
        const qid = panel.dataset.qid;

        if (panel.dataset.type === 'mcq') {
            for (const button of panel.querySelectorAll('[data-qoption]')) {
                wireMcqOption(panel, button);
            }
        }

        if (panel.dataset.type === 'true_false') {
            for (const button of panel.querySelectorAll('[data-tf]')) {
                button.addEventListener('click', () => {
                    selectTf(panel, button);
                });
            }
        }

        if (panel.dataset.type === 'identification') {
            const input = panel.querySelector('[data-ident]');

            input?.addEventListener('input', () => {
                if (input.value.trim().length > 0) {
                    answers.set(qid, input.value);
                } else {
                    answers.delete(qid);
                }

                touchAnswer();
            });
        }

        if (panel.dataset.type === 'matching') {
            const chips = [...panel.querySelectorAll('[data-chip]')];
            // Capture labels now, while badges are still empty, so answer
            // payloads never inherit badge digits from the DOM.
            matchState.set(qid, {
                pending: null,
                pairs: new Map(),
                lefts: chips.filter((chip) => chip.dataset.side === 'left').map((chip) => chip.textContent.trim()),
                rights: chips.filter((chip) => chip.dataset.side === 'right').map((chip) => chip.textContent.trim()),
            });

            for (const chip of chips) {
                chip.addEventListener('click', () => tapChip(panel, chip));
            }
        }

        if (panel.dataset.type === 'sequence') {
            wireSequence(panel);
        }
    }

    // One world-level hint control (top bar lightbulb). Its enabled state is
    // recomputed for whichever panel is on screen.
    document.getElementById('quiz-hint-btn')?.addEventListener('click', () => promptHint());
    document.getElementById('quiz-hint-chip')?.addEventListener('click', () => promptHint());

    initIntegrity();

    const hintModal = document.getElementById('quiz-hint-modal');

    // A hint prompt dismissed any way (backdrop, Escape, X) must forget
    // which panel it was aiming at, or the next HINT click silently dies.
    hintModal?.addEventListener('alice:modal-close', () => {
        pendingHintPanel = null;
    });

    hintModal?.querySelector('[data-hint-confirm]')
        ?.addEventListener('click', () => {
            if (!pendingHintPanel || hintSpending) {
                return;
            }

            if (config.balance < config.hintCost) {
                shakeConfirm('NOT ENOUGH KEYS');

                return;
            }

            spendHint(pendingHintPanel);
        });

    restoreHints();

    document.getElementById('quiz-next')?.addEventListener('click', () => reveal(current + 1));

    // SUBMIT first confirms (shared modal) when everything is answered;
    // with gaps left it falls through to the existing "tap again" nag.
    document.getElementById('quiz-submit')?.addEventListener('click', () => {
        if (submitting) {
            return;
        }

        if (answersComplete()) {
            openModal(document.getElementById('quiz-submit-modal'));

            return;
        }

        submit();
    });

    document.getElementById('quiz-submit-confirm')?.addEventListener('click', () => {
        closeModal(document.getElementById('quiz-submit-modal'));
        submit(true);
    });

    reveal(0);
}

/* ---------------------------------------------------------------- nav */

function reveal(index) {
    current = Math.min(Math.max(index, 0), panels.length - 1);
    const last = current === panels.length - 1;

    panels.forEach((panel, i) => {
        panel.hidden = i !== current;
    });

    const progress = document.getElementById('quiz-progress');
    const counter = document.getElementById('quiz-counter');

    if (progress) {
        progress.style.width = `${Math.round((100 * (current + 1)) / panels.length)}%`;
    }

    if (counter) {
        counter.textContent = `${current + 1}/${panels.length}`;
    }

    syncDots();
    syncNav();
    syncHintButton();
    armIdleHint();

    const next = document.getElementById('quiz-next');
    const submit = document.getElementById('quiz-submit');
    next?.toggleAttribute('hidden', last);
    submit?.toggleAttribute('hidden', !last);

    const panel = panels[current];

    if (panel?.dataset.type === 'identification') {
        panel.querySelector('[data-ident]')?.focus({ preventScroll: true });
    }

    panel?.scrollIntoView({ block: 'nearest' });
}

/** Forward-only (B4): NEXT stays locked until this panel is complete. */
function syncNav() {
    const next = document.getElementById('quiz-next');

    next?.toggleAttribute('disabled', !panelAnswered(panels[current]));
}

function syncDots() {
    const dots = document.getElementById('quiz-dots');

    dots?.querySelectorAll('span').forEach((dot, i) => {
        const answered = panelAnswered(panels[i]);

        dot.classList.toggle('bg-amber-400', i === current);
        dot.classList.toggle('bg-emerald-500', i !== current && answered);
        dot.classList.toggle('bg-white', i !== current && !answered);
    });
}

function note(message, isError = false) {
    const el = document.getElementById('quiz-note');

    if (!el) {
        return;
    }

    el.textContent = message;
    el.classList.toggle('text-rose-600', isError);
}

/* --------------------------------------------------------------- MCQ */

function wireMcqOption(panel, button) {
    button.addEventListener('click', () => {
        if (button.classList.contains('q-struck')) {
            return;
        }

        const letter = button.dataset.qoption;
        const already = button.getAttribute('aria-pressed') === 'true';

        for (const option of panel.querySelectorAll('[data-qoption]')) {
            option.setAttribute('aria-pressed', 'false');
        }

        if (already) {
            answers.delete(panel.dataset.qid);
        } else {
            play('pop');
            button.setAttribute('aria-pressed', 'true');
            answers.set(panel.dataset.qid, letter);
        }

        touchAnswer();
    });
}

function applyStruck(panel, letter) {
    const option = panel.querySelector(`[data-qoption="${letter}"]`);

    if (option) {
        option.classList.add('q-struck');
        option.classList.remove('q-strike-pop');
        option.classList.add('q-strike-pop');
        option.setAttribute('aria-disabled', 'true');
        option.querySelector('.q-strike-mark')?.removeAttribute('hidden');

        if (option.getAttribute('aria-pressed') === 'true') {
            option.setAttribute('aria-pressed', 'false');
            answers.delete(panel.dataset.qid);
        }
    }

    const list = struckByQuestion.get(panel.dataset.qid) ?? [];
    list.push(letter);
    struckByQuestion.set(panel.dataset.qid, list);
}

/**
 * Skin-modal style rejection: shake the confirm button and swap its label
 * for a moment (restores the cached base HTML + class after 1.2s).
 */
function shakeConfirm(msg) {
    const btn = document.querySelector('#quiz-hint-modal [data-hint-confirm]');
    if (!btn) return;
    btn.classList.remove('is-shake');
    void btn.offsetWidth;
    btn.classList.add('is-shake');
    if (msg) {
        const prev = btn.innerHTML;
        btn.textContent = msg;
        setTimeout(() => {
            btn.innerHTML = prev;
            btn.classList.remove('is-shake');
        }, 1200);
    }
}

/**
 * Retro confirm before the key cost is spent: opens the shared hint
 * modal with the current panel's type-specific description and the
 * current balance; clicking while too poor shakes the button instead
 * of spending. True/false panels are never hintable.
 */
function promptHint() {
    const modal = document.getElementById('quiz-hint-modal');
    const panel = panels[current];

    if (!modal || !hintable(panel) || hintSpending) {
        return;
    }

    pendingHintPanel = panel;

    const copyEl = modal.querySelector('[data-hint-confirm-copy]');
    const balanceEl = modal.querySelector('[data-slot="confirm-balance"]');
    const confirmBtn = modal.querySelector('[data-hint-confirm]');
    const description = panel.dataset.hintName ?? 'Reveal help for this question.';

    if (copyEl) {
        copyEl.textContent = description;
    }

    if (balanceEl) {
        balanceEl.textContent = String(config.balance);
    }

    if (confirmBtn) {
        confirmBtn.dataset.baseLabel ||= confirmBtn.innerHTML;
        confirmBtn.innerHTML = confirmBtn.dataset.baseLabel;
    }

    openModal(modal);
}

async function spendHint(panel) {
    if (hintSpending || !config.attempt) {
        return;
    }

    hintSpending = true;
    const hintButton = document.getElementById('quiz-hint-btn');

    if (hintButton) {
        hintButton.disabled = true;
    }

    try {
        const response = await fetch(config.hintUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            credentials: 'same-origin',
            body: JSON.stringify({
                attempt_uuid: config.attempt,
                question_id: Number(panel.dataset.qid),
            }),
        });
        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            if (response.status === 409) {
                window.location.reload();

                return;
            }

            note(payload.message ?? 'No hints available right now.', true);

            hintSpending = false;
            syncHintButton();

            return;
        }

        applyHint(panel, payload);
        config.balance = payload.balance;
        config.hintsUsed = payload.hints_used;
        setBalance(payload.balance);

        const modal = document.getElementById('quiz-hint-modal');

        if (modal) {
            closeModal(modal);
        }

        pendingHintPanel = null;

        const message = HINT_FEEDBACK[payload.kind ?? ''];

        if (message) {
            note(message);
        }

        hintSpending = false;
        syncNav();
        syncDots();
        syncHintButton();
    } catch {
        note('Could not reach the hint desk - check your connection.', true);

        hintSpending = false;
        syncHintButton();
    }
}

/**
 * Applies a typed hint payload (live response or restored session
 * entry). Every type lands somewhere obvious in the panel: MCQ strike,
 * identification clue banner, revealed pair, pinned first event.
 */
function applyHint(panel, payload) {
    const kind = payload.kind ?? null;

    if (kind === 'strike' || typeof payload.struck === 'string') {
        applyStruck(panel, payload.letter ?? payload.struck);

        return;
    }

    if (kind === 'clue' || typeof payload.clue === 'string') {
        applyClue(panel, payload.clue);

        return;
    }

    if (kind === 'pair' || (payload.left !== undefined && payload.right !== undefined)) {
        applyHintedPair(panel, payload.left, payload.right);

        return;
    }

    if (kind === 'first' || typeof payload.item === 'string') {
        applyPinnedFirst(panel, payload.item);
    }
}

/** Prominent identification clue: "Starts with 'P' · 9 letters" banner. */
function applyClue(panel, clue) {
    const banner = panel.querySelector('[data-hint-clue]');
    const text = panel.querySelector('[data-hint-clue-text]');

    if (banner && text) {
        text.textContent = clue;
        banner.hidden = false;
    }
}

/** Server-revealed correct pair: resolves conflicts, locks both chips. */
function applyHintedPair(panel, leftLabel, rightLabel) {
    const state = matchState.get(panel.dataset.qid);

    if (!state) {
        return;
    }

    const li = state.lefts.findIndex((label) => label === leftLabel);
    const ri = state.rights.findIndex((label) => label === rightLabel);

    if (li < 0 || ri < 0) {
        return;
    }

    if (state.pairs.get(li) !== ri) {
        const boundToRight = [...state.pairs.entries()].find(([, r]) => r === ri);

        if (boundToRight !== undefined) {
            unlockPair(panel, state, boundToRight[0]);
        }

        if (state.pairs.has(li)) {
            unlockPair(panel, state, li);
        }

        lockPair(panel, state, li, ri);
    }

    for (const [side, idx] of [['left', li], ['right', ri]]) {
        panel.querySelector(`[data-side="${side}"][data-idx="${idx}"]`)?.classList.add('q-locked');
    }
}

/** Pins the true first event into position 1 and locks it there. */
function applyPinnedFirst(panel, item) {
    const state = seqState.get(panel.dataset.qid);
    const strip = panel.querySelector('[data-seq-strip]');

    if (!state || !strip) {
        return;
    }

    const idx = state.items.indexOf(item);
    const button = idx > -1 ? panel.querySelector(`[data-seq-item][data-idx="${idx}"]`) : null;

    if (!button) {
        return;
    }

    const placedAt = state.placed.indexOf(idx);

    if (placedAt > -1) {
        state.placed.splice(placedAt, 1);
    }

    state.placed.unshift(idx);
    strip.prepend(button);
    button.classList.add('q-placed', 'q-locked');
    springySettle(button);

    refreshSeqNumbers(panel, strip);
    syncSeqAnswer(panel);
}

/** Replays every server-recorded hint after a refresh. */
function restoreHints() {
    for (const panel of panels) {
        for (const hint of config.hintsApplied[panel.dataset.qid] ?? []) {
            applyHint(panel, hint);
        }
    }
}

function parseHintsApplied(raw) {
    try {
        const parsed = JSON.parse(raw ?? '{}');

        return parsed && typeof parsed === 'object' ? parsed : {};
    } catch {
        return {};
    }
}

function syncHintButton() {
    const hintButton = document.getElementById('quiz-hint-btn');
    const panel = panels[current];

    if (!hintButton || !panel) {
        return;
    }

    const outOfHints = config.hintsMax - config.hintsUsed <= 0;
    const tooPoor = config.balance < config.hintCost;
    const notHintable = !hintable(panel);

    hintButton.disabled = notHintable || outOfHints || tooPoor;
    hintButton.title = tooPoor && !outOfHints ? 'Not enough keys' : '';
}

function hintable(panel) {
    return Boolean(panel) && panel.dataset.type !== 'true_false';
}

/* ------------------------------------------- idle help nudge (lightbulb) */

/** After a real pause on an unanswered hintable panel, offer the lightbulb. */
function armIdleHint() {
    clearTimeout(idleHintTimer);
    idleHintTimer = null;
    hideHintChip();

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    const panel = panels[current];
    const target = document.getElementById('quiz-hint-btn');

    if (!panel || !hintable(panel) || !target || target.disabled) {
        return;
    }

    idleHintTimer = setTimeout(() => {
        idleHintTimer = null;

        const now = panels[current];

        if (!now || now !== panel || panelAnswered(now)) {
            return;
        }

        showHintChip();
    }, IDLE_HINT_MS);
}

function showHintChip() {
    const chip = document.getElementById('quiz-hint-chip');

    if (!chip) {
        return;
    }

    chip.hidden = false;
    clearTimeout(idleChipTimer);
    idleChipTimer = setTimeout(() => {
        chip.hidden = true;
    }, IDLE_CHIP_SHOW_MS);
}

function hideHintChip() {
    const chip = document.getElementById('quiz-hint-chip');

    if (!chip) {
        return;
    }

    chip.hidden = true;
    clearTimeout(idleChipTimer);
    idleChipTimer = null;
}

function setBalance(balance) {
    config.balance = balance;

    const pill = document.querySelector('[data-slot="balance"]');

    if (pill) {
        pill.textContent = String(balance);
        pill.classList.remove('balance-pop');
        void pill.offsetWidth;
        pill.classList.add('balance-pop');
    }
}

/* -------------------------------------------------------- True/False */

function selectTf(panel, button) {
    const pressed = button.getAttribute('aria-pressed') === 'true';

    for (const other of panel.querySelectorAll('[data-tf]')) {
        other.setAttribute('aria-pressed', 'false');
    }

    if (pressed) {
        answers.delete(panel.dataset.qid);
    } else {
        button.setAttribute('aria-pressed', 'true');
        answers.set(panel.dataset.qid, button.dataset.tf);
    }

    touchAnswer();
}

/* ---------------------------------------------------------- Matching */

function tapChip(panel, chip) {
    const state = matchState.get(panel.dataset.qid);

    if (!state) {
        return;
    }

    const side = chip.dataset.side;
    const idx = Number(chip.dataset.idx);

    // Tapping either half of a locked pair unbinds the whole pair.
    if (side === 'left' && state.pairs.has(idx)) {
        unlockPair(panel, state, idx);

        return;
    }

    if (side === 'right') {
        const boundLeft = [...state.pairs.entries()].find(([, r]) => r === idx);

        if (boundLeft !== undefined) {
            unlockPair(panel, state, boundLeft[0]);

            return;
        }
    }

    if (side === 'left') {
        const switching = state.pending === idx;

        setPending(panel, state, null);

        if (!switching) {
            setPending(panel, state, idx);
        }

        return;
    }

    if (state.pending === null) {
        // Action-level mistake: no right card was chosen first.
        shake(chip);
        note('Pick a card on the left first.');

        return;
    }

    lockPair(panel, state, state.pending, idx);
}

function setPending(panel, state, idx) {
    panel.querySelector('[data-side="left"].q-pending')?.classList.remove('q-pending');
    state.pending = idx;

    if (idx === null) {
        return;
    }

    panel.querySelector(`[data-side="left"][data-idx="${idx}"]`)?.classList.add('q-pending');
}

function lockPair(panel, state, leftIdx, rightIdx) {
    state.pairs.set(leftIdx, rightIdx);
    state.pending = null;
    panel.querySelector('[data-side="left"].q-pending')?.classList.remove('q-pending');

    const number = state.pairs.size;
    const color = PAIR_COLORS[(number - 1) % PAIR_COLORS.length];

    for (const [side, chipIdx] of [['left', leftIdx], ['right', rightIdx]]) {
        const target = panel.querySelector(`[data-side="${side}"][data-idx="${chipIdx}"]`);

        if (!target) {
            continue;
        }

        target.classList.add('q-paired');
        target.style.setProperty('--pair-color', color);

        const badge = target.querySelector('.pair-badge');

        if (badge) {
            badge.hidden = false;
            badge.textContent = String(number);
        }
    }

    syncMatchAnswer(panel);
}

function unlockPair(panel, state, leftIdx) {
    const rightIdx = state.pairs.get(leftIdx);

    if (rightIdx === undefined) {
        return;
    }

    state.pairs.delete(leftIdx);

    for (const [side, chipIdx] of [['left', leftIdx], ['right', rightIdx]]) {
        const target = panel.querySelector(`[data-side="${side}"][data-idx="${chipIdx}"]`);

        if (!target) {
            continue;
        }

        target.classList.remove('q-paired');
        target.style.removeProperty('--pair-color');

        const badge = target.querySelector('.pair-badge');

        if (badge) {
            badge.hidden = true;
            badge.textContent = '';
        }
    }

    syncMatchAnswer(panel);
}

function shake(chip) {
    chip.classList.add('q-shake');
    setTimeout(() => chip.classList.remove('q-shake'), 400);
}

function syncMatchAnswer(panel) {
    const state = matchState.get(panel.dataset.qid);

    if (!state) {
        return;
    }

    answers.set(
        panel.dataset.qid,
        [...state.pairs.entries()].map(([li, ri]) => ({
            left: state.lefts[li] ?? '',
            right: state.rights[ri] ?? '',
        })),
    );

    touchAnswer();
}

/* ---------------------------------------------------------- Sequence */

function wireSequence(panel) {
    const pool = panel.querySelector('[data-seq-pool]');
    const strip = panel.querySelector('[data-seq-strip]');
    const state = { items: [], placed: [] };

    for (const button of panel.querySelectorAll('[data-seq-item]')) {
        // Capture labels now, while badges are still empty.
        state.items[Number(button.dataset.idx)] = button.textContent.trim();

        button.addEventListener('click', () => {
            toggleSeqItem(panel, state, pool, strip, button);
        });
    }

    seqState.set(panel.dataset.qid, state);
}

function toggleSeqItem(panel, state, pool, strip, button) {
    if (!pool || !strip) {
        return;
    }

    const idx = Number(button.dataset.idx);

    if (button.parentElement === strip) {
        pool.appendChild(button);
        button.classList.remove('q-placed');
        state.placed.splice(state.placed.indexOf(idx), 1);
    } else {
        strip.appendChild(button);
        button.classList.add('q-placed');
        state.placed.push(idx);
        springySettle(button);
    }

    refreshSeqNumbers(panel, strip);
    syncSeqAnswer(panel);
}

function refreshSeqNumbers(panel, strip) {
    for (const button of panel.querySelectorAll('[data-seq-item]')) {
        const badge = button.querySelector('.seq-num');

        if (!badge) {
            continue;
        }

        if (button.parentElement === strip) {
            badge.hidden = false;
            badge.textContent = String([...strip.children].indexOf(button) + 1);
        } else {
            badge.hidden = true;
            badge.textContent = '';
        }
    }
}

function syncSeqAnswer(panel) {
    const state = seqState.get(panel.dataset.qid);

    if (!state) {
        return;
    }

    answers.set(panel.dataset.qid, state.placed.map((idx) => state.items[idx] ?? ''));
    touchAnswer();
}

async function springySettle(block) {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    try {
        const anime = await import('animejs');
        const animate = anime.animate ?? anime.default?.animate;

        if (animate) {
            animate(block, { translateY: [4, 0], scale: [1.02, 1], duration: 220, ease: 'outElastic(1, .5)' });
        }
    } catch {
        /* motion sugar is optional */
    }
}

/* ----------------------------------------------- Integrity signals */

/**
 * Track objective, teacher-only behavioral observations during the quiz:
 * page-leave events (focus loss), total time off-page, and paste events
 * with the per-question character count. Pure facts — nothing here is a
 * judgement, and nothing affects the student's score or reward.
 */
function initIntegrity() {
    integrityActive = true;
    integrity = { focusLosses: 0, focusSeconds: 0, pastes: [] };

    let focusStartedAt = null;

    const onHidden = () => {
        if (document.hidden) {
            focusStartedAt = Date.now();
            integrity.focusLosses += 1;
        } else if (focusStartedAt !== null) {
            integrity.focusSeconds += Math.round((Date.now() - focusStartedAt) / 1000);
            focusStartedAt = null;
        }
    };

    document.addEventListener('visibilitychange', onHidden);
    window.addEventListener('blur', () => {
        if (!document.hidden) {
            focusStartedAt = Date.now();
            integrity.focusLosses += 1;
        }
    });
    window.addEventListener('focus', () => {
        if (focusStartedAt !== null && !document.hidden) {
            integrity.focusSeconds += Math.round((Date.now() - focusStartedAt) / 1000);
            focusStartedAt = null;
        }
    });

    document.addEventListener('paste', (event) => {
        const qid = currentQuestionId();
        const text = (event.clipboardData?.getData('text') ?? '').trim();
        if (text.length === 0) {
            return;
        }
        integrity.pastes.push({ question: qid, chars: text.length });
    });
}

function currentQuestionId() {
    const panel = panels[current];
    return panel ? panel.dataset.qid : null;
}

/**
 * The integrity slice added to the submit payload. Trimmed to the fields
 * the server's IntegritySignals::buildFlags() understands.
 */
function integrityPayload() {
    if (!integrityActive) {
        return {};
    }

    return {
        focus_lost: integrity.focusLosses,
        focus_duration: integrity.focusSeconds,
        pastes: integrity.pastes,
    };
}

/* ------------------------------------------------------------ Submit */

function panelAnswered(panel) {
    const qid = panel.dataset.qid;

    if (!answers.has(qid)) {
        return false;
    }

    if (panel.dataset.type === 'sequence') {
        const state = seqState.get(qid);

        return Boolean(state && state.items.length > 0 && state.placed.length === state.items.length);
    }

    if (panel.dataset.type === 'matching') {
        const state = matchState.get(qid);

        return Boolean(state && state.lefts.length > 0 && state.pairs.size === state.lefts.length);
    }

    return true;
}

function answersComplete() {
    return panels.every(panelAnswered);
}

function touchAnswer() {
    syncNav();
    syncDots();
    armIdleHint();
}

async function submit(force = false) {
    if (submitting) {
        return;
    }

    const missing = panels.filter((panel) => !panelAnswered(panel)).length;

    if (missing > 0 && !force) {
        if (submitNagging) {
            submit(true);

            return;
        }

        submitNagging = true;
        note(`${missing} question${missing > 1 ? 's' : ''} still need an answer - tap SUBMIT again to hand it in anyway.`);
        const submitButton = document.getElementById('quiz-submit');

        setTimeout(() => {
            submitNagging = false;

            if (submitButton) {
                note('');
            }
        }, 3500);

        return;
    }

    submitting = true;
    const submitButton = document.getElementById('quiz-submit');
    submitButton?.setAttribute('disabled', '');
    note('');

    const payload = {
        attempt_uuid: config.attempt,
        time_spent_seconds: Math.min(Math.round((Date.now() - openedAt) / 1000), 7200),
        answers: panels.map((panel) => ({
            question_id: Number(panel.dataset.qid),
            value: serializeValue(panel),
        })),
        integrity: integrityPayload(),
    };

    try {
        const response = await fetch(config.submitUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        });
        const result = await response.json().catch(() => ({}));

        // A duplicate/subsequent POST for the same attempt settles as a 409
        // (the session is forgotten on the first settle). If a redirect to the
        // results page is already in flight, swallow it — reloading here would
        // clobber the redirect and drop the student back on a fresh quiz.
        if (response.status === 409 && !redirected) {
            window.location.reload();

            return;
        }

        if (!response.ok || !result.ok) {
            note(result.message ?? 'The quiz desk did not answer - try again.', true);
            submitButton?.removeAttribute('disabled');
            submitting = false;

            return;
        }

        // Post/Redirect/Get: hand off to the dedicated, read-only results page.
        // Mark redirected before assigning so a racing 409 can't reload over us.
        redirected = true;
        window.location.assign(result.results_url);
    } catch {
        note('Connection hiccup - your answers are still here, try SUBMIT again.', true);
        submitButton?.removeAttribute('disabled');
        submitting = false;
    }
}

function serializeValue(panel) {
    const type = panel.dataset.type;

    if (type === 'mcq' || type === 'true_false') {
        return answers.get(panel.dataset.qid) ?? null;
    }

    if (type === 'identification') {
        return panel.querySelector('[data-ident]')?.value ?? '';
    }

    if (type === 'matching') {
        const state = matchState.get(panel.dataset.qid);

        return [...(state?.pairs.entries() ?? [])].map(([li, ri]) => ({
            left: state.lefts[li] ?? '',
            right: state.rights[ri] ?? '',
        }));
    }

    if (type === 'sequence') {
        const state = seqState.get(panel.dataset.qid);

        return (state?.placed ?? []).map((idx) => state.items[idx] ?? '');
    }

    return '';
}
